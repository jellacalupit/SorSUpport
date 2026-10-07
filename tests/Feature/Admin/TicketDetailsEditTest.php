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

class TicketDetailsEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_correct_the_category_and_suggested_recipient_of_a_pending_ticket(): void
    {
        [$admin, $ticket, $otherCategory, $recipient] = $this->createPendingTicket();

        $this->actingAs($admin)
            ->patch(route('admin.tickets.update-details', $ticket), [
                'category_id' => $otherCategory->id,
                'suggested_recipient_id' => $recipient->id,
            ])
            ->assertRedirect(route('admin.tickets.review.index'))
            ->assertSessionHasNoErrors();

        $complaint = $ticket->complaint->refresh();

        $this->assertSame($otherCategory->id, $complaint->category_id);
        $this->assertSame($recipient->id, $complaint->suggested_recipient_id);
        $this->assertDatabaseHas('audit_logs', [
            'ticket_id' => $ticket->id,
            'action' => 'ticket_details_updated',
            'performed_by' => $admin->id,
        ]);

        // The corrected suggestion can be assigned even though the category does not list it.
        $this->actingAs($admin)
            ->post(route('admin.tickets.assign', $ticket), [
                'assignment_mode' => 'recipient',
                'recipient_id' => $recipient->id,
            ])
            ->assertRedirect(route('admin.tickets.review.index'));

        $this->assertSame($recipient->user_id, $ticket->refresh()->assigned_to);
    }

    public function test_details_are_locked_once_the_ticket_is_classified(): void
    {
        [$admin, $ticket, $otherCategory] = $this->createPendingTicket();

        $ticket->update([
            'status' => Ticket::STATUS_IN_PROGRESS,
            'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.tickets.update-details', $ticket), ['category_id' => $otherCategory->id])
            ->assertNotFound();

        $this->assertNotSame($otherCategory->id, $ticket->complaint->refresh()->category_id);
    }

    /**
     * @return array{0: User, 1: Ticket, 2: ComplaintCategory, 3: Recipient}
     */
    protected function createPendingTicket(): array
    {
        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN]);

        $student = Student::create([
            'user_id' => User::factory()->create(['role' => User::ROLE_STUDENT])->id,
            'student_id' => 'S3301',
            'college' => 'IT',
            'program' => 'BSIT',
            'year_level' => '2nd Year',
            'block' => 'A',
        ]);

        $recipient = Recipient::create([
            'user_id' => User::factory()->create(['role' => User::ROLE_RECIPIENT])->id,
            'staff_id' => 'R3301',
            'unit' => 'Registrar',
            'designation' => 'Registrar',
        ]);

        $category = ComplaintCategory::create(['name' => 'Facilities', 'is_active' => true]);
        $otherCategory = ComplaintCategory::create(['name' => 'Records', 'is_active' => true]);

        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $student->id,
            'category_id' => $category->id,
            'subject_title' => 'Wrong category',
            'description' => 'This is really about my records.',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_SUBMITTED,
        ]);

        $ticket = Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => Ticket::STATUS_SUBMITTED,
            'current_handler_id' => $admin->id,
        ]);

        return [$admin, $ticket, $otherCategory, $recipient];
    }
}
