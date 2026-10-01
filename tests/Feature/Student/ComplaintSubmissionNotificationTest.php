<?php

namespace Tests\Feature\Student;

use App\Models\AuditLog;
use App\Models\Complaint as ComplaintModel;
use App\Models\ComplaintCategory;
use App\Models\EmailNotification;
use App\Models\Recipient;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplaintSubmissionNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_notifications_only_include_student_facing_audit_events(): void
    {
        /** @var User $student */
        $student = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $studentProfile = Student::create([
            'user_id' => $student->id,
            'student_id' => 'S2003',
            'department' => 'IT',
            'course' => 'BSCS',
            'year_level' => '3rd Year',
            'block' => 'A',
        ]);

        $category = ComplaintCategory::create([
            'name' => 'Notification Filter',
            'resolution_deadline_days' => 5,
            'is_active' => true,
        ]);

        $complaint = ComplaintModel::create([
            'reference_number' => ComplaintModel::generateReferenceNumber(),
            'student_id' => $studentProfile->id,
            'category_id' => $category->id,
            'subject_title' => 'Notification filter test',
            'description' => 'Notification filter test.',
            'is_anonymous' => false,
            'status' => ComplaintModel::STATUS_PENDING,
        ]);

        $ticket = Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => Ticket::STATUS_ASSIGNED,
        ]);

        $recipient = User::factory()->create([
            'name' => 'Jamie Marie Santos',
            'role' => User::ROLE_RECIPIENT,
        ]);

        AuditLog::log($ticket->id, 'complaint_submitted', $student->id, 'Complaint was submitted.');
        AuditLog::log($ticket->id, 'ticket_classified', $student->id, 'Internal classification details.');
        AuditLog::log($ticket->id, 'ticket_assigned', $recipient->id, 'Ticket assigned details.');
        AuditLog::log($ticket->id, 'message_posted', $student->id, 'Student sent a message to themself.');
        AuditLog::log($ticket->id, 'message_posted', $recipient->id, 'Recipient posted a reply message.');

        $this->actingAs($student)
            ->get(route('student.notifications'))
            ->assertOk()
            ->assertSee('Complaint received')
            ->assertSee('Ticket assigned')
            ->assertSee('Ticket assigned details.')
            ->assertSee($complaint->reference_number)
            ->assertSee('Jamie M. Santos sent you a message.')
            ->assertDontSee('Internal classification details.')
            ->assertDontSee('Student sent a message to themself.');
    }

    public function test_my_tickets_filters_by_ticket_status(): void
    {
        /** @var User $student */
        $student = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $studentProfile = Student::create([
            'user_id' => $student->id,
            'student_id' => 'S2002',
            'department' => 'IT',
            'course' => 'BSCS',
            'year_level' => '3rd Year',
            'block' => 'A',
        ]);

        $category = ComplaintCategory::create([
            'name' => 'Status Filter',
            'resolution_deadline_days' => 5,
            'is_active' => true,
        ]);

        foreach ([
            ['subject_title' => 'Assigned ticket', 'ticket_status' => Ticket::STATUS_ASSIGNED],
            ['subject_title' => 'In progress ticket', 'ticket_status' => Ticket::STATUS_IN_PROGRESS],
        ] as $ticketData) {
            $complaint = ComplaintModel::create([
                'reference_number' => ComplaintModel::generateReferenceNumber(),
                'student_id' => $studentProfile->id,
                'category_id' => $category->id,
                'subject_title' => $ticketData['subject_title'],
                'description' => 'Status filter test.',
                'is_anonymous' => false,
                'status' => ComplaintModel::STATUS_PENDING,
            ]);

            Ticket::create([
                'complaint_id' => $complaint->id,
                'status' => $ticketData['ticket_status'],
            ]);
        }

        $this->actingAs($student)
            ->get(route('student.complaints.index', ['status' => Ticket::STATUS_ASSIGNED]))
            ->assertOk()
            ->assertSee('Assigned ticket')
            ->assertDontSee('In progress ticket');
    }

    public function test_successful_complaint_submission_creates_submission_ack_notification(): void
    {
        /** @var User $student */
        $student = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $studentProfile = Student::create([
            'user_id' => $student->id,
            'student_id' => 'S2001',
            'department' => 'IT',
            'course' => 'BSCS',
            'year_level' => '3rd Year',
            'block' => 'A',
        ]);

        $recipientUser = User::factory()->create([
            'role' => User::ROLE_RECIPIENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $recipient = Recipient::create([
            'user_id' => $recipientUser->id,
            'staff_id' => 'R2001',
            'department' => 'IT',
            'designation' => 'Recipient',
        ]);

        $category = ComplaintCategory::create([
            'name' => 'General Inquiry',
            'resolution_deadline_days' => 5,
            'is_active' => true,
            'recipient_id' => $recipient->id,
        ]);

        $response = $this->actingAs($student)
            ->post(route('student.complaints.store'), [
                'category_id' => $category->id,
                'subject_title' => 'Complaint submission notification test',
                'description' => 'This complaint tests submission notification creation.',
                'is_anonymous' => false,
            ]);

        $response->assertStatus(302);

        $this->assertDatabaseHas('complaints', [
            'subject_title' => 'Complaint submission notification test',
            'student_id' => $studentProfile->id,
            'category_id' => $category->id,
            'status' => ComplaintModel::STATUS_PENDING,
        ]);

        $complaint = ComplaintModel::firstOrFail();
        $ticket = Ticket::where('complaint_id', $complaint->id)->firstOrFail();

        $this->assertSame($complaint->id, $ticket->complaint_id);

        $this->assertDatabaseHas('email_notifications', [
            'ticket_id' => $ticket->id,
            'recipient_email' => $student->email,
            'type' => EmailNotification::TYPE_SUBMISSION_ACK,
            'status' => EmailNotification::STATUS_PENDING,
        ]);

        $notification = EmailNotification::query()
            ->where('ticket_id', $ticket->id)
            ->where('recipient_email', $student->email)
            ->where('type', EmailNotification::TYPE_SUBMISSION_ACK)
            ->firstOrFail();

        $this->assertSame(EmailNotification::STATUS_PENDING, $notification->status);
        $this->assertSame($student->email, $notification->recipient_email);
        $this->assertSame($ticket->id, $notification->ticket_id);
        $this->assertSame($complaint->id, $notification->ticket->complaint_id);
    }
}
