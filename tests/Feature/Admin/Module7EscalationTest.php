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
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class Module7EscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_escalates_an_active_ticket_to_the_recipient_they_choose_and_the_date_is_recorded(): void
    {
        [$admin, $studentUser, $ticket, $recipients] = $this->createEscalationScenario();
        [, $supervisor, $director] = $recipients;

        // The admin may skip the next configured level and pick any other recipient.
        $this->actingAs($admin)
            ->from(route('admin.complaints.show', $ticket->complaint))
            ->post(route('admin.tickets.escalate', $ticket), ['recipient_id' => $director->id, 'escalation_note' => 'Not resolved at this level.'])
            ->assertRedirect(route('admin.complaints.show', $ticket->complaint))
            ->assertSessionHasNoErrors();

        $ticket->refresh();

        $this->assertSame(Ticket::STATUS_ESCALATED, $ticket->status);
        $this->assertSame($director->user_id, $ticket->assigned_to);
        $this->assertSame($director->user_id, $ticket->current_handler_id);
        $this->assertNotNull($ticket->escalated_at);
        $this->assertTrue($ticket->escalated_at->isToday());
        $this->assertNotSame($supervisor->user_id, $ticket->assigned_to);

        $this->assertDatabaseHas('audit_logs', [
            'ticket_id' => $ticket->id,
            'action' => 'ticket_escalated',
            'performed_by' => $admin->id,
        ]);

        foreach ([$admin->email, $director->user->email, $studentUser->email] as $email) {
            $this->assertDatabaseHas('email_notifications', [
                'ticket_id' => $ticket->id,
                'recipient_email' => $email,
                'type' => EmailNotification::TYPE_ESCALATED,
            ]);
        }
    }

    public function test_escalation_requires_a_recipient_other_than_the_current_handler(): void
    {
        [$admin, , $ticket, $recipients] = $this->createEscalationScenario();
        [$officer] = $recipients;

        $this->actingAs($admin)
            ->post(route('admin.tickets.escalate', $ticket))
            ->assertSessionHasErrors('recipient_id');

        $this->actingAs($admin)
            ->post(route('admin.tickets.escalate', $ticket), ['recipient_id' => $officer->id, 'escalation_note' => 'Not resolved at this level.'])
            ->assertSessionHasErrors('recipient_id');

        $ticket->refresh();

        $this->assertSame(Ticket::STATUS_IN_PROGRESS, $ticket->status);
        $this->assertSame($officer->user_id, $ticket->assigned_to);
        $this->assertNull($ticket->escalated_at);
    }

    public function test_escalation_form_suggests_the_next_configured_level(): void
    {
        [$admin, , $ticket, $recipients] = $this->createEscalationScenario();
        [, $supervisor] = $recipients;

        $this->actingAs($admin)
            ->get(route('admin.complaints.show', $ticket->complaint))
            ->assertOk()
            ->assertSee('Escalate ticket')
            ->assertSee('name="recipient_id" value="' . $supervisor->id . '"', false)
            ->assertSee($supervisor->user->table_name)
            ->assertDontSee('Level 2 · ' . $supervisor->user->table_name);
    }

    public function test_only_staff_above_the_current_recipient_can_be_selected(): void
    {
        [$admin, , $ticket, $recipients] = $this->createEscalationScenario();
        [$officer, $supervisor, $director] = $recipients;
        $ticket->update(['assigned_to' => $supervisor->user_id, 'current_handler_id' => $supervisor->user_id]);

        $targets = app(\App\Services\TicketEscalationService::class)->escalationTargets($ticket);

        $this->assertSame([$director->id], $targets->pluck('id')->all());

        $this->actingAs($admin)
            ->post(route('admin.tickets.escalate', $ticket), [
                'recipient_id' => $officer->id,
                'escalation_note' => 'This lower position must not be selectable.',
            ])
            ->assertSessionHasErrors('recipient_id');
    }

    public function test_a_recipient_outside_the_path_cannot_be_escalated_to_an_unrelated_position(): void
    {
        [, , $ticket] = $this->createEscalationScenario();
        $outsideRecipient = Recipient::create([
            'user_id' => User::factory()->create(['role' => User::ROLE_RECIPIENT])->id,
            'staff_id' => 'R7999',
            'unit' => 'Student Affairs',
            'designation' => 'Outside recipient',
        ]);
        $ticket->update(['assigned_to' => $outsideRecipient->user_id, 'current_handler_id' => $outsideRecipient->user_id]);

        $this->assertTrue(app(\App\Services\TicketEscalationService::class)->escalationTargets($ticket)->isEmpty());
    }

    public function test_automatic_escalation_and_deadline_reminder_commands_are_gone(): void
    {
        $commands = array_keys(Artisan::all());

        $this->assertNotContains('tickets:escalate', $commands);
        $this->assertNotContains('notifications:remind', $commands);
        $this->assertContains('notifications:send', $commands);
    }

    /**
     * An in-progress ticket held by the first of three recipients in the category hierarchy.
     *
     * @return array{0: User, 1: User, 2: Ticket, 3: array<int, Recipient>}
     */
    protected function createEscalationScenario(): array
    {
        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN]);
        $studentUser = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'S7001',
            'college' => 'IT',
            'program' => 'BSIT',
            'year_level' => '2nd Year',
            'block' => 'A',
        ]);

        $category = ComplaintCategory::create([
            'name' => 'Academic Concern',
            'is_active' => true,
        ]);

        $recipients = [];

        foreach (['Officer', 'Supervisor', 'Director'] as $index => $designation) {
            $recipient = Recipient::create([
                'user_id' => User::factory()->create(['role' => User::ROLE_RECIPIENT])->id,
                'staff_id' => 'R700' . ($index + 1),
                'unit' => 'Student Affairs',
                'designation' => $designation,
            ]);

            EscalationHierarchy::create([
                'complaint_category_id' => $category->id,
                'level' => $index + 1,
                'recipient_id' => $recipient->id,
            ]);

            $recipients[] = $recipient;
        }

        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $student->id,
            'category_id' => $category->id,
            'subject_title' => 'Needs escalation',
            'description' => 'Taking too long',
            'is_anonymous' => false,
            'status' => Complaint::STATUS_SUBMITTED,
        ]);

        $ticket = Ticket::create([
            'complaint_id' => $complaint->id,
            'assigned_to' => $recipients[0]->user_id,
            'current_handler_id' => $recipients[0]->user_id,
            'status' => Ticket::STATUS_IN_PROGRESS,
            'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
        ]);

        return [$admin, $studentUser, $ticket, $recipients];
    }
}
