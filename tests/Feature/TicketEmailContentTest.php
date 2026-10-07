<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\EmailNotification;
use App\Models\Recipient;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\User;
use App\Services\EmailNotificationService;
use App\Services\TicketEmailComposer;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TicketEmailContentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $student;

    protected User $handler;

    protected ComplaintCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->user(User::ROLE_SDS_ADMIN, 'Ada', 'Admin');
        $this->student = $this->user(User::ROLE_STUDENT, 'Zebediah', 'Quixote');
        $this->handler = $this->user(User::ROLE_RECIPIENT, 'Rey', 'Registrar');

        Student::create(['user_id' => $this->student->id, 'student_id' => '2024-77123', 'college' => 'CICT', 'program' => 'BSIT', 'year_level' => '1', 'block' => '1']);
        Recipient::create(['user_id' => $this->handler->id, 'staff_id' => 'STAFF-1', 'unit' => 'Registrar', 'designation' => 'Registrar']);

        $this->category = ComplaintCategory::create(['name' => 'Student Services', 'resolution_deadline_days' => 15, 'is_active' => true]);
    }

    protected function user(string $role, string $first, string $last): User
    {
        return User::factory()->create([
            'role' => $role,
            'name' => "{$first} {$last}",
            'first_name' => $first,
            'last_name' => $last,
            'email' => strtolower("{$first}.{$last}@sorsu.test"),
            'is_active' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
    }

    protected function ticket(string $status, array $attributes = [], array $complaint = []): Ticket
    {
        $complaint = Complaint::create($complaint + [
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $this->student->student->id,
            'category_id' => $this->category->id,
            'subject_title' => 'Unreleased transcript',
            'description' => 'My transcript has not been released.',
            'is_anonymous' => false,
            'status' => $status,
        ]);

        $underReview = in_array($status, [Ticket::STATUS_SUBMITTED, Ticket::STATUS_NEEDS_CLARIFICATION], true);

        return Ticket::create($attributes + [
            'complaint_id' => $complaint->id,
            'status' => $status,
            'classification' => $underReview ? null : Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
            'assigned_to' => $underReview ? null : $this->handler->id,
            'current_handler_id' => $underReview ? $this->admin->id : $this->handler->id,
        ]);
    }

    /**
     * The email a person would receive, as text.
     */
    protected function email(Ticket $ticket, string $type, User $to): array
    {
        $notification = EmailNotification::create([
            'ticket_id' => $ticket->id,
            'recipient_email' => $to->email,
            'type' => $type,
            'status' => EmailNotification::STATUS_PENDING,
        ]);

        $email = app(TicketEmailComposer::class)->compose($notification);
        $email['html'] = view('emails.notifications.layout', $email)->render();
        $email['text'] = html_entity_decode(strip_tags($email['html']), ENT_QUOTES);

        return $email;
    }

    // ------------------------------------------------------------------ wording per purpose

    public function test_each_purpose_has_its_own_subject_and_message(): void
    {
        $submitted = $this->ticket(Ticket::STATUS_SUBMITTED);
        $reference = $submitted->complaint->reference_number;

        $cases = [
            [EmailNotification::TYPE_SUBMISSION_ACK, $this->student, "We received your ticket {$reference}", 'will review it'],
            [EmailNotification::TYPE_ASSIGNMENT, $this->admin, "New ticket for review: {$reference}", 'waiting for review'],
            [EmailNotification::TYPE_CLARIFICATION_REQUESTED, $this->student, "More details needed for your ticket {$reference}", 'reply there'],
            [EmailNotification::TYPE_CLARIFICATION_PROVIDED, $this->admin, "The student replied on {$reference}", 'back in the review list'],
            [EmailNotification::TYPE_RECIPIENT_ASSIGNMENT, $this->handler, "Ticket {$reference} was assigned to you", 'acknowledge it'],
            [EmailNotification::TYPE_INFORMATIONAL_FORWARD, $this->handler, "For your information: {$reference}", 'No reply or action is needed'],
            [EmailNotification::TYPE_MESSAGE_POSTED, $this->handler, "New message on ticket {$reference}", 'replied in the conversation'],
            [EmailNotification::TYPE_RECIPIENT_RESOLVED, $this->admin, "Ticket {$reference} was marked resolved", 'has 15 days'],
            [EmailNotification::TYPE_FURTHER_ACTION_REQUESTED, $this->handler, "Further action requested on {$reference}", 'in progress again'],
            [EmailNotification::TYPE_RESOLUTION_ACCEPTED, $this->handler, "Resolution accepted for {$reference}", 'accepted the resolution'],
            [EmailNotification::TYPE_WITHDRAWN, $this->admin, "Ticket {$reference} was withdrawn", 'withdrew this ticket'],
            [EmailNotification::TYPE_INVALID_CLOSURE, $this->student, "Your ticket {$reference} was closed", 'could not be acted on'],
            [EmailNotification::TYPE_COMPLAINT_CLOSED, $this->student, "Your ticket {$reference} is closed", 'has been closed'],
        ];

        $subjects = [];

        foreach ($cases as [$type, $to, $subject, $phrase]) {
            $email = $this->email($submitted, $type, $to);

            $this->assertSame($subject, $email['subject'], $type);
            $this->assertStringContainsString($phrase, $email['text'], $type);
            $this->assertStringContainsString($reference, $email['text'], $type);
            $this->assertStringContainsString('Open Ticket', $email['text'], $type);
            $subjects[] = $email['subject'];
        }

        $this->assertSame($subjects, array_unique($subjects), 'Every purpose must have a different subject.');
    }

    public function test_a_status_update_tells_the_student_what_the_status_means(): void
    {
        $assigned = $this->email($this->ticket(Ticket::STATUS_ASSIGNED), EmailNotification::TYPE_STUDENT_STATUS_UPDATE, $this->student);
        $this->assertStringContainsString('was assigned', $assigned['subject']);
        $this->assertStringContainsString('assigned it to Rey Registrar (Registrar)', $assigned['text']);

        $inProgress = $this->email($this->ticket(Ticket::STATUS_IN_PROGRESS), EmailNotification::TYPE_STUDENT_STATUS_UPDATE, $this->student);
        $this->assertStringContainsString('is in progress', $inProgress['subject']);
        $this->assertStringContainsString('is now working on your ticket', $inProgress['text']);

        $resolved = $this->email(
            $this->ticket(Ticket::STATUS_RESOLVED, ['resolved_at' => now(), 'resolution_type' => Ticket::RESOLUTION_ACTION_TAKEN]),
            EmailNotification::TYPE_STUDENT_STATUS_UPDATE,
            $this->student
        );
        $this->assertStringContainsString('was resolved', $resolved['subject']);
        $this->assertStringContainsString('accept the resolution', $resolved['text']);
        $this->assertStringContainsString('request further action until ' . now()->addDays(15)->setTimezone('Asia/Manila')->format('F j, Y'), $resolved['text']);
        $this->assertStringContainsString('Corrective action taken', $resolved['text']);
    }

    public function test_the_same_event_reads_differently_for_each_reader(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_ESCALATED);

        $toStudent = $this->email($ticket, EmailNotification::TYPE_ESCALATED, $this->student);
        $toHandler = $this->email($ticket, EmailNotification::TYPE_ESCALATED, $this->handler);
        $toAdmin = $this->email($ticket, EmailNotification::TYPE_ESCALATED, $this->admin);

        $this->assertStringContainsString('Your ticket', $toStudent['subject']);
        $this->assertStringContainsString('escalated to you', $toHandler['subject']);
        $this->assertStringContainsString('It is now with Rey Registrar', $toStudent['text']);
        $this->assertStringContainsString('at your level', $toHandler['text']);
        $this->assertStringContainsString('date of escalation is recorded', $toAdmin['text']);

        // Each reader is taken to their own page for the ticket.
        $this->assertSame(route('student.complaints.show', $ticket->complaint), $toStudent['actionUrl']);
        $this->assertSame(route('recipient.complaints.show', $ticket->complaint), $toHandler['actionUrl']);
        $this->assertSame(route('admin.complaints.show', $ticket->complaint), $toAdmin['actionUrl']);
    }

    public function test_a_closed_ticket_email_gives_the_student_the_reason(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_CLOSED, [
            'closure_type' => Ticket::CLOSURE_DUPLICATE,
            'closure_reason' => 'Same concern as SU-2026-00001.',
            'closed_at' => now(),
        ]);

        $toStudent = $this->email($ticket, EmailNotification::TYPE_COMPLAINT_CLOSED, $this->student);
        $this->assertStringContainsString('Duplicate of another ticket', $toStudent['text']);
        $this->assertStringContainsString('Same concern as SU-2026-00001.', $toStudent['text']);
        $this->assertStringNotContainsString('rate how your concern was handled', $toStudent['text']);

        // The handler is told it closed, without the explanation written for the student.
        $toHandler = $this->email($ticket, EmailNotification::TYPE_STATUS_UPDATE, $this->handler);
        $this->assertStringContainsString('is now Closed', $toHandler['subject']);
        $this->assertStringNotContainsString('Same concern as SU-2026-00001.', $toHandler['text']);

        $resolved = $this->ticket(Ticket::STATUS_CLOSED, ['closure_type' => Ticket::CLOSURE_RESOLVED, 'closed_at' => now()]);
        $this->assertStringContainsString('rate how your concern was handled', $this->email($resolved, EmailNotification::TYPE_COMPLAINT_CLOSED, $this->student)['text']);
    }

    public function test_a_referral_email_names_the_committee(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_REFERRED, ['referred_to' => 'Student Disciplinary Board', 'referred_at' => now()]);

        $email = $this->email($ticket, EmailNotification::TYPE_REFERRED, $this->student);

        $this->assertStringContainsString('referred to Student Disciplinary Board', $email['text']);
        $this->assertStringContainsString('you will be notified', $email['text']);
    }

    // ------------------------------------------------------------------ confidentiality

    public function test_emails_never_name_a_student_who_hid_their_identity(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_ASSIGNED, [], ['is_anonymous' => true]);

        foreach ([EmailNotification::TYPE_RECIPIENT_ASSIGNMENT, EmailNotification::TYPE_MESSAGE_POSTED, EmailNotification::TYPE_ESCALATED, EmailNotification::TYPE_FURTHER_ACTION_REQUESTED] as $type) {
            foreach ([$this->handler, $this->admin] as $to) {
                $text = $this->email($ticket, $type, $to)['text'];

                $this->assertStringNotContainsString('Zebediah', $text);
                $this->assertStringNotContainsString('Quixote', $text);
                $this->assertStringNotContainsString('2024-77123', $text);
                $this->assertStringNotContainsString($this->student->email, $text);
            }
        }

        $ack = $this->email($ticket, EmailNotification::TYPE_SUBMISSION_ACK, $this->student);
        $this->assertStringContainsString('You chose to hide your identity', $ack['text']);
    }

    public function test_staff_emails_do_not_carry_the_subject_of_a_sensitive_ticket(): void
    {
        $sensitive = ComplaintCategory::create(['name' => 'Harassment', 'resolution_deadline_days' => 15, 'is_active' => true, 'is_sensitive' => true]);
        $ticket = $this->ticket(Ticket::STATUS_ASSIGNED, [], ['category_id' => $sensitive->id, 'subject_title' => 'Groped in the corridor']);

        foreach ([$this->handler, $this->admin] as $to) {
            $text = $this->email($ticket, EmailNotification::TYPE_RECIPIENT_ASSIGNMENT, $to)['text'];

            $this->assertStringNotContainsString('Groped in the corridor', $text);
            $this->assertStringContainsString('Confidential concern', $text);
        }

        // The student wrote the subject, so their own email may show it.
        $this->assertStringContainsString('Groped in the corridor', $this->email($ticket, EmailNotification::TYPE_STUDENT_STATUS_UPDATE, $this->student)['text']);
    }

    public function test_emails_do_not_quote_messages_from_the_conversation(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);
        $ticket->thread->messages()->create(['sender_id' => $this->student->id, 'content' => 'A private detail about my situation.']);

        $text = $this->email($ticket, EmailNotification::TYPE_MESSAGE_POSTED, $this->handler)['text'];

        $this->assertStringNotContainsString('A private detail about my situation.', $text);
    }

    // ------------------------------------------------------------------ delivery

    public function test_a_queued_ticket_email_is_sent_with_the_composed_subject_and_recorded(): void
    {
        Mail::fake();

        $ticket = $this->ticket(Ticket::STATUS_ASSIGNED);
        $notification = EmailNotification::create([
            'ticket_id' => $ticket->id,
            'recipient_email' => $this->handler->email,
            'type' => EmailNotification::TYPE_RECIPIENT_ASSIGNMENT,
            'status' => EmailNotification::STATUS_PENDING,
        ]);

        $this->assertSame(1, app(EmailNotificationService::class)->sendPendingNotifications());
        $this->assertSame(EmailNotification::STATUS_SENT, $notification->fresh()->status);

        $log = AuditLog::query()->where('ticket_id', $ticket->id)->where('action', 'email_notification_sent')->firstOrFail();
        $this->assertStringContainsString("Ticket {$ticket->complaint->reference_number} was assigned to you", $log->details);

        // Nothing is sent twice.
        $this->assertSame(0, app(EmailNotificationService::class)->sendPendingNotifications());
    }

    public function test_ticket_emails_are_scheduled_every_minute(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'notifications:send'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
    }

    public function test_the_whole_flow_queues_an_email_for_everyone_who_needs_to_know(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_SUBMITTED);
        $this->category->suggestedRecipients()->attach($this->handler->recipient->id);
        $complaint = $ticket->complaint;

        $this->actingAs($this->admin)->post(route('admin.tickets.assign', $ticket), ['assignment_mode' => 'recipient', 'recipient_id' => $this->handler->recipient->id]);
        $this->actingAs($this->handler)->post(route('recipient.complaints.acknowledge', $complaint));
        $this->actingAs($this->handler)->patch(route('recipient.complaints.update-status', $complaint), ['status' => 'resolved', 'resolution_type' => Ticket::RESOLUTION_ACTION_TAKEN, 'resolution_message' => 'Released.']);
        $this->actingAs($this->student)->post(route('student.complaints.accept-resolution', $complaint));

        $sent = EmailNotification::query()->where('ticket_id', $ticket->id)->get()->map(fn ($n) => $n->type . ' > ' . $n->recipient_email)->all();

        foreach ([
            'recipient_assignment > ' . $this->handler->email,
            'student_status_update > ' . $this->student->email,
            'recipient_resolved > ' . $this->admin->email,
            'resolution_accepted > ' . $this->handler->email,
            'resolution_accepted > ' . $this->admin->email,
        ] as $expected) {
            $this->assertContains($expected, $sent);
        }
    }

    // ------------------------------------------------------------------ forgot password

    public function test_forgot_password_sends_a_reset_link_that_sets_a_new_password(): void
    {
        Notification::fake();

        $this->get(route('password.request'))->assertOk();

        $this->post(route('password.email'), ['username' => 'no-such-id'])->assertSessionHasErrors('username');

        $this->post(route('password.email'), ['username' => $this->student->username])
            ->assertRedirect(route('password.sent'));

        $token = null;
        Notification::assertSentTo($this->student, \App\Notifications\ResetPasswordNotification::class, function ($notification) use (&$token) {
            $token = $notification->token;

            $mail = $notification->toMail($this->student);
            $this->assertSame('Reset Your SorSUpport Password', $mail->subject);
            $this->assertStringContainsString('/reset-password/' . $token, $mail->viewData['resetUrl']);

            return true;
        });

        $this->get(route('password.reset', ['token' => $token, 'email' => $this->student->email]))->assertOk();

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $this->student->email,
            'password' => 'BrandNew@2026',
            'password_confirmation' => 'BrandNew@2026',
        ])->assertSessionHasNoErrors();

        $this->post(route('login'), ['username' => $this->student->username, 'password' => 'BrandNew@2026']);
        $this->assertAuthenticatedAs($this->student);
    }

    // ------------------------------------------------------------------ declaration setting

    public function test_the_admin_can_reword_the_declaration_students_agree_to(): void
    {
        $this->actingAs($this->student)->get(route('student.complaints.create'))->assertOk()->assertSee(Setting::DEFAULT_DECLARATION);

        $this->actingAs($this->admin)->get(route('admin.settings', ['settings_tab' => 'declaration']))
            ->assertOk()
            ->assertSee('Ticket declaration');

        $this->actingAs($this->admin)->put(route('admin.settings.declaration'), ['declaration' => 'Too short'])->assertSessionHasErrors('declaration');
        $this->actingAs($this->student)->put(route('admin.settings.declaration'), ['declaration' => str_repeat('A student may not change this. ', 2)])->assertForbidden();

        $wording = 'I certify that this report is truthful, in keeping with the SorSU Student Handbook.';

        $this->actingAs($this->admin)->put(route('admin.settings.declaration'), ['declaration' => $wording])->assertSessionHasNoErrors();

        $this->assertSame($wording, Setting::declaration());
        $this->actingAs($this->student)->get(route('student.complaints.create'))->assertOk()->assertSee($wording)->assertDontSee(Setting::DEFAULT_DECLARATION);
        $this->assertDatabaseHas('audit_logs', ['action' => 'declaration_updated']);

        $this->actingAs($this->admin)->put(route('admin.settings.declaration'), ['restore_default' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(Setting::DEFAULT_DECLARATION, Setting::declaration());
    }

    // ------------------------------------------------------------------ audit trail

    public function test_student_sign_ins_are_not_listed_in_the_audit_trail(): void
    {
        $this->post(route('login'), ['username' => $this->student->username, 'password' => 'password']);
        auth()->logout();
        $this->post(route('login'), ['username' => $this->handler->username, 'password' => 'password']);
        auth()->logout();

        $this->assertSame(2, AuditLog::query()->where('action', 'user_logged_in')->count());

        $this->actingAs($this->admin)->get(route('admin.audit'))
            ->assertOk()
            ->assertSee('User Rey Registrar logged in.')
            ->assertDontSee('Zebediah');

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('Zebediah');
    }

    public function test_a_name_search_in_the_audit_trail_skips_hidden_identity_tickets(): void
    {
        $hidden = $this->ticket(Ticket::STATUS_IN_PROGRESS, [], ['is_anonymous' => true]);
        $named = $this->ticket(Ticket::STATUS_IN_PROGRESS);

        AuditLog::log($hidden->id, 'message_posted', $this->student->id, 'Student posted a reply message.');
        AuditLog::log($named->id, 'message_posted', $this->student->id, 'Student posted a reply message.');

        $this->actingAs($this->admin)->get(route('admin.audit', ['search' => 'Quixote']))
            ->assertOk()
            ->assertSee($named->complaint->reference_number)
            ->assertDontSee($hidden->complaint->reference_number);
    }
}
