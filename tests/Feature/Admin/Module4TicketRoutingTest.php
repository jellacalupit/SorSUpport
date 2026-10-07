<?php

namespace Tests\Feature\Admin;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Recipient;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Module4TicketRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_a_needs_resolution_ticket_to_a_recipient_without_a_deadline(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        /** @var User $student */
        $student = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $studentProfile = Student::create([
            'user_id' => $student->id,
            'student_id' => 'S2001',
            'college' => 'IT',
            'program' => 'BSIT',
            'year_level' => '2nd Year',
            'block' => 'A',
        ]);

        $recipientUser = User::factory()->create([
            'role' => User::ROLE_RECIPIENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $recipient = Recipient::create([
            'user_id' => $recipientUser->id,
            'staff_id' => 'R1001',
            'unit' => 'Student Affairs',
            'designation' => 'Officer',
        ]);

        $category = ComplaintCategory::create([
            'name' => 'Academic Concern',
            'is_active' => true,
        ]);

        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $studentProfile->id,
            'category_id' => $category->id,
            'subject_title' => 'Needs resolution',
            'description' => 'Needs resolution description',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_SUBMITTED,
        ]);

        $ticket = Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => Ticket::STATUS_SUBMITTED,
            'assigned_to' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.tickets.classify', $ticket), [
                'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
                'jurisdiction' => Ticket::JURISDICTION_RECIPIENT,
            ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.tickets.assign', $ticket), [
                'assignment_mode' => 'recipient',
                'recipient_id' => $recipient->id,
            ]);

        $response->assertRedirect(route('admin.tickets.review.index'));

        $ticket->refresh();

        // The ticket waits as Assigned until the recipient acknowledges it.
        $this->assertSame(Ticket::STATUS_ASSIGNED, $ticket->status);
        $this->assertSame($recipient->user_id, $ticket->current_handler_id);
        $this->assertSame($recipient->user_id, $ticket->assigned_to);
        $this->assertNull($ticket->deadline);
        $this->assertTrue($ticket->thread->is_active);

        $this->assertDatabaseHas('email_notifications', [
            'ticket_id' => $ticket->id,
            'recipient_email' => $recipientUser->email,
            'type' => \App\Models\EmailNotification::TYPE_RECIPIENT_ASSIGNMENT,
            'status' => \App\Models\EmailNotification::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('email_notifications', [
            'ticket_id' => $ticket->id,
            'recipient_email' => $student->email,
            'type' => \App\Models\EmailNotification::TYPE_STUDENT_STATUS_UPDATE,
            'status' => \App\Models\EmailNotification::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'ticket_id' => $ticket->id,
            'action' => 'ticket_assigned',
        ]);
    }
}
