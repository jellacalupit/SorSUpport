<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\EscalationHierarchy;
use App\Models\Recipient;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Module9AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_ticket_lifecycle_creates_expected_audit_logs_and_audit_logs_are_chronological(): void
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN, 'email_verified_at' => now()]);
        /** @var \App\Models\User $studentUser */
        $studentUser = User::factory()->create(['role' => User::ROLE_STUDENT, 'email_verified_at' => now()]);
        /** @var \App\Models\User $recipientOneUser */
        $recipientOneUser = User::factory()->create(['role' => User::ROLE_RECIPIENT, 'email_verified_at' => now()]);
        /** @var \App\Models\User $recipientTwoUser */
        $recipientTwoUser = User::factory()->create(['role' => User::ROLE_RECIPIENT, 'email_verified_at' => now()]);

        /** @var \App\Models\Student $student */
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'S9001',
            'college' => 'IT',
            'program' => 'BSCS',
            'year_level' => '4th Year',
            'block' => 'D',
        ]);

        /** @var \App\Models\Recipient $recipientOne */
        $recipientOne = Recipient::create([
            'user_id' => $recipientOneUser->id,
            'staff_id' => 'R9001',
            'unit' => 'Student Affairs',
            'designation' => 'Officer',
        ]);

        /** @var \App\Models\Recipient $recipientTwo */
        $recipientTwo = Recipient::create([
            'user_id' => $recipientTwoUser->id,
            'staff_id' => 'R9002',
            'unit' => 'Student Affairs',
            'designation' => 'Senior Officer',
        ]);

        /** @var \App\Models\ComplaintCategory $category */
        $category = ComplaintCategory::create([
            'name' => 'Audit Trail Category',
            'recipient_id' => $recipientOne->id,
            'resolution_deadline_days' => 5,
            'is_active' => true,
        ]);

        EscalationHierarchy::create([
            'complaint_category_id' => $category->id,
            'recipient_id' => $recipientOne->id,
            'level' => 1,
        ]);

        EscalationHierarchy::create([
            'complaint_category_id' => $category->id,
            'recipient_id' => $recipientTwo->id,
            'level' => 2,
        ]);

        $this->actingAs($studentUser)
            ->post(route('student.complaints.store'), [
                'category_id' => $category->id,
                'subject_title' => 'Audit trail submission',
                'personnel_involved' => 'None',
                'description' => 'Audit trail test description.',
                'is_anonymous' => false,
            ])
            ->assertRedirect();

        /** @var \App\Models\Complaint $complaint */
        $complaint = Complaint::query()->firstOrFail();
        /** @var \App\Models\Ticket $ticket */
        $ticket = Ticket::where('complaint_id', $complaint->id)->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'ticket_id' => $ticket->id,
            'action' => 'complaint_submitted',
            'performed_by' => $studentUser->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.tickets.classify', $ticket), [
                'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
                'jurisdiction' => Ticket::JURISDICTION_RECIPIENT,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.tickets.assign', $ticket), [
                'assignment_mode' => 'recipient',
                'recipient_id' => $recipientOne->id,
            ])
            ->assertRedirect();

        $this->actingAs($studentUser)
            ->post(route('student.complaints.reply', $complaint), [
                'content' => 'Student reply for audit tracking.',
            ])
            ->assertRedirect();

        $this->actingAs($recipientOneUser)
            ->post(route('recipient.complaints.reply', $complaint), [
                'content' => 'Recipient reply for audit tracking.',
            ])
            ->assertRedirect();

        // Assigning to a recipient moves the ticket straight to in progress, so there is no acknowledge step.
        $this->actingAs($admin)
            ->post(route('admin.tickets.escalate', $ticket), ['recipient_id' => $recipientTwo->id])
            ->assertRedirect();

        $ticket->refresh();

        $this->actingAs($recipientTwoUser)
            ->patch(route('recipient.complaints.update-status', $complaint), [
                'status' => 'resolved',
                'details' => 'Resolved after escalation.',
                'resolution_message' => 'Resolved by second recipient.',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.tickets.close', $ticket))
            ->assertRedirect();

        $ticket->refresh();

        $expectedActions = [
            'complaint_submitted',
            'ticket_classified',
            'ticket_assigned',
            'message_posted',
            'message_posted',
            'ticket_escalated',
            'complaint_resolved',
            'ticket_closed',
        ];

        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\AuditLog> $auditLogs */
        $auditLogs = $ticket->auditLogs;
        $this->assertCount(count($expectedActions), $auditLogs);

        $this->assertSame($expectedActions, $auditLogs->pluck('action')->all());

        $this->assertDatabaseHas('audit_logs', [
            'ticket_id' => $ticket->id,
            'action' => 'ticket_assigned',
            'performed_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'ticket_id' => $ticket->id,
            'action' => 'ticket_escalated',
            'performed_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'ticket_id' => $ticket->id,
            'action' => 'complaint_resolved',
            'performed_by' => $recipientTwoUser->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'ticket_id' => $ticket->id,
            'action' => 'ticket_closed',
            'performed_by' => $admin->id,
        ]);

        /** @var \App\Models\AuditLog $firstStudentMessage */
        $firstStudentMessage = AuditLog::query()
            ->where('ticket_id', $ticket->id)
            ->where('action', 'message_posted')
            ->where('performed_by', $studentUser->id)
            ->firstOrFail();
        $this->assertNotNull($firstStudentMessage);
        $this->assertStringContainsString('Student posted a reply message', $firstStudentMessage->details);
        $this->assertNotNull($firstStudentMessage->created_at);

        /** @var \App\Models\AuditLog $firstRecipientMessage */
        $firstRecipientMessage = AuditLog::query()
            ->where('ticket_id', $ticket->id)
            ->where('action', 'message_posted')
            ->where('performed_by', $recipientOneUser->id)
            ->firstOrFail();
        $this->assertNotNull($firstRecipientMessage);
        $this->assertStringContainsString('Recipient posted a reply message', $firstRecipientMessage->details);
        $this->assertNotNull($firstRecipientMessage->created_at);

        $createdAtSequence = $auditLogs->pluck('created_at')->map(fn ($createdAt) => $createdAt->getTimestamp())->all();
        $this->assertSame($createdAtSequence, collect($createdAtSequence)->sort()->values()->all());
    }

    public function test_ticket_rejection_logs_invalid_closure_action(): void
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN, 'email_verified_at' => now()]);
        /** @var \App\Models\User $studentUser */
        $studentUser = User::factory()->create(['role' => User::ROLE_STUDENT, 'email_verified_at' => now()]);
        /** @var \App\Models\Student $student */
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'S9002',
            'college' => 'IT',
            'program' => 'BSCS',
            'year_level' => '4th Year',
            'block' => 'D',
        ]);
        /** @var \App\Models\User $recipientUser */
        $recipientUser = User::factory()->create(['role' => User::ROLE_RECIPIENT, 'email_verified_at' => now()]);
        /** @var \App\Models\Recipient $recipient */
        $recipient = Recipient::create([
            'user_id' => $recipientUser->id,
            'staff_id' => 'R9003',
            'unit' => 'Student Affairs',
            'designation' => 'Officer',
        ]);

        /** @var \App\Models\ComplaintCategory $category */
        $category = ComplaintCategory::create([
            'name' => 'Invalid Closure Category',
            'recipient_id' => $recipient->id,
            'resolution_deadline_days' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($studentUser)
            ->post(route('student.complaints.store'), [
                'category_id' => $category->id,
                'subject_title' => 'Rejection test',
                'personnel_involved' => 'None',
                'description' => 'Testing invalid closure audit logging.',
                'is_anonymous' => false,
            ])
            ->assertRedirect();

        /** @var \App\Models\Ticket $ticket */
        $ticket = Ticket::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.tickets.reject', $ticket), [
                'closure_reason' => 'Not a valid complaint.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'ticket_id' => $ticket->id,
            'action' => 'ticket_closed_invalid',
            'performed_by' => $admin->id,
        ]);

        /** @var \App\Models\AuditLog $log */
        $log = AuditLog::where('ticket_id', $ticket->id)
            ->where('action', 'ticket_closed_invalid')
            ->firstOrFail();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Not a valid complaint.', $log->details);
        $this->assertNotNull($log->created_at);
    }

    public function test_anonymous_complaint_stays_pending_for_admin_review_and_hides_identity(): void
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN, 'email_verified_at' => now()]);
        /** @var \App\Models\User $studentUser */
        $studentUser = User::factory()->create(['role' => User::ROLE_STUDENT, 'email_verified_at' => now()]);
        /** @var \App\Models\Student $student */
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'S9003',
            'college' => 'IT',
            'program' => 'BSCS',
            'year_level' => '4th Year',
            'block' => 'D',
        ]);
        /** @var \App\Models\User $recipientUser */
        $recipientUser = User::factory()->create(['role' => User::ROLE_RECIPIENT, 'email_verified_at' => now()]);
        /** @var \App\Models\Recipient $recipient */
        $recipient = Recipient::create([
            'user_id' => $recipientUser->id,
            'staff_id' => 'R9004',
            'unit' => 'Student Affairs',
            'designation' => 'Officer',
        ]);

        /** @var \App\Models\ComplaintCategory $category */
        $category = ComplaintCategory::create([
            'name' => 'Anonymous Category',
            'recipient_id' => $recipient->id,
            'resolution_deadline_days' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($studentUser)
            ->post(route('student.complaints.store'), [
                'category_id' => $category->id,
                'subject_title' => 'Anonymous complaint',
                'personnel_involved' => 'None',
                'description' => 'This is anonymous.',
                'is_anonymous' => true,
            ])
            ->assertRedirect();

        /** @var \App\Models\Complaint $complaint */
        $complaint = Complaint::query()->firstOrFail();

        $this->assertDatabaseHas('tickets', [
            'complaint_id' => $complaint->id,
            'status' => Ticket::STATUS_PENDING,
            'classification' => null,
            'current_handler_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'anonymous_complaint_submitted',
            'performed_by' => null,
        ]);

        $ticket = $complaint->ticket;

        $this->actingAs($admin)
            ->get(route('admin.tickets.review.index'))
            ->assertOk()
            ->assertSee($complaint->reference_number)
            ->assertDontSee($studentUser->name)
            ->assertDontSee($studentUser->email);

        // An anonymous submission can only be kept as an informational record.
        $this->actingAs($admin)
            ->post(route('admin.tickets.assign', $ticket), [
                'assignment_mode' => 'recipient',
                'recipient_id' => $recipient->id,
            ])
            ->assertStatus(422);

        $this->actingAs($admin)
            ->post(route('admin.tickets.forward-informational-close', $ticket), [
                'recipient_id' => $recipient->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'status' => Ticket::STATUS_CLOSED,
            'classification' => Ticket::CLASSIFICATION_INFORMATIONAL,
            'forwarded_to' => $recipient->id,
        ]);
    }

    public function test_admin_ticket_review_page_loads_and_displays_audit_entries_in_order(): void
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN, 'email_verified_at' => now()]);
        /** @var \App\Models\User $studentUser */
        $studentUser = User::factory()->create(['role' => User::ROLE_STUDENT, 'email_verified_at' => now()]);
        /** @var \App\Models\User $recipientUser */
        $recipientUser = User::factory()->create(['role' => User::ROLE_RECIPIENT, 'email_verified_at' => now()]);

        /** @var \App\Models\Student $student */
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'S9004',
            'college' => 'IT',
            'program' => 'BSCS',
            'year_level' => '4th Year',
            'block' => 'D',
        ]);

        /** @var \App\Models\Recipient $recipient */
        $recipient = Recipient::create([
            'user_id' => $recipientUser->id,
            'staff_id' => 'R9005',
            'unit' => 'Student Affairs',
            'designation' => 'Officer',
        ]);

        /** @var \App\Models\ComplaintCategory $category */
        $category = ComplaintCategory::create([
            'name' => 'Audit History Category',
            'recipient_id' => $recipient->id,
            'resolution_deadline_days' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($studentUser)
            ->post(route('student.complaints.store'), [
                'category_id' => $category->id,
                'subject_title' => 'Admin audit page test',
                'personnel_involved' => 'None',
                'description' => 'Testing admin audit page.',
                'is_anonymous' => false,
            ])
            ->assertRedirect();

        /** @var \App\Models\Ticket $ticket */
        $ticket = Ticket::query()->firstOrFail();

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

        /** @var \App\Models\Complaint $complaint */
        $complaint = Complaint::query()->firstOrFail();

        $response = $this->actingAs($admin)
            ->get(route('admin.complaints.show', $complaint));

        $response->assertOk();

        // The admin ticket view lists the latest audit entries, newest first.
        $this->assertStringContainsInOrder($response->getContent(), [
            'Ticket assigned',
            'Ticket classified',
            'Complaint submitted',
        ]);
    }

    private function assertStringContainsInOrder(string $content, array $needles): void
    {
        $position = -1;

        foreach ($needles as $needle) {
            $next = strpos($content, $needle);
            $this->assertNotFalse($next, "Expected to find [{$needle}] in response content.");
            $this->assertGreaterThan($position, $next, "Expected [{$needle}] to appear after previous content.");
            $position = $next;
        }
    }
}
