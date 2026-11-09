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
    public const FORWARDED_INFORMATION_STATUS = 'forwarded_for_information';

    public const FORWARDED_INFORMATION_LABEL = 'Forwarded for information';

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
            'labels' => array_map(fn ($status) => $status === self::FORWARDED_INFORMATION_STATUS
                ? self::FORWARDED_INFORMATION_LABEL
                : (Ticket::STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status))), array_keys($statusCounts)),
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
        $finishedStatuses = [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED];
        $resolutionTickets = $tickets->where('classification', Ticket::CLASSIFICATION_NEEDS_RESOLUTION);
        $resolutionRate = $this->resolutionRateMetrics($tickets);
        $resolved = $resolutionTickets->whereIn('status', $finishedStatuses);
        $resolutionTimes = $this->resolutionTimeStats($tickets);
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
        $openTickets = $tickets->whereIn('status', $openStatuses);
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

    protected function statusCountsForTickets(Collection $tickets): array
    {
        $counts = $tickets->groupBy('status')->map->count();
        $forwarded = $tickets->filter(fn ($ticket) => $ticket->isForwardedInformational())->count();

        $counts[Ticket::STATUS_SUBMITTED] = max(($counts[Ticket::STATUS_SUBMITTED] ?? 0) - $forwarded, 0);
        $counts[self::FORWARDED_INFORMATION_STATUS] = $forwarded;

        return collect(array_keys(Ticket::STATUS_LABELS))
            ->mapWithKeys(fn ($status) => [$status => $counts[$status] ?? 0])
            ->put(self::FORWARDED_INFORMATION_STATUS, $forwarded)
            ->all();
    }

    protected function labelledStatusCounts(array $statusCounts): array
    {
        return collect(Ticket::STATUS_LABELS)
            ->mapWithKeys(fn ($label, $status) => [$label => $statusCounts[$status] ?? 0])
            ->put(self::FORWARDED_INFORMATION_LABEL, $statusCounts[self::FORWARDED_INFORMATION_STATUS] ?? 0)
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

    protected function buildInsights(Collection $categoryCounts, Collection $durations, Collection $escalations, Collection $tickets, int $identified): array
    {
        $topCategory = $categoryCounts->sortDesc()->keys()->first() ?? 'No category';
        $anonymousRate = $tickets->count() ? round(($tickets->count() - $identified) / $tickets->count() * 100) : 0;
        return [$topCategory . ' generated the highest number of tickets.', $durations->isNotEmpty() ? 'Resolved tickets averaged ' . round($durations->avg(), 1) . ' days of active handling.' : 'Resolution timing will appear after tickets are resolved.', $escalations->isNotEmpty() ? $escalations->pluck('ticket_id')->unique()->count() . ' tickets required escalation.' : 'No escalated tickets in this period.', 'Anonymous submissions account for ' . $anonymousRate . '% of total tickets.'];
    }

    public function getReportData(array $filters = []): array
    {
        $resolutionRate = $this->getResolutionRate($filters);

        return [
            'summary' => [
                'total_complaints' => $this->getTotalComplaints($filters),
                'total_tickets' => $this->getTotalTickets($filters),
                'resolution_rate' => $resolutionRate['rate'],
                'escalated_tickets' => array_sum($this->getEscalationFrequency($filters)['data']),
            ],
            'resolution_rate_details' => $resolutionRate,
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

        if (filled($filters['status'] ?? null)) {
            if ($filters['status'] === self::FORWARDED_INFORMATION_STATUS) {
                $query->where('status', Ticket::STATUS_SUBMITTED)
                    ->where('classification', Ticket::CLASSIFICATION_INFORMATIONAL)
                    ->whereNotNull('forwarded_to')
                    ->whereNotNull('forwarded_at');
            } elseif ($filters['status'] === Ticket::STATUS_SUBMITTED) {
                $query->where('status', Ticket::STATUS_SUBMITTED)
                    ->where(fn ($tickets) => $tickets
                        ->where('classification', '!=', Ticket::CLASSIFICATION_INFORMATIONAL)
                        ->orWhereNull('classification')
                        ->orWhereNull('forwarded_to')
                        ->orWhereNull('forwarded_at'));
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
            $query->where('classification', $filters['classification']);
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
