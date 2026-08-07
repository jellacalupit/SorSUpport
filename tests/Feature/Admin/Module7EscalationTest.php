<?php

namespace Tests\Feature\Admin;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\EmailNotification;
use App\Models\EscalationHierarchy;
use App\Models\Recipient;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Module7EscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_ticket_is_escalated_by_scheduler_command(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $studentUser = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'S7001',
            'department' => 'IT',
            'course' => 'BSIT',
            'year_level' => '2nd Year',
            'block' => 'A',
        ]);

        $firstRecipientUser = User::factory()->create([
            'role' => User::ROLE_RECIPIENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $secondRecipientUser = User::factory()->create([
            'role' => User::ROLE_RECIPIENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $firstRecipient = Recipient::create([
            'user_id' => $firstRecipientUser->id,
            'staff_id' => 'R7001',
            'department' => 'Student Affairs',
            'designation' => 'Officer',
        ]);

        $secondRecipient = Recipient::create([
            'user_id' => $secondRecipientUser->id,
            'staff_id' => 'R7002',
            'department' => 'Student Affairs',
            'designation' => 'Supervisor',
        ]);

        $category = ComplaintCategory::create([
            'name' => 'Academic Concern',
            'resolution_deadline_days' => 3,
            'is_active' => true,
        ]);

        EscalationHierarchy::create([
            'complaint_category_id' => $category->id,
            'level' => 1,
            'recipient_id' => $firstRecipient->id,
        ]);

        EscalationHierarchy::create([
            'complaint_category_id' => $category->id,
            'level' => 2,
            'recipient_id' => $secondRecipient->id,
        ]);

        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $student->id,
            'category_id' => $category->id,
            'subject_title' => 'Overdue escalation',
            'description' => 'Escalate due to overdue deadline',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_PENDING,
        ]);

        $ticket = Ticket::create([
            'complaint_id' => $complaint->id,
            'assigned_to' => $firstRecipientUser->id,
            'current_handler_id' => $firstRecipientUser->id,
            'status' => Ticket::STATUS_IN_PROGRESS,
            'deadline' => now()->subDay(),
        ]);

        $this->artisan('tickets:escalate')->assertSuccessful();

        $ticket->refresh();

        $this->assertSame(Ticket::STATUS_IN_PROGRESS, $ticket->status);
        $this->assertSame($secondRecipientUser->id, $ticket->assigned_to);
        $this->assertSame($secondRecipientUser->id, $ticket->current_handler_id);
        $this->assertTrue($ticket->deadline->isFuture());
        $this->assertDatabaseHas('audit_logs', [
            'ticket_id' => $ticket->id,
            'action' => 'ticket_escalated',
        ]);

        $this->assertDatabaseHas('email_notifications', [
            'ticket_id' => $ticket->id,
            'recipient_email' => $admin->email,
            'type' => EmailNotification::TYPE_ESCALATED,
        ]);

        $this->assertDatabaseHas('email_notifications', [
            'ticket_id' => $ticket->id,
            'recipient_email' => $secondRecipientUser->email,
            'type' => EmailNotification::TYPE_ESCALATED,
        ]);

        $this->assertDatabaseHas('email_notifications', [
            'ticket_id' => $ticket->id,
            'recipient_email' => $studentUser->email,
            'type' => EmailNotification::TYPE_ESCALATED,
        ]);
    }

    public function test_admin_can_manually_escalate_an_active_ticket_without_waiting_for_deadline(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $studentUser = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'S7002',
            'department' => 'IT',
            'course' => 'BSIT',
            'year_level' => '3rd Year',
            'block' => 'B',
        ]);

        $firstRecipientUser = User::factory()->create([
            'role' => User::ROLE_RECIPIENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $secondRecipientUser = User::factory()->create([
            'role' => User::ROLE_RECIPIENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $firstRecipient = Recipient::create([
            'user_id' => $firstRecipientUser->id,
            'staff_id' => 'R7003',
            'department' => 'Student Affairs',
            'designation' => 'Officer',
        ]);

        $secondRecipient = Recipient::create([
            'user_id' => $secondRecipientUser->id,
            'staff_id' => 'R7004',
            'department' => 'Student Affairs',
            'designation' => 'Supervisor',
        ]);

        $category = ComplaintCategory::create([
            'name' => 'Disciplinary Concern',
            'resolution_deadline_days' => 2,
            'is_active' => true,
        ]);

        EscalationHierarchy::create([
            'complaint_category_id' => $category->id,
            'level' => 1,
            'recipient_id' => $firstRecipient->id,
        ]);

        EscalationHierarchy::create([
            'complaint_category_id' => $category->id,
            'level' => 2,
            'recipient_id' => $secondRecipient->id,
        ]);

        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $student->id,
            'category_id' => $category->id,
            'subject_title' => 'Manual escalation',
            'description' => 'Escalate manually',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_PENDING,
        ]);

        $ticket = Ticket::create([
            'complaint_id' => $complaint->id,
            'assigned_to' => $firstRecipientUser->id,
            'current_handler_id' => $firstRecipientUser->id,
            'status' => Ticket::STATUS_ASSIGNED,
            'deadline' => now()->addDay(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.tickets.escalate', $ticket))
            ->assertRedirect(route('admin.tickets.review.show', $ticket));

        $ticket->refresh();

        $this->assertSame(Ticket::STATUS_ASSIGNED, $ticket->status);
        $this->assertSame($secondRecipientUser->id, $ticket->assigned_to);
        $this->assertSame($secondRecipientUser->id, $ticket->current_handler_id);
        $this->assertDatabaseHas('email_notifications', [
            'ticket_id' => $ticket->id,
            'recipient_email' => $admin->email,
            'type' => EmailNotification::TYPE_ESCALATED,
        ]);
        $this->assertDatabaseHas('email_notifications', [
            'ticket_id' => $ticket->id,
            'recipient_email' => $secondRecipientUser->email,
            'type' => EmailNotification::TYPE_ESCALATED,
        ]);
    }
}
