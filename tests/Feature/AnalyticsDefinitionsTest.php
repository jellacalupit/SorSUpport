<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Recipient;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AnalyticsDefinitionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;

    protected User $recipientUser;

    protected Recipient $recipient;

    protected ComplaintCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Student::create([
            'user_id' => $this->student->id,
            'student_id' => 'ANALYTICS-1',
            'college' => 'CICT',
            'program' => 'BSIT',
            'year_level' => '1',
            'block' => 'A',
        ]);
        $this->recipientUser = User::factory()->create(['role' => User::ROLE_RECIPIENT]);
        $this->recipient = Recipient::create([
            'user_id' => $this->recipientUser->id,
            'staff_id' => 'ANALYTICS-RECIPIENT',
            'unit' => 'Registrar Office',
            'designation' => 'Officer',
        ]);
        $this->category = ComplaintCategory::create(['name' => 'Student Services', 'is_active' => true]);
    }

    public function test_forwarded_informational_tickets_have_their_own_balanced_status_bucket_and_filter(): void
    {
        $submitted = $this->ticket(Ticket::STATUS_SUBMITTED, [
            'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
        ]);
        $forwarded = $this->ticket(Ticket::STATUS_SUBMITTED, [
            'classification' => Ticket::CLASSIFICATION_INFORMATIONAL,
            'jurisdiction' => Ticket::JURISDICTION_RECIPIENT,
            'forwarded_to' => $this->recipient->id,
            'forwarded_at' => now(),
        ]);

        $analytics = app(AnalyticsService::class);
        $counts = $analytics->getTotalTicketsByStatus();

        $this->assertSame(1, $counts[Ticket::STATUS_SUBMITTED]);
        $this->assertSame(1, $counts[AnalyticsService::FORWARDED_INFORMATION_STATUS]);
        $this->assertSame($analytics->getTotalTickets(), array_sum($counts));
        $statusChart = $analytics->getActiveTicketStatuses();
        $this->assertContains(AnalyticsService::FORWARDED_INFORMATION_LABEL, $statusChart['labels']);
        $this->assertSame($analytics->getTotalTickets(), array_sum($statusChart['data']));
        $this->assertSame(1, $analytics->getTotalTickets(['status' => Ticket::STATUS_SUBMITTED]));
        $this->assertSame(1, $analytics->getTotalTickets(['status' => AnalyticsService::FORWARDED_INFORMATION_STATUS]));
        $this->assertSame(1, $analytics->getTotalTickets(['recipient_id' => $this->recipientUser->id]));
        $this->assertSame(1, $analytics->getTotalTickets(['unit' => 'Registrar Office']));

        $dashboard = $analytics->getDashboardData();
        $this->assertSame(1, $dashboard['statusCounts']['Submitted']);
        $this->assertSame(1, $dashboard['statusCounts'][AnalyticsService::FORWARDED_INFORMATION_LABEL]);

        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN]);
        $this->actingAs($admin)
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertSee(AnalyticsService::FORWARDED_INFORMATION_LABEL)
            ->assertSee('data-value="'.AnalyticsService::FORWARDED_INFORMATION_STATUS.'"', false);

        $this->assertSame(Ticket::STATUS_SUBMITTED, $forwarded->fresh()->status);
        $this->assertSame(Ticket::STATUS_SUBMITTED, $submitted->fresh()->status);
    }

    public function test_resolution_rate_counts_only_recorded_resolutions_and_excludes_all_nonresolution_closures(): void
    {
        $this->ticket(Ticket::STATUS_RESOLVED, ['resolved_at' => now()]);
        $this->ticket(Ticket::STATUS_CLOSED, [
            'closure_type' => Ticket::CLOSURE_RESOLVED,
            'resolved_at' => now(),
            'closed_at' => now(),
        ]);
        $this->ticket(Ticket::STATUS_CLOSED, [
            'closure_type' => Ticket::CLOSURE_RESOLVED_ACCEPTED,
            'resolved_at' => now(),
            'closed_at' => now(),
        ]);
        foreach ([
            Ticket::CLOSURE_WITHDRAWN,
            Ticket::CLOSURE_DUPLICATE,
            Ticket::CLOSURE_OUT_OF_SCOPE,
            Ticket::CLOSURE_INVALID,
            Ticket::CLOSURE_NO_RESPONSE,
        ] as $closureType) {
            $this->ticket(Ticket::STATUS_CLOSED, [
                'closure_type' => $closureType,
                'closed_at' => now(),
            ]);
        }
        $this->ticket(Ticket::STATUS_IN_PROGRESS);

        $rate = app(AnalyticsService::class)->getResolutionRate();

        $this->assertSame(3, $rate['resolved']);
        $this->assertSame(4, $rate['total']);
        $this->assertSame(75.0, $rate['rate']);
        $this->assertSame(5, $rate['excluded_total']);
        $this->assertSame([
            Ticket::CLOSURE_WITHDRAWN => 1,
            Ticket::CLOSURE_DUPLICATE => 1,
            Ticket::CLOSURE_OUT_OF_SCOPE => 1,
            Ticket::CLOSURE_INVALID => 1,
            Ticket::CLOSURE_NO_RESPONSE => 1,
        ], $rate['excluded']);
        $this->assertSame(9, array_sum($rate['chart']['data']));
    }

    public function test_resolution_time_subtracts_waits_reports_fallbacks_and_separates_committee_outcomes(): void
    {
        $direct = $this->timedTicket('2026-01-01', '2026-01-03');
        $clarification = $this->timedTicket('2026-01-01', '2026-01-07', [
            'clarification_requested_at' => '2026-01-02',
        ]);
        $this->audit($clarification, 'clarification_requested', '2026-01-02');
        $this->audit($clarification, 'clarification_provided', '2026-01-04');

        $committee = $this->timedTicket('2026-01-01', '2026-01-07', [
            'referred_at' => '2026-01-02',
            'resolution_type' => Ticket::RESOLUTION_COMMITTEE_DECISION,
        ]);
        $this->audit($committee, 'ticket_referred', '2026-01-02');
        $this->audit($committee, 'referral_outcome_recorded', '2026-01-05');

        $fallback = $this->timedTicket('2026-01-01', '2026-01-07', [
            'clarification_requested_at' => '2026-01-02',
        ]);
        $this->audit($fallback, 'clarification_requested', '2026-01-02');

        $analytics = app(AnalyticsService::class);
        $time = $analytics->getAverageResolutionTimePerCategory();
        $dashboard = $analytics->getDashboardData();

        $this->assertSame(3.8, $time['average_days']);
        $this->assertSame(3.5, $time['median_days']);
        $this->assertSame(1, $time['fallback_count']);
        $this->assertSame(4, $time['ticket_count']);
        $this->assertSame(4.0, $time['categories']['Student Services']['average_days']);
        $this->assertSame(4.0, $time['categories']['Student Services']['median_days']);
        $this->assertSame(1, $time['categories']['Student Services']['fallback_count']);
        $this->assertSame(3.0, $time['committee_outcomes']['average_days']);
        $this->assertSame(3.0, $time['committee_outcomes']['median_days']);
        $this->assertSame(3.8, $dashboard['resolutionTime']['average_days']);
        $this->assertSame(3.5, $dashboard['resolutionTime']['median_days']);
        $this->assertSame(1, $dashboard['resolutionTime']['fallback_count']);
        $this->assertSame(4.0, $dashboard['recipients']->first()['average']);
        $this->assertSame(2.0, $this->durationFor($direct));
    }

    protected function timedTicket(string $submittedAt, string $resolvedAt, array $attributes = []): Ticket
    {
        $ticket = $this->ticket(Ticket::STATUS_RESOLVED, array_merge([
            'resolved_at' => Carbon::parse($resolvedAt),
            'assigned_to' => $this->recipientUser->id,
            'current_handler_id' => $this->recipientUser->id,
        ], $attributes));
        Complaint::query()->whereKey($ticket->complaint_id)->update(['created_at' => Carbon::parse($submittedAt)]);

        return $ticket->fresh();
    }

    protected function audit(Ticket $ticket, string $action, string $at): void
    {
        $event = AuditLog::create([
            'ticket_id' => $ticket->id,
            'action' => $action,
        ]);
        AuditLog::query()->whereKey($event->id)->update(['created_at' => Carbon::parse($at)]);
    }

    protected function ticket(string $status, array $attributes = []): Ticket
    {
        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $this->student->student->id,
            'category_id' => $this->category->id,
            'subject_title' => 'Analytics test ticket',
            'description' => 'Analytics test description',
            'is_anonymous' => false,
            'status' => $status,
        ]);

        return Ticket::create(array_merge([
            'complaint_id' => $complaint->id,
            'status' => $status,
            'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
            'jurisdiction' => Ticket::JURISDICTION_RECIPIENT,
        ], $attributes));
    }

    protected function durationFor(Ticket $ticket): float
    {
        return $ticket->complaint->created_at->diffInDays($ticket->resolved_at);
    }
}
