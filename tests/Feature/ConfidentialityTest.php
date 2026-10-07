<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Recipient;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\User;
use App\Services\EmailNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConfidentialityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $student;

    protected User $otherStudent;

    protected User $handler;

    protected User $otherRecipient;

    protected ComplaintCategory $category;

    protected ComplaintCategory $sensitive;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->admin = $this->user(User::ROLE_SDS_ADMIN, 'Ada', 'Admin');
        // An unusual name, so any leak of the student's identity is easy to detect.
        $this->student = $this->user(User::ROLE_STUDENT, 'Zebediah', 'Quixote');
        $this->otherStudent = $this->user(User::ROLE_STUDENT, 'Olive', 'Other');
        $this->handler = $this->user(User::ROLE_RECIPIENT, 'Rey', 'Registrar');
        $this->otherRecipient = $this->user(User::ROLE_RECIPIENT, 'Dana', 'Dean');

        Student::create(['user_id' => $this->student->id, 'student_id' => '2024-77123', 'college' => 'CICT', 'program' => 'BSIT', 'year_level' => '1', 'block' => '1']);
        Student::create(['user_id' => $this->otherStudent->id, 'student_id' => '2024-00002', 'college' => 'CICT', 'program' => 'BSIT', 'year_level' => '1', 'block' => '1']);
        Recipient::create(['user_id' => $this->handler->id, 'staff_id' => 'STAFF-1', 'unit' => 'Registrar', 'designation' => 'Registrar']);
        Recipient::create(['user_id' => $this->otherRecipient->id, 'staff_id' => 'STAFF-2', 'unit' => 'CICT', 'designation' => 'Dean']);

        $this->category = ComplaintCategory::create(['name' => 'Student Services', 'is_active' => true]);
        $this->sensitive = ComplaintCategory::create(['name' => 'Harassment', 'is_active' => true, 'is_sensitive' => true]);

        foreach ([$this->category, $this->sensitive] as $category) {
            $category->suggestedRecipients()->attach([$this->handler->recipient->id, $this->otherRecipient->recipient->id]);
        }
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

    /**
     * Submit a ticket through the real form.
     */
    protected function submit(array $fields = []): Ticket
    {
        $this->actingAs($this->student)
            ->post(route('student.complaints.store'), $fields + [
                'category_id' => $this->category->id,
                'subject_title' => 'Unreleased transcript',
                'description' => 'My transcript has not been released for a month.',
                'declaration' => '1',
            ])
            ->assertSessionHasNoErrors();

        return Ticket::query()->latest('id')->firstOrFail();
    }

    protected function assign(Ticket $ticket, ?User $to = null): Ticket
    {
        $this->actingAs($this->admin)
            ->post(route('admin.tickets.assign', $ticket), [
                'assignment_mode' => 'recipient',
                'recipient_id' => ($to ?? $this->handler)->recipient->id,
            ])
            ->assertSessionHasNoErrors();

        return $ticket->fresh();
    }

    /**
     * Nothing that identifies the student may appear in the response.
     */
    protected function assertIdentityHidden($response): void
    {
        $response->assertOk()
            ->assertDontSee('Zebediah')
            ->assertDontSee('Quixote')
            ->assertDontSee('2024-77123')
            ->assertDontSee($this->student->email);
    }

    // ------------------------------------------------------------------ declaration

    public function test_a_ticket_cannot_be_submitted_without_the_declaration(): void
    {
        $this->actingAs($this->student)
            ->get(route('student.complaints.create'))
            ->assertOk()
            ->assertSee('I declare that the information I am submitting is true and correct')
            ->assertSee('Data Privacy Act of 2012');

        $this->actingAs($this->student)
            ->post(route('student.complaints.store'), [
                'category_id' => $this->category->id,
                'subject_title' => 'No declaration',
                'description' => 'Submitted without ticking the declaration.',
            ])
            ->assertSessionHasErrors('declaration');

        $this->assertSame(0, Complaint::count());

        $ticket = $this->submit();
        $this->assertNotNull($ticket->complaint->declared_at);
    }

    // ------------------------------------------------------------------ hidden identity

    public function test_a_hidden_identity_ticket_is_assigned_handled_and_resolved_like_any_other(): void
    {
        $ticket = $this->assign($this->submit(['is_anonymous' => '1']));

        $this->assertTrue($ticket->complaint->is_anonymous);
        $this->assertSame(Ticket::STATUS_ASSIGNED, $ticket->status);
        $this->assertSame(Ticket::CLASSIFICATION_NEEDS_RESOLUTION, $ticket->classification);

        $complaint = $ticket->complaint;

        $this->actingAs($this->handler)->post(route('recipient.complaints.acknowledge', $complaint))->assertSessionHasNoErrors();
        $this->actingAs($this->handler)->post(route('recipient.complaints.reply', $complaint), ['content' => 'Can you tell us the semester?'])->assertSessionHasNoErrors();
        $this->actingAs($this->student)->post(route('student.complaints.reply', $complaint), ['content' => 'It was the first semester.'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.complaints.reply', $complaint), ['content' => 'SDS is following this up.'])->assertSessionHasNoErrors();

        $this->actingAs($this->handler)
            ->patch(route('recipient.complaints.update-status', $complaint), [
                'status' => 'resolved',
                'resolution_type' => Ticket::RESOLUTION_ACTION_TAKEN,
                'resolution_message' => 'The transcript was released.',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->student)->post(route('student.complaints.accept-resolution', $complaint))->assertSessionHasNoErrors();

        $this->assertSame(Ticket::STATUS_CLOSED, $ticket->fresh()->status);
    }

    public function test_the_student_behind_a_hidden_identity_ticket_is_shown_to_nobody_else(): void
    {
        $ticket = $this->assign($this->submit(['is_anonymous' => '1']));
        $complaint = $ticket->complaint;

        $this->actingAs($this->handler)->post(route('recipient.complaints.acknowledge', $complaint));
        $this->actingAs($this->student)->post(route('student.complaints.reply', $complaint), ['content' => 'A message from the hidden student.']);

        // The admin, on every screen that lists or shows the ticket.
        foreach ([
            route('admin.complaints.show', $complaint),
            route('admin.complaints.index'),
            route('admin.tickets.review.index'),
            route('admin.tickets.my'),
            route('admin.dashboard'),
            '/admin/analytics',
        ] as $url) {
            $this->assertIdentityHidden($this->actingAs($this->admin)->get($url));
        }

        // The handler, on every screen that lists or shows the ticket.
        foreach ([
            route('recipient.complaints.show', $complaint),
            route('recipient.tickets.index'),
            route('recipient.dashboard'),
            route('recipient.notifications'),
        ] as $url) {
            $this->assertIdentityHidden($this->actingAs($this->handler)->get($url));
        }

        // The message itself is still shown, from "Anonymous".
        $this->actingAs($this->handler)
            ->get(route('recipient.complaints.show', $complaint))
            ->assertSee('A message from the hidden student.')
            ->assertSee('Anonymous')
            ->assertSee('Identity hidden');

        // The student still sees their own ticket normally.
        $this->actingAs($this->student)
            ->get(route('student.complaints.show', $complaint))
            ->assertOk()
            ->assertSee('A message from the hidden student.');
    }

    public function test_a_name_search_does_not_reveal_who_filed_a_hidden_identity_ticket(): void
    {
        $hidden = $this->submit(['is_anonymous' => '1', 'subject_title' => 'Hidden filer']);
        $named = $this->submit(['subject_title' => 'Named filer']);

        foreach ([route('admin.complaints.index', ['search' => 'Quixote']), route('admin.tickets.review.index', ['search' => 'Quixote'])] as $url) {
            $this->actingAs($this->admin)->get($url)
                ->assertOk()
                ->assertSee($named->complaint->reference_number)
                ->assertDontSee($hidden->complaint->reference_number);
        }
    }

    public function test_the_audit_trail_does_not_reveal_the_hidden_student(): void
    {
        Mail::fake();

        $ticket = $this->assign($this->submit(['is_anonymous' => '1']));
        $complaint = $ticket->complaint;

        $this->actingAs($this->handler)->post(route('recipient.complaints.acknowledge', $complaint));
        $this->actingAs($this->student)->post(route('student.complaints.reply', $complaint), ['content' => 'Reply for the audit trail.']);
        $this->actingAs($this->student)->post(route('student.complaints.withdraw', $complaint));

        // Emails to the student are recorded without their address.
        app(EmailNotificationService::class)->sendPendingNotifications();

        $details = AuditLog::query()->where('ticket_id', $ticket->id)->pluck('details')->implode(' ');
        $this->assertStringNotContainsString($this->student->email, $details);
        $this->assertStringNotContainsString('Quixote', $details);
        $this->assertStringContainsString('Email sent to the student: "', $details);

        // The student's own actions are attributed to "Anonymous" for everyone else.
        $log = AuditLog::query()->where('ticket_id', $ticket->id)->where('action', 'ticket_withdrawn')->firstOrFail();

        $this->actingAs($this->admin);
        $this->assertSame('Anonymous', $log->fresh()->display_performer->table_name);

        $this->actingAs($this->student);
        $this->assertSame($this->student->id, $log->fresh()->display_performer->id);

        $this->assertIdentityHidden($this->actingAs($this->admin)->get(route('admin.complaints.show', $complaint)));
    }

    public function test_an_identified_ticket_still_shows_the_student(): void
    {
        $ticket = $this->assign($this->submit());

        $this->actingAs($this->admin)->get(route('admin.complaints.show', $ticket->complaint))->assertOk()->assertSee('Quixote');
        $this->actingAs($this->handler)->get(route('recipient.complaints.show', $ticket->complaint))->assertOk()->assertSee('Quixote');
    }

    // ------------------------------------------------------------------ sensitive categories

    public function test_a_sensitive_ticket_is_open_only_to_the_admin_the_student_and_the_assigned_handler(): void
    {
        $ticket = $this->assign($this->submit(['category_id' => $this->sensitive->id, 'subject_title' => 'Unwanted messages']));
        $complaint = $ticket->complaint;

        $this->actingAs($this->admin)->get(route('admin.complaints.show', $complaint))->assertOk()->assertSee('Sensitive ticket');
        $this->actingAs($this->handler)->get(route('recipient.complaints.show', $complaint))->assertOk()->assertSee('Sensitive ticket');
        $this->actingAs($this->student)->get(route('student.complaints.show', $complaint))->assertOk()->assertDontSee('Sensitive ticket');

        $this->actingAs($this->otherRecipient)->get(route('recipient.complaints.show', $complaint))->assertForbidden();
        $this->actingAs($this->otherRecipient)->get(route('recipient.tickets.index'))->assertOk()->assertDontSee($complaint->reference_number);
        $this->actingAs($this->otherStudent)->get(route('student.complaints.show', $complaint))->assertForbidden();
    }

    public function test_the_previous_handler_loses_access_when_a_sensitive_ticket_is_escalated(): void
    {
        $ticket = $this->assign($this->submit(['category_id' => $this->sensitive->id]));
        $this->sensitive->escalationHierarchies()->create(['recipient_id' => $this->otherRecipient->recipient->id, 'level' => 1]);

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.escalate', $ticket), ['recipient_id' => $this->otherRecipient->recipient->id])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->handler)->get(route('recipient.complaints.show', $ticket->complaint))->assertForbidden();
        $this->actingAs($this->otherRecipient)->get(route('recipient.complaints.show', $ticket->complaint))->assertOk();
    }

    public function test_sensitive_subjects_stay_out_of_the_dashboard_and_reports(): void
    {
        $this->assign($this->submit(['category_id' => $this->sensitive->id, 'subject_title' => 'Groped in the corridor']));
        $this->submit(['subject_title' => 'Ordinary library concern']);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Groped in the corridor')
            ->assertSee('Confidential concern')
            ->assertSee('Ordinary library concern');

        $this->actingAs($this->admin)->get('/admin/analytics')
            ->assertOk()
            ->assertDontSee('Groped in the corridor');
    }

    // ------------------------------------------------------------------ attachments

    public function test_attachments_are_stored_privately_and_downloaded_only_by_people_on_the_ticket(): void
    {
        $ticket = $this->assign($this->submit([
            'file_attachment' => [UploadedFile::fake()->create('evidence.pdf', 20, 'application/pdf')],
        ]));
        $complaint = $ticket->complaint;
        $path = $complaint->attachment_files[0]['path'];

        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);

        $url = route('attachments.complaint', [$complaint, 0]);
        $this->assertSame($url, $complaint->attachment_files[0]['url']);

        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));

        foreach ([$this->student, $this->admin, $this->handler] as $allowed) {
            $this->actingAs($allowed)->get($url)->assertOk();
        }

        foreach ([$this->otherStudent, $this->otherRecipient] as $denied) {
            $this->actingAs($denied)->get($url)->assertForbidden();
        }

        $this->actingAs($this->student)->get(route('attachments.complaint', [$complaint, 5]))->assertNotFound();

        // The ticket pages link to the protected route, never to the storage folder.
        $this->actingAs($this->handler)->get(route('recipient.complaints.show', $complaint))
            ->assertOk()
            ->assertSee($url, false)
            ->assertDontSee('storage/complaints', false);
    }

    public function test_conversation_attachments_are_private_too(): void
    {
        $ticket = $this->assign($this->submit());
        $complaint = $ticket->complaint;
        $this->actingAs($this->handler)->post(route('recipient.complaints.acknowledge', $complaint));

        $this->actingAs($this->student)
            ->post(route('student.complaints.reply', $complaint), [
                'content' => 'Here is the receipt.',
                'file_attachment' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $message = $ticket->thread->messages()->whereNotNull('file_attachment')->firstOrFail();

        Storage::disk('local')->assertExists($message->file_attachment);
        Storage::disk('public')->assertMissing($message->file_attachment);

        $url = route('attachments.message', $message);

        $this->actingAs($this->handler)->get($url)->assertOk();
        $this->actingAs($this->admin)->get($url)->assertOk();
        $this->actingAs($this->otherRecipient)->get($url)->assertForbidden();
        $this->actingAs($this->otherStudent)->get($url)->assertForbidden();

        $this->actingAs($this->handler)->get(route('recipient.complaints.show', $complaint))->assertOk()->assertSee($url, false);
    }

    // ------------------------------------------------------------------ conflict of interest

    public function test_the_admin_is_warned_when_the_handler_is_the_person_named_in_the_complaint(): void
    {
        $ticket = $this->submit([
            'subject_title' => 'Unfair treatment',
            'personnel_involved' => 'Mr. Rey Registrar',
            'description' => 'I was refused service at the window without any reason.',
        ]);

        $this->assertTrue($ticket->complaint->names($this->handler));
        $this->assertFalse($ticket->complaint->names($this->otherRecipient));

        // The person named is marked when choosing who to assign the ticket to.
        $this->actingAs($this->admin)->get(route('admin.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertSee('Named in this complaint')
            ->assertSee('Mr. Rey Registrar');

        $ticket = $this->assign($ticket, $this->handler);

        $this->assertDatabaseHas('audit_logs', ['ticket_id' => $ticket->id, 'action' => 'conflict_of_interest_flagged']);

        $this->actingAs($this->admin)->get(route('admin.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertSee('Possible conflict of interest');
    }

    public function test_no_conflict_warning_when_the_handler_is_not_named(): void
    {
        $ticket = $this->assign($this->submit(['personnel_involved' => 'Mr. Rey Registrar']), $this->otherRecipient);

        $this->assertDatabaseMissing('audit_logs', ['ticket_id' => $ticket->id, 'action' => 'conflict_of_interest_flagged']);

        $this->actingAs($this->admin)->get(route('admin.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertDontSee('Possible conflict of interest');
    }

    public function test_a_surname_alone_names_a_person_only_in_the_person_involved_field(): void
    {
        $complaint = new Complaint(['personnel_involved' => 'Prof. Registrar', 'description' => 'A general concern.', 'subject_title' => 'Concern']);
        $this->assertTrue($complaint->names($this->handler));

        // "Dean" is also this recipient's surname, but here it is only a word in the description.
        $complaint = new Complaint(['personnel_involved' => null, 'description' => 'I went to the dean about my grade.', 'subject_title' => 'Grade']);
        $this->assertFalse($complaint->names($this->otherRecipient));
    }
}
