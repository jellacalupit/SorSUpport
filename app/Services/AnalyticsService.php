<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Ticket;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AnalyticsService
{
    public function getTotalComplaints(array $filters = []): int
    {
        return $this->filterComplaints(Complaint::query(), $filters)->count();
    }

    public function getTotalTickets(array $filters = []): int
    {
        return $this->filterTickets(Ticket::query(), $filters)->count();
    }

    public function getTotalTicketsByStatus(array $filters = []): array
    {
        $statuses = [
            Ticket::STATUS_PENDING,
            Ticket::STATUS_ASSIGNED,
            Ticket::STATUS_IN_PROGRESS,
            Ticket::STATUS_RESOLVED,
            Ticket::STATUS_CLOSED,
        ];

        $counts = $this->filterTickets(Ticket::query(), $filters)
            ->get(['status'])
            ->groupBy('status')
            ->map(fn ($items) => $items->count())
            ->toArray();

        return collect($statuses)->mapWithKeys(fn ($status) => [$status => $counts[$status] ?? 0])->toArray();
    }

    public function getComplaintVolumeDaily(array $filters = []): array
    {
        return $this->getComplaintVolume($filters, 'day');
    }

    public function getComplaintVolumeWeekly(array $filters = []): array
    {
        return $this->getComplaintVolume($filters, 'week');
    }

    public function getComplaintVolumeMonthly(array $filters = []): array
    {
        return $this->getComplaintVolume($filters, 'month');
    }

    public function getComplaintDistributionByCategory(array $filters = []): array
    {
        $categories = ComplaintCategory::query()
            ->withCount(['complaints as complaint_count' => fn ($query) => $this->applyComplaintFilters($query, $filters)])
            ->orderBy('name')
            ->get();

        return [
            'labels' => $categories->pluck('name')->toArray(),
            'data' => $categories->pluck('complaint_count')->toArray(),
        ];
    }

    public function getResolutionRate(array $filters = []): array
    {
        $tickets = $this->filterTickets(Ticket::query(), $filters);
        $total = $tickets->count();
        $resolved = $tickets->whereIn('status', [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED])->count();

        return [
            'rate' => $total ? round($resolved / $total * 100, 2) : 0,
            'resolved' => $resolved,
            'total' => $total,
            'chart' => [
                'labels' => ['Resolved', 'Remaining'],
                'data' => [$resolved, max($total - $resolved, 0)],
            ],
        ];
    }

    public function getAverageResolutionTimePerCategory(array $filters = []): array
    {
        $tickets = $this->filterTickets(Ticket::query(), $filters)
            ->whereNotNull('resolved_at')
            ->with('complaint.category')
            ->get();

        $grouped = $tickets->filter(fn ($ticket) => $ticket->complaint && $ticket->complaint->category)
            ->groupBy(fn ($ticket) => $ticket->complaint->category->name);

        $labels = [];
        $data = [];

        foreach ($grouped as $categoryName => $group) {
            $averageHours = $group->avg(function ($ticket) {
                return $ticket->resolved_at->floatDiffInHours($ticket->complaint->created_at);
            });

            $labels[] = $categoryName;
            $data[] = round($averageHours, 1);
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    public function getEscalationFrequency(array $filters = []): array
    {
        [$start, $end] = $this->buildPeriodRange($filters, 'month');

        $events = AuditLog::query()
            ->where('action', 'ticket_escalated');

        if (isset($filters['start_date']) && filled($filters['start_date'])) {
            $events->where('created_at', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }

        if (isset($filters['end_date']) && filled($filters['end_date'])) {
            $events->where('created_at', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        $counts = $events->get(['created_at'])
            ->groupBy(fn ($event) => $event->created_at->startOfMonth()->format('Y-m'))
            ->map->count();

        $labels = [];
        $data = [];

        foreach (CarbonPeriod::create($start, CarbonInterval::month(), $end) as $period) {
            $key = $period->format('Y-m');
            $labels[] = $key;
            $data[] = $counts[$key] ?? 0;
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    public function getActiveTicketStatuses(array $filters = []): array
    {
        $statusCounts = $this->getTotalTicketsByStatus($filters);

        return [
            'labels' => array_map(fn ($status) => ucfirst(str_replace('_', ' ', $status)), array_keys($statusCounts)),
            'data' => array_values($statusCounts),
        ];
    }

    public function getReportData(array $filters = []): array
    {
        return [
            'summary' => [
                'total_complaints' => $this->getTotalComplaints($filters),
                'total_tickets' => $this->getTotalTickets($filters),
                'resolution_rate' => $this->getResolutionRate($filters)['rate'],
                'escalated_tickets' => array_sum($this->getEscalationFrequency($filters)['data']),
            ],
            'category_breakdown' => $this->getComplaintDistributionByCategory($filters),
            'resolution_chart' => $this->getResolutionRate($filters)['chart'],
            'average_resolution_time' => $this->getAverageResolutionTimePerCategory($filters),
            'escalation_frequency' => $this->getEscalationFrequency($filters),
            'status_distribution' => $this->getActiveTicketStatuses($filters),
            'filters' => $filters,
        ];
    }

    protected function getComplaintVolume(array $filters, string $range): array
    {
        [$start, $end] = $this->buildPeriodRange($filters, $range);

        $complaints = $this->filterComplaints(Complaint::query(), $filters)
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at']);

        $counts = $complaints->groupBy(fn ($complaint) => $this->formatPeriod($complaint->created_at, $range))
            ->map->count();

        $labels = [];
        $data = [];

        foreach (CarbonPeriod::create($start, $this->periodInterval($range), $end) as $period) {
            $label = $this->formatPeriod($period, $range);
            $labels[] = $label;
            $data[] = $counts[$label] ?? 0;
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    protected function buildPeriodRange(array $filters, string $range): array
    {
        $start = isset($filters['start_date']) && filled($filters['start_date'])
            ? Carbon::parse($filters['start_date'])->startOfDay()
            : null;

        $end = isset($filters['end_date']) && filled($filters['end_date'])
            ? Carbon::parse($filters['end_date'])->endOfDay()
            : null;

        if (! $start || ! $end) {
            if ($range === 'week') {
                $end = $end ?: Carbon::now()->endOfWeek();
                $start = $start ?: Carbon::now()->subWeeks(11)->startOfWeek();
            } elseif ($range === 'month') {
                $end = $end ?: Carbon::now()->endOfMonth();
                $start = $start ?: Carbon::now()->subMonths(11)->startOfMonth();
            } else {
                $end = $end ?: Carbon::now()->endOfDay();
                $start = $start ?: Carbon::now()->subDays(13)->startOfDay();
            }
        }

        if ($start && $end && $start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }

    protected function periodInterval(string $range): CarbonInterval
    {
        return match ($range) {
            'week' => CarbonInterval::week(),
            'month' => CarbonInterval::month(),
            default => CarbonInterval::day(),
        };
    }

    protected function formatPeriod(Carbon $date, string $range): string
    {
        return match ($range) {
            'week' => $date->startOfWeek()->format('Y-m-d'),
            'month' => $date->format('Y-m'),
            default => $date->format('Y-m-d'),
        };
    }

    protected function filterComplaints(Builder $query, array $filters): Builder
    {
        return $this->applyComplaintFilters($query, $filters);
    }

    protected function filterTickets(Builder $query, array $filters): Builder
    {
        return $query->whereHas('complaint', fn ($complaint) => $this->applyComplaintFilters($complaint, $filters));
    }

    protected function applyComplaintFilters(Builder $query, array $filters): Builder
    {
        if (isset($filters['category_id']) && filled($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['start_date']) && filled($filters['start_date'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }

        if (isset($filters['end_date']) && filled($filters['end_date'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        return $query;
    }
}
