<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Arrayable;
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
        $rows = [];

        $rows[] = ['Analytics Report'];
        $rows[] = [];

        $rows[] = ['Summary'];
        foreach ($this->reportData['summary'] as $label => $value) {
            $rows[] = [Str::headline($label), $value];
        }

        $rows[] = [];
        $rows[] = ['Complaint Category Breakdown'];
        $rows[] = ['Category', 'Count'];
        foreach ($this->reportData['category_breakdown']['labels'] as $index => $label) {
            $rows[] = [$label, $this->reportData['category_breakdown']['data'][$index] ?? 0];
        }

        $rows[] = [];
        $rows[] = ['Resolution Chart'];
        foreach ($this->reportData['resolution_chart']['labels'] as $index => $label) {
            $rows[] = [$label, $this->reportData['resolution_chart']['data'][$index] ?? 0];
        }
        $rows[] = [];
        $rows[] = ['Resolution Rate Excluded Closures'];
        foreach ($this->reportData['resolution_rate_details']['excluded'] ?? [] as $type => $count) {
            if ($count > 0) {
                $rows[] = [Str::headline($type), $count];
            }
        }

        $rows[] = [];
        $resolutionTime = $this->reportData['average_resolution_time'];
        $rows[] = [];
        $rows[] = ['Active Handling Time (Calendar Days)'];
        $rows[] = ['Overall average', $resolutionTime['average_days'] ?? 0];
        $rows[] = ['Overall median', $resolutionTime['median_days'] ?? 0];
        $rows[] = ['Tickets using full elapsed time', $resolutionTime['fallback_count'] ?? 0];
        $committee = $resolutionTime['committee_outcomes'] ?? [];
        $rows[] = ['Committee outcomes average', $committee['average_days'] ?? 0];
        $rows[] = ['Committee outcomes median', $committee['median_days'] ?? 0];
        $rows[] = ['Committee outcomes tickets using full elapsed time', $committee['fallback_count'] ?? 0];
        $rows[] = [];
        $rows[] = ['Category', 'Average days', 'Median days', 'Tickets', 'Tickets using full elapsed time'];
        foreach ($resolutionTime['categories'] ?? [] as $category => $timing) {
            $rows[] = [
                $category,
                $timing['average_days'] ?? 0,
                $timing['median_days'] ?? 0,
                $timing['ticket_count'] ?? 0,
                $timing['fallback_count'] ?? 0,
            ];
        }

        $performance = $this->reportData['performance_metrics'] ?? [];
        $rows[] = [];
        $rows[] = ['Recipient and Office Performance'];
        $rows[] = ['Measure', 'Average days', 'Median days', 'Tickets'];
        foreach ([
            'Review time' => 'review_time',
            'Time to acknowledge' => 'acknowledgment_time',
        ] as $label => $key) {
            $timing = $performance[$key] ?? [];
            $rows[] = [$label, $timing['average_days'] ?? 'Not available', $timing['median_days'] ?? 'Not available', $timing['sample_count'] ?? 0];
        }
        $rows[] = ['Further-action rate', ($performance['further_action_rate'] ?? 0) . '%', '', ($performance['further_action_ticket_count'] ?? 0) . ' of ' . ($performance['resolved_ticket_count'] ?? 0)];
        $rows[] = ['Reopened by SDS', '', '', $performance['reopen_count'] ?? 0];
        foreach (['update' => 'Student update requests', 'escalation' => 'Student escalation requests'] as $key => $label) {
            $request = $performance['student_requests'][$key] ?? [];
            $rows[] = [$label, '', ($request['followed_by_sds_action'] ?? 0) . ' followed by SDS action', $request['count'] ?? 0];
        }
        $rows[] = ['Escalations overall (not attributed to handler)', '', '', $performance['escalation_count'] ?? 0];

        foreach ([
            'handler_rows' => ['Performance by Handler', 'Handler'],
            'office_rows' => ['Performance by Office', 'Office'],
        ] as $key => [$title, $nameHeading]) {
            $rows[] = [];
            $rows[] = [$title];
            $rows[] = [$nameHeading, 'Unit', 'Open Tickets', 'Oldest Open Age (Days)', 'Median Acknowledgment (Days)', 'Category', 'Median Handling Time (Days)', 'Resolved Tickets', 'Escalations'];
            foreach ($performance[$key] ?? [] as $staffRow) {
                if ($staffRow['handling_by_category'] === []) {
                    $rows[] = [
                        $staffRow['name'],
                        $key === 'handler_rows' ? $staffRow['unit'] : $staffRow['name'],
                        $staffRow['open_tickets'],
                        $staffRow['oldest_open_ticket_age_days'] ?? 'Not available',
                        $staffRow['median_acknowledgment_days'] ?? 'Not enough data',
                        '',
                        'Not enough data',
                        $staffRow['resolved_tickets'],
                        $staffRow['escalations'],
                    ];
                    continue;
                }

                foreach ($staffRow['handling_by_category'] as $category => $timing) {
                    $rows[] = [
                        $staffRow['name'],
                        $key === 'handler_rows' ? $staffRow['unit'] : $staffRow['name'],
                        $staffRow['open_tickets'],
                        $staffRow['oldest_open_ticket_age_days'] ?? 'Not available',
                        $staffRow['median_acknowledgment_days'] ?? 'Not enough data',
                        $category,
                        $timing['median_days'] ?? 'Not enough data',
                        $staffRow['resolved_tickets'],
                        $staffRow['escalations'],
                    ];
                }
            }
        }

        $rows[] = [];
        $rows[] = ['Escalation Frequency'];
        foreach ($this->reportData['escalation_frequency']['labels'] as $index => $label) {
            $rows[] = [$label, $this->reportData['escalation_frequency']['data'][$index] ?? 0];
        }

        $rows[] = [];
        $rows[] = ['Active Ticket Status Counts'];
        foreach ($this->reportData['status_distribution']['labels'] as $index => $label) {
            $rows[] = [$label, $this->reportData['status_distribution']['data'][$index] ?? 0];
        }

        $breakdowns = $this->reportData['breakdowns'] ?? [];

        foreach ([
            'colleges' => ['Tickets by College', 'College'],
            'programs' => ['Tickets by Program', 'Program'],
            'year_levels' => ['Tickets by Year Level', 'Year Level'],
            'origin' => ['Identified and Anonymous Origin', 'Origin'],
            'resolution_types' => ['How Tickets Were Resolved', 'Resolution'],
            'closure_reasons' => ['Why Tickets Were Closed', 'Reason'],
            'escalation_levels' => ['Escalation Level', 'Level'],
        ] as $key => [$title, $column]) {
            $rows[] = [];
            $rows[] = [$title];
            $rows[] = [$column, 'Count'];
            foreach ($breakdowns[$key] ?? [] as $label => $count) {
                $rows[] = [$label, $count];
            }
            if (in_array($key, ['colleges', 'programs', 'year_levels', 'origin'], true)) {
                $rows[] = ['Sensitive categories (not broken down)', $breakdowns['sensitive_not_broken_down'] ?? 0];
            }
        }

        $rows[] = [];
        $rows[] = ['Student Satisfaction'];
        $rows[] = ['Average rating', $breakdowns['satisfaction']['average'] ?? 'No ratings yet'];
        $rows[] = ['Tickets rated', $breakdowns['satisfaction']['count'] ?? 0];
        foreach ($breakdowns['satisfaction']['distribution'] ?? [] as $rating => $count) {
            $rows[] = [$rating . ' out of 5', $count];
        }

        if (! empty($this->reportData['filters'])) {
            $rows[] = [];
            $rows[] = ['Applied Filters'];
            foreach ($this->reportData['filters'] as $filter => $value) {
                if (! filled($value)) {
                    continue;
                }
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
