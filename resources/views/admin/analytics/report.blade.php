@php
    use App\Models\Ticket;

    // Which parts of the report were asked for; all of them when none are named.
    $allSections = array_keys(\App\Http\Controllers\Admin\AnalyticsController::REPORT_SECTIONS);
    $sections = array_values(array_intersect($allSections, $sections ?? $allSections));
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

    $percent = fn (int|float $part, int|float $whole): float => $whole > 0 ? round($part / $whole * 100, 1) : 0;
    $duration = function (?float $days): string {
        if ($days === null) {
            return 'Not available';
        }

        return number_format($days, 1) . ' days';
    };

    // label => value rows built from a chart's labels and data.
    $rows = fn (?array $chart): array => collect($chart['labels'] ?? [])->mapWithKeys(fn ($label, $index) => [$label => $chart['data'][$index] ?? 0])->all();

    $total = $overview['total'] ?? 0;
    $needsAction = $overview['needs_action'] ?? 0;
    $needsActionDenominator = $overview['needs_action_denominator'] ?? 0;
    $needsActionResolved = $overview['needs_action_resolved'] ?? 0;
    $excludedClosures = collect($reportData['resolution_rate_details']['excluded'] ?? [])->filter();
    $notCounted = $excludedClosures
        ->map(fn ($count, $type) => str_replace('_', ' ', $type) . ' ' . $count)
        ->implode(', ');
    $escalatedTickets = $overview['escalated_tickets'] ?? 0;
    $studentAttributeBreakdownKeys = ['colleges', 'programs', 'year_levels'];
    $sensitiveNotBrokenDown = $breakdowns['sensitive_not_broken_down'] ?? 0;

    // title, description, headings, data, empty state, note.
    $tables = [
        'colleges' => [
            'Tickets by College',
            '',
            'College', 'Tickets', $breakdowns['colleges'] ?? [],
            'No tickets were received in this period.',
            null,
        ],
        'programs' => [
            'Tickets by Program',
            '',
            'Program', 'Tickets', $breakdowns['programs'] ?? [],
            'No tickets were received in this period.',
            null,
        ],
        'year_levels' => [
            'Tickets by Year Level',
            '',
            'Year level', 'Tickets', $breakdowns['year_levels'] ?? [],
            'No tickets were received in this period.',
            null,
        ],
        'origin' => [
            'Identified and Anonymous Origin',
            '',
            'Origin', 'Tickets', $breakdowns['origin'] ?? [],
            'No tickets were received in this period.',
            null,
        ],
        'resolution_types' => [
            'How Tickets Were Resolved',
            '',
            'Resolution', 'Tickets', $breakdowns['resolution_types'] ?? [],
            'No tickets have been resolved in this period.',
            null,
        ],
        'closure_reasons' => [
            'Why Tickets Were Closed',
            '',
            'Reason', 'Tickets', $breakdowns['closure_reasons'] ?? [],
            'No tickets have been closed in this period.',
            null,
        ],
        'escalation_levels' => [
            'Escalation Level',
            '',
            'Level', 'Tickets', $needsAction > 0 ? ($breakdowns['escalation_levels'] ?? []) : [],
            'No ticket in this period needed an office to act.',
            null,
        ],
        'satisfaction' => [
            'Student Satisfaction',
            '',
            'Rating', 'Tickets', $satisfaction['count'] > 0 ? collect($satisfaction['distribution'])->mapWithKeys(fn ($tickets, $rating) => ["{$rating} out of 5" => $tickets])->all() : [],
            'No student has rated a ticket yet.',
            null,
        ],
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

        .section { margin-bottom: 12px; page-break-inside: avoid; }
        h2 { margin: 0 0 2px 0; font-size: 12.5px; color: #7a1d2a; }
        h3 { margin: 10px 0 4px 0; font-size: 11px; color: #111827; }
        .about { margin: 0 0 6px 0; font-size: 10px; line-height: 1.35; color: #4b5563; }
        .note { margin: 4px 0 0 0; font-size: 9.5px; line-height: 1.35; color: #6b7280; font-style: italic; }
        .empty { margin: 0; padding: 6px 9px; border: 1px solid #e5e7eb; background: #f9fafb; color: #4b5563; font-style: italic; }

        table.cards { width: 100%; border-collapse: separate; border-spacing: 0; margin: 6px 0 8px 0; }
        table.cards td { width: 20%; border: 1px solid #e5d5d7; background: #faf7f7; padding: 8px 6px; text-align: center; vertical-align: top; }
        .cards .figure { font-size: 20px; font-weight: bold; color: #7a1d2a; }
        .cards .label { margin-top: 2px; font-size: 9.5px; font-weight: bold; color: #111827; }
        .cards .hint { margin-top: 1px; font-size: 8.5px; color: #6b7280; }

        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #d1d5db; padding: 4px 7px; text-align: left; vertical-align: middle; }
        table.data thead th { background: #7a1d2a; color: #fff; font-size: 10px; }
        table.data .number { text-align: right; width: 13%; }
        table.data .wide { width: 26%; }
        table.data tfoot td { background: #f3f4f6; font-weight: bold; }

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

                    <table class="cards">
                        <tr>
                            <td>
                                <div class="figure">{{ $total }}</div>
                                <div class="label">Total tickets</div>
                                <div class="hint">{{ $overview['open'] }} active</div>
                            </td>
                            <td>
                                <div class="figure">{{ $percent($needsActionResolved, $needsActionDenominator) }}%</div>
                                <div class="label">Resolution rate</div>
                                <div class="hint">{{ $needsActionResolved }} / {{ $needsActionDenominator }}</div>
                            </td>
                            <td>
                                <div class="figure">{{ $duration($overview['median_days'] ?? null) }}</div>
                                <div class="label">Active handling</div>
                                <div class="hint">Average {{ $duration($overview['average_days'] ?? null) }}</div>
                            </td>
                            <td>
                                <div class="figure">{{ $percent($escalatedTickets, $needsAction) }}%</div>
                                <div class="label">Escalation rate</div>
                                <div class="hint">{{ $escalatedTickets }} / {{ $needsAction }}</div>
                            </td>
                            <td>
                                <div class="figure">{{ $satisfaction['count'] ? number_format($satisfaction['average'], 1) . ' / 5' : '—' }}</div>
                                <div class="label">Satisfaction</div>
                                <div class="hint">{{ $satisfaction['count'] }} ratings</div>
                            </td>
                        </tr>
                    </table>
                    @if ($notCounted)
                        <p class="about">Not counted: {{ $notCounted }}</p>
                    @endif
                    <h3>Monthly ticket volume</h3>
                    <table class="data">
                        <thead><tr><th>Month</th><th class="number">Submitted</th><th class="number">Resolved</th><th class="number">Informational</th><th class="number">Invalid</th></tr></thead>
                        <tbody>
                            @foreach ($reportData['monthly_ticket_volumes']['labels'] ?? [] as $index => $month)
                                @php $monthValues = $reportData['monthly_ticket_volumes']['values'][$index] ?? []; @endphp
                                <tr><td>{{ $month }}</td><td class="number">{{ $monthValues['submitted'] ?? 0 }}</td><td class="number">{{ $monthValues['resolved'] ?? 0 }}</td><td class="number">{{ $monthValues['informational'] ?? 0 }}</td><td class="number">{{ $monthValues['invalid'] ?? 0 }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
        @elseif ($key === 'performance')
                <h2>Recipient Performance</h2>
                <table class="data">
                    <thead><tr><th>Name / office</th><th>Designation</th><th class="number">Tickets</th><th class="number">Open</th><th class="wide number">Oldest open (days)</th><th class="number">Resolved</th><th class="wide number">Avg acknowledge (days)</th><th class="wide number">Avg handling (days)</th><th class="number">Rating</th></tr></thead>
                    <tbody>
                        @foreach ($performance['handler_rows'] ?? [] as $row)
                            <tr>
                                <td>{{ $row['name'] }}<br>{{ $row['unit'] }}</td><td>{{ $row['designation'] }}</td>
                                <td class="number">{{ $row['assigned_tickets'] }}</td><td class="number">{{ $row['open_tickets'] }}</td>
                                <td class="wide number">{{ $row['oldest_open_ticket_age_days'] === null ? '—' : $duration($row['oldest_open_ticket_age_days']) }}</td>
                                <td class="number">{{ $row['resolved_tickets'] }} ({{ number_format($row['resolved_percentage'], 1) }}%)</td>
                                <td class="wide number">{{ $row['acknowledgment_average_days'] === null ? '—' : $duration($row['acknowledgment_average_days']) }}</td>
                                <td class="wide number">{{ $row['handling_average_days'] === null ? '—' : $duration($row['handling_average_days']) }}</td>
                                <td class="number">{{ $row['rating_average'] === null ? '—' : number_format($row['rating_average'], 1) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if (empty($performance['handler_rows']))
                    <p class="empty">No recipient data in this period.</p>
                @endif
                <h3>Escalation and Follow-up</h3>
                <table class="data">
                    <thead><tr><th>Measure</th><th class="number">Value</th></tr></thead>
                    <tbody>
                        <tr><td>Escalation rate</td><td class="number">{{ $escalatedTickets }} / {{ $needsAction }} ({{ $percent($escalatedTickets, $needsAction) }}%)</td></tr>
                        <tr><td>Student escalation requests (followed by SDS)</td><td class="number">{{ $performance['student_requests']['escalation']['count'] }} ({{ $performance['student_requests']['escalation']['followed_by_sds_action'] }})</td></tr>
                        <tr><td>Student update requests (followed by SDS)</td><td class="number">{{ $performance['student_requests']['update']['count'] }} ({{ $performance['student_requests']['update']['followed_by_sds_action'] }})</td></tr>
                        <tr><td>Further-action rate</td><td class="number">{{ number_format($performance['further_action_rate'], 1) }}%</td></tr>
                        <tr><td>Reopened</td><td class="number">{{ $performance['reopen_count'] }}</td></tr>
                    </tbody>
                </table>
        @elseif ($key === 'categories')
                <h2>Volume by Category</h2>
                @if (empty($reportData['category_volumes']['rows']))
                    <p class="empty">No category data in this period.</p>
                @else
                    <table class="data">
                        <thead><tr><th>Category</th><th class="number">Submitted</th><th class="number">Resolved</th><th class="number">Informational</th><th class="number">Invalid</th><th class="number">Unclassified</th></tr></thead>
                        <tbody>
                            @foreach ($reportData['category_volumes']['rows'] as $row)
                                <tr><td>{{ $row['name'] }}</td>@foreach (['submitted', 'resolved', 'informational', 'invalid', 'unclassified'] as $segment)<td class="number">{{ $row['segments'][$segment] }}</td>@endforeach</tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
        @elseif ($key === 'statuses')
            @php $statuses = $rows($reportData['status_distribution'] ?? null); @endphp
                <h2>Status and Classification</h2>
                <h3>Status</h3>
                <table class="data">
                    <thead><tr><th>Status</th><th class="number">Tickets</th></tr></thead>
                    <tbody>
                        @foreach ($statuses as $label => $tickets)
                            <tr><td>{{ $label }}</td><td class="number">{{ $tickets }}</td></tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr><td>Total</td><td class="number">{{ array_sum($statuses) }}</td></tr></tfoot>
                </table>
                <h3>Classification</h3>
                <table class="data">
                    <thead><tr><th>Classification</th><th class="number">Tickets</th></tr></thead>
                    <tbody>
                        @foreach ($reportData['classification_distribution'] ?? [] as $label => $tickets)
                            <tr><td>{{ $label }}</td><td class="number">{{ $tickets }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
        @else
            @php [$title, $about, $column, $valueColumn, $data, $emptyText, $note] = $tables[$key]; @endphp
                @php
                    $isStudentAttributeBreakdown = in_array($key, $studentAttributeBreakdownKeys, true);
                    $tableTotal = array_sum($data);
                @endphp
                <h2>{{ $title }}</h2>
                @if (filled($about))<p class="about">{{ $about }}</p>@endif
                @if (empty($data))
                    <p class="empty">{{ $emptyText }}</p>
                @else
                    <table class="data">
                        <thead>
                            <tr>
                                <th>{{ $column }}</th><th class="number">{{ $valueColumn }}</th>
                                @if ($isStudentAttributeBreakdown)<th class="number">Share</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data as $label => $value)
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td class="number">{{ $value }}</td>
                                    @if ($isStudentAttributeBreakdown)<td class="number">{{ $percent($value, $tableTotal) }}%</td>@endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
                @if ($isStudentAttributeBreakdown && $sensitiveNotBrokenDown > 0)
                    <p class="note">Sensitive categories (not broken down): {{ $sensitiveNotBrokenDown }}</p>
                @endif
                @if ($note)<p class="note">{{ $note }}</p>@endif
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
