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
        $rows[] = ['Average Resolution Time Per Category (Days)'];
        foreach ($this->reportData['average_resolution_time']['labels'] as $index => $label) {
            $rows[] = [$label, $this->reportData['average_resolution_time']['data'][$index] ?? 0];
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
