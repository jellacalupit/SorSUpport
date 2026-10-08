<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Ticket;
use App\Models\User;
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
        $statuses = array_keys(Ticket::STATUS_LABELS);

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
        $tickets = $this->filterTickets(Ticket::query(), $filters)
            ->where('classification', Ticket::CLASSIFICATION_NEEDS_RESOLUTION);
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
            ->where('classification', Ticket::CLASSIFICATION_NEEDS_RESOLUTION)
            ->whereNotNull('resolved_at')
            ->with('complaint.category')
            ->get();

        $grouped = $tickets->filter(fn ($ticket) => $ticket->complaint && $ticket->complaint->category)
            ->groupBy(fn ($ticket) => $ticket->complaint->category->name);

        $labels = [];
        $data = [];

        foreach ($grouped as $categoryName => $group) {
            // Days from submission to resolution, counted forward so it is never negative.
            $averageDays = $group->avg(fn ($ticket) => abs($ticket->complaint->created_at->floatDiffInDays($ticket->resolved_at)));

            $labels[] = $categoryName;
            $data[] = round($averageDays, 1);
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
            'labels' => array_map(fn ($status) => Ticket::STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status)), array_keys($statusCounts)),
            'data' => array_values($statusCounts),
        ];
    }

    public function getDashboardData(array $filters = []): array
    {
        $tickets = $this->filterTickets(Ticket::query(), $filters)
            ->with(['complaint.category', 'assignee.recipient', 'currentHandler.recipient', 'auditLogs'])
            ->get();
        $categories = ComplaintCategory::query()->orderBy('name')->get();
        $categoryNames = $categories->pluck('name')->values();
        $resolvedStatuses = [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED];
        $resolutionTickets = $tickets->where('classification', Ticket::CLASSIFICATION_NEEDS_RESOLUTION);
        $resolved = $resolutionTickets->whereIn('status', $resolvedStatuses);
        $resolutionHours = $resolved->filter(fn ($ticket) => $ticket->resolved_at && $ticket->complaint?->created_at)
            ->map(fn ($ticket) => $ticket->complaint->created_at->floatDiffInHours($ticket->resolved_at));
        $escalationEvents = $tickets->flatMap->auditLogs->where('action', 'ticket_escalated');
        $escalationsPerTicket = $escalationEvents->groupBy('ticket_id')->map->count();
        $escalatedTicketIds = $escalationsPerTicket->keys();
        $escalatedOnce = $escalationsPerTicket->filter(fn ($count) => $count === 1)->count();
        $escalatedRepeatedly = $escalationsPerTicket->filter(fn ($count) => $count > 1)->count();
        $neverEscalated = max($resolutionTickets->count() - $resolutionTickets->whereIn('id', $escalatedTicketIds)->count(), 0);
        $categoryCounts = $tickets->groupBy(fn ($ticket) => $ticket->complaint?->category?->name ?? 'Uncategorized')->map->count();
        $statusCounts = $tickets->groupBy('status')->map->count();
        $period = $this->volumePeriod($filters);
        [$start, $end] = $this->buildPeriodRange($filters, $period === 'monthly' ? 'month' : ($period === 'weekly' ? 'week' : 'day'));
        $submittedSeries = $this->seriesForTickets($tickets, 'complaint.created_at', $period, $start, $end);
        $resolvedSeries = $this->seriesForTickets($resolved, 'resolved_at', $period, $start, $end);
        $closedSeries = $this->seriesForTickets($tickets->where('status', Ticket::STATUS_CLOSED), 'resolved_at', $period, $start, $end);
        $recipientRows = $tickets
            ->map(function ($ticket) {
                $handler = $ticket->assignee ?? $ticket->currentHandler;
                if (! $handler) {
                    return null;
                }
                if (! in_array($handler->role, [User::ROLE_RECIPIENT, User::ROLE_SDS_ADMIN], true)) {
                    return null;
                }

                return ['ticket' => $ticket, 'handler' => $handler];
            })
            ->filter()
            ->groupBy(fn ($row) => $row['handler']->id)
            ->map(function ($group) use ($resolvedStatuses, $escalatedTicketIds) {
                $user = $group->first()['handler'];
                $ticketGroup = $group->pluck('ticket');
                $resolved = $ticketGroup->where('classification', Ticket::CLASSIFICATION_NEEDS_RESOLUTION)->whereIn('status', $resolvedStatuses);
                $durations = $resolved
                    ->filter(fn ($ticket) => $ticket->resolved_at && $ticket->complaint?->created_at)
                    ->map(fn ($ticket) => $ticket->complaint->created_at->floatDiffInHours($ticket->resolved_at));

                return [
                    'user_id' => $user->id,
                    'name' => $user->table_name ?: 'Unassigned',
                    'unit' => $user->recipient?->unit ?: 'Unassigned',
                    'assigned' => $ticketGroup->count(),
                    'resolved' => $resolved->count(),
                    'escalated' => $ticketGroup->whereIn('id', $escalatedTicketIds)->count(),
                    'average' => $durations->isNotEmpty() ? round($durations->avg() / 24, 1) : 0,
                ];
            })
            ->sortByDesc('assigned')
            ->values();
        $classificationCounts = $tickets->groupBy(fn ($ticket) => $ticket->classification ?: 'unclassified')->map->count();
        $identified = $tickets->filter(fn ($ticket) => ! $ticket->complaint?->is_anonymous)->count();
        $subjects = $tickets->groupBy(fn ($ticket) => $ticket->complaint?->public_subject ?? 'Untitled')->map->count()->sortDesc()->take(10);
        $openStatuses = [Ticket::STATUS_SUBMITTED, Ticket::STATUS_NEEDS_CLARIFICATION, Ticket::STATUS_ASSIGNED, Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_ESCALATED, Ticket::STATUS_REFERRED];
        $openTickets = $tickets->whereIn('status', $openStatuses);
        $unitRows = $tickets
            ->groupBy(function ($ticket) {
                $handler = $ticket->assignee ?? $ticket->currentHandler;
                return $handler?->recipient?->unit ?: 'Unassigned';
            })
            ->map(function ($group, $unit) use ($resolvedStatuses, $openStatuses) {
                $resolvedGroup = $group->where('classification', Ticket::CLASSIFICATION_NEEDS_RESOLUTION)->whereIn('status', $resolvedStatuses);
                $durations = $resolvedGroup
                    ->filter(fn ($ticket) => $ticket->resolved_at && $ticket->complaint?->created_at)
                    ->map(fn ($ticket) => $ticket->complaint->created_at->floatDiffInHours($ticket->resolved_at));

                return [
                    'name' => $unit,
                    'total' => $group->count(),
                    'open' => $group->whereIn('status', $openStatuses)->count(),
                    'resolved' => $resolvedGroup->count(),
                    'average' => $durations->isNotEmpty() ? round($durations->avg() / 24, 1) : 0,
                ];
            })
            ->sortByDesc('total')
            ->values();
        $oldestOpenTickets = $openTickets
            ->sortBy(fn ($ticket) => $ticket->complaint?->created_at?->timestamp ?? PHP_INT_MAX)
            ->take(8)
            ->values();

        return [
            'tickets' => $tickets, 'categories' => $categories, 'categoryNames' => $categoryNames,
            'total' => $tickets->count(), 'resolutionTickets' => $resolutionTickets->count(), 'resolved' => $resolved->count(), 'resolutionRate' => $resolutionTickets->count() ? round($resolved->count() / $resolutionTickets->count() * 100) : 0,
            'averageHours' => $resolutionHours->isNotEmpty() ? round($resolutionHours->avg(), 1) : 0, 'fastestHours' => $resolutionHours->min() ?? 0, 'longestHours' => $resolutionHours->max() ?? 0,
            'volume' => ['labels' => $submittedSeries['labels'], 'submitted' => $submittedSeries['data'], 'resolved' => $resolvedSeries['data'], 'closed' => $closedSeries['data'], 'period' => $period],
            'categoryCounts' => $categoryNames->mapWithKeys(fn ($name) => [$name => $categoryCounts[$name] ?? 0])->toArray(),
            'statusCounts' => collect(Ticket::STATUS_LABELS)->mapWithKeys(fn ($label, $key) => [$label => ($statusCounts[$key] ?? 0)])->toArray(),
            'resolutionByCategory' => $this->resolutionByCategory($tickets, $categoryNames),
            'escalations' => ['total' => $escalationEvents->count(), 'tickets' => $escalatedTicketIds->count(), 'once' => $escalatedOnce, 'repeated' => $escalatedRepeatedly, 'never' => $neverEscalated, 'rate' => $tickets->count() ? round($escalatedTicketIds->count() / $tickets->count() * 100) : 0, 'averageLevel' => $escalationsPerTicket->avg() ?: 0, 'byCategory' => $escalationEvents->groupBy(fn ($log) => $tickets->firstWhere('id', $log->ticket_id)?->complaint?->category?->name ?? 'Uncategorized')->map->count()->toArray()],
            'recipients' => $recipientRows, 'classification' => ['Needs Resolution' => $classificationCounts['needs_resolution'] ?? 0, 'Informational' => $classificationCounts['informational'] ?? 0, 'Invalid' => $classificationCounts['invalid'] ?? 0, 'Unclassified' => $classificationCounts['unclassified'] ?? 0], 'submission' => ['Identified' => $identified, 'Anonymous' => $tickets->count() - $identified],
            'subjects' => $subjects, 'insights' => $this->buildInsights($categoryCounts, $resolutionHours, $escalationEvents, $tickets, $identified),
            'openCount' => $openTickets->count(), 'units' => $unitRows,
            'oldestOpenTickets' => $oldestOpenTickets,
            'waiting' => \App\Support\TicketProgress::waitingTimes($tickets),
            'breakdowns' => $this->getBreakdowns($filters, $tickets),
        ];
    }

    /**
     * Counts by where tickets come from and how they end: the student's college and program,
     * how tickets were resolved and closed, how far they were escalated, and how students rated
     * the handling. Students who hid their identity are counted as "Not disclosed".
     */
    public function getBreakdowns(array $filters = [], ?Collection $tickets = null): array
    {
        $tickets ??= $this->filterTickets(Ticket::query(), $filters)->with(['complaint.student', 'auditLogs'])->get();
        $tickets->loadMissing(['complaint.student', 'auditLogs']);

        $origin = fn (string $field) => $tickets
            ->groupBy(fn ($ticket) => $ticket->complaint?->is_anonymous
                ? 'Not disclosed'
                : ($ticket->complaint?->student?->{$field} ?: 'Not specified'))
            ->map->count()
            ->sortDesc()
            ->toArray();

        $labelled = fn (string $field, array $labels) => collect($labels)
            ->mapWithKeys(fn ($label, $key) => [$label => $tickets->where($field, $key)->count()])
            ->filter()
            ->sortDesc()
            ->toArray();

        $escalations = $tickets->mapWithKeys(fn ($ticket) => [
            $ticket->id => $ticket->auditLogs->where('action', 'ticket_escalated')->count(),
        ]);
        $handled = $tickets->where('classification', Ticket::CLASSIFICATION_NEEDS_RESOLUTION)->pluck('id');
        $levels = $escalations->only($handled->all());

        $rated = $tickets->whereNotNull('satisfaction_rating');

        return [
            'colleges' => $origin('college'),
            'programs' => $origin('program'),
            'resolution_types' => $labelled('resolution_type', Ticket::RESOLUTION_LABELS),
            'closure_reasons' => $labelled('closure_type', Ticket::CLOSURE_LABELS),
            'escalation_levels' => [
                'Not escalated' => $levels->filter(fn ($count) => $count === 0)->count(),
                'Escalated once' => $levels->filter(fn ($count) => $count === 1)->count(),
                'Escalated twice' => $levels->filter(fn ($count) => $count === 2)->count(),
                'Escalated three or more times' => $levels->filter(fn ($count) => $count >= 3)->count(),
            ],
            'satisfaction' => [
                'count' => $rated->count(),
                'average' => $rated->isNotEmpty() ? round($rated->avg('satisfaction_rating'), 1) : null,
                'distribution' => collect(range(5, 1))
                    ->mapWithKeys(fn ($rating) => [$rating => $rated->where('satisfaction_rating', $rating)->count()])
                    ->toArray(),
            ],
        ];
    }

    protected function seriesForTickets(Collection $tickets, string $field, string $period, Carbon $start, Carbon $end): array
    {
        $format = $period === 'monthly' ? 'Y-m' : ($period === 'weekly' ? 'o-W' : 'Y-m-d');
        $values = $tickets->map(fn ($ticket) => data_get($ticket, $field))->filter()->groupBy(fn ($date) => $date->format($format))->map->count();
        $labels = [];
        $data = [];
        $cursor = $start->copy();
        $step = $period === 'monthly' ? 'addMonth' : ($period === 'weekly' ? 'addWeek' : 'addDay');
        while ($cursor->lte($end)) { $labels[] = $cursor->format($format); $data[] = $values[$cursor->format($format)] ?? 0; $cursor->{$step}(); }
        return ['labels' => $labels, 'data' => $data];
    }

    protected function volumePeriod(array $filters): string
    {
        if (filled($filters['start_date'] ?? null) && filled($filters['end_date'] ?? null)) {
            $days = Carbon::parse($filters['start_date'])->diffInDays(Carbon::parse($filters['end_date'])) + 1;

            return $days <= 31 ? 'daily' : ($days <= 180 ? 'weekly' : 'monthly');
        }

        return match ($filters['period'] ?? 'all_time') {
            'this_month', 'last_month' => 'daily',
            'last_3_months' => 'weekly',
            default => 'monthly',
        };
    }

    protected function resolutionByCategory(Collection $tickets, Collection $categoryNames): array
    {
        return $categoryNames->mapWithKeys(function ($name) use ($tickets) {
            $values = $tickets
                ->filter(fn ($ticket) => $ticket->classification === Ticket::CLASSIFICATION_NEEDS_RESOLUTION && ($ticket->complaint?->category?->name ?? 'Uncategorized') === $name && $ticket->resolved_at && $ticket->complaint?->created_at)
                ->map(fn ($ticket) => $ticket->complaint->created_at->floatDiffInHours($ticket->resolved_at) / 24);

            return [$name => $values->isNotEmpty() ? round($values->avg(), 1) : 0];
        })->toArray();
    }

    protected function buildInsights(Collection $categoryCounts, Collection $durations, Collection $escalations, Collection $tickets, int $identified): array
    {
        $topCategory = $categoryCounts->sortDesc()->keys()->first() ?? 'No category';
        $anonymousRate = $tickets->count() ? round(($tickets->count() - $identified) / $tickets->count() * 100) : 0;
        return [$topCategory . ' generated the highest number of tickets.', $durations->isNotEmpty() ? 'Resolved tickets averaged ' . round($durations->avg() / 24, 1) . ' days.' : 'Resolution timing will appear after tickets are resolved.', $escalations->isNotEmpty() ? $escalations->pluck('ticket_id')->unique()->count() . ' tickets required escalation.' : 'No escalated tickets in this period.', 'Anonymous submissions account for ' . $anonymousRate . '% of total tickets.'];
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
            'breakdowns' => $this->getBreakdowns($filters),
            'waiting' => \App\Support\TicketProgress::waitingTimes(
                $this->filterTickets(Ticket::query(), $filters)->with(['complaint', 'assignee.recipient', 'currentHandler.recipient', 'auditLogs'])->get()
            ),
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
        $query->whereHas('complaint', fn ($complaint) => $this->applyComplaintFilters($complaint, $filters));

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        if (filled($filters['recipient_id'] ?? null)) {
            $query->where(function ($tickets) use ($filters) {
                $tickets->where('assigned_to', $filters['recipient_id'])->orWhere('current_handler_id', $filters['recipient_id']);
            });
        }

        if (filled($filters['classification'] ?? null)) {
            $query->where('classification', $filters['classification']);
        }

        if (filled($filters['unit'] ?? null)) {
            $query->where(function ($tickets) use ($filters) {
                $tickets->whereHas('assignee.recipient', fn ($recipient) => $recipient->where('unit', $filters['unit']))
                    ->orWhereHas('currentHandler.recipient', fn ($recipient) => $recipient->where('unit', $filters['unit']));
            });
        }

        return $query;
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
