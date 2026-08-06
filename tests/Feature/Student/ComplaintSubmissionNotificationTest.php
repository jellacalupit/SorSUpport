<?php

namespace Tests\Feature\Student;

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
