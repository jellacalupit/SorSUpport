<?php

namespace Tests\Feature\Admin;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Recipient;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\TicketThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Module3TicketReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_ticket_closes_and_records_reason(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $student = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $studentProfile = Student::create([
            'user_id' => $student->id,
            'student_id' => 'S1001',
            'department' => 'IT',
            'course' => 'BSIT',
            'year_level' => '2nd Year',
            'block' => 'A',
        ]);

        $category = ComplaintCategory::create([
            'name' => 'Academic Concern',
            'resolution_deadline_days' => 3,
            'is_active' => true,
        ]);

        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $studentProfile->id,
            'category_id' => $category->id,
            'subject_title' => 'Test complaint',
            'description' => 'Test description',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_PENDING,
        ]);

        $ticket = Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => Ticket::STATUS_PENDING,
            'assigned_to' => null,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.tickets.reject', $ticket), [
                'closure_reason' => 'Not a valid complaint.',
            ]);

        $response->assertRedirect(route('admin.tickets.review.index'));
        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_CLOSED, $ticket->status);
        $this->assertSame(Ticket::CLASSIFICATION_INVALID, $ticket->classification);
        $this->assertSame('Not a valid complaint.', $ticket->closure_reason);
        $this->assertNotNull($ticket->closed_at);
    }

    public function test_needs_resolution_classification_creates_thread(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $student = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $studentProfile = Student::create([
            'user_id' => $student->id,
            'student_id' => 'S1002',
            'department' => 'IT',
            'course' => 'BSIT',
            'year_level' => '2nd Year',
            'block' => 'A',
        ]);

        $category = ComplaintCategory::create([
            'name' => 'Service Concern',
            'resolution_deadline_days' => 5,
            'is_active' => true,
        ]);

        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $studentProfile->id,
            'category_id' => $category->id,
            'subject_title' => 'Needs resolution',
            'description' => 'Needs resolution description',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_PENDING,
        ]);

        $ticket = Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => Ticket::STATUS_PENDING,
            'assigned_to' => null,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.tickets.classify', $ticket), [
                'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
                'jurisdiction' => Ticket::JURISDICTION_SDS,
            ]);

        $response->assertRedirect(route('admin.tickets.review.index'));
        $this->assertDatabaseHas('ticket_threads', [
            'ticket_id' => $ticket->id,
            'is_active' => true,
        ]);
    }

    public function test_informational_classification_does_not_create_thread_or_deadline(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SDS_ADMIN,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $student = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $studentProfile = Student::create([
            'user_id' => $student->id,
            'student_id' => 'S1003',
            'department' => 'IT',
            'course' => 'BSIT',
            'year_level' => '2nd Year',
            'block' => 'A',
        ]);

        $category = ComplaintCategory::create([
            'name' => 'Information Request',
            'resolution_deadline_days' => 4,
            'is_active' => true,
        ]);

        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $studentProfile->id,
            'category_id' => $category->id,
            'subject_title' => 'Informational',
            'description' => 'Informational description',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_PENDING,
        ]);

        $ticket = Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => Ticket::STATUS_PENDING,
            'assigned_to' => null,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.tickets.classify', $ticket), [
                'classification' => Ticket::CLASSIFICATION_INFORMATIONAL,
                'jurisdiction' => Ticket::JURISDICTION_SDS,
            ]);

        $response->assertRedirect(route('admin.tickets.review.index'));
        $this->assertDatabaseMissing('ticket_threads', [
            'ticket_id' => $ticket->id,
        ]);
        $ticket->refresh();
        $this->assertNull($ticket->deadline);
    }
}
