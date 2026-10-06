<?php

namespace Tests\Feature\Admin;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\EmailNotification;
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
        /** @var User $admin */
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
            'college' => 'IT',
            'program' => 'BSIT',
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
            'status' => Complaint::STATUS_SUBMITTED,
        ]);

        $ticket = Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => Ticket::STATUS_SUBMITTED,
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

        $this->assertDatabaseHas('email_notifications', [
            'ticket_id' => $ticket->id,
            'recipient_email' => $student->email,
            'type' => EmailNotification::TYPE_INVALID_CLOSURE,
            'status' => EmailNotification::STATUS_PENDING,
        ]);

        $notification = EmailNotification::query()
            ->where('ticket_id', $ticket->id)
            ->where('recipient_email', $student->email)
            ->where('type', EmailNotification::TYPE_INVALID_CLOSURE)
            ->firstOrFail();

        $this->assertSame(EmailNotification::STATUS_PENDING, $notification->status);
        $this->assertSame($student->email, $notification->recipient_email);
        $this->assertSame($ticket->id, $notification->ticket_id);
        $this->assertSame($complaint->id, $notification->ticket->complaint_id);
    }

    public function test_pending_tickets_can_be_filtered_by_search_terms(): void
    {
        /** @var User $admin */
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
            'college' => 'IT',
            'program' => 'BSIT',
            'year_level' => '2nd Year',
            'block' => 'A',
        ]);

        $matchingCategory = ComplaintCategory::create([
            'name' => 'Academic Concern',
            'resolution_deadline_days' => 3,
            'is_active' => true,
        ]);

        $otherCategory = ComplaintCategory::create([
            'name' => 'Facilities Concern',
            'resolution_deadline_days' => 7,
            'is_active' => true,
        ]);

        $matchingComplaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $studentProfile->id,
            'category_id' => $matchingCategory->id,
            'subject_title' => 'Searchable Academic Concern',
            'description' => 'This one should match the search.',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_SUBMITTED,
        ]);

        $otherComplaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $studentProfile->id,
            'category_id' => $otherCategory->id,
            'subject_title' => 'Unrelated Facilities Request',
            'description' => 'This one should not match the search.',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_SUBMITTED,
        ]);

        Ticket::create([
            'complaint_id' => $matchingComplaint->id,
            'status' => Ticket::STATUS_SUBMITTED,
            'assigned_to' => null,
        ]);

        Ticket::create([
            'complaint_id' => $otherComplaint->id,
            'status' => Ticket::STATUS_SUBMITTED,
            'assigned_to' => null,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.tickets.review.index', ['search' => 'Academic']));

        $response->assertOk();
        $response->assertSee('Searchable Academic Concern');
        $response->assertDontSee('Unrelated Facilities Request');
    }

    public function test_admin_my_tickets_supports_search_category_status_and_deadline_sort(): void
    {
        /** @var User $admin */
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
            'student_id' => 'S1009',
            'college' => 'IT',
            'program' => 'BSIT',
            'year_level' => '2nd Year',
            'block' => 'A',
        ]);

        $matchingCategory = ComplaintCategory::create([
            'name' => 'Academic Concern',
            'resolution_deadline_days' => 3,
            'is_active' => true,
        ]);

        $otherCategory = ComplaintCategory::create([
            'name' => 'Facilities Concern',
            'resolution_deadline_days' => 7,
            'is_active' => true,
        ]);

        $matchingComplaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $studentProfile->id,
            'category_id' => $matchingCategory->id,
            'subject_title' => 'Priority Academic Request',
            'description' => 'This complaint should match all filters.',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_SUBMITTED,
        ]);

        $otherComplaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $studentProfile->id,
            'category_id' => $otherCategory->id,
            'subject_title' => 'Other Facilities Request',
            'description' => 'This should not appear.',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_SUBMITTED,
        ]);

        $matchingTicket = Ticket::create([
            'complaint_id' => $matchingComplaint->id,
            'status' => Ticket::STATUS_IN_PROGRESS,
            'current_handler_id' => $admin->id,
            'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
            'deadline' => now()->addDays(2),
            'assigned_to' => $admin->id,
        ]);

        Ticket::create([
            'complaint_id' => $otherComplaint->id,
            'status' => Ticket::STATUS_CLOSED,
            'current_handler_id' => $admin->id,
            'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
            'deadline' => now()->addDays(10),
            'assigned_to' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.tickets.my', [
                'search' => 'Academic',
                'category_filter' => $matchingCategory->id,
                'status_filter' => 'in_progress',
                'sort' => 'deadline_urgency',
            ]));

        $response->assertOk();
        $response->assertSee('Priority Academic Request');
        $response->assertDontSee('Other Facilities Request');

        $this->assertDatabaseHas('tickets', [
            'id' => $matchingTicket->id,
            'current_handler_id' => $admin->id,
            'status' => Ticket::STATUS_IN_PROGRESS,
        ]);
    }

    public function test_admin_can_acknowledge_a_classified_needs_resolution_ticket(): void
    {
        /** @var User $admin */
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
            'college' => 'IT',
            'program' => 'BSIT',
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
            'status' => Complaint::STATUS_SUBMITTED,
        ]);

        $ticket = Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => Ticket::STATUS_SUBMITTED,
            'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
            'jurisdiction' => Ticket::JURISDICTION_SDS,
            'assigned_to' => null,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.tickets.acknowledge', $ticket), [
                'resolution_time' => 5,
            ]);

        $response->assertRedirect(route('admin.tickets.review.index'));
        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_IN_PROGRESS, $ticket->status);
        $this->assertSame(Ticket::CLASSIFICATION_NEEDS_RESOLUTION, $ticket->classification);
        $this->assertSame($admin->id, $ticket->current_handler_id);
    }

    public function test_needs_resolution_classification_creates_thread(): void
    {
        /** @var User $admin */
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
            'college' => 'IT',
            'program' => 'BSIT',
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
            'status' => Complaint::STATUS_SUBMITTED,
        ]);

        $ticket = Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => Ticket::STATUS_SUBMITTED,
            'assigned_to' => null,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.tickets.classify', $ticket), [
                'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
                'jurisdiction' => Ticket::JURISDICTION_SDS,
            ]);

        $response->assertRedirect(route('admin.tickets.review.index'));

        // The conversation opens once someone takes the ticket.
        $this->actingAs($admin)->post(route('admin.tickets.acknowledge', $ticket))->assertRedirect();

        $this->assertSame(Ticket::STATUS_IN_PROGRESS, $ticket->fresh()->status);
        $this->assertDatabaseHas('ticket_threads', [
            'ticket_id' => $ticket->id,
            'is_active' => true,
        ]);
    }

    public function test_ticket_review_surfaces_only_the_category_configured_recipients(): void
    {
        /** @var User $admin */
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
            'college' => 'IT',
            'program' => 'BSIT',
            'year_level' => '2nd Year',
            'block' => 'A',
        ]);

        $recipientUser = User::factory()->create([
            'role' => User::ROLE_RECIPIENT,
            'name' => 'Configured Recipient',
            'email' => 'configured.recipient@example.com',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $recipientUserTwo = User::factory()->create([
            'role' => User::ROLE_RECIPIENT,
            'name' => 'Configured Recipient Two',
            'email' => 'configured.recipient.two@example.com',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $otherRecipientUser = User::factory()->create([
            'role' => User::ROLE_RECIPIENT,
            'name' => 'Other Recipient',
            'email' => 'other.recipient@example.com',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $recipient = Recipient::create([
            'user_id' => $recipientUser->id,
            'staff_id' => 'R-1001',
            'unit' => 'Academic Affairs',
            'designation' => 'Academic Coordinator',
        ]);

        $recipientTwo = Recipient::create([
            'user_id' => $recipientUserTwo->id,
            'staff_id' => 'R-1002',
            'unit' => 'Academic Affairs',
            'designation' => 'Academic Coordinator',
        ]);

        $otherRecipient = Recipient::create([
            'user_id' => $otherRecipientUser->id,
            'staff_id' => 'R-1003',
            'unit' => 'Academic Affairs',
            'designation' => 'Other Office',
        ]);

        $category = ComplaintCategory::create([
            'name' => 'Information Request',
            'resolution_deadline_days' => 4,
            'recipient_id' => $recipient->id,
            'is_active' => true,
        ]);

        $category->suggestedRecipients()->sync([$recipient->id, $recipientTwo->id]);

        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $studentProfile->id,
            'category_id' => $category->id,
            'subject_title' => 'Informational',
            'description' => 'Informational description',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_SUBMITTED,
        ]);

        $ticket = Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => Ticket::STATUS_SUBMITTED,
            'assigned_to' => null,
        ]);

        // The review steps live on the ticket page.
        $response = $this->actingAs($admin)
            ->get(route('admin.complaints.show', $complaint));

        $response->assertOk();
        $response->assertSee($recipientUser->name);
        $response->assertSee($recipientUserTwo->name);
        $response->assertSee('R-1001 ·');
        $response->assertSee('R-1002 ·');
        $response->assertDontSee('R-1003 ·');
    }
}
