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
        $tickets = $this->filterTickets(Ticket::query(), $filters)
            ->get(['status', 'classification', 'forwarded_to', 'forwarded_at']);

        return $this->statusCountsForTickets($tickets);
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
        return $this->resolutionRateMetrics(
            $this->filterTickets(Ticket::query(), $filters)->get()
        );
    }

    public function getAverageResolutionTimePerCategory(array $filters = []): array
    {
        $tickets = $this->filterTickets(Ticket::query(), $filters)
            ->with(['complaint.category', 'auditLogs'])
            ->get();
        $times = $this->resolutionTimeStats($tickets);
        $categories = $times['categories'];

        return [
            'labels' => array_keys($categories),
            'data' => array_values(array_map(fn ($category) => $category['average_days'] ?? 0, $categories)),
            'average_days' => $times['average_days'],
            'median_days' => $times['median_days'],
            'ticket_count' => $times['ticket_count'],
            'fallback_count' => $times['fallback_count'],
            'categories' => $categories,
            'committee_outcomes' => $times['committee_outcomes'],
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
            'labels' => array_values(Ticket::DISPLAY_STATUS_LABELS),
            'data' => array_values($statusCounts),
        ];
    }

    public function getDashboardData(array $filters = []): array
    {
        $tickets = $this->filterTickets(Ticket::query(), $filters)
            ->with(['complaint.category', 'assignee.recipient', 'currentHandler.recipient', 'auditLogs.performer', 'thread.messages.sender'])
            ->get();
        $categories = ComplaintCategory::query()->orderBy('name')->get();
        $categoryNames = $categories->pluck('name')->values();
        $finishedStatuses = [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED];
        $resolutionTickets = $tickets->where('classification', Ticket::CLASSIFICATION_NEEDS_RESOLUTION);
        $resolutionRate = $this->resolutionRateMetrics($tickets);
        $resolved = $resolutionTickets->whereIn('status', $finishedStatuses);
        $resolutionTimes = $this->resolutionTimeStats($tickets);
        $performanceMetrics = $this->performanceMetricsForTickets($tickets, $resolutionTimes);
        $timeEntries = $resolutionTimes['entries']->keyBy('ticket_id');
        $resolutionDays = $resolutionTimes['entries']->pluck('days')->filter(fn ($days) => $days !== null);
        $escalationEvents = $tickets->flatMap->auditLogs->where('action', 'ticket_escalated');
        $escalationsPerTicket = $escalationEvents->groupBy('ticket_id')->map->count();
        $escalatedTicketIds = $escalationsPerTicket->keys();
        $escalatedOnce = $escalationsPerTicket->filter(fn ($count) => $count === 1)->count();
        $escalatedRepeatedly = $escalationsPerTicket->filter(fn ($count) => $count > 1)->count();
        $neverEscalated = max($resolutionTickets->count() - $resolutionTickets->whereIn('id', $escalatedTicketIds)->count(), 0);
        $categoryCounts = $tickets->groupBy(fn ($ticket) => $ticket->complaint?->category?->name ?? 'Uncategorized')->map->count();
        $statusCounts = $this->statusCountsForTickets($tickets);
        $statusClassificationCounts = [];
        foreach (Ticket::DISPLAY_STATUS_LABELS as $status => $label) {
            $statusClassificationCounts[$status . ':' . Ticket::CLASSIFICATION_NEEDS_RESOLUTION] = $tickets
                ->where('status', $status)
                ->where('classification', Ticket::CLASSIFICATION_NEEDS_RESOLUTION)
                ->count();
            if (in_array($status, Ticket::UNCLASSIFIED_DISPLAY_STATUSES, true)) {
                $statusClassificationCounts[$status . ':unclassified'] = $tickets
                    ->where('status', $status)
                    ->filter(fn ($ticket) => blank($ticket->classification))
                    ->count();
            }
        }
        $monthlyTicketVolumes = $this->monthlyTicketVolumes($tickets, $filters);
        $categoryVolumes = $this->categoryVolumes($tickets, $categories);
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
            ->map(function ($group) use ($escalatedTicketIds, $timeEntries, $finishedStatuses) {
                $user = $group->first()['handler'];
                $ticketGroup = $group->pluck('ticket');
                $resolved = $ticketGroup
                    ->where('classification', Ticket::CLASSIFICATION_NEEDS_RESOLUTION)
                    ->whereIn('status', $finishedStatuses);
                $durations = $resolved
                    ->filter(fn ($ticket) => isset($timeEntries[$ticket->id]) && ! $timeEntries[$ticket->id]['committee'] && $timeEntries[$ticket->id]['days'] !== null)
                    ->map(fn ($ticket) => $timeEntries[$ticket->id]['days']);

                return [
                    'user_id' => $user->id,
                    'name' => $user->table_name ?: 'Unassigned',
                    'unit' => $user->recipient?->unit ?: 'Unassigned',
                    'assigned' => $ticketGroup->count(),
                    'resolved' => $resolved->count(),
                    'escalated' => $ticketGroup->whereIn('id', $escalatedTicketIds)->count(),
                    'average' => $durations->isNotEmpty() ? round($durations->avg(), 1) : 0,
                ];
            })
            ->sortByDesc('assigned')
            ->values();
        $classificationCounts = $tickets->groupBy(fn ($ticket) => $ticket->classification ?: 'unclassified')->map->count();
        $identified = $tickets->filter(fn ($ticket) => ! $ticket->complaint?->is_anonymous)->count();
        $subjects = $tickets->groupBy(fn ($ticket) => $ticket->complaint?->public_subject ?? 'Untitled')->map->count()->sortDesc()->take(10);
        $openStatuses = [Ticket::STATUS_SUBMITTED, Ticket::STATUS_NEEDS_CLARIFICATION, Ticket::STATUS_ASSIGNED, Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_ESCALATED, Ticket::STATUS_REFERRED];
        $openTickets = $tickets
            ->whereIn('status', $openStatuses)
            ->filter(fn ($ticket) => in_array($ticket->classification, [
                Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
                null,
            ], true));
        $unitRows = $tickets
            ->groupBy(function ($ticket) {
                $handler = $ticket->assignee ?? $ticket->currentHandler;
                return $handler?->recipient?->unit ?: 'Unassigned';
            })
            ->map(function ($group, $unit) use ($openStatuses, $timeEntries, $finishedStatuses) {
                $resolvedGroup = $group
                    ->where('classification', Ticket::CLASSIFICATION_NEEDS_RESOLUTION)
                    ->whereIn('status', $finishedStatuses);
                $durations = $resolvedGroup
                    ->filter(fn ($ticket) => isset($timeEntries[$ticket->id]) && ! $timeEntries[$ticket->id]['committee'] && $timeEntries[$ticket->id]['days'] !== null)
                    ->map(fn ($ticket) => $timeEntries[$ticket->id]['days']);

                return [
                    'name' => $unit,
                    'total' => $group->count(),
                    'open' => $group->whereIn('status', $openStatuses)->count(),
                    'resolved' => $resolvedGroup->count(),
                    'average' => $durations->isNotEmpty() ? round($durations->avg(), 1) : 0,
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
            'total' => $tickets->count(), 'resolutionTickets' => $resolutionRate['total'], 'needsResolutionTickets' => $resolutionTickets->count(), 'resolved' => $resolutionRate['resolved'], 'resolutionRate' => $resolutionRate['rate'], 'resolutionMetrics' => $resolutionRate,
            'resolutionTime' => collect($resolutionTimes)->except('entries')->all(),
            'performanceMetrics' => $performanceMetrics,
            'monthlyTicketVolumes' => $monthlyTicketVolumes,
            'categoryVolumes' => $categoryVolumes,
            'statusClassificationCounts' => $statusClassificationCounts,
            'averageHours' => round($resolutionTimes['average_days'] * 24, 1),
            'fastestHours' => round(($resolutionDays->min() ?? 0) * 24, 1), 'longestHours' => round(($resolutionDays->max() ?? 0) * 24, 1),
            'volume' => ['labels' => $submittedSeries['labels'], 'submitted' => $submittedSeries['data'], 'resolved' => $resolvedSeries['data'], 'closed' => $closedSeries['data'], 'period' => $period],
            'categoryCounts' => $categoryNames->mapWithKeys(fn ($name) => [$name => $categoryCounts[$name] ?? 0])->toArray(),
            'statusCounts' => $this->labelledStatusCounts($statusCounts),
            'resolutionByCategory' => $categoryNames
                ->mapWithKeys(fn ($name) => [$name => $resolutionTimes['categories'][$name]['average_days'] ?? 0])
                ->when(isset($resolutionTimes['categories']['Uncategorized']), fn ($values) => $values->put('Uncategorized', $resolutionTimes['categories']['Uncategorized']['average_days'] ?? 0))
                ->all(),
            'escalations' => ['total' => $escalationEvents->count(), 'tickets' => $escalatedTicketIds->count(), 'once' => $escalatedOnce, 'repeated' => $escalatedRepeatedly, 'never' => $neverEscalated, 'rate' => $tickets->count() ? round($escalatedTicketIds->count() / $tickets->count() * 100) : 0, 'averageLevel' => $escalationsPerTicket->avg() ?: 0, 'byCategory' => $escalationEvents->groupBy(fn ($log) => $tickets->firstWhere('id', $log->ticket_id)?->complaint?->category?->name ?? 'Uncategorized')->map->count()->toArray()],
            'recipients' => $recipientRows, 'classification' => ['Needs Resolution' => $classificationCounts['needs_resolution'] ?? 0, 'Informational' => $classificationCounts['informational'] ?? 0, 'Invalid' => $classificationCounts['invalid'] ?? 0, 'Unclassified' => $classificationCounts['unclassified'] ?? 0], 'submission' => ['Identified' => $identified, 'Anonymous' => $tickets->count() - $identified],
            'subjects' => $subjects, 'insights' => $this->buildInsights($categoryCounts, $resolutionDays, $escalationEvents, $tickets, $identified),
            'openCount' => $openTickets->count(), 'units' => $unitRows,
            'oldestOpenTickets' => $oldestOpenTickets,
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
        $tickets->loadMissing(['complaint.category', 'complaint.student', 'auditLogs']);

        $studentAttributeTickets = $tickets
            ->reject(fn ($ticket) => $ticket->complaint?->isSensitive())
            ->values();
        $sensitiveNotBrokenDown = $tickets->count() - $studentAttributeTickets->count();
        $origin = fn (string $field) => $studentAttributeTickets
            ->groupBy(fn ($ticket) => $ticket->complaint?->is_anonymous
                ? 'Not disclosed'
                : ($ticket->complaint?->student?->{$field} ?: 'Not specified'))
            ->map->count()
            ->sortDesc()
            ->toArray();
        $identityOrigin = $studentAttributeTickets
            ->groupBy(fn ($ticket) => $ticket->complaint?->is_anonymous ? 'Anonymous' : 'Identified')
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
            'year_levels' => $origin('year_level'),
            'blocks' => $origin('block'),
            'origin' => $identityOrigin,
            'sensitive_not_broken_down' => $sensitiveNotBrokenDown,
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

    protected function statusCountsForTickets(Collection $tickets): array
    {
        $counts = $tickets
            ->filter(fn ($ticket) => $ticket->displayStatus() !== null)
            ->groupBy('status')
            ->map
            ->count();

        return collect(array_keys(Ticket::DISPLAY_STATUS_LABELS))
            ->mapWithKeys(fn ($status) => [$status => $counts[$status] ?? 0])
            ->all();
    }

    protected function monthlyTicketVolumes(Collection $tickets, array $filters): array
    {
        $timezone = config('app.timezone');
        $endMonth = filled($filters['end_date'] ?? null)
            ? Carbon::parse($filters['end_date'], $timezone)->startOfMonth()
            : Carbon::now($timezone)->startOfMonth();
        $months = collect(range(0, 11))
            ->map(fn ($offset) => $endMonth->copy()->subMonths(11 - $offset)->startOfMonth());
        $values = $months->mapWithKeys(fn (Carbon $month) => [
            $month->format('Y-m') => ['submitted' => 0, 'resolved' => 0, 'informational' => 0, 'invalid' => 0],
        ]);

        foreach ($tickets as $ticket) {
            $submittedAt = $ticket->complaint?->created_at;
            if ($submittedAt) {
                $month = $submittedAt->copy()->setTimezone($timezone)->format('Y-m');
                if ($values->has($month)) {
                    $monthCounts = $values->get($month);
                    if ($ticket->classification === Ticket::CLASSIFICATION_NEEDS_RESOLUTION) {
                        $monthCounts['submitted']++;
                    } elseif ($ticket->classification === Ticket::CLASSIFICATION_INFORMATIONAL) {
                        $monthCounts['informational']++;
                    } elseif ($ticket->classification === Ticket::CLASSIFICATION_INVALID) {
                        $monthCounts['invalid']++;
                    }
                    $values->put($month, $monthCounts);
                }
            }

            if ($this->isRecordedResolution($ticket) && $ticket->resolved_at) {
                $month = $ticket->resolved_at->copy()->setTimezone($timezone)->format('Y-m');
                if ($values->has($month)) {
                    $monthCounts = $values->get($month);
                    $monthCounts['resolved']++;
                    $values->put($month, $monthCounts);
                }
            }
        }

        return [
            'labels' => $months->map(fn (Carbon $month) => $month->format('M y'))->all(),
            'keys' => $months->map(fn (Carbon $month) => $month->format('Y-m'))->all(),
            'values' => $values->values()->all(),
        ];
    }

    protected function categoryVolumes(Collection $tickets, Collection $categories): array
    {
        $rows = $tickets
            ->groupBy(fn ($ticket) => $ticket->complaint?->category?->id ?? 'uncategorized')
            ->map(function (Collection $group, $categoryId) {
                $counts = [
                    'submitted' => 0,
                    'resolved' => 0,
                    'informational' => 0,
                    'invalid' => 0,
                    'unclassified' => 0,
                ];

                foreach ($group as $ticket) {
                    if ($ticket->classification === Ticket::CLASSIFICATION_NEEDS_RESOLUTION) {
                        $counts[$this->isRecordedResolution($ticket) ? 'resolved' : 'submitted']++;
                    } elseif ($ticket->classification === Ticket::CLASSIFICATION_INFORMATIONAL) {
                        $counts['informational']++;
                    } elseif ($ticket->classification === Ticket::CLASSIFICATION_INVALID) {
                        $counts['invalid']++;
                    } else {
                        $counts['unclassified']++;
                    }
                }

                return [
                    'id' => is_numeric($categoryId) ? (int) $categoryId : null,
                    'name' => $group->first()?->complaint?->category?->name ?? 'Uncategorized',
                    'segments' => $counts,
                    'total' => array_sum($counts),
                ];
            })
            ->sort(fn (array $left, array $right) => $right['total'] <=> $left['total'] ?: strcmp($left['name'], $right['name']))
            ->values();

        return [
            'rows' => $rows->all(),
            'empty_category_count' => max(0, $categories->count() - $rows->whereNotNull('id')->count()),
        ];
    }

    protected function labelledStatusCounts(array $statusCounts): array
    {
        return collect(Ticket::DISPLAY_STATUS_LABELS)
            ->mapWithKeys(fn ($label, $status) => [$label => $statusCounts[$status] ?? 0])
            ->all();
    }

    protected function resolutionRateMetrics(Collection $tickets): array
    {
        $needsResolution = $tickets->where('classification', Ticket::CLASSIFICATION_NEEDS_RESOLUTION);
        $excludedTypes = [
            Ticket::CLOSURE_WITHDRAWN,
            Ticket::CLOSURE_DUPLICATE,
            Ticket::CLOSURE_OUT_OF_SCOPE,
            Ticket::CLOSURE_INVALID,
            Ticket::CLOSURE_NO_RESPONSE,
        ];
        $excludedTickets = $needsResolution->filter(fn ($ticket) => $ticket->status === Ticket::STATUS_CLOSED
            && in_array($ticket->closure_type, $excludedTypes, true));
        $excluded = collect($excludedTypes)
            ->mapWithKeys(fn ($type) => [$type => $excludedTickets->where('closure_type', $type)->count()])
            ->all();
        $notCounted = array_sum($excluded);
        $eligible = $needsResolution->count() - $notCounted;
        $resolved = $needsResolution
            ->reject(fn ($ticket) => $ticket->status === Ticket::STATUS_CLOSED && in_array($ticket->closure_type, $excludedTypes, true))
            ->filter(fn ($ticket) => $this->isRecordedResolution($ticket))
            ->count();

        return [
            'rate' => $eligible ? round($resolved / $eligible * 100, 2) : 0,
            'resolved' => $resolved,
            'total' => $eligible,
            'needs_resolution_total' => $needsResolution->count(),
            'excluded' => $excluded,
            'excluded_total' => $notCounted,
            'chart' => [
                'labels' => ['Resolved', 'Not resolved', 'Not counted'],
                'data' => [$resolved, max($eligible - $resolved, 0), $notCounted],
            ],
        ];
    }

    protected function isRecordedResolution(Ticket $ticket): bool
    {
        return $ticket->status === Ticket::STATUS_RESOLVED
            || ($ticket->status === Ticket::STATUS_CLOSED
                && in_array($ticket->closure_type, [Ticket::CLOSURE_RESOLVED_ACCEPTED, Ticket::CLOSURE_RESOLVED], true));
    }

    /**
     * The overall figures include committee outcomes; category and handler figures do not.
     * Unmatched clarification/referral events fall back to the full elapsed duration.
     */
    protected function resolutionTimeStats(Collection $tickets): array
    {
        $resolved = $tickets
            ->filter(fn ($ticket) => $ticket->classification === Ticket::CLASSIFICATION_NEEDS_RESOLUTION && $this->isRecordedResolution($ticket))
            ->values();

        $entries = $resolved->map(function ($ticket) {
            $ticket->loadMissing(['complaint.category', 'auditLogs']);
            $start = $ticket->complaint?->created_at;
            $end = $ticket->resolved_at;
            $committee = $ticket->auditLogs->contains('action', 'referral_outcome_recorded');

            if (! $start || ! $end || $end->lt($start)) {
                return [
                    'ticket_id' => $ticket->id,
                    'category' => $ticket->complaint?->category?->name ?? 'Uncategorized',
                    'committee' => $committee,
                    'days' => null,
                    'fallback' => true,
                ];
            }

            $elapsedSeconds = $end->getTimestamp() - $start->getTimestamp();
            $waitIntervals = [
                'clarification' => [
                    'start' => 'clarification_requested',
                    'end' => 'clarification_provided',
                    'timestamp' => $ticket->clarification_requested_at,
                ],
                'referral' => [
                    'start' => 'ticket_referred',
                    'end' => 'referral_outcome_recorded',
                    'timestamp' => $ticket->referred_at,
                ],
            ];
            $events = $ticket->auditLogs
                ->filter(fn ($event) => collect($waitIntervals)->contains(fn ($interval) => in_array($event->action, [$interval['start'], $interval['end']], true)))
                ->map(fn ($event) => ['action' => $event->action, 'at' => $event->created_at, 'id' => $event->id])
                ->values();
            $fallback = false;
            $intervals = [];

            foreach ($waitIntervals as $interval) {
                $hasStartEvent = $events->contains('action', $interval['start']);
                if (! $hasStartEvent && $interval['timestamp']) {
                    $events->push(['action' => $interval['start'], 'at' => $interval['timestamp'], 'id' => 0]);
                }
            }

            $events = $events->sort(function ($left, $right) {
                $time = $left['at']->getTimestamp() <=> $right['at']->getTimestamp();

                return $time !== 0 ? $time : $left['id'] <=> $right['id'];
            })->values();
            $openIntervals = ['clarification' => [], 'referral' => []];
            $actions = collect($waitIntervals)->mapWithKeys(fn ($interval, $type) => [
                $interval['start'] => [$type, 'start'],
                $interval['end'] => [$type, 'end'],
            ]);

            foreach ($events as $event) {
                [$type, $edge] = $actions[$event['action']];
                $at = $event['at'];
                if ($at->lt($start) || $at->gt($end)) {
                    $fallback = true;
                    continue;
                }

                if ($edge === 'start') {
                    $openIntervals[$type][] = $at;
                    continue;
                }

                $intervalStart = array_shift($openIntervals[$type]);
                if (! $intervalStart || $at->lt($intervalStart)) {
                    $fallback = true;
                    continue;
                }

                $intervals[] = $at->getTimestamp() - $intervalStart->getTimestamp();
            }

            if ($openIntervals['clarification'] !== [] || $openIntervals['referral'] !== []) {
                $fallback = true;
            }

            $waitSeconds = array_sum($intervals);
            if ($waitSeconds > $elapsedSeconds) {
                $fallback = true;
            }

            $activeSeconds = $fallback ? $elapsedSeconds : $elapsedSeconds - $waitSeconds;

            return [
                'ticket_id' => $ticket->id,
                'category' => $ticket->complaint?->category?->name ?? 'Uncategorized',
                'committee' => $committee,
                'days' => $activeSeconds / 86400,
                'fallback' => $fallback,
            ];
        });

        $timed = $entries->whereNotNull('days');
        $summarize = function (Collection $group, ?Collection $all = null): array {
            $durations = $group->pluck('days')->filter(fn ($days) => $days !== null)->values();

            return [
                'average_days' => $durations->isNotEmpty() ? round($durations->avg(), 1) : null,
                'median_days' => $durations->isNotEmpty() ? round($durations->median(), 1) : null,
                'ticket_count' => $durations->count(),
                'fallback_count' => ($all ?? $group)->where('fallback', true)->count(),
            ];
        };
        $categoryEntries = $entries->where('committee', false)->groupBy('category');
        $categories = $categoryEntries
            ->map(fn ($group) => $summarize($group))
            ->sortBy(fn ($details) => $details['average_days'] ?? -1, SORT_REGULAR, true)
            ->all();
        $committeeEntries = $entries->where('committee', true);
        $overall = $summarize($timed, $entries);

        return [
            ...$overall,
            'committee_outcomes' => $summarize($committeeEntries),
            'categories' => $categories,
            'entries' => $entries,
        ];
    }

    protected function performanceMetricsForTickets(Collection $tickets, array $resolutionTimes): array
    {
        $staffActions = ['ticket_classified', 'ticket_assigned', 'ticket_acknowledged', 'clarification_requested'];
        $reviewDurations = [];
        $acknowledgmentDurations = [];
        $firstResponseDurations = [];
        $acknowledgmentsByHandler = [];
        $resolvedByHandler = [];
        $handlingByHandlerAndCategory = [];
        $resolvedTicketIds = [];
        $furtherActionTicketIds = [];
        $reopenCount = 0;
        $studentRequests = ['update_requested' => [], 'escalation_requested' => []];
        $escalationCount = 0;
        $handlerIds = [];
        $handlerOpenTickets = [];
        $assignedTicketsByHandler = [];
        $handlingDurationsByHandler = [];
        $handlingFallbacksByHandler = [];
        $ratingsByHandler = [];
        $openStatuses = [
            Ticket::STATUS_SUBMITTED,
            Ticket::STATUS_NEEDS_CLARIFICATION,
            Ticket::STATUS_ASSIGNED,
            Ticket::STATUS_IN_PROGRESS,
            Ticket::STATUS_ESCALATED,
            Ticket::STATUS_REFERRED,
        ];

        foreach ($tickets as $ticket) {
            $logs = $ticket->auditLogs->sortBy(fn ($event) => [$event->created_at?->getTimestamp() ?? 0, $event->id])->values();
            $assignedHandler = $ticket->assignee ?? $ticket->currentHandler;
            if ($assignedHandler && in_array($assignedHandler->role, [User::ROLE_RECIPIENT, User::ROLE_SDS_ADMIN], true)) {
                $assignedHandlerId = (int) $assignedHandler->id;
                $handlerIds[] = $assignedHandlerId;
                $assignedTicketsByHandler[$assignedHandlerId][$ticket->id] = true;
            }
            $submittedAt = $ticket->complaint?->created_at;
            $reviewEvent = $logs
                ->filter(fn ($event) => in_array($event->action, $staffActions, true))
                ->first(fn ($event) => $submittedAt && $event->created_at && $event->created_at->greaterThanOrEqualTo($submittedAt));
            if ($reviewEvent && $submittedAt) {
                $reviewDurations[] = ($reviewEvent->created_at->getTimestamp() - $submittedAt->getTimestamp()) / 86400;
            }

            $pendingAssignment = null;
            foreach ($logs as $event) {
                if ($event->action === 'ticket_assigned') {
                    $pendingAssignment = $event->created_at;
                } elseif ($event->action === 'ticket_acknowledged' && $pendingAssignment && $event->created_at?->greaterThanOrEqualTo($pendingAssignment)) {
                    $days = ($event->created_at->getTimestamp() - $pendingAssignment->getTimestamp()) / 86400;
                    $acknowledgmentDurations[] = $days;
                    if ($event->performed_by) {
                        $acknowledgmentsByHandler[$event->performed_by][] = $days;
                        $handlerIds[] = (int) $event->performed_by;
                    }
                    $pendingAssignment = null;
                }
            }

            $firstStaffMessage = $ticket->thread?->messages
                ->filter(fn ($message) => $message->sender && in_array($message->sender->role, [User::ROLE_RECIPIENT, User::ROLE_SDS_ADMIN], true))
                ->sortBy(fn ($message) => [$message->created_at?->getTimestamp() ?? 0, $message->id])
                ->first(fn ($message) => $submittedAt && $message->created_at && $message->created_at->greaterThanOrEqualTo($submittedAt));
            if ($firstStaffMessage && $submittedAt) {
                $firstResponseDurations[] = ($firstStaffMessage->created_at->getTimestamp() - $submittedAt->getTimestamp()) / 86400;
            }

            $resolutionEvents = $logs->where('action', 'complaint_resolved');
            foreach ($resolutionEvents as $event) {
                if ($event->performed_by) {
                    $handlerIds[] = (int) $event->performed_by;
                }
            }
            if ($resolutionEvents->isNotEmpty() && $this->isRecordedResolution($ticket)) {
                $resolvedTicketIds[$ticket->id] = true;
                $lastResolution = $resolutionEvents->last();
                if ($lastResolution->performed_by) {
                    $resolvedByHandler[$lastResolution->performed_by][$ticket->id] = true;
                }
            }
            if ($logs->contains('action', 'referral_outcome_recorded') || $this->isRecordedResolution($ticket)) {
                $resolvedTicketIds[$ticket->id] = true;
            }
            if ($logs->contains('action', 'further_action_requested')) {
                $furtherActionTicketIds[$ticket->id] = true;
            }
            $reopenCount += $logs->where('action', 'ticket_reopened_from_resolved')->count();
            $escalationCount += $logs->where('action', 'ticket_escalated')->count();

            foreach (['update_requested', 'escalation_requested'] as $requestAction) {
                $studentRequests[$requestAction] = [
                    ...$studentRequests[$requestAction],
                    ...$this->requestFollowUpCounts($logs, $requestAction),
                ];
            }

            if (! $ticket->isForwardedInformational() && in_array($ticket->status, $openStatuses, true)) {
                $handlerId = $ticket->assigned_to ?: $ticket->current_handler_id;
                if ($handlerId) {
                    $handlerIds[] = (int) $handlerId;
                    $handlerOpenTickets[$handlerId][] = $ticket;
                }
            }
        }

        $timeEntries = collect($resolutionTimes['entries'])->keyBy('ticket_id');
        foreach ($tickets as $ticket) {
            $lastResolution = $ticket->auditLogs
                ->where('action', 'complaint_resolved')
                ->sortBy(fn ($event) => [$event->created_at?->getTimestamp() ?? 0, $event->id])
                ->last();
            $entry = $timeEntries->get($ticket->id);
            if (! $lastResolution?->performed_by || ! $entry || $entry['committee'] || $entry['days'] === null
                || ! $this->isRecordedResolution($ticket)) {
                continue;
            }

            $handlerId = (int) $lastResolution->performed_by;
            $handlerIds[] = $handlerId;

            $assignmentEvent = $this->assignmentEventForHandler($ticket, $lastResolution->performer, $lastResolution->created_at);
            if (! $assignmentEvent?->created_at) {
                $handlingFallbacksByHandler[$handlerId] = ($handlingFallbacksByHandler[$handlerId] ?? 0) + 1;
                $handlingDays = $entry['days'];
            } else {
                $handlingDays = $this->activeHandlingDaysFrom($ticket, $assignmentEvent->created_at, $ticket->resolved_at);
                if ($handlingDays === null) {
                    $handlingFallbacksByHandler[$handlerId] = ($handlingFallbacksByHandler[$handlerId] ?? 0) + 1;
                    $handlingDays = $entry['days'];
                }
            }
            if ($handlingDays !== null) {
                $handlingDurationsByHandler[$handlerId][] = $handlingDays;
            }
            $handlingByHandlerAndCategory[$handlerId][$entry['category']][] = $entry['days'];
            if ($ticket->satisfaction_rating !== null) {
                $ratingsByHandler[$handlerId][] = (int) $ticket->satisfaction_rating;
            }
        }

        $handlerIds = array_values(array_unique(array_filter($handlerIds)));
        $handlers = User::query()->with('recipient')->whereIn('id', $handlerIds)->get()->keyBy('id');
        $handlerRows = [];
        foreach ($handlers as $handler) {
            $handlerId = (int) $handler->id;
            $handlerRows[] = array_merge($this->performanceRow(
                $handler->table_name ?: 'Staff member',
                $handler->recipient?->unit ?: 'SDS Office',
                [$handlerId],
                $handlerOpenTickets,
                $acknowledgmentsByHandler,
                $resolvedByHandler,
                $handlingByHandlerAndCategory,
            ), $this->handlerPerformanceDetails(
                $handlerId,
                $assignedTicketsByHandler,
                $acknowledgmentsByHandler,
                $resolvedByHandler,
                $handlingDurationsByHandler,
                $handlingFallbacksByHandler,
                $ratingsByHandler,
            ));
        }
        usort($handlerRows, fn ($left, $right) => $right['open_tickets'] <=> $left['open_tickets'] ?: strcmp($left['name'], $right['name']));

        $handlerIdsByUnit = $handlers->groupBy(fn ($handler) => $handler->recipient?->unit ?: 'SDS Office', true);
        $officeRows = [];
        foreach ($handlerIdsByUnit as $unit => $unitHandlers) {
            $officeRows[] = $this->performanceRow(
                $unit,
                $unit,
                $unitHandlers->keys()->all(),
                $handlerOpenTickets,
                $acknowledgmentsByHandler,
                $resolvedByHandler,
                $handlingByHandlerAndCategory,
            );
        }
        usort($officeRows, fn ($left, $right) => $right['open_tickets'] <=> $left['open_tickets'] ?: strcmp($left['name'], $right['name']));

        return [
            'review_time' => $this->durationSummary($reviewDurations),
            'acknowledgment_time' => $this->durationSummary($acknowledgmentDurations),
            'first_response_time' => $this->durationSummary($firstResponseDurations),
            'further_action_rate' => count($resolvedTicketIds)
                ? round(count(array_intersect_key($furtherActionTicketIds, $resolvedTicketIds)) / count($resolvedTicketIds) * 100, 1)
                : 0,
            'resolved_ticket_count' => count($resolvedTicketIds),
            'further_action_ticket_count' => count(array_intersect_key($furtherActionTicketIds, $resolvedTicketIds)),
            'reopen_count' => $reopenCount,
            'student_requests' => [
                'update' => ['count' => count($studentRequests['update_requested']), 'followed_by_sds_action' => array_sum($studentRequests['update_requested'])],
                'escalation' => ['count' => count($studentRequests['escalation_requested']), 'followed_by_sds_action' => array_sum($studentRequests['escalation_requested'])],
            ],
            'escalation_count' => $escalationCount,
            'handler_rows' => $handlerRows,
            'office_rows' => $officeRows,
            'handler_escalations_attribution_available' => false,
        ];
    }

    protected function handlerPerformanceDetails(
        int $handlerId,
        array $assignedTicketsByHandler,
        array $acknowledgmentsByHandler,
        array $resolvedByHandler,
        array $handlingDurationsByHandler,
        array $handlingFallbacksByHandler,
        array $ratingsByHandler,
    ): array {
        $acknowledgments = $acknowledgmentsByHandler[$handlerId] ?? [];
        $handlingDurations = $handlingDurationsByHandler[$handlerId] ?? [];
        $ratings = $ratingsByHandler[$handlerId] ?? [];
        $assignedCount = count($assignedTicketsByHandler[$handlerId] ?? []);
        $resolvedCount = count($resolvedByHandler[$handlerId] ?? []);

        return [
            'user_id' => $handlerId,
            'assigned_tickets' => $assignedCount,
            'resolved_percentage' => $assignedCount ? round($resolvedCount / $assignedCount * 100, 1) : 0,
            'acknowledgment_average_days' => $acknowledgments ? round(array_sum($acknowledgments) / count($acknowledgments), 1) : null,
            'acknowledgment_count' => count($acknowledgments),
            'handling_average_days' => $handlingDurations ? round(array_sum($handlingDurations) / count($handlingDurations), 1) : null,
            'handling_count' => count($handlingDurations),
            'handling_fallback_count' => $handlingFallbacksByHandler[$handlerId] ?? 0,
            'rating_average' => $ratings ? round(array_sum($ratings) / count($ratings), 1) : null,
            'rating_count' => count($ratings),
        ];
    }

    protected function assignmentEventForHandler(Ticket $ticket, ?User $handler, ?Carbon $resolvedAt): ?AuditLog
    {
        if (! $handler || ! $resolvedAt) {
            return null;
        }

        $displayName = $handler->display_name;

        return $ticket->auditLogs
            ->filter(fn ($event) => $event->created_at && $event->created_at->lte($resolvedAt))
            ->filter(function ($event) use ($handler, $displayName) {
                $details = (string) $event->details;

                if ($event->action === 'ticket_assigned') {
                    return str_contains($details, " to {$displayName} for handling.")
                        || ((int) $event->performed_by === (int) $handler->id
                            && $details === 'Ticket assigned to SDS admin for direct handling.');
                }

                return $event->action === 'ticket_escalated'
                    && str_starts_with($details, "Ticket escalated to {$displayName}.");
            })
            ->sortBy(fn ($event) => [$event->created_at?->getTimestamp() ?? 0, $event->id])
            ->last();
    }

    protected function activeHandlingDaysFrom(Ticket $ticket, ?Carbon $start, ?Carbon $end): ?float
    {
        if (! $start || ! $end || $end->lt($start)) {
            return null;
        }

        $intervals = [
            'clarification' => [
                'start' => 'clarification_requested',
                'end' => 'clarification_provided',
                'timestamp' => $ticket->clarification_requested_at,
            ],
            'referral' => [
                'start' => 'ticket_referred',
                'end' => 'referral_outcome_recorded',
                'timestamp' => $ticket->referred_at,
            ],
        ];
        $events = $ticket->auditLogs
            ->filter(fn ($event) => collect($intervals)->contains(
                fn ($interval) => in_array($event->action, [$interval['start'], $interval['end']], true)
            ))
            ->map(fn ($event) => ['action' => $event->action, 'at' => $event->created_at, 'id' => $event->id])
            ->filter(fn ($event) => $event['at'] !== null)
            ->values();

        foreach ($intervals as $interval) {
            if (! $events->contains('action', $interval['start']) && $interval['timestamp']) {
                $events->push(['action' => $interval['start'], 'at' => $interval['timestamp'], 'id' => 0]);
            }
        }

        $actions = collect($intervals)->mapWithKeys(fn ($interval, $type) => [
            $interval['start'] => [$type, 'start'],
            $interval['end'] => [$type, 'end'],
        ]);
        $open = ['clarification' => [], 'referral' => []];
        $waitSeconds = 0;
        $events = $events->sort(fn ($left, $right) => $left['at']->getTimestamp() <=> $right['at']->getTimestamp()
            ?: $left['id'] <=> $right['id'])->values();

        foreach ($events as $event) {
            [$type, $edge] = $actions[$event['action']];
            if ($edge === 'start') {
                $open[$type][] = $event['at'];
                continue;
            }

            $intervalStart = array_shift($open[$type]);
            if (! $intervalStart || $event['at']->lt($intervalStart)) {
                return null;
            }

            $overlapStart = $intervalStart->greaterThan($start) ? $intervalStart : $start;
            $overlapEnd = $event['at']->lessThan($end) ? $event['at'] : $end;
            if ($overlapEnd->gt($overlapStart)) {
                $waitSeconds += $overlapEnd->getTimestamp() - $overlapStart->getTimestamp();
            }
        }

        if ($open['clarification'] !== [] || $open['referral'] !== []) {
            return null;
        }

        $elapsedSeconds = $end->getTimestamp() - $start->getTimestamp();
        if ($waitSeconds > $elapsedSeconds) {
            return null;
        }

        return ($elapsedSeconds - $waitSeconds) / 86400;
    }

    protected function requestFollowUpCounts(Collection $logs, string $requestAction): array
    {
        $counts = [];
        $requestIndexes = $logs->where('action', $requestAction)->keys()->values();

        foreach ($requestIndexes as $position => $index) {
            $nextRequestIndex = $requestIndexes->get($position + 1, $logs->count());
            $followed = $logs
                ->slice($index + 1, $nextRequestIndex - $index - 1)
                ->contains(fn ($event) => $event->performer?->role === User::ROLE_SDS_ADMIN);
            $counts[] = $followed ? 1 : 0;
        }

        return $counts;
    }

    protected function durationSummary(array $durations): array
    {
        return [
            'average_days' => $durations ? round(array_sum($durations) / count($durations), 1) : null,
            'median_days' => $durations ? round(collect($durations)->median(), 1) : null,
            'sample_count' => count($durations),
        ];
    }

    protected function performanceRow(
        string $name,
        string $unit,
        array $handlerIds,
        array $handlerOpenTickets,
        array $acknowledgmentsByHandler,
        array $resolvedByHandler,
        array $handlingByHandlerAndCategory,
    ): array {
        $openTickets = collect($handlerIds)->flatMap(fn ($id) => $handlerOpenTickets[$id] ?? []);
        $acknowledgmentDurations = collect($handlerIds)->flatMap(fn ($id) => $acknowledgmentsByHandler[$id] ?? [])->all();
        $resolvedIds = collect($handlerIds)->flatMap(fn ($id) => array_keys($resolvedByHandler[$id] ?? []))->unique();
        $categoryDurations = [];
        foreach ($handlerIds as $id) {
            foreach ($handlingByHandlerAndCategory[$id] ?? [] as $category => $durations) {
                $categoryDurations[$category] = [...($categoryDurations[$category] ?? []), ...$durations];
            }
        }
        $handlingCategories = [];
        foreach ($categoryDurations as $category => $durations) {
            $handlingCategories[$category] = [
                'median_days' => count($durations) >= 3 ? round(collect($durations)->median(), 1) : null,
                'ticket_count' => count($durations),
            ];
        }
        $openAges = $openTickets
            ->map(fn ($ticket) => $ticket->complaint?->created_at
                ? max(0, now()->getTimestamp() - $ticket->complaint->created_at->getTimestamp()) / 86400
                : null)
            ->filter(fn ($age) => $age !== null);

        return [
            'name' => $name,
            'unit' => $unit,
            'open_tickets' => $openTickets->count(),
            'oldest_open_ticket_age_days' => $openAges->isNotEmpty() ? round($openAges->max(), 1) : null,
            'median_acknowledgment_days' => count($acknowledgmentDurations) >= 3 ? round(collect($acknowledgmentDurations)->median(), 1) : null,
            'acknowledgment_ticket_count' => count($acknowledgmentDurations),
            'handling_by_category' => $handlingCategories,
            'resolved_tickets' => $resolvedIds->count(),
            'escalations' => 'Not tracked per handler',
        ];
    }

    protected function buildInsights(Collection $categoryCounts, Collection $durations, Collection $escalations, Collection $tickets, int $identified): array
    {
        $topCategory = $categoryCounts->sortDesc()->keys()->first() ?? 'No category';
        $anonymousRate = $tickets->count() ? round(($tickets->count() - $identified) / $tickets->count() * 100) : 0;
        return [$topCategory . ' generated the highest number of tickets.', $durations->isNotEmpty() ? 'Resolved tickets averaged ' . round($durations->avg(), 1) . ' days of active handling.' : 'Resolution timing will appear after tickets are resolved.', $escalations->isNotEmpty() ? $escalations->pluck('ticket_id')->unique()->count() . ' tickets required escalation.' : 'No escalated tickets in this period.', 'Anonymous submissions account for ' . $anonymousRate . '% of total tickets.'];
    }

    public function getReportData(array $filters = []): array
    {
        $resolutionRate = $this->getResolutionRate($filters);
        $tickets = $this->filterTickets(Ticket::query(), $filters)
            ->with(['complaint.category', 'assignee.recipient', 'currentHandler.recipient', 'auditLogs.performer', 'thread.messages.sender'])
            ->get();
        $resolutionTimes = $this->resolutionTimeStats($tickets);

        return [
            'summary' => [
                'total_complaints' => $this->getTotalComplaints($filters),
                'total_tickets' => $this->getTotalTickets($filters),
                'resolution_rate' => $resolutionRate['rate'],
                'escalated_tickets' => array_sum($this->getEscalationFrequency($filters)['data']),
            ],
            'resolution_rate_details' => $resolutionRate,
            'performance_metrics' => $this->performanceMetricsForTickets($tickets, $resolutionTimes),
            'category_breakdown' => $this->getComplaintDistributionByCategory($filters),
            'resolution_chart' => $this->getResolutionRate($filters)['chart'],
            'average_resolution_time' => $this->getAverageResolutionTimePerCategory($filters),
            'escalation_frequency' => $this->getEscalationFrequency($filters),
            'status_distribution' => $this->getActiveTicketStatuses($filters),
            'breakdowns' => $this->getBreakdowns($filters),
            'overview' => $this->getReportOverview($filters),
            'filters' => $filters,
        ];
    }

    /**
     * The figures the printed report explains in plain words: how many tickets came in, how many
     * are open or finished, how many needed action and were resolved, and how long that took.
     */
    public function getReportOverview(array $filters = []): array
    {
        $tickets = $this->filterTickets(Ticket::query(), $filters)->with(['complaint.category', 'auditLogs'])->get();
        $finishedStatuses = [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED];
        $needsAction = $tickets->where('classification', Ticket::CLASSIFICATION_NEEDS_RESOLUTION);
        $resolutionRate = $this->resolutionRateMetrics($tickets);
        $resolutionTimes = $this->resolutionTimeStats($tickets);
        $escalated = $tickets->filter(fn ($ticket) => $ticket->auditLogs->where('action', 'ticket_escalated')->isNotEmpty());

        return [
            'total' => $tickets->count(),
            'open' => $tickets->whereNotIn('status', $finishedStatuses)->count(),
            'finished' => $tickets->whereIn('status', $finishedStatuses)->count(),
            'needs_action' => $needsAction->count(),
            'needs_action_denominator' => $resolutionRate['total'],
            'needs_action_resolved' => $resolutionRate['resolved'],
            'resolution_rate' => $resolutionRate['rate'],
            'excluded_closures' => $resolutionRate['excluded'],
            'excluded_closures_total' => $resolutionRate['excluded_total'],
            'informational' => $tickets->where('classification', Ticket::CLASSIFICATION_INFORMATIONAL)->count(),
            'invalid' => $tickets->where('classification', Ticket::CLASSIFICATION_INVALID)->count(),
            'unclassified' => $tickets->filter(fn ($ticket) => blank($ticket->classification))->count(),
            'escalated_tickets' => $escalated->count(),
            'average_hours' => $resolutionTimes['average_days'] !== null ? $resolutionTimes['average_days'] * 24 : null,
            'average_days' => $resolutionTimes['average_days'],
            'median_days' => $resolutionTimes['median_days'],
            'resolution_time_ticket_count' => $resolutionTimes['ticket_count'],
            'resolution_time_fallback_count' => $resolutionTimes['fallback_count'],
            'committee_outcomes' => $resolutionTimes['committee_outcomes'],
            'resolution_times' => collect($resolutionTimes['categories'])
                ->map(fn ($group) => [
                    'tickets' => $group['ticket_count'],
                    'average_days' => $group['average_days'],
                    'median_days' => $group['median_days'],
                    'fallback_count' => $group['fallback_count'],
                ])
                ->toArray(),
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

        $classification = $filters['classification'] ?? null;
        $statusFilterEnabled = in_array($classification, [
            Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
            'unclassified',
        ], true);

        if ($statusFilterEnabled && filled($filters['status'] ?? null)) {
            if ($classification === 'unclassified'
                && ! in_array($filters['status'], Ticket::UNCLASSIFIED_DISPLAY_STATUSES, true)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (filled($filters['recipient_id'] ?? null)) {
            $query->where(function ($tickets) use ($filters) {
                $tickets->where('assigned_to', $filters['recipient_id'])->orWhere('current_handler_id', $filters['recipient_id']);
                $tickets->orWhereHas('forwardedRecipient', fn ($recipient) => $recipient->where('user_id', $filters['recipient_id']));
            });
        }

        if (filled($filters['classification'] ?? null)) {
            if ($filters['classification'] === 'unclassified') {
                $query->whereNull('classification');
            } else {
                $query->where('classification', $filters['classification']);
            }
        }

        if (filled($filters['unit'] ?? null)) {
            $query->where(function ($tickets) use ($filters) {
                $tickets->whereHas('assignee.recipient', fn ($recipient) => $recipient->where('unit', $filters['unit']))
                    ->orWhereHas('currentHandler.recipient', fn ($recipient) => $recipient->where('unit', $filters['unit']))
                    ->orWhereHas('forwardedRecipient', fn ($recipient) => $recipient->where('unit', $filters['unit']));
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
