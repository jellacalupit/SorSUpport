@php
    use App\Models\Ticket;
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    // Which parts of the report were asked for; all of them when none are named.
    $allSections = array_keys(\App\Http\Controllers\Admin\AnalyticsController::REPORT_SECTIONS);
    $sections = array_values(array_intersect($allSections, $sections ?? $allSections));
    $has = fn (string $key): bool => in_array($key, $sections, true);
    $breakdowns = $reportData['breakdowns'] ?? [];
    $overview = $reportData['overview'] ?? [];
    $performance = $reportData['performance_metrics'] ?? [];
    $satisfaction = $breakdowns['satisfaction'] ?? ['count' => 0, 'average' => null, 'distribution' => []];
    $image = fn (string $name): string => public_path("branding/report/{$name}.png");

    $filterLabels = array_filter([
        'Period' => match (true) {
            filled($filters['start_date'] ?? null) || filled($filters['end_date'] ?? null) => trim(($filters['start_date'] ?? 'the start') . ' to ' . ($filters['end_date'] ?? 'today')),
            default => 'All time',
        },
        'Category' => optional(\App\Models\ComplaintCategory::find($filters['category_id'] ?? null))->name,
        'Classification' => match ($filters['classification'] ?? null) {
            'needs_resolution' => 'Needs Resolution',
            'informational' => 'Informational',
            'invalid' => 'Invalid',
            'unclassified' => 'Unclassified',
            default => null,
        },
        'Status' => filled($filters['status'] ?? null)
            ? (Ticket::DISPLAY_STATUS_LABELS[$filters['status']] ?? ucfirst(str_replace('_', ' ', $filters['status'])))
            : null,
        'College / Office' => $filters['unit'] ?? null,
    ]);

    // Small helpers for writing the figures out in plain words.
    $count = fn (int $number, string $word): string => $number . ' ' . Str::plural($word, $number);
    $verb = fn (int $number, string $one, string $many): string => $number === 1 ? $one : $many;
    $percent = fn (int|float $part, int|float $whole): float => $whole > 0 ? round($part / $whole * 100, 1) : 0;
    $duration = function (?float $days): string {
        if ($days === null) {
            return 'Not available';
        }

        return number_format($days, 1) . ' calendar days';
    };

    // label => value rows built from a chart's labels and data.
    $rows = fn (?array $chart): array => collect($chart['labels'] ?? [])->mapWithKeys(fn ($label, $index) => [$label => $chart['data'][$index] ?? 0])->all();

    $total = $overview['total'] ?? 0;
    $needsAction = $overview['needs_action'] ?? 0;
    $needsActionDenominator = $overview['needs_action_denominator'] ?? 0;
    $needsActionResolved = $overview['needs_action_resolved'] ?? 0;
    $excludedClosures = collect($overview['excluded_closures'] ?? [])->filter();
    $excludedClosureText = $excludedClosures
        ->map(fn ($count, $type) => str_replace('_', ' ', $type) . ' ' . $count)
        ->implode(', ');
    $committeeOutcomes = $overview['committee_outcomes'] ?? [];
    $escalatedTickets = $overview['escalated_tickets'] ?? 0;
    $studentAttributeBreakdownKeys = ['colleges', 'programs', 'year_levels', 'blocks', 'origin'];
    $sensitiveNotBrokenDown = $breakdowns['sensitive_not_broken_down'] ?? 0;

    $categories = collect($rows($reportData['category_breakdown'] ?? null))->sortDesc();
    $emptyCategories = $categories->filter(fn ($tickets) => $tickets === 0)->keys();
    $escalationMonths = collect($rows($reportData['escalation_frequency'] ?? null))
        ->filter()
        ->mapWithKeys(fn ($escalations, $month) => [Carbon::parse($month . '-01')->format('F Y') => $escalations]);

    // The count tables: title, what it shows, column heading, value heading, rows, and what to say when empty.
    $tables = [
        'categories' => [
            'Tickets by Category',
            'What students raised concerns about, from the most common to the least.',
            'Category', 'Tickets', $categories->filter()->all(),
            'No tickets were received in this period.',
            $emptyCategories->isNotEmpty() && $categories->filter()->isNotEmpty() ? 'No tickets were received for: ' . $emptyCategories->implode(', ') . '.' : null,
        ],
        'resolution_rate' => [
            'Resolution Rate',
            "Counts recorded resolutions among {$count($needsActionDenominator, 'eligible ticket')} classified as Needs Resolution. Withdrawn, duplicate, out-of-scope, invalid and no-response closures are excluded from the denominator.",
            'Outcome', 'Tickets', $needsAction > 0 ? array_filter([
                'Resolved' => $needsActionResolved,
                'Still being handled' => $needsActionDenominator - $needsActionResolved,
                'Not counted' => $overview['excluded_closures_total'] ?? 0,
            ], fn ($value) => $value > 0) : [],
            'No ticket in this period needed an office to act.',
            $excludedClosureText ? 'Not counted: ' . $excludedClosureText . '.' : null,
        ],
        'escalation' => [
            'Escalations by Month',
            'An escalation is when a ticket is passed to a higher office because it was not settled at the first one. Only months with escalations are listed.',
            'Month', 'Escalations', $escalationMonths->all(),
            'No tickets were escalated in this period.',
            null,
        ],
        'colleges' => [
            'Tickets by College',
            'The college of the student who filed each ticket. "Not disclosed" means the student chose to hide their identity. Sensitive-category tickets are counted separately and not broken down by student attributes.',
            'College', 'Tickets', $breakdowns['colleges'] ?? [],
            'No tickets were received in this period.',
            null,
        ],
        'programs' => [
            'Tickets by Program',
            'The program of the student who filed each ticket. "Not disclosed" means the student chose to hide their identity. Sensitive-category tickets are counted separately and not broken down by student attributes.',
            'Program', 'Tickets', $breakdowns['programs'] ?? [],
            'No tickets were received in this period.',
            null,
        ],
        'year_levels' => [
            'Tickets by Year Level',
            'Year level of the student who filed each ticket. Sensitive-category tickets are counted separately and not broken down by student attributes.',
            'Year level', 'Tickets', $breakdowns['year_levels'] ?? [],
            'No tickets were received in this period.',
            null,
        ],
        'blocks' => [
            'Tickets by Block',
            'Block of the student who filed each ticket. Sensitive-category tickets are counted separately and not broken down by student attributes.',
            'Block', 'Tickets', $breakdowns['blocks'] ?? [],
            'No tickets were received in this period.',
            null,
        ],
        'origin' => [
            'Identified and Anonymous Origin',
            'Whether the student identified themselves or hid their identity. Sensitive-category tickets are counted separately and not broken down by student attributes.',
            'Origin', 'Tickets', $breakdowns['origin'] ?? [],
            'No tickets were received in this period.',
            null,
        ],
        'resolution_types' => [
            'How Tickets Were Resolved',
            'What the handling office did to settle each resolved ticket.',
            'Resolution', 'Tickets', $breakdowns['resolution_types'] ?? [],
            'No tickets have been resolved in this period.',
            null,
        ],
        'closure_reasons' => [
            'Why Tickets Were Closed',
            'The reason recorded when each ticket was closed.',
            'Reason', 'Tickets', $breakdowns['closure_reasons'] ?? [],
            'No tickets have been closed in this period.',
            null,
        ],
        'escalation_levels' => [
            'Escalation Level',
            "How many times each ticket was passed to a higher office. Counts only the {$count($needsAction, 'ticket')} classified as Needs Resolution.",
            'Level', 'Tickets', $needsAction > 0 ? ($breakdowns['escalation_levels'] ?? []) : [],
            'No ticket in this period needed an office to act.',
            null,
        ],
        'satisfaction' => [
            'Student Satisfaction',
            $satisfaction['count'] > 0
                ? "Students rate how their ticket was handled, from 1 (lowest) to 5 (highest). {$count($satisfaction['count'], 'ticket')} {$verb($satisfaction['count'], 'was', 'were')} rated, with an average of {$satisfaction['average']} out of 5."
                : 'Students rate how their ticket was handled, from 1 (lowest) to 5 (highest).',
            'Rating', 'Tickets', $satisfaction['count'] > 0 ? collect($satisfaction['distribution'])->mapWithKeys(fn ($tickets, $rating) => ["{$rating} out of 5" => $tickets])->all() : [],
            'No student has rated a ticket yet.',
            null,
        ],
    ];

    $statusMeanings = [
        'Submitted' => 'Received and waiting for the SDS Office to review',
        'In review' => 'SDS asked the student for more information',
        'Assigned' => 'Sent to an office that has not started on it yet',
        'In progress' => 'Being worked on by the assigned office',
        'Escalated' => 'Passed to a higher office',
        'Referred' => 'Sent to another office or body to handle',
        'Resolved' => 'A resolution has been given',
        'Closed' => 'Finished, with nothing more to do',
    ];
    $openLabels = collect(Ticket::DISPLAY_STATUS_LABELS)->except([Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED])->values();

    // The main points of the report in sentences.
    $findings = [];
    if ($total > 0) {
        $findings[] = "{$count($total, 'ticket')} {$verb($total, 'was', 'were')} received. {$overview['open']} {$verb($overview['open'], 'is', 'are')} still open and {$overview['finished']} {$verb($overview['finished'], 'is', 'are')} finished (resolved or closed).";

        $highest = $categories->max();
        $leading = $categories->filter(fn ($tickets) => $tickets === $highest && $tickets > 0)->keys();
        if ($leading->isNotEmpty() && $leading->count() <= 3) {
            $findings[] = "The most common {$verb($leading->count(), 'concern was', 'concerns were')} {$leading->join(', ', ' and ')}, with {$count($highest, 'ticket')}{$verb($leading->count(), '', ' each')}.";
        } elseif ($leading->isNotEmpty()) {
            $findings[] = "Tickets were spread across categories; no category received more than {$count($highest, 'ticket')}.";
        }

        $findings[] = $needsAction > 0
            ? "{$needsAction} of the {$count($total, 'ticket')} needed an office to act. {$needsActionResolved} of the {$needsActionDenominator} counted tickets {$verb($needsActionResolved, 'has', 'have')} a recorded resolution ({$percent($needsActionResolved, $needsActionDenominator)}%)." . ($excludedClosureText ? ' Not counted: ' . $excludedClosureText . '.' : '')
            : 'None of the tickets has been classified as needing an office to act.';

        if (($overview['average_days'] ?? null) !== null) {
            $findings[] = 'Active handling averaged ' . $duration($overview['average_days']) . ' (median ' . $duration($overview['median_days']) . '), excluding clarification and referral waits.';
        }

        $findings[] = $escalatedTickets > 0
            ? "{$count($escalatedTickets, 'ticket')} had to be passed to a higher office (escalated)."
            : 'No ticket had to be passed to a higher office (escalated).';

        if ($satisfaction['count'] > 0) {
            $findings[] = "Students rated {$count($satisfaction['count'], 'ticket')}, giving an average of {$satisfaction['average']} out of 5.";
        }
    }

    $kinds = [
        'Needs Resolution (an office had to act)' => $needsAction,
        'Informational (recorded for information only)' => $overview['informational'] ?? 0,
        'Invalid (not a valid complaint)' => $overview['invalid'] ?? 0,
        'Not yet classified by the SDS Office' => $overview['unclassified'] ?? 0,
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SorSUpport Analytics Report</title>
    <style>
        /* The SDS letterhead is on long bond paper (8.5 x 13 in) with 0.75 in side margins. */
        @page { size: 8.5in 13in; margin: 150pt 54pt 92pt 54pt; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #1f2937; }

        .letterhead { position: fixed; top: -102pt; left: 0; right: 0; height: 90pt; }
        .letterhead table { width: 100%; border-collapse: collapse; }
        .letterhead td { vertical-align: middle; text-align: center; padding: 0; }
        .letterhead .logo { width: 72pt; }
        .letterhead .logo img { width: 72pt; }
        .letterhead p { margin: 0; font-size: 10pt; line-height: 1.15; color: #000; }
        .letterhead .strong { font-weight: bold; }
        .letterhead .small { font-size: 9pt; }
        .letterhead .place { font-style: italic; }
        .letterhead .rule { border-top: 1.5pt solid #000; width: 360pt; margin: 4pt auto 0 auto; }

        /* The band runs edge to edge: 612pt wide, about 79pt tall. */
        .footer { position: fixed; bottom: -92pt; left: -54pt; width: 612pt; height: 80pt; }
        .footer img { width: 612pt; height: 79.2pt; }

        h1 { margin: 0; text-align: center; font-size: 15px; color: #7a1d2a; letter-spacing: 0.3px; }
        .subtitle { margin: 3px 0 0 0; text-align: center; font-size: 10px; color: #4b5563; }
        .filters { margin: 10px 0 14px 0; padding: 6px 9px; border: 1px solid #e5e7eb; background: #faf7f7; font-size: 10px; color: #374151; }
        .filters strong { color: #111827; }

        .section { margin-bottom: 16px; page-break-inside: avoid; }
        h2 { margin: 0 0 2px 0; font-size: 12.5px; color: #7a1d2a; }
        h3 { margin: 10px 0 4px 0; font-size: 11px; color: #111827; }
        .about { margin: 0 0 6px 0; font-size: 10px; line-height: 1.35; color: #4b5563; }
        .note { margin: 4px 0 0 0; font-size: 9.5px; line-height: 1.35; color: #6b7280; font-style: italic; }
        .empty { margin: 0; padding: 6px 9px; border: 1px solid #e5e7eb; background: #f9fafb; color: #4b5563; font-style: italic; }

        table.cards { width: 100%; border-collapse: separate; border-spacing: 0; margin: 6px 0 8px 0; }
        table.cards td { width: 25%; border: 1px solid #e5d5d7; background: #faf7f7; padding: 8px 6px; text-align: center; vertical-align: top; }
        .cards .figure { font-size: 20px; font-weight: bold; color: #7a1d2a; }
        .cards .label { margin-top: 2px; font-size: 9.5px; font-weight: bold; color: #111827; }
        .cards .hint { margin-top: 1px; font-size: 8.5px; color: #6b7280; }

        ul.findings { margin: 0 0 4px 0; padding-left: 16px; }
        ul.findings li { margin-bottom: 3px; line-height: 1.4; }

        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #d1d5db; padding: 4px 7px; text-align: left; vertical-align: middle; }
        table.data thead th { background: #7a1d2a; color: #fff; font-size: 10px; }
        table.data .number { text-align: right; width: 13%; }
        table.data .share { width: 30%; }
        table.data .wide { width: 26%; }
        table.data .meaning { width: 52%; }
        table.data td.meaning { color: #4b5563; }
        table.data tfoot td { background: #f3f4f6; font-weight: bold; }
        .track { width: 100%; height: 7px; background: #f1e9ea; }
        .fill { height: 7px; background: #7a1d2a; }

        .signature { margin-top: 26px; page-break-inside: avoid; font-size: 11px; }
        .signature .name { margin-top: 26px; font-weight: bold; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="letterhead">
        <table>
            <tr>
                <td class="logo"><img src="{{ $image('seal') }}" alt=""></td>
                <td>
                    <p>Republic of the Philippines</p>
                    <p class="strong">Sorsogon State University</p>
                    <p class="strong">OFFICE OF THE STUDENT DEVELOPMENT SERVICES</p>
                    <p class="strong">Bulan Campus</p>
                    <p class="small place">Zone 8, Bulan, Sorsogon</p>
                    <p class="small">Tel. No.; 056 311-0103; Email Address: sas_bc@sorsu.edu.ph</p>
                </td>
                <td class="logo"><img src="{{ $image('bagong-pilipinas') }}" alt=""></td>
            </tr>
        </table>
        <div class="rule"></div>
    </div>

    <div class="footer"><img src="{{ $image('footer') }}" alt=""></div>

    <h1>SORSUPPORT ANALYTICS REPORT</h1>
    <p class="subtitle">Student Complaint and Ticketing System &middot; Generated {{ now()->setTimezone('Asia/Manila')->format('F j, Y, g:i A') }}</p>

    <div class="filters">
        <strong>Covers:</strong>
        @foreach ($filterLabels as $label => $value)
            {{ $label }}: {{ $value }}@if (! $loop->last) &nbsp;|&nbsp; @endif
        @endforeach
    </div>

    @foreach ($sections as $key)
        <div class="section">
        @if ($key === 'summary')
                <h2>Summary</h2>
                <p class="about">The main figures for the period this report covers.</p>

                @if ($total === 0)
                    <p class="empty">No tickets were received in this period.</p>
                @else
                    <table class="cards">
                        <tr>
                            <td>
                                <div class="figure">{{ $total }}</div>
                                <div class="label">Tickets received</div>
                                <div class="hint">All tickets filed by students</div>
                            </td>
                            <td>
                                <div class="figure">{{ $overview['open'] }}</div>
                                <div class="label">Still open</div>
                                <div class="hint">Not yet resolved or closed</div>
                            </td>
                            <td>
                                <div class="figure">{{ $overview['finished'] }}</div>
                                <div class="label">Finished</div>
                                <div class="hint">Resolved or closed</div>
                            </td>
                            <td>
                                <div class="figure">{{ $percent($needsActionResolved, $needsActionDenominator) }}%</div>
                                <div class="label">Resolution rate</div>
                                <div class="hint">{{ $needsActionResolved }} of {{ $needsActionDenominator }} counted tickets</div>
                            </td>
                        </tr>
                    </table>

                    <h3>What the figures say</h3>
                    <ul class="findings">
                        @foreach ($findings as $finding)
                            <li>{{ $finding }}</li>
                        @endforeach
                    </ul>

                    <h3>What kind of tickets were received</h3>
                    <table class="data">
                        <thead>
                            <tr><th>Kind of ticket</th><th class="number">Tickets</th><th class="share" colspan="2">Share of all tickets</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($kinds as $label => $tickets)
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td class="number">{{ $tickets }}</td>
                                    <td class="share"><div class="track"><div class="fill" style="width: {{ $percent($tickets, $total) }}%"></div></div></td>
                                    <td class="number">{{ $percent($tickets, $total) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr><td>Total</td><td class="number">{{ $total }}</td><td colspan="2"></td></tr>
                        </tfoot>
                    </table>
                @endif
        @elseif ($key === 'performance')
                <h2>Staff and Office Performance</h2>
                <p class="about">Calendar-time averages and medians use the selected filters. A student request is counted as followed by SDS action when an SDS administrator performs a later audited action before another request of the same type. Escalations are counted overall; existing audit records do not identify the handler being escalated away from.</p>
                <table class="data">
                    <thead><tr><th>Measure</th><th class="wide number">Average (days)</th><th class="wide number">Median (days)</th><th class="wide number">Tickets</th></tr></thead>
                    <tbody>
                        @foreach (['Review time' => 'review_time', 'Time to acknowledge' => 'acknowledgment_time'] as $label => $key)
                            @php $timing = $performance[$key]; @endphp
                            <tr><td>{{ $label }}</td><td class="wide number">{{ $duration($timing['average_days']) }}</td><td class="wide number">{{ $duration($timing['median_days']) }}</td><td class="wide number">{{ $timing['sample_count'] }}</td></tr>
                        @endforeach
                        <tr><td>Further-action rate</td><td class="wide number" colspan="2">{{ number_format($performance['further_action_rate'], 1) }}%</td><td class="wide number">{{ $performance['further_action_ticket_count'] }} of {{ $performance['resolved_ticket_count'] }}</td></tr>
                        <tr><td>Reopened by SDS</td><td class="wide number" colspan="2">—</td><td class="wide number">{{ $performance['reopen_count'] }}</td></tr>
                        <tr><td>Student update requests</td><td class="wide number" colspan="2">{{ $performance['student_requests']['update']['followed_by_sds_action'] }} followed by SDS action</td><td class="wide number">{{ $performance['student_requests']['update']['count'] }}</td></tr>
                        <tr><td>Student escalation requests</td><td class="wide number" colspan="2">{{ $performance['student_requests']['escalation']['followed_by_sds_action'] }} followed by SDS action</td><td class="wide number">{{ $performance['student_requests']['escalation']['count'] }}</td></tr>
                        <tr><td>Escalations (overall, not attributed to handler)</td><td class="wide number" colspan="2">—</td><td class="wide number">{{ $performance['escalation_count'] }}</td></tr>
                    </tbody>
                </table>

                @foreach ([['Handlers', $performance['handler_rows']], ['Offices', $performance['office_rows']]] as [$heading, $staffRows])
                    <h3>Performance by {{ Str::lower($heading) }}</h3>
                    @if (empty($staffRows))
                        <p class="empty">No handler activity in this period.</p>
                    @else
                        <table class="data">
                            <thead><tr><th>{{ $heading === 'Handlers' ? 'Handler' : 'Office' }}</th><th class="number">Open</th><th class="wide number">Oldest open age</th><th class="wide number">Median to acknowledge</th><th>Median handling time by category</th><th class="number">Resolved</th><th class="wide number">Escalations</th></tr></thead>
                            <tbody>
                                @foreach ($staffRows as $row)
                                    <tr>
                                        <td>{{ $row['name'] }}@if ($heading === 'Handlers')<br><span class="note">{{ $row['unit'] }}</span>@endif</td>
                                        <td class="number">{{ $row['open_tickets'] }}</td>
                                        <td class="wide number">{{ $row['oldest_open_ticket_age_days'] === null ? 'Not available' : $duration($row['oldest_open_ticket_age_days']) }}</td>
                                        <td class="wide number">{{ $row['median_acknowledgment_days'] === null ? 'Not enough data' : $duration($row['median_acknowledgment_days']) }}</td>
                                        <td>
                                            @forelse ($row['handling_by_category'] as $category => $timing)
                                                {{ $category }}: {{ $timing['median_days'] === null ? 'Not enough data' : $duration($timing['median_days']) }}@if (! $loop->last); @endif
                                            @empty
                                                Not enough data
                                            @endforelse
                                        </td>
                                        <td class="number">{{ $row['resolved_tickets'] }}</td>
                                        <td class="wide number">{{ $row['escalations'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                @endforeach
        @elseif ($key === 'resolution_time')
                <h2>Active Handling Time</h2>
                <p class="about">Calendar days from submission to recorded resolution, less time waiting for student clarification and committee referral outcomes. Average and median are shown overall and by category. Committee outcomes are separate from category averages.</p>
                @if (empty($overview['resolution_times']) && empty($committeeOutcomes['ticket_count']) && empty($committeeOutcomes['fallback_count']))
                    <p class="empty">No tickets have been resolved in this period.</p>
                @else
                    <table class="data">
                        <thead>
                            <tr><th>Category / outcome</th><th class="wide number">Tickets</th><th class="wide number">Average</th><th class="wide number">Median</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($overview['resolution_times'] as $category => $time)
                                <tr><td>{{ $category }}</td><td class="wide number">{{ $time['tickets'] }}</td><td class="wide number">{{ $duration($time['average_days']) }}</td><td class="wide number">{{ $duration($time['median_days']) }}</td></tr>
                            @endforeach
                            @if (($committeeOutcomes['ticket_count'] ?? 0) > 0 || ($committeeOutcomes['fallback_count'] ?? 0) > 0)
                                <tr><td>Committee outcomes</td><td class="wide number">{{ $committeeOutcomes['ticket_count'] }}</td><td class="wide number">{{ $duration($committeeOutcomes['average_days']) }}</td><td class="wide number">{{ $duration($committeeOutcomes['median_days']) }}</td></tr>
                            @endif
                        </tbody>
                        <tfoot>
                            <tr><td>Overall (including committee outcomes)</td><td class="wide number">{{ $overview['resolution_time_ticket_count'] }}</td><td class="wide number">{{ $duration($overview['average_days']) }}</td><td class="wide number">{{ $duration($overview['median_days']) }}</td></tr>
                        </tfoot>
                    </table>
                    <p class="note">{{ $overview['resolution_time_fallback_count'] }} ticket(s) used full elapsed time because a clarification or referral wait interval could not be determined.</p>
                @endif
        @elseif ($key === 'statuses')
            @php $statuses = $rows($reportData['status_distribution'] ?? null); @endphp
                <h2>Tickets by Status</h2>
                <p class="about">Where each Needs Resolution or Unclassified ticket stands right now. Informational and invalid tickets have no displayed status; Resolved and Closed are finished.</p>
                <table class="data">
                    <thead>
                        <tr><th>Status</th><th class="meaning">What it means</th><th class="number">Tickets</th><th class="number">Share</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($statuses as $label => $tickets)
                            <tr>
                                <td>{{ $label }}</td>
                                <td class="meaning">{{ $statusMeanings[$label] ?? '' }}</td>
                                <td class="number">{{ $tickets }}</td>
                                <td class="number">{{ $percent($tickets, array_sum($statuses)) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr><td colspan="2">Total</td><td class="number">{{ array_sum($statuses) }}</td><td></td></tr>
                    </tfoot>
                </table>
        @else
            @php [$title, $about, $column, $valueColumn, $data, $emptyText, $note] = $tables[$key]; @endphp
                @php
                    $isStudentAttributeBreakdown = in_array($key, $studentAttributeBreakdownKeys, true);
                    $tableTotal = array_sum($data) + ($isStudentAttributeBreakdown ? $sensitiveNotBrokenDown : 0);
                @endphp
                <h2>{{ $title }}</h2>
                <p class="about">{{ $about }}</p>
                @if ($tableTotal === 0 && ! $isStudentAttributeBreakdown)
                    <p class="empty">{{ $emptyText }}</p>
                @else
                    <table class="data">
                        <thead>
                            <tr><th>{{ $column }}</th><th class="number">{{ $valueColumn }}</th><th class="share" colspan="2">Share</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($data as $label => $value)
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td class="number">{{ $value }}</td>
                                    <td class="share"><div class="track"><div class="fill" style="width: {{ $percent($value, array_sum($data)) }}%"></div></div></td>
                                    <td class="number">{{ $percent($value, $tableTotal) }}%</td>
                                </tr>
                            @endforeach
                            @if ($isStudentAttributeBreakdown)
                                <tr>
                                    <td>Sensitive categories (not broken down)</td>
                                    <td class="number">{{ $sensitiveNotBrokenDown }}</td>
                                    <td class="share"><div class="track"><div class="fill" style="width: {{ $percent($sensitiveNotBrokenDown, $tableTotal) }}%"></div></div></td>
                                    <td class="number">{{ $percent($sensitiveNotBrokenDown, $tableTotal) }}%</td>
                                </tr>
                            @endif
                        </tbody>
                        <tfoot>
                            <tr><td>Total</td><td class="number">{{ $tableTotal }}</td><td colspan="2"></td></tr>
                        </tfoot>
                    </table>
                    @if ($note)
                        <p class="note">{{ $note }}</p>
                    @endif
                @endif
        @endif

        {{-- The signature stays on the same page as the last part. --}}
        @if ($loop->last && filled($preparedBy ?? null))
            <div class="signature">
                <p>Prepared by:</p>
                <p class="name">{{ $preparedBy }}</p>
                <p>Student Development and Services</p>
            </div>
        @endif
        </div>
    @endforeach
</body>
</html>
