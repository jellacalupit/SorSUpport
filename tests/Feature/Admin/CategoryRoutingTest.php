<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\EmailNotification;
use App\Models\Recipient;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketRoutingService;
use App\Services\TicketWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CategoryRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Student $student;

    protected ComplaintCategory $category;

    protected Recipient $recipient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN, 'is_active' => true, 'must_change_password' => false]);
        $this->student = Student::create([
            'user_id' => User::factory()->create(['role' => User::ROLE_STUDENT])->id,
            'student_id' => '20240001', 'college' => 'CICT', 'program' => 'BSIT', 'year_level' => '1', 'block' => '1',
        ]);
        $this->recipient = $this->recipient();
        $this->category = ComplaintCategory::create(['name' => 'Academic Concern', 'is_active' => true, 'recipient_id' => $this->recipient->id]);
    }

    protected function recipient(): Recipient
    {
        return Recipient::create([
            'user_id' => User::factory()->create(['role' => User::ROLE_RECIPIENT, 'is_active' => true])->id,
            'staff_id' => 'STAFF-'.Recipient::count(), 'unit' => 'CICT', 'designation' => 'Staff',
        ]);
    }

    protected function ticket(string $status = Ticket::STATUS_SUBMITTED, array $attributes = []): Ticket
    {
        $complaint = Complaint::create([
            'student_id' => $this->student->id, 'category_id' => $this->category->id,
            'reference_number' => Complaint::generateReferenceNumber(),
            'subject_title' => 'Concern', 'description' => 'Please investigate this concern.', 'status' => $status,
        ]);

        return Ticket::create($attributes + [
            'complaint_id' => $complaint->id, 'status' => $status,
            'classification' => $status === Ticket::STATUS_SUBMITTED ? null : Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
            'current_handler_id' => $this->admin->id,
        ]);
    }

    public function test_unconfigured_recipient_is_rejected_before_and_after_classification_and_during_reassignment(): void
    {
        $outsider = $this->recipient();

        foreach ([Ticket::STATUS_SUBMITTED, Ticket::STATUS_ASSIGNED, Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_ESCALATED] as $status) {
            foreach ($status === Ticket::STATUS_SUBMITTED ? [null, Ticket::CLASSIFICATION_NEEDS_RESOLUTION] : [Ticket::CLASSIFICATION_NEEDS_RESOLUTION] as $classification) {
                $ticket = $this->ticket($status, ['classification' => $classification, 'assigned_to' => $this->recipient->user_id]);
                $before = $ticket->fresh()->getAttributes();
                $auditCount = AuditLog::count();
                $emailCount = EmailNotification::count();

                $this->actingAs($this->admin)->postJson(route('admin.tickets.assign', $ticket), [
                    'assignment_mode' => 'recipient', 'recipient_id' => $outsider->id,
                ])->assertUnprocessable()->assertJsonValidationErrors('recipient_id');

                $this->assertSame($before, $ticket->fresh()->getAttributes());
                $this->assertSame($auditCount, AuditLog::count());
                $this->assertSame($emailCount, EmailNotification::count());
            }
        }
    }

    public function test_all_category_configuration_sources_allow_assignment(): void
    {
        $suggested = $this->recipient();
        $hierarchy = $this->recipient();
        $this->category->suggestedRecipients()->attach($suggested);
        $this->category->escalationHierarchies()->create(['path_number' => 1, 'level' => 1, 'recipient_id' => $hierarchy->id]);

        foreach ([$this->recipient, $suggested, $hierarchy] as $recipient) {
            $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);
            $this->actingAs($this->admin)->post(route('admin.tickets.assign', $ticket), [
                'assignment_mode' => 'recipient', 'recipient_id' => $recipient->id,
            ])->assertRedirect()->assertSessionHasNoErrors();

            $this->assertSame($recipient->user_id, $ticket->fresh()->assigned_to);
            $this->assertSame(Ticket::STATUS_ASSIGNED, $ticket->fresh()->status);
            $this->assertDatabaseHas('email_notifications', ['ticket_id' => $ticket->id, 'recipient_email' => $recipient->user->email, 'type' => EmailNotification::TYPE_RECIPIENT_ASSIGNMENT]);
        }
    }

    public function test_student_suggestion_does_not_grant_category_eligibility(): void
    {
        $outsider = $this->recipient();
        $ticket = $this->ticket();
        $ticket->complaint->update(['suggested_recipient_id' => $outsider->id]);

        $this->actingAs($this->admin)->postJson(route('admin.tickets.assign', $ticket), [
            'assignment_mode' => 'recipient', 'recipient_id' => $outsider->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('recipient_id');

        $this->assertSame([$this->recipient->id], app(TicketRoutingService::class)->availableRecipients($ticket)->modelKeys());
    }

    public function test_inactive_and_named_recipients_are_excluded_and_rejected(): void
    {
        foreach (['inactive', 'named'] as $reason) {
            $ticket = $this->ticket();
            if ($reason === 'inactive') {
                $this->recipient->user->update(['is_active' => false]);
            } else {
                $this->recipient->user->update(['is_active' => true]);
                $ticket->complaint->update(['personnel_involved' => $this->recipient->user->display_name]);
            }

            $this->assertCount(0, app(TicketRoutingService::class)->availableRecipients($ticket));
            $this->actingAs($this->admin)->postJson(route('admin.tickets.assign', $ticket), [
                'assignment_mode' => 'recipient', 'recipient_id' => $this->recipient->id,
            ])->assertUnprocessable()->assertJsonValidationErrors('recipient_id');
            $this->assertSame(Ticket::STATUS_SUBMITTED, $ticket->fresh()->status);
        }
    }

    public function test_workflow_service_also_rejects_an_inactive_recipient_with_a_stale_user_relation(): void
    {
        $ticket = $this->ticket();
        $this->recipient->load('user');
        User::whereKey($this->recipient->user_id)->update(['is_active' => false]);

        $this->expectException(ValidationException::class);
        app(TicketWorkflow::class)->assign($ticket, $this->admin, $this->recipient);
    }

    public function test_informational_forwarding_requires_an_active_configured_recipient(): void
    {
        foreach (['admin.tickets.forward', 'admin.tickets.forward-informational-close'] as $route) {
            foreach (['unconfigured', 'inactive'] as $reason) {
                $ticket = $this->ticket();
                if ($route === 'admin.tickets.forward') {
                    $ticket->update(['classification' => Ticket::CLASSIFICATION_INFORMATIONAL, 'jurisdiction' => Ticket::JURISDICTION_RECIPIENT]);
                }
                $recipient = $reason === 'unconfigured' ? $this->recipient() : $this->recipient;
                if ($reason === 'inactive') {
                    $recipient->user->update(['is_active' => false]);
                }

                $this->actingAs($this->admin)->postJson(route($route, $ticket), ['recipient_id' => $recipient->id])
                    ->assertUnprocessable()->assertJsonValidationErrors('recipient_id');
                $this->assertNull($ticket->fresh()->forwarded_to);
                $this->assertNull($ticket->fresh()->closed_at);
            }
        }
    }

    public function test_sds_direct_assignment_does_not_require_a_configured_recipient(): void
    {
        $this->category->update(['recipient_id' => null]);
        $ticket = $this->ticket();

        $this->actingAs($this->admin)->post(route('admin.tickets.assign', $ticket), ['assignment_mode' => 'direct'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($this->admin->id, $ticket->fresh()->current_handler_id);
        $this->assertNull($ticket->fresh()->assigned_to);
    }

    public function test_valid_informational_forwards_do_not_assign_a_resolution_handler(): void
    {
        foreach (['admin.tickets.forward', 'admin.tickets.forward-informational-close'] as $route) {
            $ticket = $this->ticket();
            if ($route === 'admin.tickets.forward') {
                $ticket->update(['classification' => Ticket::CLASSIFICATION_INFORMATIONAL, 'jurisdiction' => Ticket::JURISDICTION_RECIPIENT]);
            }

            $this->actingAs($this->admin)->post(route($route, $ticket), ['recipient_id' => $this->recipient->id])
                ->assertRedirect()->assertSessionHasNoErrors();
            $ticket->refresh();
            $this->assertSame($this->recipient->id, $ticket->forwarded_to);
            $this->assertNull($ticket->assigned_to);
            $this->assertSame($route === 'admin.tickets.forward' ? Ticket::STATUS_SUBMITTED : Ticket::STATUS_CLOSED, $ticket->status);
            $this->assertDatabaseHas('email_notifications', [
                'ticket_id' => $ticket->id, 'recipient_email' => $this->recipient->user->email,
                'type' => EmailNotification::TYPE_INFORMATIONAL_FORWARD,
            ]);
        }
    }

    public function test_workflow_service_rejects_an_unconfigured_recipient_even_if_suggested_by_the_student(): void
    {
        $outsider = $this->recipient();
        $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);
        $ticket->complaint->update(['suggested_recipient_id' => $outsider->id]);

        $this->expectException(ValidationException::class);
        app(TicketWorkflow::class)->assign($ticket, $this->admin, $outsider);
    }

    public function test_removing_a_category_recipient_invalidates_a_previously_available_choice(): void
    {
        $replacement = $this->recipient();
        $this->category->suggestedRecipients()->attach($replacement);
        $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);
        $this->assertContains($replacement->id, app(TicketRoutingService::class)->availableRecipients($ticket)->modelKeys());

        $this->category->suggestedRecipients()->detach($replacement);

        $this->actingAs($this->admin)->postJson(route('admin.tickets.assign', $ticket), [
            'assignment_mode' => 'recipient', 'recipient_id' => $replacement->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('recipient_id');
        $this->assertSame(Ticket::STATUS_IN_PROGRESS, $ticket->fresh()->status);
    }
}
