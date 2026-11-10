<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Exports\AnalyticsReportExport;
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

    public function test_status_counts_and_exports_include_only_needs_resolution_and_unclassified_tickets(): void
    {
        $submitted = $this->ticket(Ticket::STATUS_SUBMITTED, [
            'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
        ]);
        $unclassified = $this->ticket(Ticket::STATUS_NEEDS_CLARIFICATION, [
            'classification' => null,
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
        $this->assertSame(1, $counts[Ticket::STATUS_NEEDS_CLARIFICATION]);
        $this->assertArrayNotHasKey('forwarded_for_information', $counts);
        $this->assertSame(2, array_sum($counts));
        $statusChart = $analytics->getActiveTicketStatuses();
        $this->assertSame(array_values(Ticket::DISPLAY_STATUS_LABELS), $statusChart['labels']);
        $this->assertSame(2, array_sum($statusChart['data']));
        $this->assertSame(1, $analytics->getTotalTickets([
            'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
            'status' => Ticket::STATUS_SUBMITTED,
        ]));
        $this->assertSame(1, $analytics->getTotalTickets([
            'classification' => 'unclassified',
            'status' => Ticket::STATUS_NEEDS_CLARIFICATION,
        ]));
        $this->assertSame(1, $analytics->getTotalTickets(['recipient_id' => $this->recipientUser->id]));
        $this->assertSame(1, $analytics->getTotalTickets(['unit' => 'Registrar Office']));

        $dashboard = $analytics->getDashboardData();
        $this->assertSame(1, $dashboard['statusCounts']['Submitted']);
        $this->assertSame(1, $dashboard['statusCounts']['In review']);
        $this->assertArrayNotHasKey('Forwarded for information', $dashboard['statusCounts']);
        $this->assertSame(2, $dashboard['openCount']);

        $report = $analytics->getReportData();
        $this->assertSame(array_values(Ticket::DISPLAY_STATUS_LABELS), $report['status_distribution']['labels']);
        $this->assertSame(2, array_sum($report['status_distribution']['data']));
        $excelRows = (new AnalyticsReportExport($report))->array();
        $statusSectionIndex = array_search(['Active Ticket Status Counts'], $excelRows, true);
        $this->assertNotFalse($statusSectionIndex);
        $excelStatusRows = array_slice($excelRows, $statusSectionIndex + 1, count(Ticket::DISPLAY_STATUS_LABELS));
        $this->assertSame('Submitted', $excelStatusRows[0][0]);
        $this->assertSame(1, $excelStatusRows[0][1]);
        $this->assertNotContains('Forwarded for information', array_column($excelStatusRows, 0));

        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN]);
        $response = $this->actingAs($admin)
            ->get(route('admin.analytics.index'))
            ->assertOk();
        $response->assertDontSee('Forwarded for information')
            ->assertSee('In review');

        $this->assertSame(Ticket::STATUS_SUBMITTED, $forwarded->fresh()->status);
        $this->assertSame(Ticket::STATUS_SUBMITTED, $submitted->fresh()->status);
        $this->assertSame(Ticket::STATUS_NEEDS_CLARIFICATION, $unclassified->fresh()->status);
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
        $this->assertArrayNotHasKey('first_response_time', $metrics);
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
        $this->assertSame('Not tracked per handler', $handler['escalations']);
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
        $this->assertStringContainsString('Recipient and Office Performance', $performanceReport);
        $this->assertStringNotContainsString('First staff reply', $performanceReport);
        $this->assertStringContainsString('Not tracked per handler', $performanceReport);
        $this->assertStringContainsString('Not enough data', $performanceReport);
        $this->assertStringNotContainsString('Analytics test ticket', $performanceReport);
        $excelRows = collect((new AnalyticsReportExport($report))->array())
            ->map(fn ($row) => implode(' | ', $row));
        $this->assertTrue($excelRows->contains('Performance by Handler'));
        $this->assertTrue($excelRows->contains('Performance by Office'));
        $this->assertFalse($excelRows->contains(fn ($row) => str_contains($row, 'First staff reply')));
        $this->assertTrue($excelRows->contains(fn ($row) => str_contains($row, 'Not tracked per handler')));
        $this->assertTrue($excelRows->contains(fn ($row) => str_contains($row, 'Not enough data')));

        $reviewOnly = $analytics->getReportData([
            'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
            'status' => Ticket::STATUS_SUBMITTED,
        ]);
        $this->assertSame(1, $reviewOnly['performance_metrics']['review_time']['sample_count']);
        $this->assertSame(0, $reviewOnly['performance_metrics']['resolved_ticket_count']);

        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN]);
        $this->actingAs($admin)
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertSee('Recipient performance')
            ->assertDontSee('First staff reply')
            ->assertDontSee('Escalations are counted by category and level, not per handler.')
            ->assertDontSee('Averages from very few tickets are not reliable; see the count in brackets.')
            ->assertDontSee('Committee outcomes: average')
            ->assertSee('Avg time to acknowledge (tickets)')
            ->assertSee('Avg handling time from assignment (tickets)')
            ->assertDontSee('n/a means fewer than 3 tickets');
        $this->assertArrayHasKey('office_rows', $report['performance_metrics']);
    }

    public function test_reopened_ticket_resolution_is_attributed_only_to_its_last_resolving_handler(): void
    {
        $otherUser = User::factory()->create(['role' => User::ROLE_RECIPIENT]);
        Recipient::create([
            'user_id' => $otherUser->id,
            'staff_id' => 'ANALYTICS-LAST-RESOLVER',
            'unit' => 'Registrar Office',
            'designation' => 'Officer',
        ]);

        $ticket = $this->timedTicket('2026-01-01', '2026-01-06');
        $this->audit($ticket, 'complaint_resolved', '2026-01-03', $this->recipientUser->id);
        $this->audit($ticket, 'ticket_reopened_from_resolved', '2026-01-04', $this->student->id);
        $this->audit($ticket, 'complaint_resolved', '2026-01-06', $otherUser->id);

        $metrics = app(AnalyticsService::class)->getDashboardData()['performanceMetrics'];
        $firstHandler = collect($metrics['handler_rows'])->firstWhere('name', $this->recipientUser->table_name);
        $lastHandler = collect($metrics['handler_rows'])->firstWhere('name', $otherUser->table_name);

        $this->assertSame(1, $metrics['resolved_ticket_count']);
        $this->assertSame(0, $firstHandler['resolved_tickets']);
        $this->assertSame(1, $lastHandler['resolved_tickets']);
    }

    public function test_sensitive_tickets_are_counted_but_not_broken_down_by_student_attributes_or_exposed_in_analytics(): void
    {
        $regular = $this->ticket(Ticket::STATUS_SUBMITTED);
        Complaint::query()->whereKey($regular->complaint_id)->update([
            'subject_title' => 'PUBLIC_TEST_SUBJECT',
            'description' => 'PUBLIC_TEST_DESCRIPTION',
            'personnel_involved' => 'PUBLIC_TEST_PERSONNEL',
        ]);
        $privateCategory = ComplaintCategory::create([
            'name' => 'Sensitive Analytics Category',
            'is_active' => true,
            'is_sensitive' => true,
        ]);
        $privateStudentUser = User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'name' => 'PRIVATE_HIDDEN_STUDENT_NAME',
        ]);
        $privateStudent = Student::create([
            'user_id' => $privateStudentUser->id,
            'student_id' => 'PRIVATE_STUDENT_NUMBER',
            'college' => 'PRIVATE_COLLEGE',
            'program' => 'PRIVATE_PROGRAM',
            'year_level' => 'PRIVATE_YEAR',
            'block' => 'PRIVATE_BLOCK',
        ]);
        $sensitive = $this->ticket(Ticket::STATUS_SUBMITTED);
        Complaint::query()->whereKey($sensitive->complaint_id)->update([
            'category_id' => $privateCategory->id,
            'student_id' => $privateStudent->id,
            'subject_title' => 'PRIVATE_SENSITIVE_SUBJECT',
            'description' => 'PRIVATE_SENSITIVE_DESCRIPTION',
            'personnel_involved' => 'PRIVATE_SENSITIVE_PERSONNEL',
            'is_anonymous' => true,
        ]);

        $analytics = app(AnalyticsService::class);
        $dashboard = $analytics->getDashboardData();
        $report = $analytics->getReportData();
        $breakdowns = $dashboard['breakdowns'];
        $reportBreakdowns = $report['breakdowns'];

        $this->assertSame(2, $dashboard['total']);
        $this->assertSame(2, $report['summary']['total_tickets']);
        $this->assertSame(1, $dashboard['categoryCounts']['Sensitive Analytics Category']);
        $categoryIndex = array_search('Sensitive Analytics Category', $report['category_breakdown']['labels'], true);
        $this->assertNotFalse($categoryIndex);
        $this->assertSame(1, $report['category_breakdown']['data'][$categoryIndex]);
        $this->assertSame(1, $breakdowns['sensitive_not_broken_down']);
        $this->assertSame(1, $reportBreakdowns['sensitive_not_broken_down']);
        $this->assertArrayNotHasKey('blocks', $breakdowns);
        $this->assertArrayNotHasKey('blocks', $reportBreakdowns);

        foreach (['colleges' => 'CICT', 'programs' => 'BSIT', 'year_levels' => '1', 'origin' => 'Identified'] as $key => $regularLabel) {
            $this->assertSame([$regularLabel => 1], $breakdowns[$key]);
            $this->assertSame([$regularLabel => 1], $reportBreakdowns[$key]);
            $this->assertArrayNotHasKey('PRIVATE_COLLEGE', $breakdowns[$key]);
            $this->assertArrayNotHasKey('PRIVATE_PROGRAM', $breakdowns[$key]);
            $this->assertArrayNotHasKey('PRIVATE_YEAR', $breakdowns[$key]);
            $this->assertArrayNotHasKey('PRIVATE_BLOCK', $breakdowns[$key]);
            $this->assertArrayNotHasKey('Anonymous', $breakdowns[$key]);
        }

        $subjectSummary = $dashboard['subjects']->all();
        $this->assertArrayHasKey('Confidential concern', $subjectSummary);
        $this->assertArrayNotHasKey('PRIVATE_SENSITIVE_SUBJECT', $subjectSummary);

        $privateValues = [
            'PUBLIC_TEST_SUBJECT',
            'PUBLIC_TEST_DESCRIPTION',
            'PUBLIC_TEST_PERSONNEL',
            'PRIVATE_HIDDEN_STUDENT_NAME',
            'PRIVATE_SENSITIVE_SUBJECT',
            'PRIVATE_SENSITIVE_DESCRIPTION',
            'PRIVATE_SENSITIVE_PERSONNEL',
            'PRIVATE_COLLEGE',
            'PRIVATE_PROGRAM',
            'PRIVATE_YEAR',
            'PRIVATE_BLOCK',
        ];
        $reportJson = json_encode($report, JSON_THROW_ON_ERROR);
        $excelRows = (new AnalyticsReportExport($report))->array();
        $excelData = json_encode($excelRows, JSON_THROW_ON_ERROR);
        foreach ($privateValues as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $reportJson);
            $this->assertStringNotContainsString($privateValue, $excelData);
        }
        $sensitiveExcelRows = collect($excelRows)
            ->filter(fn ($row) => ($row[0] ?? null) === 'Sensitive categories (not broken down)')
            ->values();
        $this->assertCount(4, $sensitiveExcelRows);
        $this->assertSame(1, $sensitiveExcelRows->first()[1]);

        $pdfHtml = view('admin.analytics.report', [
            'reportData' => $report,
            'filters' => [],
            'sections' => array_keys(\App\Http\Controllers\Admin\AnalyticsController::REPORT_SECTIONS),
        ])->render();
        foreach ($privateValues as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $pdfHtml);
        }
        foreach (['Sensitive categories (not broken down)', 'Tickets by Year Level', 'Identified and Anonymous Origin'] as $expectedText) {
            $this->assertStringContainsString($expectedText, $pdfHtml);
        }
        $this->assertStringNotContainsString('Tickets by Block', $pdfHtml);
        $this->assertSame(4, substr_count($pdfHtml, 'Sensitive categories (not broken down)'));

        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN]);
        $this->actingAs($admin)
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertDontSee('Confidential concern')
            ->assertSee('Sensitive categories (not broken down)')
            ->assertDontSee('PRIVATE_SENSITIVE_SUBJECT')
            ->assertDontSee('PRIVATE_SENSITIVE_DESCRIPTION')
            ->assertDontSee('PRIVATE_SENSITIVE_PERSONNEL')
            ->assertDontSee('PRIVATE_HIDDEN_STUDENT_NAME');
        $this->actingAs($admin)
            ->get(route('admin.analytics.export.pdf'))
            ->assertOk();
        $this->actingAs($admin)
            ->get(route('admin.analytics.export.excel'))
            ->assertOk();

        $this->assertSame($this->category->id, $regular->fresh()->complaint->category_id);
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
