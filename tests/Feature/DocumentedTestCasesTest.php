<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\EmailNotification;
use App\Models\EscalationHierarchy;
use App\Models\Recipient;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\AccountUpdateNotification;
use App\Services\TicketReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Test cases from the SorSUpport test case document that no other test covered. Each test is
 * named after its test case ID.
 */
class DocumentedTestCasesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $student;

    protected User $otherStudent;

    protected User $handler;

    protected User $otherRecipient;

    protected User $outsider;

    protected ComplaintCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->user(User::ROLE_SDS_ADMIN, 'Ada Admin');
        $this->student = $this->user(User::ROLE_STUDENT, 'Sam Student');
        $this->otherStudent = $this->user(User::ROLE_STUDENT, 'Olive Other');
        $this->handler = $this->user(User::ROLE_RECIPIENT, 'Rey Registrar');
        $this->otherRecipient = $this->user(User::ROLE_RECIPIENT, 'Dana Dean');
        $this->outsider = $this->user(User::ROLE_RECIPIENT, 'Owen Outside');

        foreach ([$this->student, $this->otherStudent] as $index => $user) {
            Student::create(['user_id' => $user->id, 'student_id' => '2024000' . $index, 'college' => 'CICT', 'program' => 'BSIT', 'year_level' => '1', 'block' => '1']);
        }

        foreach ([$this->handler, $this->otherRecipient, $this->outsider] as $index => $user) {
            Recipient::create(['user_id' => $user->id, 'staff_id' => 'STAFF-' . $index, 'unit' => 'Registrar', 'designation' => 'Staff']);
        }

        $this->category = ComplaintCategory::create(['name' => 'Student Services', 'is_active' => true]);
    }

    // ------------------------------------------------------------ authentication

    public function test_auth_04_a_deactivated_account_cannot_sign_in(): void
    {
        $this->student->update(['is_active' => false]);

        $this->post('/login', ['username' => $this->student->username, 'password' => 'password'])
            ->assertSessionHasErrors(['username' => 'This account is inactive. Please contact Student Development Services.']);

        $this->assertGuest();
    }

    public function test_auth_05_too_many_failed_attempts_block_sign_in_for_a_while(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['username' => $this->student->username, 'password' => 'wrong-password']);
        }

        // Even the right password is refused while blocked.
        $this->post('/login', ['username' => $this->student->username, 'password' => 'password'])
            ->assertSessionHasErrors('username');
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('username'));
        $this->assertGuest();

        $this->travel(61)->seconds();

        $this->post('/login', ['username' => $this->student->username, 'password' => 'password']);
        $this->assertAuthenticatedAs($this->student);
    }

    public function test_auth_08_a_new_password_must_be_long_enough_and_confirmed(): void
    {
        $this->student->update(['must_change_password' => true]);

        $this->actingAs($this->student)
            ->post(route('password.force.update'), ['password' => $this->student->username, 'password_confirmation' => $this->student->username])
            ->assertSessionHasErrors(['password' => 'Your new password cannot be your ID.']);

        $this->actingAs($this->student)
            ->post(route('password.force.update'), ['password' => 'short1', 'password_confirmation' => 'short1'])
            ->assertSessionHasErrors('password');

        $this->actingAs($this->student)
            ->post(route('password.force.update'), ['password' => 'LongEnough1', 'password_confirmation' => 'Different1'])
            ->assertSessionHasErrors('password');

        // Nothing was saved: the user still has to set a password.
        $this->assertTrue((bool) $this->student->fresh()->must_change_password);
    }

    // ------------------------------------------------------------ account management

    public function test_acc_02_a_student_id_must_have_eight_digits(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.accounts.store'), $this->studentAccount(['student_id' => '12345']))
            ->assertSessionHasErrors('student_id');

        $this->assertDatabaseMissing('users', ['email' => 'new@student.test']);
    }

    public function test_acc_03_an_email_can_be_used_by_one_account_only(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.accounts.store'), $this->studentAccount(['email' => $this->student->email]))
            ->assertSessionHasErrors('email');
    }

    public function test_acc_07_invalid_rows_are_skipped_and_listed_in_a_downloadable_report(): void
    {
        $this->college('CICT', ['BSIT']);
        $file = UploadedFile::fake()->createWithContent('students.csv', "Student ID,Last Name,First Name,Middle Name,Email,Department,Course,Year,Block,Status\n20243001,Valid,Vera,,vera@student.test,CICT,BSIT,1,,Active\n123,Bad,Ben,,not-an-email,CICT,BSIT,1,1,Active\n");

        $this->actingAs($this->admin)
            ->post(route('admin.accounts.upload.store'), ['account_type' => User::ROLE_STUDENT, 'file' => $file]);

        $this->assertDatabaseHas('students', ['student_id' => '20243001']);
        $this->assertSame(1, session('upload_summary')['failed']);

        $this->actingAs($this->admin)
            ->withSession(['upload_summary' => session('upload_summary')])
            ->get(route('admin.accounts.upload.errors'))
            ->assertOk()
            ->assertDownload();
    }

    public function test_acc_08_only_spreadsheet_files_can_be_imported(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.accounts.upload.store'), ['account_type' => User::ROLE_STUDENT, 'file' => UploadedFile::fake()->create('accounts.pdf', 20, 'application/pdf')])
            ->assertSessionHasErrors('file');

        $this->actingAs($this->admin)
            ->post(route('admin.accounts.upload.store'), ['account_type' => User::ROLE_STUDENT, 'file' => UploadedFile::fake()->create('accounts.csv', 11000, 'text/csv')])
            ->assertSessionHasErrors('file');
    }

    public function test_acc_10_a_deactivated_user_is_emailed_and_cannot_sign_in(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)->patch(route('admin.accounts.deactivate', $this->student));

        $this->assertFalse($this->student->fresh()->is_active);
        Notification::assertSentTo($this->student, AccountUpdateNotification::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account_deactivated']);

        auth()->logout();
        $this->post('/login', ['username' => $this->student->username, 'password' => 'password'])
            ->assertSessionHasErrors(['username' => 'This account is inactive. Please contact Student Development Services.']);
    }

    // ------------------------------------------------------------ system settings

    public function test_set_02_a_category_needs_a_name(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.categories.store'), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_set_07_a_category_can_be_deleted(): void
    {
        $category = ComplaintCategory::create(['name' => 'Old Category', 'is_active' => true]);

        $this->actingAs($this->admin)
            ->delete(route('admin.categories.destroy', $category))
            ->assertSessionHas('success', 'Complaint category deleted successfully.');

        $this->assertDatabaseMissing('complaint_categories', ['id' => $category->id]);
    }

    public function test_set_08_an_escalation_path_is_saved(): void
    {
        $this->category->suggestedRecipients()->sync([$this->handler->recipient->id, $this->otherRecipient->recipient->id]);

        $this->actingAs($this->admin)
            ->put(route('admin.categories.escalation.update', $this->category), [
                'paths' => [['name' => 'Main path', 'levels' => [[$this->handler->recipient->id], [$this->otherRecipient->recipient->id]]]],
            ])
            ->assertSessionHas('success', 'Escalation hierarchy saved.');

        $this->assertSame(2, $this->category->escalationHierarchies()->count());
    }

    public function test_set_09_only_the_categorys_active_recipients_can_be_in_its_path(): void
    {
        $this->category->suggestedRecipients()->sync([$this->handler->recipient->id]);

        $this->actingAs($this->admin)
            ->put(route('admin.categories.escalation.update', $this->category), [
                'paths' => [['name' => 'Main path', 'levels' => [[$this->outsider->recipient->id]]]],
            ])
            ->assertSessionHasErrors('paths');

        $this->assertSame(0, $this->category->escalationHierarchies()->count());
    }

    public function test_set_13_the_reminder_settings_start_with_their_defaults(): void
    {
        $this->assertSame([
            'enabled' => true,
            'unreviewed_days' => 2,
            'sensitive_unreviewed_days' => 1,
            'unacknowledged_days' => 2,
            'idle_days' => 5,
            'referral_days' => 10,
            'student_response_days' => 15,
        ], app(TicketReminderService::class)->settings());

        $this->actingAs($this->admin)
            ->get(route('admin.settings', ['settings_tab' => 'reminders']))
            ->assertOk()
            ->assertSee('Reminders for waiting tickets')
            ->assertSee('Student did not respond');
    }

    public function test_set_15_waiting_days_cannot_be_more_than_thirty(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.reminders.update'), [
                'enabled' => '1', 'unreviewed_days' => 31, 'sensitive_unreviewed_days' => 1, 'unacknowledged_days' => 2,
                'idle_days' => 5, 'referral_days' => 10, 'student_response_days' => 15,
            ])
            ->assertSessionHasErrors(['unreviewed_days' => 'Enter at most 30 days.']);
    }

    // ------------------------------------------------------------ ticket submission

    public function test_sub_01_a_submitted_ticket_gets_a_reference_number_and_emails(): void
    {
        $this->actingAs($this->student)
            ->post(route('student.complaints.store'), $this->ticketForm())
            ->assertSessionHasNoErrors();

        $complaint = Complaint::firstOrFail();
        $this->assertMatchesRegularExpression('/^SU-\d{4}-\d+$/', $complaint->reference_number);
        $this->assertSame(Ticket::STATUS_SUBMITTED, $complaint->ticket->status);
        $this->assertDatabaseHas('email_notifications', ['recipient_email' => $this->student->email, 'type' => EmailNotification::TYPE_SUBMISSION_ACK]);
        $this->assertDatabaseHas('email_notifications', ['recipient_email' => $this->admin->email, 'type' => EmailNotification::TYPE_ASSIGNMENT]);
    }

    public function test_sub_02_required_fields_must_be_filled_in(): void
    {
        $this->actingAs($this->student)
            ->post(route('student.complaints.store'), ['declaration' => '1'])
            ->assertSessionHasErrors(['category_id', 'subject_title', 'description']);

        $this->assertSame(0, Complaint::count());
    }

    public function test_sub_03_subject_and_description_have_a_length_limit(): void
    {
        $this->actingAs($this->student)
            ->post(route('student.complaints.store'), $this->ticketForm(['subject_title' => str_repeat('a', 51)]))
            ->assertSessionHasErrors('subject_title');

        $this->actingAs($this->student)
            ->post(route('student.complaints.store'), $this->ticketForm(['description' => str_repeat('a', 5001)]))
            ->assertSessionHasErrors('description');

        $this->assertSame(0, Complaint::count());
    }

    public function test_sub_06_files_of_other_types_or_over_ten_megabytes_are_refused(): void
    {
        Storage::fake('local');

        $this->actingAs($this->student)
            ->post(route('student.complaints.store'), $this->ticketForm(['file_attachment' => [UploadedFile::fake()->create('program.exe', 10)]]))
            ->assertSessionHasErrors('file_attachment.0');

        $this->actingAs($this->student)
            ->post(route('student.complaints.store'), $this->ticketForm(['file_attachment' => [UploadedFile::fake()->create('scan.pdf', 10241, 'application/pdf')]]))
            ->assertSessionHasErrors('file_attachment.0');

        $this->assertSame(0, Complaint::count());
    }

    public function test_sub_09_the_suggested_recipient_is_kept_with_the_ticket(): void
    {
        $this->actingAs($this->student)
            ->post(route('student.complaints.store'), $this->ticketForm(['suggested_recipient_id' => $this->handler->recipient->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->handler->recipient->id, (int) Complaint::firstOrFail()->suggested_recipient_id);
    }

    // ------------------------------------------------------------ review and assignment

    public function test_rev_09_assigning_to_a_recipient_needs_a_recipient(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_SUBMITTED);

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.assign', $ticket), ['assignment_mode' => 'recipient'])
            ->assertSessionHasErrors('recipient_id');

        $this->assertSame(Ticket::STATUS_SUBMITTED, $ticket->fresh()->status);
    }

    public function test_rev_12_a_ticket_can_be_reassigned_and_waits_for_the_new_handler(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.assign', $ticket), ['assignment_mode' => 'recipient', 'recipient_id' => $this->otherRecipient->recipient->id])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_ASSIGNED, $ticket->status);
        $this->assertSame($this->otherRecipient->id, (int) $ticket->assigned_to);
        $this->assertNull($ticket->acknowledged_at);
        $this->assertDatabaseHas('email_notifications', ['ticket_id' => $ticket->id, 'recipient_email' => $this->otherRecipient->email, 'type' => EmailNotification::TYPE_RECIPIENT_ASSIGNMENT]);
    }

    // ------------------------------------------------------------ handling

    public function test_hnd_04_an_empty_message_is_not_sent(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);

        $this->actingAs($this->handler)
            ->post(route('recipient.complaints.reply', $ticket->complaint), ['content' => ''])
            ->assertSessionHasErrors('content');
    }

    // ------------------------------------------------------------ escalation and referral

    public function test_esc_03_a_ticket_cannot_be_escalated_outside_its_path(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);
        EscalationHierarchy::create(['complaint_category_id' => $this->category->id, 'path_number' => 1, 'path_name' => 'Main', 'level' => 1, 'recipient_id' => $this->otherRecipient->recipient->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.escalate', $ticket), ['recipient_id' => $this->outsider->recipient->id, 'escalation_note' => 'Needs a higher office.'])
            ->assertSessionHasErrors(['recipient_id' => 'The ticket cannot be escalated to the selected recipient.']);

        $this->assertSame(Ticket::STATUS_IN_PROGRESS, $ticket->fresh()->status);
    }

    public function test_esc_05_an_unlisted_committee_needs_a_name(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);

        $this->actingAs($this->admin)
            ->post(route('admin.tickets.refer', $ticket), ['referred_to' => 'other', 'referred_to_other' => '', 'referral_note' => 'Needs a hearing.'])
            ->assertSessionHasErrors(['referred_to_other' => 'Enter the name of the committee or board.']);

        $this->assertSame(Ticket::STATUS_IN_PROGRESS, $ticket->fresh()->status);
    }

    // ------------------------------------------------------------ student response

    public function test_cls_03_further_action_needs_a_reason(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_RESOLVED);

        $this->actingAs($this->student)
            ->post(route('student.complaints.further-action', $ticket->complaint), ['further_action_reason' => ''])
            ->assertSessionHasErrors(['further_action_reason' => 'Tell us what still needs to be addressed.']);

        $this->assertSame(Ticket::STATUS_RESOLVED, $ticket->fresh()->status);
    }

    // ------------------------------------------------------------ access

    public function test_con_05_a_student_cannot_open_another_students_ticket(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);

        $this->actingAs($this->otherStudent)
            ->get(route('student.complaints.show', $ticket->complaint))
            ->assertForbidden();
    }

    // ------------------------------------------------------------ notifications

    public function test_not_03_all_notifications_can_be_marked_as_read(): void
    {
        $first = $this->ticket(Ticket::STATUS_ASSIGNED);
        $second = $this->ticket(Ticket::STATUS_ASSIGNED);
        $logs = collect([$first, $second])->map(fn (Ticket $ticket) => AuditLog::log($ticket->id, 'ticket_assigned', $this->admin->id, 'Assigned.'));

        $this->actingAs($this->student)->get(route('student.notifications'))->assertSee('2 unread');

        $this->actingAs($this->student)
            ->post(route('student.notifications.read-all'), ['notification_ids' => $logs->map(fn ($log) => "audit-{$log->id}")->all()]);

        $this->actingAs($this->student)->get(route('student.notifications'))->assertSee('0 unread');
    }

    // ------------------------------------------------------------ audit trail

    public function test_aud_02_the_audit_trail_can_be_searched_by_reference(): void
    {
        $wanted = $this->ticket(Ticket::STATUS_SUBMITTED);
        $other = $this->ticket(Ticket::STATUS_SUBMITTED);
        AuditLog::log($wanted->id, 'ticket_details_updated', $this->admin->id, 'Wanted entry.');
        AuditLog::log($other->id, 'ticket_details_updated', $this->admin->id, 'Other entry.');

        $this->actingAs($this->admin)
            ->get(route('admin.audit', ['search' => $wanted->complaint->reference_number]))
            ->assertOk()
            ->assertSee('Wanted entry.')
            ->assertDontSee('Other entry.');
    }

    public function test_aud_03_the_audit_trail_can_be_filtered_by_account_type_and_date(): void
    {
        $ticket = $this->ticket(Ticket::STATUS_SUBMITTED);
        AuditLog::create(['ticket_id' => $ticket->id, 'performed_by' => null, 'action' => 'email_notification_sent', 'details' => 'System entry.']);
        AuditLog::log($ticket->id, 'ticket_details_updated', $this->admin->id, 'Admin entry.');
        $old = AuditLog::log($ticket->id, 'ticket_details_updated', $this->admin->id, 'Old admin entry.');
        $old->forceFill(['created_at' => now()->subMonth()])->save();

        $this->actingAs($this->admin)
            ->get(route('admin.audit', ['account_type' => 'system']))
            ->assertSee('System entry.')
            ->assertDontSee('Admin entry.');

        $this->actingAs($this->admin)
            ->get(route('admin.audit', ['account_type' => User::ROLE_SDS_ADMIN, 'from' => now()->subDay()->toDateString(), 'to' => now()->toDateString()]))
            ->assertSee('Admin entry.')
            ->assertDontSee('Old admin entry.')
            ->assertDontSee('System entry.');
    }

    // ------------------------------------------------------------ profile

    public function test_pro_01_a_profile_photo_can_be_uploaded(): void
    {
        Storage::fake('public');

        $this->actingAs($this->student)
            ->post(route('student.profile.photo'), ['avatar' => UploadedFile::fake()->image('me.jpg', 300, 300)])
            ->assertSessionHasNoErrors();

        $path = $this->student->fresh()->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_pro_02_a_photo_must_be_an_image_up_to_two_megabytes(): void
    {
        Storage::fake('public');

        $this->actingAs($this->student)
            ->post(route('student.profile.photo'), ['avatar' => UploadedFile::fake()->create('me.pdf', 50, 'application/pdf')])
            ->assertSessionHasErrors('avatar');

        $this->actingAs($this->student)
            ->post(route('student.profile.photo'), ['avatar' => UploadedFile::fake()->image('me.jpg')->size(3000)])
            ->assertSessionHasErrors('avatar');

        $this->assertNull($this->student->fresh()->avatar_path);
    }

    // ------------------------------------------------------------ helpers

    protected function user(string $role, string $name): User
    {
        [$first, $last] = explode(' ', $name);

        return User::factory()->create([
            'role' => $role,
            'name' => $name,
            'first_name' => $first,
            'last_name' => $last,
            'is_active' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
    }

    protected function college(string $name, array $programs): Unit
    {
        $college = Unit::create(['name' => $name, 'type' => Unit::TYPE_COLLEGE]);

        foreach ($programs as $program) {
            $college->programs()->create(['name' => $program, 'year_level' => 4, 'block' => 5]);
        }

        return $college;
    }

    protected function studentAccount(array $overrides = []): array
    {
        $this->college('CICT', ['BSIT']);

        return $overrides + [
            'first_name' => 'Nina',
            'last_name' => 'New',
            'email' => 'new@student.test',
            'role' => User::ROLE_STUDENT,
            'student_id' => '20249999',
            'college' => 'CICT',
            'program' => 'BSIT',
            'year_level' => 1,
        ];
    }

    protected function ticketForm(array $overrides = []): array
    {
        return $overrides + [
            'declaration' => '1',
            'category_id' => $this->category->id,
            'subject_title' => 'Transcript request',
            'description' => 'My transcript has not been released.',
        ];
    }

    /**
     * A ticket of the student in the given status, handled by the recipient once it has left review.
     */
    protected function ticket(string $status): Ticket
    {
        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $this->student->student->id,
            'category_id' => $this->category->id,
            'subject_title' => 'Transcript request',
            'description' => 'My transcript has not been released.',
            'is_anonymous' => false,
            'status' => $status,
        ]);

        $underReview = $status === Ticket::STATUS_SUBMITTED;

        return Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => $status,
            'classification' => $underReview ? null : Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
            'jurisdiction' => $underReview ? null : Ticket::JURISDICTION_RECIPIENT,
            'assigned_to' => $underReview ? null : $this->handler->id,
            'current_handler_id' => $underReview ? $this->admin->id : $this->handler->id,
            'resolved_at' => $status === Ticket::STATUS_RESOLVED ? now() : null,
        ]);
    }
}
