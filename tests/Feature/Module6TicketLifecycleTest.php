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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Module6TicketLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_ticket_lifecycle_acknowledge_resolve_and_close(): void
    {
        // Setup users
        /** @var \App\Models\User $admin */
        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN, 'email_verified_at' => now()]);
        /** @var \App\Models\User $studentUser */
        $studentUser = User::factory()->create(['role' => User::ROLE_STUDENT, 'email_verified_at' => now()]);
        /** @var \App\Models\User $recipientUser */
        $recipientUser = User::factory()->create(['role' => User::ROLE_RECIPIENT, 'email_verified_at' => now()]);

        $studentProfile = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'S3001',
            'college' => 'IT',
            'program' => 'BSCS',
            'year_level' => '3rd Year',
            'block' => 'B',
        ]);

        $recipient = Recipient::create([
            'user_id' => $recipientUser->id,
            'staff_id' => 'R3001',
            'unit' => 'Student Affairs',
            'designation' => 'Officer',
        ]);

        $category = ComplaintCategory::create([
            'name' => 'General',
            'is_active' => true,
        ]);

        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $studentProfile->id,
            'category_id' => $category->id,
            'subject_title' => 'Lifecycle test',
            'description' => 'Testing lifecycle',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_SUBMITTED,
        ]);

        $ticket = Ticket::create(['complaint_id' => $complaint->id, 'status' => Ticket::STATUS_SUBMITTED]);

        // Classify and assign to recipient
        $this->actingAs($admin)
            ->post(route('admin.tickets.classify', $ticket), [
                'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
                'jurisdiction' => Ticket::JURISDICTION_RECIPIENT,
            ]);

        $this->actingAs($admin)
            ->post(route('admin.tickets.assign', $ticket), [
                'assignment_mode' => 'recipient',
                'recipient_id' => $recipient->id,
            ]);

        $ticket->refresh();
        $this->assertEquals(Ticket::STATUS_ASSIGNED, $ticket->status);
        $this->assertDatabaseHas('audit_logs', [
            'ticket_id' => $ticket->id,
            'action' => 'ticket_assigned',
            'details' => "SDS Admin assigned the ticket {$complaint->reference_number} to {$recipientUser->display_name} for handling.",
        ]);

        // Recipient acknowledges the ticket
        $this->actingAs($recipientUser)
            ->post(route('recipient.complaints.acknowledge', $complaint))
            ->assertRedirect();

        $ticket->refresh();
        $this->assertEquals(Ticket::STATUS_IN_PROGRESS, $ticket->status);
        $this->assertNotNull($ticket->acknowledged_at);

        // Recipient resolves with a resolution type and message
        $this->actingAs($recipientUser)
            ->patch(route('recipient.complaints.update-status', $complaint), [
                'status' => 'resolved',
                'resolution_type' => Ticket::RESOLUTION_ACTION_TAKEN,
                'resolution_message' => 'Issue resolved by recipient',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertEquals(Ticket::STATUS_RESOLVED, $ticket->status);
        $this->assertEquals(Ticket::RESOLUTION_ACTION_TAKEN, $ticket->resolution_type);
        $this->assertNotNull($ticket->resolved_at);

        $this->assertDatabaseHas('thread_messages', ['content' => 'Issue resolved by recipient', 'sender_id' => $recipientUser->id]);
        $this->assertDatabaseHas('email_notifications', ['ticket_id' => $ticket->id, 'type' => \App\Models\EmailNotification::TYPE_RECIPIENT_RESOLVED]);
        $this->assertDatabaseHas('email_notifications', ['ticket_id' => $ticket->id, 'type' => \App\Models\EmailNotification::TYPE_STUDENT_STATUS_UPDATE]);

        // Admin closes the ticket
        $this->actingAs($admin)
            ->post(route('admin.tickets.close', $ticket))
            ->assertRedirect();

        $ticket->refresh();
        $this->assertEquals(Ticket::STATUS_CLOSED, $ticket->status);
        $this->assertEquals(Ticket::CLOSURE_RESOLVED, $ticket->closure_type);
        $this->assertNotNull($ticket->closed_at);
        $this->assertFalse($ticket->thread->is_active);
        $this->assertDatabaseHas('email_notifications', ['ticket_id' => $ticket->id, 'type' => \App\Models\EmailNotification::TYPE_COMPLAINT_CLOSED]);
    }

    public function test_recipient_status_update_rejects_pending_state(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN, 'email_verified_at' => now()]);
        $studentUser = User::factory()->create(['role' => User::ROLE_STUDENT, 'email_verified_at' => now()]);
        $recipientUser = User::factory()->create(['role' => User::ROLE_RECIPIENT, 'email_verified_at' => now()]);

        $studentProfile = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'S3002',
            'college' => 'IT',
            'program' => 'BSCS',
            'year_level' => '3rd Year',
            'block' => 'A',
        ]);

        $recipient = Recipient::create([
            'user_id' => $recipientUser->id,
            'staff_id' => 'R3002',
            'unit' => 'Student Affairs',
            'designation' => 'Officer',
        ]);

        $category = ComplaintCategory::create([
            'name' => 'General',
            'is_active' => true,
        ]);

        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $studentProfile->id,
            'category_id' => $category->id,
            'subject_title' => 'Pending status test',
            'description' => 'Attempting invalid recipient status.',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_SUBMITTED,
        ]);

        $ticket = Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => Ticket::STATUS_ASSIGNED,
            'assigned_to' => $recipientUser->id,
        ]);

        $this->actingAs($recipientUser)
            ->patch(route('recipient.complaints.update-status', $complaint), [
                'status' => 'pending',
                'details' => 'This should be rejected.',
            ])
            ->assertSessionHasErrors('status');

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_ASSIGNED, $ticket->status);
    }
}
