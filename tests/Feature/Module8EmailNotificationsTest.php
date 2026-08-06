<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\EmailNotification;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\User;
use App\Services\EmailNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Module8EmailNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_notification_is_sent_and_logged(): void
    {
        Mail::fake();

        $ticket = $this->createTicket();

        $notification = EmailNotification::create([
            'ticket_id' => $ticket->id,
            'recipient_email' => 'student@example.com',
            'type' => EmailNotification::TYPE_VERIFICATION,
            'status' => EmailNotification::STATUS_PENDING,
        ]);

        $result = app(EmailNotificationService::class)->sendPendingNotifications();

        $this->assertSame(1, $result);

        $notification->refresh();

        $this->assertSame(
            EmailNotification::STATUS_SENT,
            $notification->status
        );

        $this->assertNotNull($notification->sent_at);
    }

    public function test_supported_notification_types_use_their_templates(): void
    {
        Mail::fake();

        $ticket = $this->createTicket();

        $cases = [
            [EmailNotification::TYPE_SUBMISSION_ACK, 'submission@example.com'],
            [EmailNotification::TYPE_INVALID_CLOSURE, 'invalid@example.com'],
            [EmailNotification::TYPE_ASSIGNMENT, 'assignment@example.com'],
            [EmailNotification::TYPE_STATUS_UPDATE, 'status@example.com'],
            [EmailNotification::TYPE_ESCALATED, 'escalation@example.com'],
            [EmailNotification::TYPE_DAILY_REMINDER, 'reminder@example.com'],
            [EmailNotification::TYPE_CLOSED, 'closed@example.com'],
        ];

        foreach ($cases as [$type, $recipient]) {

            $notification = EmailNotification::create([
                'ticket_id' => $ticket->id,
                'recipient_email' => $recipient,
                'type' => $type,
                'status' => EmailNotification::STATUS_PENDING,
            ]);

            $result = app(EmailNotificationService::class)
                ->sendNotification($notification);

            $this->assertTrue($result);

            $notification->refresh();

            $this->assertSame(
                EmailNotification::STATUS_SENT,
                $notification->status
            );

            $this->assertNotNull($notification->sent_at);
        }
    }

    public function test_daily_reminders_are_sent_to_current_handler_for_tickets_nearing_deadline(): void
    {
        Mail::fake();

        $handler = User::factory()->create([
            'email' => 'handler@example.com',
            'role' => User::ROLE_RECIPIENT,
        ]);

        $this->createTicket([
            'current_handler_id' => $handler->id,
            'deadline' => now()->addDays(3),
            'status' => Ticket::STATUS_ASSIGNED,
        ]);

        $sentCount = app(EmailNotificationService::class)
            ->sendDailyReminders();

        $this->assertSame(1, $sentCount);

        $this->assertDatabaseHas('email_notifications', [
            'recipient_email' => $handler->email,
            'type' => EmailNotification::TYPE_DAILY_REMINDER,
            'status' => EmailNotification::STATUS_SENT,
        ]);
    }

    private function createTicket(array $overrides = []): Ticket
    {
        $studentUser = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'email_verified_at' => now(),
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'S3001',
            'department' => 'IT',
            'course' => 'BSCS',
            'year_level' => '3rd Year',
            'block' => 'B',
        ]);

        $category = ComplaintCategory::create([
            'name' => 'General',
            'resolution_deadline_days' => 5,
            'is_active' => true,
        ]);

        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $student->id,
            'category_id' => $category->id,
            'subject_title' => 'Module 8 test',
            'description' => 'Testing notification delivery.',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_PENDING,
        ]);

        return Ticket::create(array_merge([
            'complaint_id' => $complaint->id,
            'status' => Ticket::STATUS_PENDING,
        ], $overrides));
    }
}