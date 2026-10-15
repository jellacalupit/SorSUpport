<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\EmailNotification;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\User;
use App\Services\EmailNotificationService;
use App\Services\TicketReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TicketRemindersAndDeliveryTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------ email retries

    public function test_a_failed_email_is_tried_again_later_instead_of_given_up(): void
    {
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('Mail server unavailable'));

        $notification = $this->pendingEmail($this->ticket());

        $this->assertSame(0, app(EmailNotificationService::class)->sendPendingNotifications());

        $notification->refresh();
        $this->assertSame(EmailNotification::STATUS_PENDING, $notification->status);
        $this->assertSame(1, $notification->attempts);
        $this->assertTrue($notification->next_attempt_at->isFuture());

        // Not tried again before its time.
        $this->assertSame(0, app(EmailNotificationService::class)->sendPendingNotifications());
        $this->assertSame(1, $notification->fresh()->attempts);
    }

    public function test_an_email_that_keeps_failing_is_given_up_and_recorded_in_the_audit_trail(): void
    {
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('Mail server unavailable'));

        $notification = $this->pendingEmail($this->ticket(), 'office@example.com');

        for ($try = 1; $try <= EmailNotification::MAX_ATTEMPTS; $try++) {
            // Each retry comes due.
            EmailNotification::whereKey($notification->id)->update(['next_attempt_at' => now()->subMinute()]);
            app(EmailNotificationService::class)->sendPendingNotifications();
        }

        $notification->refresh();
        $this->assertSame(EmailNotification::STATUS_FAILED, $notification->status);
        $this->assertSame(EmailNotification::MAX_ATTEMPTS, $notification->attempts);
        $this->assertDatabaseHas('audit_logs', [
            'ticket_id' => $notification->ticket_id,
            'action' => 'email_notification_failed',
            'details' => 'Email to office@example.com could not be sent after 5 tries. Please reach them another way.',
        ]);
    }

    public function test_a_failed_email_entry_hides_a_hidden_identity_students_address(): void
    {
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('Mail server unavailable'));

        $ticket = $this->ticket(anonymous: true);
        $notification = $this->pendingEmail($ticket, $ticket->complaint->student->user->email);
        $notification->update(['attempts' => EmailNotification::MAX_ATTEMPTS - 1]);

        app(EmailNotificationService::class)->sendPendingNotifications();

        $details = AuditLog::query()->where('action', 'email_notification_failed')->value('details');
        $this->assertStringContainsString('Email to the student could not be sent', $details);
    }

    public function test_a_failed_email_does_not_count_as_an_action_on_the_ticket(): void
    {
        $ticket = $this->daysAgo(fn () => $this->ticket(), days: 3);

        AuditLog::create(['ticket_id' => $ticket->id, 'performed_by' => null, 'action' => 'email_notification_failed', 'details' => 'x']);

        $this->assertSame(3, $ticket->fresh()->daysSinceLastAction());
    }

    // ------------------------------------------------------------ waiting reminders

    public function test_the_sds_office_is_reminded_of_a_ticket_waiting_for_review(): void
    {
        $admin = $this->admin();
        $ticket = $this->daysAgo(fn () => $this->ticket(), days: 3);

        $this->assertSame(1, app(TicketReminderService::class)->queueReminders());

        $this->assertDatabaseHas('email_notifications', [
            'ticket_id' => $ticket->id,
            'recipient_email' => $admin->email,
            'type' => EmailNotification::TYPE_WAITING_REMINDER,
        ]);
    }

    public function test_the_handler_is_reminded_of_a_ticket_not_yet_acknowledged(): void
    {
        $admin = $this->admin();
        $handler = User::factory()->create(['role' => User::ROLE_RECIPIENT]);
        $ticket = $this->daysAgo(fn () => $this->ticket([
            'status' => Ticket::STATUS_ASSIGNED,
            'assigned_to' => $handler->id,
            'current_handler_id' => $handler->id,
        ]), days: 3);

        app(TicketReminderService::class)->queueReminders();

        $this->assertDatabaseHas('email_notifications', ['ticket_id' => $ticket->id, 'recipient_email' => $handler->email, 'type' => EmailNotification::TYPE_WAITING_REMINDER]);
        $this->assertDatabaseMissing('email_notifications', ['recipient_email' => $admin->email]);
    }

    public function test_the_handler_is_reminded_when_nothing_is_recorded_for_too_long(): void
    {
        $handler = User::factory()->create(['role' => User::ROLE_RECIPIENT]);
        $ticket = $this->daysAgo(fn () => $this->ticket([
            'status' => Ticket::STATUS_IN_PROGRESS,
            'assigned_to' => $handler->id,
            'current_handler_id' => $handler->id,
        ]), days: 6);

        app(TicketReminderService::class)->queueReminders();

        $this->assertDatabaseHas('email_notifications', ['ticket_id' => $ticket->id, 'recipient_email' => $handler->email]);
    }

    public function test_no_reminder_while_the_ticket_is_within_its_allowed_days(): void
    {
        $this->admin();
        $handler = User::factory()->create(['role' => User::ROLE_RECIPIENT]);
        $this->daysAgo(fn () => $this->ticket(['status' => Ticket::STATUS_IN_PROGRESS, 'current_handler_id' => $handler->id]), days: 3);
        $this->ticket();

        $this->assertSame(0, app(TicketReminderService::class)->queueReminders());
    }

    public function test_a_recent_action_resets_the_wait(): void
    {
        $this->admin();
        $ticket = $this->daysAgo(fn () => $this->ticket(), days: 3);

        AuditLog::log($ticket->id, 'ticket_details_updated', null, 'Category corrected.');

        $this->assertSame(0, app(TicketReminderService::class)->queueReminders());
    }

    public function test_a_reminder_repeats_only_after_another_waiting_period(): void
    {
        $this->admin();
        $this->daysAgo(fn () => $this->ticket(), days: 5);
        $service = app(TicketReminderService::class);

        $this->assertSame(1, $service->queueReminders());
        $this->assertSame(0, $service->queueReminders());

        $this->travel(2)->days();
        $this->assertSame(1, $service->queueReminders());
    }

    public function test_referred_and_resolved_tickets_are_not_reminded(): void
    {
        $this->admin();
        $this->daysAgo(fn () => $this->ticket(['status' => Ticket::STATUS_REFERRED]), days: 30);
        $this->daysAgo(fn () => $this->ticket(['status' => Ticket::STATUS_RESOLVED]), days: 30);

        $this->assertSame(0, app(TicketReminderService::class)->queueReminders());
    }

    public function test_a_reminder_says_how_long_the_ticket_waited_and_is_marked_important(): void
    {
        config(['mail.default' => 'array']);
        $admin = $this->admin();
        $ticket = $this->daysAgo(fn () => $this->ticket(), days: 3);

        app(TicketReminderService::class)->queueReminders();
        app(EmailNotificationService::class)->sendPendingNotifications();

        $email = app('mailer')->getSymfonyTransport()->messages()->first()->getOriginalMessage();

        $this->assertSame($admin->email, $email->getTo()[0]->getAddress());
        $this->assertSame("Action needed: {$ticket->complaint->reference_number} has waited 3 days for review", $email->getSubject());
        $this->assertSame(1, $email->getPriority());
        $this->assertSame('High', $email->getHeaders()->get('Importance')?->getBodyAsString());
    }

    // ------------------------------------------------------------ submission limit

    public function test_a_student_can_submit_only_a_few_tickets_an_hour(): void
    {
        config(['tickets.submissions_per_hour' => 2]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Student::create(['user_id' => $student->id, 'student_id' => '20240009', 'college' => 'CICT', 'program' => 'BSIT', 'year_level' => '1', 'block' => '1']);
        $category = ComplaintCategory::create(['name' => 'General', 'is_active' => true]);
        $ticket = ['declaration' => '1', 'category_id' => $category->id, 'subject_title' => 'Concern', 'description' => 'Details of the concern.'];

        $this->actingAs($student)->post(route('student.complaints.store'), $ticket)->assertSessionHasNoErrors();
        $this->actingAs($student)->post(route('student.complaints.store'), $ticket)->assertSessionHasNoErrors();
        $this->actingAs($student)->post(route('student.complaints.store'), $ticket)->assertSessionHasErrors('submission');

        $this->assertSame(2, Complaint::count());

        $this->travel(61)->minutes();
        $this->actingAs($student)->post(route('student.complaints.store'), $ticket)->assertSessionHasNoErrors();
        $this->assertSame(3, Complaint::count());
    }

    // ------------------------------------------------------------ helpers

    /**
     * Create something as if it happened the given number of days ago.
     */
    private function daysAgo(callable $create, int $days): mixed
    {
        $this->travel(-$days)->days();
        $created = $create();
        $this->travelBack();

        return $created;
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SDS_ADMIN]);
    }

    private function pendingEmail(Ticket $ticket, string $email = 'someone@example.com'): EmailNotification
    {
        return EmailNotification::create([
            'ticket_id' => $ticket->id,
            'recipient_email' => $email,
            'type' => EmailNotification::TYPE_STATUS_UPDATE,
            'status' => EmailNotification::STATUS_PENDING,
        ]);
    }

    private function ticket(array $attributes = [], bool $anonymous = false): Ticket
    {
        $user = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $student = Student::create(['user_id' => $user->id, 'student_id' => (string) $user->id, 'college' => 'CICT', 'program' => 'BSIT', 'year_level' => '1', 'block' => '1']);
        $category = ComplaintCategory::firstOrCreate(['name' => 'General'], ['is_active' => true]);

        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $student->id,
            'category_id' => $category->id,
            'subject_title' => 'Waiting ticket',
            'description' => 'Testing reminders.',
            'is_anonymous' => $anonymous,
            'status' => Complaint::STATUS_SUBMITTED,
        ]);

        return Ticket::create($attributes + ['complaint_id' => $complaint->id, 'status' => Ticket::STATUS_SUBMITTED]);
    }
}
