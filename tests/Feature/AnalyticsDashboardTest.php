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

class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_tabs_render_without_ticket_text_and_show_sensitive_count(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN]);
        $student = $this->student('PRIVATE_DASHBOARD_IDENTITY');
        $regular = ComplaintCategory::create(['name' => 'Student Services', 'is_active' => true]);
        $sensitive = ComplaintCategory::create(['name' => 'Sensitive concern category', 'is_active' => true, 'is_sensitive' => true]);
        $this->ticket($student, $regular, Ticket::STATUS_IN_PROGRESS, Ticket::CLASSIFICATION_NEEDS_RESOLUTION, [
            'subject_title' => 'PRIVATE_DASHBOARD_SUBJECT',
            'description' => 'PRIVATE_DASHBOARD_DESCRIPTION',
            'personnel_involved' => 'PRIVATE_DASHBOARD_PERSONNEL',
        ]);
        $this->ticket($student, $sensitive, Ticket::STATUS_IN_PROGRESS, Ticket::CLASSIFICATION_NEEDS_RESOLUTION, [
            'subject_title' => 'PRIVATE_SENSITIVE_DASHBOARD_SUBJECT',
            'description' => 'PRIVATE_SENSITIVE_DASHBOARD_DESCRIPTION',
            'personnel_involved' => 'PRIVATE_SENSITIVE_DASHBOARD_PERSONNEL',
            'is_anonymous' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.analytics.index'))->assertOk();

        foreach (['Overview', 'Recipient performance', 'Students and feedback'] as $heading) {
            $response->assertSee($heading);
        }
        foreach (['Total tickets', 'Resolution rate', 'Active handling time', 'Escalation rate', 'Satisfaction'] as $cardLabel) {
            $response->assertSee('font-semibold text-black">' . $cardLabel, false);
        }
        $response->assertSee('Sensitive categories (not broken down): 1')
            ->assertSee('#2563eb')
            ->assertSee('#0e7490')
            ->assertDontSee('categories have no tickets')
            ->assertDontSee('All time')
            ->assertSee('data-student-attribute-dropdown', false)
            ->assertSee('aria-label="Student attribute"', false)
            ->assertSee('items-center gap-2 text-left', false)
            ->assertDontSee('id="student-dimension"', false)
            ->assertDontSee("studentDimension === 'blocks'", false)
            ->assertDontSee('First staff reply')
            ->assertSee('Volume of Needs Resolution Tickets')
            ->assertSee('Volume of Informational and Invalid Tickets')
            ->assertSee('Submitted =')
            ->assertSee('Resolved =')
            ->assertSee('Informational =')
            ->assertSee('Invalid =')
            ->assertDontSee('12-month totals:')
            ->assertSee('renderAnalyticsChartsWhenVisible', false)
            ->assertSee('window.destroyAnalyticsVolumeCharts?.();', false)
            ->assertSee("position: analyticsCategoryTooltipPosition", false)
            ->assertSee('const fitsToRight = barEnd + gap + tooltipWidth <= chartArea.right;', false)
            ->assertSee("interaction: { mode: 'index', intersect: true, axis: 'xy' }", false)
            ->assertSee("yAlign: placeAbove ? 'bottom' : 'top'", false)
            ->assertDontSee('analyticsCategoryTotalLabels', false)
            ->assertSee('data-analytics-distribution-row', false)
            ->assertSee('data-analytics-distribution-stack', false)
            ->assertSee('lg:grid-cols-[minmax(20rem,0.8fr)_minmax(0,1.5fr)]', false)
            ->assertSee('data-staff-sort-table class="w-full min-w-[1200px] text-left text-xs"', false)
            ->assertSee('data-staff-sort="designation"', false)
            ->assertSee('thead class="border-b bg-primary text-white"', false)
            ->assertSee('window.renderAnalyticsVolumeCharts();', false)
            ->assertDontSee('Common subjects')
            ->assertDontSee('Top 5 categories')
            ->assertDontSee('Handled by office')
            ->assertDontSee('Response and handling times')
            ->assertDontSee('Active handling time by category')
            ->assertDontSee('PRIVATE_DASHBOARD_SUBJECT')
            ->assertDontSee('PRIVATE_DASHBOARD_DESCRIPTION')
            ->assertDontSee('PRIVATE_DASHBOARD_PERSONNEL')
            ->assertDontSee('PRIVATE_SENSITIVE_DASHBOARD_SUBJECT')
            ->assertDontSee('PRIVATE_SENSITIVE_DASHBOARD_DESCRIPTION')
            ->assertDontSee('PRIVATE_SENSITIVE_DASHBOARD_PERSONNEL')
            ->assertDontSee('PRIVATE_DASHBOARD_IDENTITY');
    }

    public function test_non_admin_users_cannot_open_analytics_dashboard(): void
    {
        foreach ([User::ROLE_STUDENT, User::ROLE_RECIPIENT] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('admin.analytics.index'))->assertForbidden();
        }
    }

    public function test_empty_analytics_dashboard_shows_friendly_empty_states_in_every_tab(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN]);

        $response = $this->actingAs($admin)->get(route('admin.analytics.index'))->assertOk();
        foreach ([
            'Overview',
            'Recipient performance',
            'Students and feedback',
            'No ticket volume data in this period.',
            'No category totals in this period.',
            'No outcome data in this period.',
            'No tickets in this period.',
            '—',
            'No college breakdown data in this period.',
            'No ticket origin data in this period.',
            'No satisfaction ratings in this period.',
        ] as $expected) {
            $response->assertSee($expected);
        }
    }

    public function test_escalation_kpi_and_card_use_the_same_needs_resolution_rate(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN]);
        $student = $this->student('ESCALATION-DASHBOARD-ID');
        $category = ComplaintCategory::create(['name' => 'Student Services', 'is_active' => true]);

        foreach (range(1, 4) as $index) {
            $ticket = $this->ticket($student, $category, Ticket::STATUS_IN_PROGRESS, Ticket::CLASSIFICATION_NEEDS_RESOLUTION);
            if ($index <= 2) {
                AuditLog::log($ticket->id, 'ticket_escalated', $admin->id);
            }
        }
        $informational = $this->ticket($student, $category, Ticket::STATUS_SUBMITTED, Ticket::CLASSIFICATION_INFORMATIONAL);
        AuditLog::log($informational->id, 'ticket_escalated', $admin->id);

        $response = $this->actingAs($admin)->get(route('admin.analytics.index'))->assertOk();
        $response->assertSee('50.0%')
            ->assertSee('2 of 4 needs-resolution')
            ->assertSee('2 of 4 (50.0%)');

        $analytics = app(AnalyticsService::class);
        $dashboard = $analytics->getDashboardData();
        $report = $analytics->getReportData();
        $this->assertSame(
            $dashboard['openCount'],
            $report['overview']['open']
        );
        $this->assertSame($dashboard['monthlyTicketVolumes'], $report['monthly_ticket_volumes']);
        $this->assertSame($dashboard['categoryVolumes'], $report['category_volumes']);
        $this->assertSame(2, $report['overview']['escalated_tickets']);
    }

    public function test_overview_series_cover_twelve_months_and_category_segments_are_exclusive(): void
    {
        $student = $this->student('MONTHLY-ANALYTICS-ID');
        $category = ComplaintCategory::create(['name' => 'Monthly totals', 'is_active' => true]);
        $fixtures = [
            [$this->ticket($student, $category, Ticket::STATUS_SUBMITTED, Ticket::CLASSIFICATION_NEEDS_RESOLUTION), '2026-01-15', null],
            [$this->ticket($student, $category, Ticket::STATUS_RESOLVED, Ticket::CLASSIFICATION_NEEDS_RESOLUTION, [
                'resolved_at' => Carbon::parse('2026-09-10'),
            ]), '2026-02-15', '2026-09-10'],
            [$this->ticket($student, $category, Ticket::STATUS_SUBMITTED, Ticket::CLASSIFICATION_INFORMATIONAL), '2026-03-15', null],
            [$this->ticket($student, $category, Ticket::STATUS_CLOSED, Ticket::CLASSIFICATION_INVALID, [
                'closure_type' => Ticket::CLOSURE_INVALID,
            ]), '2026-04-15', null],
            [$this->ticket($student, $category, Ticket::STATUS_SUBMITTED, null), '2026-05-15', null],
            [$this->ticket($student, $category, Ticket::STATUS_SUBMITTED, Ticket::CLASSIFICATION_NEEDS_RESOLUTION), '2026-10-15', null],
            [$this->ticket($student, $category, Ticket::STATUS_CLOSED, Ticket::CLASSIFICATION_NEEDS_RESOLUTION, [
                'closure_type' => Ticket::CLOSURE_WITHDRAWN,
                'resolved_at' => Carbon::parse('2026-08-10'),
            ]), '2026-06-15', '2026-08-10'],
        ];

        foreach ($fixtures as [$ticket, $submittedAt, $resolvedAt]) {
            Complaint::query()->whereKey($ticket->complaint_id)->update([
                'created_at' => Carbon::parse($submittedAt),
            ]);
            if ($resolvedAt) {
                Ticket::query()->whereKey($ticket->id)->update(['resolved_at' => Carbon::parse($resolvedAt)]);
            }
        }

        $dashboard = app(AnalyticsService::class)->getDashboardData([
            'start_date' => '2025-11-01',
            'end_date' => '2026-10-31',
        ]);
        $volumes = $dashboard['monthlyTicketVolumes'];

        $this->assertCount(12, $volumes['labels']);
        $this->assertSame('Nov 25', $volumes['labels'][0]);
        $this->assertSame('Oct 26', $volumes['labels'][11]);
        $this->assertSame(1, $volumes['values'][2]['submitted']);
        $this->assertSame(1, $volumes['values'][3]['submitted']);
        $this->assertSame(1, $volumes['values'][10]['resolved']);
        $this->assertSame(1, $volumes['values'][4]['informational']);
        $this->assertSame(1, $volumes['values'][5]['invalid']);
        $this->assertSame(0, $volumes['values'][0]['submitted']);

        $category = $dashboard['categoryVolumes']['rows'][0];
        $this->assertSame(7, $category['total']);
        $this->assertSame($category['total'], array_sum($category['segments']));
        $this->assertSame(3, $category['segments']['submitted']);
        $this->assertSame(1, $category['segments']['resolved']);
        $this->assertSame(1, $category['segments']['informational']);
        $this->assertSame(1, $category['segments']['invalid']);
        $this->assertSame(1, $category['segments']['unclassified']);
        $this->assertCount(10, $dashboard['statusClassificationCounts']);
        $this->assertSame(2, $dashboard['statusClassificationCounts']['submitted:needs_resolution']);
        $this->assertSame(1, $dashboard['statusClassificationCounts']['submitted:unclassified']);
        $this->assertSame(1, $dashboard['statusClassificationCounts']['resolved:needs_resolution']);
        $this->assertSame(1, $dashboard['statusClassificationCounts']['closed:needs_resolution']);
        $this->assertStringNotContainsString('informational', implode('|', array_keys($dashboard['statusClassificationCounts'])));
        $this->assertStringNotContainsString('invalid', implode('|', array_keys($dashboard['statusClassificationCounts'])));
        $this->assertSame(5, array_sum($dashboard['statusClassificationCounts']));

        $response = $this->actingAs(User::factory()->create(['role' => User::ROLE_SDS_ADMIN]))
            ->get(route('admin.analytics.index', ['start_date' => '2025-11-01', 'end_date' => '2026-10-31']))
            ->assertOk()
            ->assertSee('Submitted')
            ->assertDontSee('Submitted (Unclassified)')
            ->assertDontSee('Needs-resolution and unclassified tickets')
            ->assertDontSee('Each ticket appears in exactly one classification segment.');
    }

    public function test_staff_table_uses_final_resolver_assignment_handling_fallbacks_and_rating_counts(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SDS_ADMIN]);
        $student = $this->student('STAFF-ANALYTICS-ID');
        $category = ComplaintCategory::create(['name' => 'Staff metrics', 'is_active' => true]);
        $firstHandler = $this->recipientUser('First Handler');
        $lastHandler = $this->recipientUser('Last Handler');
        $ticket = $this->ticket($student, $category, Ticket::STATUS_RESOLVED, Ticket::CLASSIFICATION_NEEDS_RESOLUTION, [
            'assigned_to' => $lastHandler->id,
            'current_handler_id' => $lastHandler->id,
            'satisfaction_rating' => 4,
            'resolved_at' => Carbon::parse('2026-01-07'),
            'subject_title' => 'PRIVATE_STAFF_TABLE_SUBJECT',
            'description' => 'PRIVATE_STAFF_TABLE_DESCRIPTION',
            'personnel_involved' => 'PRIVATE_STAFF_TABLE_PERSONNEL',
        ]);
        Complaint::query()->whereKey($ticket->complaint_id)->update(['created_at' => Carbon::parse('2026-01-01')]);
        Ticket::query()->whereKey($ticket->id)->update(['resolved_at' => Carbon::parse('2026-01-07')]);
        $this->auditAt($ticket, 'ticket_assigned', $admin->id, '2026-01-01', "SDS Admin assigned the ticket {$ticket->complaint->reference_number} to {$firstHandler->display_name} for handling.");
        $this->auditAt($ticket, 'ticket_acknowledged', $firstHandler->id, '2026-01-02');
        $this->auditAt($ticket, 'complaint_resolved', $firstHandler->id, '2026-01-03');
        $this->auditAt($ticket, 'ticket_reopened_from_resolved', $admin->id, '2026-01-04');
        $this->auditAt($ticket, 'ticket_assigned', $admin->id, '2026-01-05', "SDS Admin assigned the ticket {$ticket->complaint->reference_number} to {$lastHandler->display_name} for handling.");
        $this->auditAt($ticket, 'ticket_acknowledged', $lastHandler->id, '2026-01-05 06:00:00');
        $this->auditAt($ticket, 'clarification_requested', $admin->id, '2026-01-05 12:00:00');
        $this->auditAt($ticket, 'clarification_provided', $student->id, '2026-01-06 12:00:00');
        $this->auditAt($ticket, 'complaint_resolved', $lastHandler->id, '2026-01-07');

        $fallbackTicket = $this->ticket($student, $category, Ticket::STATUS_RESOLVED, Ticket::CLASSIFICATION_NEEDS_RESOLUTION, [
            'assigned_to' => $lastHandler->id,
            'current_handler_id' => $lastHandler->id,
            'satisfaction_rating' => 5,
            'resolved_at' => Carbon::parse('2026-02-11'),
            'subject_title' => 'PRIVATE_FALLBACK_SUBJECT',
        ]);
        Complaint::query()->whereKey($fallbackTicket->complaint_id)->update(['created_at' => Carbon::parse('2026-02-01')]);
        Ticket::query()->whereKey($fallbackTicket->id)->update(['resolved_at' => Carbon::parse('2026-02-11')]);
        $this->auditAt($fallbackTicket, 'complaint_resolved', $lastHandler->id, '2026-02-11');

        $dashboard = app(AnalyticsService::class)->getDashboardData();
        $rows = collect($dashboard['performanceMetrics']['handler_rows']);
        $lastRow = $rows->firstWhere('user_id', $lastHandler->id);
        $firstRow = $rows->firstWhere('user_id', $firstHandler->id);

        $this->assertSame(2, $lastRow['assigned_tickets']);
        $this->assertSame(2, $lastRow['resolved_tickets']);
        $this->assertSame('Officer', $lastRow['designation']);
        $this->assertSame(100.0, $lastRow['resolved_percentage']);
        $this->assertSame(0, $firstRow['resolved_tickets']);
        $this->assertSame(0.3, $lastRow['acknowledgment_average_days']);
        $this->assertSame(1, $lastRow['acknowledgment_count']);
        $this->assertSame(5.5, $lastRow['handling_average_days']);
        $this->assertSame(2, $lastRow['handling_count']);
        $this->assertSame(1, $lastRow['handling_fallback_count']);
        $this->assertSame(4.5, $lastRow['rating_average']);
        $this->assertSame(2, $lastRow['rating_count']);

        $this->actingAs($admin)
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertSee('Designation')
            ->assertSee('Avg time to')
            ->assertSee('acknowledge (tickets)')
            ->assertSee('Avg handling time')
            ->assertSee('from assignment (tickets)')
            ->assertSee('Officer');

        $response = $this->actingAs($admin)->get(route('admin.analytics.index'))->assertOk();
        foreach ([
            'Name',
            'Tickets',
            'Open',
            'Oldest open',
            'Resolved',
            'Designation',
            'Avg time to',
            'acknowledge (tickets)',
            'Avg handling time',
            'from assignment (tickets)',
            'Avg rating',
            '0.3 days',
            '5.5 days',
            '1 fallback(s)',
            '4.5',
        ] as $expected) {
            $response->assertSee($expected);
        }
        $response->assertDontSee('PRIVATE_STAFF_TABLE_SUBJECT')
            ->assertDontSee('PRIVATE_STAFF_TABLE_DESCRIPTION')
            ->assertDontSee('PRIVATE_STAFF_TABLE_PERSONNEL')
            ->assertDontSee('PRIVATE_FALLBACK_SUBJECT');

        preg_match_all('/data-sort-name="([^"]+)"/', $response->getContent(), $sortNames);
        $this->assertSame([
            $lastHandler->table_name,
            $firstHandler->table_name,
        ], $sortNames[1]);
    }

    private function student(string $studentId): User
    {
        $user = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Student::create([
            'user_id' => $user->id,
            'student_id' => $studentId,
            'college' => 'CICT',
            'program' => 'BSIT',
            'year_level' => '1',
            'block' => 'A',
        ]);

        return $user;
    }

    private function ticket(User $student, ComplaintCategory $category, string $status, ?string $classification, array $attributes = []): Ticket
    {
        $complaint = Complaint::create([
            'reference_number' => Complaint::generateReferenceNumber(),
            'student_id' => $student->student->id,
            'category_id' => $category->id,
            'subject_title' => $attributes['subject_title'] ?? 'Analytics fixture subject',
            'description' => $attributes['description'] ?? 'Analytics fixture description.',
            'personnel_involved' => $attributes['personnel_involved'] ?? 'Not applicable',
            'is_anonymous' => $attributes['is_anonymous'] ?? false,
            'status' => $status,
        ]);

        return Ticket::create([
            'complaint_id' => $complaint->id,
            'status' => $status,
            'classification' => $classification,
            'resolved_at' => $status === Ticket::STATUS_RESOLVED ? now() : null,
            'satisfaction_rating' => $attributes['satisfaction_rating'] ?? null,
        ] + collect($attributes)->except(['subject_title', 'description', 'personnel_involved', 'is_anonymous', 'satisfaction_rating'])->all());
    }

    private function recipientUser(string $name): User
    {
        [$firstName, $lastName] = explode(' ', $name, 2);
        $user = User::factory()->create([
            'role' => User::ROLE_RECIPIENT,
            'first_name' => $firstName,
            'middle_name' => null,
            'last_name' => $lastName,
        ]);
        Recipient::create([
            'user_id' => $user->id,
            'staff_id' => strtoupper(str_replace(' ', '-', $name)),
            'unit' => $name . ' Office',
            'designation' => 'Officer',
        ]);

        return $user;
    }

    private function auditAt(Ticket $ticket, string $action, int $userId, string $createdAt, ?string $details = null): void
    {
        $audit = AuditLog::create([
            'ticket_id' => $ticket->id,
            'performed_by' => $userId,
            'action' => $action,
            'details' => $details,
        ]);
        $timestamp = Carbon::parse($createdAt);
        $audit->forceFill(['created_at' => $timestamp, 'updated_at' => $timestamp])->save();
    }
}
