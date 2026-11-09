<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Exports\AnalyticsReportExport;
use App\Models\Recipient;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\ThreadMessage;
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

    public function test_staff_metrics_and_handler_performance_are_derived_from_existing_events(): void
    {
        $acknowledgmentTickets = [];
        foreach (['2026-01-03', '2026-01-04', '2026-01-05'] as $index => $acknowledgedAt) {
            $ticket = $this->ticket(Ticket::STATUS_ASSIGNED, [
                'assigned_to' => $this->recipientUser->id,
                'current_handler_id' => $this->recipientUser->id,
                'acknowledged_at' => Carbon::parse($acknowledgedAt),
            ]);
            Complaint::query()->whereKey($ticket->complaint_id)->update(['created_at' => Carbon::parse('2026-01-01')]);
            $this->audit($ticket, 'ticket_assigned', '2026-01-02');
            $this->audit($ticket, 'ticket_acknowledged', $acknowledgedAt, $this->recipientUser->id);
            $acknowledgmentTickets[] = $ticket->fresh();
        }
        $message = ThreadMessage::create([
            'thread_id' => $acknowledgmentTickets[0]->thread->id,
            'sender_id' => $this->recipientUser->id,
            'content' => 'Staff response',
        ]);
        ThreadMessage::query()->whereKey($message->id)->update(['created_at' => Carbon::parse('2026-01-02 12:00:00')]);

        $classified = $this->ticket(Ticket::STATUS_SUBMITTED);
        Complaint::query()->whereKey($classified->complaint_id)->update(['created_at' => Carbon::parse('2026-01-01')]);
        $this->audit($classified, 'ticket_classified', '2026-01-04');

        $requestTicket = $acknowledgmentTickets[0];
        $this->audit($requestTicket, 'update_requested', '2026-01-06', $this->student->id);
        $this->audit($requestTicket, 'ticket_classified', '2026-01-06 01:00:00', User::factory()->create(['role' => User::ROLE_SDS_ADMIN])->id);
        $this->audit($requestTicket, 'escalation_requested', '2026-01-07', $this->student->id);
        $this->audit($requestTicket, 'ticket_escalated', '2026-01-07 01:00:00', User::factory()->create(['role' => User::ROLE_SDS_ADMIN])->id);

        $resolvedTickets = [];
        foreach (range(1, 3) as $index) {
            $ticket = $this->timedTicket('2026-01-01', '2026-01-03');
            $this->audit($ticket, 'complaint_resolved', '2026-01-03', $this->recipientUser->id);
            $resolvedTickets[] = $ticket;
        }
        $this->audit($resolvedTickets[0], 'further_action_requested', '2026-01-04', $this->student->id);
        $this->audit($resolvedTickets[1], 'ticket_reopened_from_resolved', '2026-01-05', User::factory()->create(['role' => User::ROLE_SDS_ADMIN])->id);
        $extraOpen = $this->ticket(Ticket::STATUS_IN_PROGRESS, [
            'assigned_to' => $this->recipientUser->id,
            'current_handler_id' => $this->recipientUser->id,
        ]);
        Complaint::query()->whereKey($extraOpen->complaint_id)->update(['created_at' => Carbon::parse('2026-01-01')]);

        $otherUser = User::factory()->create(['role' => User::ROLE_RECIPIENT]);
        Recipient::create([
            'user_id' => $otherUser->id,
            'staff_id' => 'ANALYTICS-OTHER',
            'unit' => 'Registrar Office',
            'designation' => 'Officer',
        ]);
        foreach (range(1, 2) as $ignored) {
            $ticket = $this->timedTicket('2026-01-01', '2026-01-03', [
                'assigned_to' => $otherUser->id,
                'current_handler_id' => $otherUser->id,
            ]);
            $this->audit($ticket, 'complaint_resolved', '2026-01-03', $otherUser->id);
        }

        $analytics = app(AnalyticsService::class);
        $dashboard = $analytics->getDashboardData();
        $metrics = $dashboard['performanceMetrics'];
        $report = $analytics->getReportData();

        $this->assertSame(1.5, $metrics['review_time']['average_days']);
        $this->assertSame(1.0, $metrics['review_time']['median_days']);
        $this->assertSame(2.0, $metrics['acknowledgment_time']['average_days']);
        $this->assertSame(2.0, $metrics['acknowledgment_time']['median_days']);
        $this->assertSame(1.5, $metrics['first_response_time']['average_days']);
        $this->assertSame(20.0, $metrics['further_action_rate']);
        $this->assertSame(5, $metrics['resolved_ticket_count']);
        $this->assertSame(1, $metrics['further_action_ticket_count']);
        $this->assertSame(1, $metrics['reopen_count']);
        $this->assertSame(['count' => 1, 'followed_by_sds_action' => 1], $metrics['student_requests']['update']);
        $this->assertSame(['count' => 1, 'followed_by_sds_action' => 1], $metrics['student_requests']['escalation']);
        $this->assertSame(1, $metrics['escalation_count']);

        $handler = collect($metrics['handler_rows'])->firstWhere('name', $this->recipientUser->table_name);
        $otherHandler = collect($metrics['handler_rows'])->firstWhere('name', $otherUser->table_name);
        $this->assertSame(4, $handler['open_tickets']);
        $this->assertSame(round((now()->getTimestamp() - Carbon::parse('2026-01-01')->getTimestamp()) / 86400, 1), $handler['oldest_open_ticket_age_days']);
        $this->assertSame(2.0, $handler['median_acknowledgment_days']);
        $this->assertSame(3, $handler['resolved_tickets']);
        $this->assertSame(2.0, $handler['handling_by_category']['Student Services']['median_days']);
        $this->assertNull($handler['escalations']);
        $this->assertNull($otherHandler['handling_by_category']['Student Services']['median_days']);
        $this->assertSame(2, $otherHandler['handling_by_category']['Student Services']['ticket_count']);
        $this->assertSame(5, $report['performance_metrics']['resolved_ticket_count']);

        $office = collect($metrics['office_rows'])->firstWhere('name', 'Registrar Office');
        $this->assertSame(4, $office['open_tickets']);
        $this->assertSame(5, $office['resolved_tickets']);
        $this->assertSame(2.0, $office['handling_by_category']['Student Services']['median_days']);

        $performanceReport = view('admin.analytics.report', [
            'reportData' => $report,
            'filters' => [],
            'sections' => ['performance'],
        ])->render();
        $this->assertStringContainsString('Staff and Office Performance', $performanceReport);
        $this->assertStringContainsString('Not enough data', $performanceReport);
        $this->assertStringNotContainsString('Analytics test ticket', $performanceReport);
        $excelRows = collect((new AnalyticsReportExport($report))->array())
            ->map(fn ($row) => implode(' | ', $row));
        $this->assertTrue($excelRows->contains('Performance by Handler'));
        $this->assertTrue($excelRows->contains('Performance by Office'));
        $this->assertTrue($excelRows->contains(fn ($row) => str_contains($row, 'Not enough data')));

        $reviewOnly = $analytics->getReportData(['status' => Ticket::STATUS_SUBMITTED]);
        $this->assertSame(1, $reviewOnly['performance_metrics']['review_time']['sample_count']);
        $this->assertSame(0, $reviewOnly['performance_metrics']['resolved_ticket_count']);

        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN]);
        $this->actingAs($admin)
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertSee('Staff and office performance')
            ->assertSee('Not enough data');
        $this->assertArrayHasKey('office_rows', $report['performance_metrics']);
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

    protected function audit(Ticket $ticket, string $action, string $at, ?int $performedBy = null): void
    {
        $event = AuditLog::create([
            'ticket_id' => $ticket->id,
            'action' => $action,
            'performed_by' => $performedBy,
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
