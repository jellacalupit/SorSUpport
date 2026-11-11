<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\EmailNotification;
use App\Models\Recipient;
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

        $this->category = ComplaintCategory::create(['name' => 'Student Services', 'is_active' => true]);
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
            [EmailNotification::TYPE_WITHDRAWN, $this->admin, "Ticket {$reference} was withdrawn", 'withdrew this SorSUpport ticket'],
            [EmailNotification::TYPE_WITHDRAWAL_REQUESTED, $this->admin, "Withdrawal requested for {$reference}", 'please review the request'],
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

    public function test_waiting_reminder_emails_describe_handbook_timelines_as_system_defaults(): void
    {
        $sensitiveCategory = ComplaintCategory::create(['name' => 'Sensitive category', 'is_active' => true, 'is_sensitive' => true]);
        $sensitive = $this->ticket(Ticket::STATUS_SUBMITTED, [], ['category_id' => $sensitiveCategory->id]);
        $sensitiveEmail = $this->email($sensitive, EmailNotification::TYPE_WAITING_REMINDER, $this->admin);

        $this->assertStringContainsString('Internal SDS follow-up reminder informed by the CODI 24-hour notice timeline (Handbook p. 58). It is adjustable and is not an official committee deadline or a measure of committee compliance.', $sensitiveEmail['text']);
        $this->assertStringNotContainsString('requires action within 24 hours', $sensitiveEmail['text']);

        $referred = $this->ticket(Ticket::STATUS_REFERRED, [
            'referred_to' => Ticket::REFERRAL_DESTINATION_CODI,
            'is_codi_referral' => true,
        ]);
        $referredEmail = $this->email($referred, EmailNotification::TYPE_WAITING_REMINDER, $this->admin);

        $this->assertStringContainsString('Internal SDS follow-up reminder informed by the CODI 10-working-day period (pp. 57 to 58). It applies only to CODI referrals and is not an official committee deadline or a measure of committee compliance.', $referredEmail['text']);
        $this->assertStringNotContainsString('The Student Handbook gives the Committee', $referredEmail['text']);

        $otherReferral = $this->ticket(Ticket::STATUS_REFERRED, ['referred_to' => 'Campus Disciplinary Committee']);
        $otherReferralEmail = $this->email($otherReferral, EmailNotification::TYPE_WAITING_REMINDER, $this->admin);
        $this->assertStringContainsString('Internal SDS follow-up reminder. It is not an official committee deadline or a measure of committee compliance.', $otherReferralEmail['text']);
        $this->assertStringNotContainsString('CODI 10-working-day period', $otherReferralEmail['text']);
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

    public function test_rejecting_a_ticket_emails_the_student_its_explanation(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_SUBMITTED);
        $reason = 'This complaint is not a valid submission.';

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.reject', $ticket), [
                'closure_type' => Ticket::CLOSURE_INVALID,
                'closure_reason' => $reason,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('email_notifications', [
            'ticket_id' => $ticket->id,
            'recipient_email' => $this->student->email,
            'type' => EmailNotification::TYPE_INVALID_CLOSURE,
        ]);

        AuditLog::create([
            'ticket_id' => $ticket->id,
            'performed_by' => $this->admin->id,
            'action' => 'admin_private_note',
            'details' => 'Staff-only note must not be sent.',
        ]);

        $email = $this->email($ticket->fresh(), EmailNotification::TYPE_INVALID_CLOSURE, $this->student);
        $this->assertStringContainsString($reason, $email['text']);
        $this->assertStringNotContainsString('Staff-only note must not be sent.', $email['text']);

        $this->actingAs($this->student)
            ->get(route('student.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertSee($reason)
            ->assertDontSee('Staff-only note must not be sent.');
    }

    public function test_a_referral_email_names_the_committee(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_REFERRED, ['referred_to' => 'Student Disciplinary Board', 'referred_at' => now()]);

        $email = $this->email($ticket, EmailNotification::TYPE_REFERRED, $this->student);

        $this->assertStringContainsString('sent outside Bulan Campus to Student Disciplinary Board', $email['text']);
        $this->assertStringContainsString('you will be notified', $email['text']);
    }

    public function test_closing_a_referred_ticket_says_the_external_process_may_continue(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_CLOSED, [
            'referred_to' => 'Student Disciplinary Board',
            'referred_at' => now()->subDay(),
            'closed_at' => now(),
            'closure_type' => Ticket::CLOSURE_WITHDRAWN,
        ]);

        $email = $this->email($ticket, EmailNotification::TYPE_COMPLAINT_CLOSED, $this->student);

        $this->assertStringContainsString('does not necessarily stop or close the external investigation or proceeding', $email['text']);
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

    public function test_all_email_types_hide_sensitive_complaint_details_for_every_audience(): void
    {
        $sensitive = ComplaintCategory::create(['name' => 'Harassment', 'is_active' => true, 'is_sensitive' => true]);
        $realSubject = 'SENSITIVE_SUBJECT_6F83D2';
        $description = 'SENSITIVE_DESCRIPTION_A15C09';
        $personnel = 'SENSITIVE_PERSONNEL_77B421';
        $ticket = $this->ticket(Ticket::STATUS_ASSIGNED, [], [
            'category_id' => $sensitive->id,
            'subject_title' => $realSubject,
            'description' => $description,
            'personnel_involved' => $personnel,
        ]);
        $types = array_values(array_filter(
            (new \ReflectionClass(EmailNotification::class))->getConstants(),
            fn (string $name) => str_starts_with($name, 'TYPE_'),
            ARRAY_FILTER_USE_KEY
        ));

        foreach ($types as $type) {
            foreach ([$this->student, $this->handler, $this->admin] as $audience) {
                $email = $this->email($ticket, $type, $audience);
                $content = implode(' ', [
                    $email['subject'],
                    $email['heading'],
                    ...$email['lines'],
                    $email['body'],
                    ...array_values($email['details']),
                    $email['text'],
                ]);

                foreach ([$realSubject, $description, $personnel] as $privateText) {
                    $this->assertStringNotContainsString($privateText, $email['subject'], "{$type} subject for {$audience->role}");
                    $this->assertStringNotContainsString($privateText, $email['body'], "{$type} body for {$audience->role}");
                    $this->assertStringNotContainsString($privateText, $content, "{$type} full content for {$audience->role}");
                }
                $this->assertStringContainsString('Confidential concern', $email['text'], "{$type} for {$audience->role}");
            }
        }
    }

    public function test_non_sensitive_student_email_still_shows_the_real_subject(): void
    {
        $subject = 'Unreleased transcript';
        $ticket = $this->ticket(Ticket::STATUS_ASSIGNED, [], ['subject_title' => $subject]);
        $email = $this->email($ticket, EmailNotification::TYPE_STUDENT_STATUS_UPDATE, $this->student);

        $this->assertSame($subject, $email['details']['Subject']);
        $this->assertStringContainsString($subject, $email['text']);
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

        // The audit entry says who it went to and the subject, nothing more.
        $log = AuditLog::query()->where('ticket_id', $ticket->id)->where('action', 'email_notification_sent')->firstOrFail();
        $this->assertSame(
            sprintf('Email sent to %s: "Ticket %s was assigned to you".', $this->handler->email, $ticket->complaint->reference_number),
            $log->details
        );

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

    public function test_the_create_password_page_is_a_card_with_a_way_back_to_login(): void
    {
        // Reached from a reset link, signed out: a plain link back.
        $this->get(route('password.reset', ['token' => 'any-token', 'email' => $this->student->email]))
            ->assertOk()
            ->assertSee('Create Your Password')
            ->assertSee('max-w-md', false)
            ->assertSee('Back to login')
            ->assertSee('href="' . route('login') . '"', false);

        // Reached on first login, signed in: going back signs the user out.
        $this->student->forceFill(['must_change_password' => true])->save();

        $this->actingAs($this->student)->get(route('password.force'))
            ->assertOk()
            ->assertSee('Back to login')
            ->assertSee('action="' . route('logout') . '"', false);

        $this->actingAs($this->student)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_old_email_audit_entries_are_shortened(): void
    {
        $log = AuditLog::create([
            'ticket_id' => null,
            'performed_by' => null,
            'action' => 'email_notification_sent',
            'details' => 'Email sent to rey@sorsu.test. Subject: "Ticket assigned". Message: A new ticket has been assigned to you for #4. Notification type: recipient_assignment.',
        ]);

        (require database_path('migrations/2026_10_09_100000_shorten_email_audit_entries.php'))->up();

        $this->assertSame('Email sent to rey@sorsu.test: "Ticket assigned".', $log->fresh()->details);
    }

    public function test_every_email_carries_the_logo_and_the_system_name(): void
    {
        $logo = 'branding/sorsu%20logo.png';

        $ticketEmail = $this->email($this->ticket(Ticket::STATUS_ASSIGNED), EmailNotification::TYPE_RECIPIENT_ASSIGNMENT, $this->handler);
        $this->assertStringContainsString($logo, $ticketEmail['html']);

        foreach ([
            view('emails.password-reset', ['resetUrl' => 'https://example.test/reset', 'expires' => 15])->render(),
            view('emails.verify-email', ['verificationUrl' => 'https://example.test/verify', 'url' => 'https://example.test/verify', 'user' => $this->student])->render(),
            view('emails.account-update', ['subject' => 'Your SORSUPPORT account was activated', 'email_message' => 'Your account has been activated.'])->render(),
        ] as $html) {
            $this->assertStringContainsString($logo, $html);
            $this->assertStringContainsString('SorSUpport', $html);
        }

        $this->assertSame('SorSUpport', config('mail.from.name') ?: 'SorSUpport');
    }

    public function test_the_login_form_does_not_ask_the_browser_to_fill_in_credentials(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('autocomplete="off"', false)
            ->assertSee('autocomplete="new-password"', false)
            ->assertDontSee('autocomplete="current-password"', false);
    }

    public function test_forgot_password_explains_when_the_email_cannot_be_sent(): void
    {
        \Illuminate\Support\Facades\Password::shouldReceive('sendResetLink')->once()->andThrow(new \RuntimeException('Connection could not be established'));

        $this->post(route('password.email'), ['username' => $this->student->username])
            ->assertRedirect()
            ->assertSessionHasErrors(['username' => 'We could not send the reset email right now. Please try again later or visit the SDS Office.']);
    }

    // ------------------------------------------------------------------ declaration

    public function test_students_agree_to_the_fixed_declaration_and_admins_have_nothing_to_configure(): void
    {
        $this->actingAs($this->student)->get(route('student.complaints.create'))
            ->assertOk()
            ->assertSee(Complaint::DECLARATION);

        $this->actingAs($this->admin)->get(route('admin.settings'))
            ->assertOk()
            ->assertDontSee('Ticket declaration');

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.settings.declaration'));
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
