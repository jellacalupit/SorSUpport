<?php

namespace App\Exports;

use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AnalyticsReportExport implements FromArray, WithHeadings, ShouldAutoSize
{
    protected array $reportData;

    public function __construct(array $reportData)
    {
        $this->reportData = $reportData;
    }

    public function array(): array
    {
        $rows = [['Analytics Report'], []];
        $overview = $this->reportData['overview'] ?? [];
        $breakdowns = $this->reportData['breakdowns'] ?? [];
        $satisfaction = $breakdowns['satisfaction'] ?? [];
        $needsAction = $overview['needs_action'] ?? 0;
        $escalated = $overview['escalated_tickets'] ?? 0;

        $rows[] = ['Summary'];
        $rows[] = ['Measure', 'Value'];
        $rows[] = ['Total tickets', $overview['total'] ?? 0];
        $rows[] = ['Active tickets', $overview['open'] ?? 0];
        $rows[] = ['Resolution rate', ($overview['resolution_rate'] ?? 0) . '%'];
        $excluded = collect($this->reportData['resolution_rate_details']['excluded'] ?? [])->filter();
        $notCounted = $excluded
            ->map(fn ($count, $type) => str_replace('_', ' ', $type) . ' ' . $count)
            ->implode(', ');
        if ($notCounted !== '') {
            $rows[] = ['Not counted', $notCounted];
        }
        $rows[] = ['Active handling median (days)', $overview['median_days'] ?? '—'];
        $rows[] = ['Active handling average (days)', $overview['average_days'] ?? '—'];
        $rows[] = ['Escalation rate', ($needsAction ? round($escalated / $needsAction * 100, 1) : 0) . '%'];
        $rows[] = ['Satisfaction', $satisfaction['count'] ? ($satisfaction['average'] . ' / 5') : 'No ratings'];

        $monthly = $this->reportData['monthly_ticket_volumes'] ?? [];
        $rows[] = [];
        $rows[] = ['Monthly Ticket Volume'];
        $rows[] = ['Month', 'Submitted', 'Resolved', 'Informational', 'Invalid'];
        foreach ($monthly['labels'] ?? [] as $index => $month) {
            $values = $monthly['values'][$index] ?? [];
            $rows[] = [
                $month,
                $values['submitted'] ?? 0,
                $values['resolved'] ?? 0,
                $values['informational'] ?? 0,
                $values['invalid'] ?? 0,
            ];
        }

        $rows[] = [];
        $rows[] = ['Volume by Category'];
        $rows[] = ['Category', 'Submitted', 'Resolved', 'Informational', 'Invalid', 'Unclassified'];
        foreach ($this->reportData['category_volumes']['rows'] ?? [] as $category) {
            $segments = $category['segments'];
            $rows[] = [
                $category['name'],
                $segments['submitted'],
                $segments['resolved'],
                $segments['informational'],
                $segments['invalid'],
                $segments['unclassified'],
            ];
        }

        $performance = $this->reportData['performance_metrics'] ?? [];
        $rows[] = [];
        $rows[] = ['Recipient Performance'];
        $rows[] = ['Name', 'Office', 'Designation', 'Tickets', 'Open', 'Oldest open (days)', 'Resolved', 'Resolved %', 'Avg acknowledge (days)', 'Avg handling (days)', 'Rating'];
        foreach ($performance['handler_rows'] ?? [] as $recipient) {
            $rows[] = [
                $recipient['name'],
                $recipient['unit'],
                $recipient['designation'],
                $recipient['assigned_tickets'],
                $recipient['open_tickets'],
                $recipient['oldest_open_ticket_age_days'] ?? '—',
                $recipient['resolved_tickets'],
                $recipient['resolved_percentage'],
                $recipient['acknowledgment_average_days'] ?? '—',
                $recipient['handling_average_days'] ?? '—',
                $recipient['rating_average'] ?? '—',
            ];
        }
        $rows[] = ['Escalation and Follow-up'];
        $rows[] = ['Measure', 'Value'];
        $rows[] = ['Escalation rate', ($needsAction ? round($escalated / $needsAction * 100, 1) : 0) . '%'];
        foreach (['escalation' => 'Student escalation requests', 'update' => 'Student update requests'] as $key => $label) {
            $request = $performance['student_requests'][$key] ?? [];
            $rows[] = [$label, ($request['count'] ?? 0) . ' (' . ($request['followed_by_sds_action'] ?? 0) . ' followed by SDS)'];
        }
        $rows[] = ['Further-action rate', ($performance['further_action_rate'] ?? 0) . '%'];
        $rows[] = ['Reopened', $performance['reopen_count'] ?? 0];

        foreach ([
            'resolution_types' => ['Resolution Outcomes', 'Outcome'],
            'closure_reasons' => ['Closure Reasons', 'Reason'],
            'escalation_levels' => ['Escalation Levels', 'Level'],
        ] as $key => [$title, $heading]) {
            $rows[] = [];
            $rows[] = [$title];
            $rows[] = [$heading, 'Tickets'];
            foreach ($breakdowns[$key] ?? [] as $label => $count) {
                $rows[] = [$label, $count];
            }
        }

        $rows[] = [];
        $rows[] = ['Active Ticket Status Counts'];
        $rows[] = ['Status', 'Tickets'];
        foreach ($this->reportData['status_distribution']['labels'] ?? [] as $index => $label) {
            $rows[] = [$label, $this->reportData['status_distribution']['data'][$index] ?? 0];
        }

        $rows[] = [];
        $rows[] = ['Classification'];
        $rows[] = ['Classification', 'Tickets'];
        foreach ($this->reportData['classification_distribution'] ?? [] as $label => $count) {
            $rows[] = [$label, $count];
        }

        foreach ([
            'colleges' => ['Tickets by College', 'College'],
            'programs' => ['Tickets by Program', 'Program'],
            'year_levels' => ['Tickets by Year Level', 'Year Level'],
            'origin' => ['Ticket Origin', 'Origin'],
        ] as $key => [$title, $heading]) {
            $rows[] = [];
            $rows[] = [$title];
            $rows[] = [$heading, 'Tickets'];
            foreach ($breakdowns[$key] ?? [] as $label => $count) {
                $rows[] = [$label, $count];
            }
            if (in_array($key, ['colleges', 'programs', 'year_levels'], true)) {
                $rows[] = ['Sensitive categories (not broken down)', $breakdowns['sensitive_not_broken_down'] ?? 0];
            }
        }

        $rows[] = [];
        $rows[] = ['Student Satisfaction'];
        $rows[] = ['Average rating', $satisfaction['average'] ?? 'No ratings yet'];
        $rows[] = ['Tickets rated', $satisfaction['count'] ?? 0];
        foreach ($satisfaction['distribution'] ?? [] as $rating => $count) {
            $rows[] = [$rating . ' out of 5', $count];
        }

        $filters = array_filter($this->reportData['filters'] ?? [], fn ($value) => filled($value));
        if ($filters !== []) {
            $rows[] = [];
            $rows[] = ['Applied Filters'];
            foreach ($filters as $filter => $value) {
                $rows[] = [Str::headline($filter), $value];
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return [];
    }
}
