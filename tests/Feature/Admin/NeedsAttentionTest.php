<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Recipient;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NeedsAttentionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $student;

    protected User $handler;

    protected ComplaintCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-09 12:00:00'));
        $this->admin = $this->user(User::ROLE_SDS_ADMIN, 'Ada Admin');
        $this->student = $this->user(User::ROLE_STUDENT, 'Zebediah Quixote');
        $this->handler = $this->user(User::ROLE_RECIPIENT, 'Rey Registrar');

        Student::create([
            'user_id' => $this->student->id,
            'student_id' => '202477123',
            'college' => 'CICT',
            'program' => 'BSIT',
            'year_level' => '1',
            'block' => '1',
        ]);
        Recipient::create([
            'user_id' => $this->handler->id,
            'staff_id' => 'STAFF-1',
            'unit' => 'Registrar',
            'designation' => 'Staff',
        ]);
        $this->category = ComplaintCategory::create(['name' => 'Student Services', 'is_active' => true]);
    }

    protected function user(string $role, string $name, bool $active = true): User
    {
        [$first, $last] = explode(' ', $name, 2);

        return User::factory()->create([
            'role' => $role,
            'name' => $name,
            'first_name' => $first,
            'last_name' => $last,
            'is_active' => $active,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
    }

    protected function ticket(string $status, array $attributes = [], ?ComplaintCategory $category = null): Ticket
    {
        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $this->student->student->id,
            'category_id' => ($category ?? $this->category)->id,
            'subject_title' => 'Private student services concern',
            'description' => 'A concern that needs review.',
            'is_anonymous' => false,
            'status' => $status,
        ]);

        $underReview = in_array($status, [Ticket::STATUS_SUBMITTED, Ticket::STATUS_NEEDS_CLARIFICATION], true);

        return Ticket::create($attributes + [
            'complaint_id' => $complaint->id,
            'status' => $status,
            'classification' => $underReview ? null : Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
            'jurisdiction' => $underReview ? null : Ticket::JURISDICTION_RECIPIENT,
            'assigned_to' => $underReview ? null : $this->handler->id,
            'current_handler_id' => $underReview ? $this->admin->id : $this->handler->id,
            'is_codi_referral' => false,
        ]);
    }

    protected function recordActionAt(Ticket $ticket, string $action, Carbon $createdAt): void
    {
        $log = AuditLog::create([
            'ticket_id' => $ticket->id,
            'performed_by' => $this->admin->id,
            'action' => $action,
            'details' => 'Recorded test activity.',
        ]);
        $log->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
    }

    public function test_pending_student_requests_and_inactive_handlers_are_listed_but_closed_tickets_are_not(): void
    {
        $update = $this->ticket(Ticket::STATUS_SUBMITTED);
        $update->complaint->forceFill(['created_at' => now()->subDays(5)])->save();
        app(TicketWorkflow::class)->requestUpdate($update, $this->student, 'Please share an update.');

        $escalation = $this->ticket(Ticket::STATUS_ASSIGNED);
        $escalation->complaint->forceFill(['created_at' => now()->subDays(2)])->save();
        app(TicketWorkflow::class)->requestEscalation($escalation, $this->student, 'Please consider a higher review level.');

        $inactiveHandler = $this->user(User::ROLE_RECIPIENT, 'Ira Inactive', false);
        Recipient::create([
            'user_id' => $inactiveHandler->id,
            'staff_id' => 'STAFF-2',
            'unit' => 'CICT',
            'designation' => 'Staff',
        ]);
        $inactive = $this->ticket(Ticket::STATUS_IN_PROGRESS, [
            'current_handler_id' => $inactiveHandler->id,
            'assigned_to' => $inactiveHandler->id,
        ]);

        $resolved = $this->ticket(Ticket::STATUS_RESOLVED);
        $this->recordActionAt($resolved, 'escalation_requested', now());
        $closed = $this->ticket(Ticket::STATUS_CLOSED);
        $this->recordActionAt($closed, 'update_requested', now());

        $response = $this->actingAs($this->admin)->get(route('admin.tickets.needs-attention'))->assertOk();
        $response->assertSee('Student requested an update')
            ->assertSee('Student requested escalation')
            ->assertSee('Handler account inactive')
            ->assertDontSee($resolved->complaint->reference_number)
            ->assertDontSee($closed->complaint->reference_number);

        $response->assertViewHas('tickets', function ($tickets) use ($update, $escalation, $inactive): bool {
            $rows = collect($tickets->items());
            $listed = $rows->pluck('ticket');

            return $listed->contains('id', $update->id)
                && $listed->contains('id', $escalation->id)
                && $listed->contains('id', $inactive->id)
                && $rows->first()['ticket']->is($update)
                && $rows->get(1)['ticket']->is($escalation);
        });
        $response->assertSee('working day');
    }

    public function test_no_action_reasons_use_existing_thresholds_even_when_reminder_emails_are_disabled(): void
    {
        SystemSetting::put('reminders.enabled', 0);
        SystemSetting::put('reminders.unreviewed_days', 3);
        SystemSetting::put('reminders.sensitive_unreviewed_days', 4);
        SystemSetting::put('reminders.unacknowledged_days', 2);
        SystemSetting::put('reminders.idle_days', 4);
        SystemSetting::put('reminders.referral_days', 5);
        SystemSetting::put('reminders.generic_referral_enabled', 0);

        $submitted = $this->ticket(Ticket::STATUS_SUBMITTED);
        $this->recordActionAt($submitted, 'ticket_created', Carbon::parse('2026-11-04 12:00:00'));

        $fresh = $this->ticket(Ticket::STATUS_SUBMITTED);
        $this->recordActionAt($fresh, 'ticket_created', now());

        $sensitiveCategory = ComplaintCategory::create([
            'name' => 'Sensitive Concern',
            'is_active' => true,
            'is_sensitive' => true,
        ]);
        $sensitiveSubmitted = $this->ticket(Ticket::STATUS_SUBMITTED, [], $sensitiveCategory);
        $this->recordActionAt($sensitiveSubmitted, 'ticket_created', Carbon::parse('2026-11-04 12:00:00'));

        $assigned = $this->ticket(Ticket::STATUS_ASSIGNED);
        $this->recordActionAt($assigned, 'ticket_assigned', Carbon::parse('2026-11-05 12:00:00'));

        $acknowledged = $this->ticket(Ticket::STATUS_ASSIGNED, ['acknowledged_at' => now()]);
        $this->recordActionAt($acknowledged, 'ticket_assigned', Carbon::parse('2026-11-02 12:00:00'));

        $inProgress = $this->ticket(Ticket::STATUS_IN_PROGRESS);
        $this->recordActionAt($inProgress, 'ticket_acknowledged', Carbon::parse('2026-11-03 12:00:00'));

        $escalated = $this->ticket(Ticket::STATUS_ESCALATED);
        $this->recordActionAt($escalated, 'ticket_escalated', Carbon::parse('2026-11-03 12:00:00'));

        $referredCodi = $this->ticket(Ticket::STATUS_REFERRED, ['is_codi_referral' => true]);
        $this->recordActionAt($referredCodi, 'ticket_referred', Carbon::parse('2026-11-02 12:00:00'));

        $referredGeneral = $this->ticket(Ticket::STATUS_REFERRED);
        $this->recordActionAt($referredGeneral, 'ticket_referred', Carbon::parse('2026-10-01 12:00:00'));

        $clarification = $this->ticket(Ticket::STATUS_NEEDS_CLARIFICATION);
        $this->recordActionAt($clarification, 'clarification_requested', Carbon::parse('2026-10-01 12:00:00'));

        $response = $this->actingAs($this->admin)->get(route('admin.tickets.needs-attention'))->assertOk();
        $response->assertSee($submitted->complaint->reference_number)
            ->assertSee('No action for 3 working days')
            ->assertSee($assigned->complaint->reference_number)
            ->assertSee($inProgress->complaint->reference_number)
            ->assertSee($escalated->complaint->reference_number)
            ->assertSee($referredCodi->complaint->reference_number)
            ->assertDontSee($fresh->complaint->reference_number)
            ->assertDontSee($sensitiveSubmitted->complaint->reference_number)
            ->assertDontSee($acknowledged->complaint->reference_number)
            ->assertDontSee($referredGeneral->complaint->reference_number)
            ->assertDontSee($clarification->complaint->reference_number);

        app(TicketWorkflow::class)->requestUpdate($submitted->fresh(), $this->student, 'Any update is appreciated.');
        $this->actingAs($this->admin)
            ->get(route('admin.tickets.needs-attention'))
            ->assertOk()
            ->assertSee($submitted->complaint->reference_number)
            ->assertSee('No action for 3 working days')
            ->assertSee('Student requested an update');
    }

    public function test_list_privacy_access_filters_and_viewing_are_read_only(): void
    {
        $this->handler->update(['is_active' => false]);
        $ticket = $this->ticket(Ticket::STATUS_IN_PROGRESS);
        $ticket->complaint->update([
            'is_anonymous' => true,
            'subject_title' => 'Identity revealing subject line',
        ]);
        $ticket->complaint->category->update(['is_sensitive' => true]);
        $before = $ticket->fresh()->only(['status', 'assigned_to', 'current_handler_id']);
        $updatedAt = $ticket->fresh()->updated_at->toISOString();
        $auditCount = $ticket->auditLogs()->count();

        $this->actingAs($this->admin)
            ->get(route('admin.tickets.needs-attention'))
            ->assertOk()
            ->assertSee('Anonymous')
            ->assertSee('Identity revealing subject line')
            ->assertDontSee($this->student->name);

        $otherRecipient = $this->user(User::ROLE_RECIPIENT, 'Dora Dean');
        $this->actingAs($otherRecipient)
            ->get(route('admin.tickets.needs-attention'))
            ->assertForbidden();
        $this->actingAs($this->student)
            ->get(route('admin.tickets.needs-attention'))
            ->assertForbidden();

        $this->assertSame($before, $ticket->fresh()->only(['status', 'assigned_to', 'current_handler_id']));
        $this->assertSame($updatedAt, $ticket->fresh()->updated_at->toISOString());
        $this->assertSame($auditCount, $ticket->auditLogs()->count());
    }

    public function test_list_filters_and_pagination_work_and_dashboard_keeps_the_list_off_dashboard(): void
    {
        $inactiveHandler = $this->user(User::ROLE_RECIPIENT, 'Ira Inactive', false);
        Recipient::create([
            'user_id' => $inactiveHandler->id,
            'staff_id' => 'STAFF-3',
            'unit' => 'CICT',
            'designation' => 'Staff',
        ]);

        $tickets = collect(range(1, 17))->map(function (int $number) use ($inactiveHandler): Ticket {
            $category = $number === 1
                ? ComplaintCategory::create(['name' => 'Special Category', 'is_active' => true])
                : $this->category;

            return $this->ticket(Ticket::STATUS_IN_PROGRESS, [
                'current_handler_id' => $inactiveHandler->id,
                'assigned_to' => $inactiveHandler->id,
            ], $category);
        });

        $firstPage = $this->actingAs($this->admin)
            ->get(route('admin.tickets.needs-attention'))
            ->assertOk();
        $firstPageContent = $firstPage->getContent();
        $this->assertStringContainsString('17 tickets', $firstPageContent);
        $this->assertStringContainsString('<table', $firstPageContent);
        $this->assertStringContainsString('data-follow-up-ticket-link', $firstPageContent);
        $this->assertStringContainsString('data-follow-up-ticket-drawer', $firstPageContent);
        $this->assertStringContainsString('Next', $firstPageContent);
        $this->assertCount(15, $firstPage->viewData('tickets')->items());

        $secondPage = $this->get(route('admin.tickets.needs-attention', ['page' => 2]))
            ->assertOk();
        $this->assertCount(2, $secondPage->viewData('tickets')->items());

        $filteredPage = $this->get(route('admin.tickets.needs-attention', [
            'category_filter' => $tickets->first()->complaint->category_id,
            'status_filter' => Ticket::STATUS_IN_PROGRESS,
            'search' => $tickets->first()->complaint->reference_number,
        ]))->assertOk();
        $filteredPageContent = $filteredPage->getContent();
        $this->assertStringContainsString($tickets->first()->complaint->reference_number, $filteredPageContent);
        $this->assertStringNotContainsString($tickets->last()->complaint->reference_number, $filteredPageContent);

        $dashboard = $this->get(route('admin.dashboard'))->assertOk();
        $this->assertStringNotContainsString('data-needs-attention-ticket', $dashboard->getContent());
    }
}
