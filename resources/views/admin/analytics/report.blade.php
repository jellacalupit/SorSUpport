@php
    // Which parts of the report were asked for; all of them when none are named.
    $sections = $sections ?? array_keys(\App\Http\Controllers\Admin\AnalyticsController::REPORT_SECTIONS);
    $has = fn (string $key): bool => in_array($key, $sections, true);
    $breakdowns = $reportData['breakdowns'] ?? [];
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
        'Status' => filled($filters['status'] ?? null) ? ucfirst(str_replace('_', ' ', $filters['status'])) : null,
        'College / Office' => $filters['unit'] ?? null,
    ]);

    // label => value tables built from a chart's labels and data.
    $rows = fn (?array $chart): array => collect($chart['labels'] ?? [])->mapWithKeys(fn ($label, $index) => [$label => $chart['data'][$index] ?? 0])->all();

    $tables = [
        'categories' => ['Tickets by Category', 'Category', 'Tickets', $rows($reportData['category_breakdown'] ?? null)],
        'resolution_rate' => ['Resolution Rate', 'Outcome', 'Tickets', $rows($reportData['resolution_chart'] ?? null)],
        'resolution_time' => ['Average Resolution Time by Category', 'Category', 'Average days', $rows($reportData['average_resolution_time'] ?? null)],
        'escalation' => ['Escalation Frequency', 'Category', 'Escalations', $rows($reportData['escalation_frequency'] ?? null)],
        'statuses' => ['Tickets by Status', 'Status', 'Tickets', $rows($reportData['status_distribution'] ?? null)],
        'colleges' => ['Tickets by College', 'College', 'Tickets', $breakdowns['colleges'] ?? []],
        'programs' => ['Tickets by Program', 'Program', 'Tickets', $breakdowns['programs'] ?? []],
        'resolution_types' => ['How Tickets Were Resolved', 'Resolution', 'Tickets', $breakdowns['resolution_types'] ?? []],
        'closure_reasons' => ['Why Tickets Were Closed', 'Reason', 'Tickets', $breakdowns['closure_reasons'] ?? []],
        'escalation_levels' => ['Escalation Level', 'Level', 'Tickets', $breakdowns['escalation_levels'] ?? []],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SorSUpport Analytics Report</title>
    <style>
        /* The margins leave room for the letterhead and the footer band on every page. */
        @page { size: letter; margin: 112pt 40pt 92pt 40pt; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #1f2937; }

        .letterhead { position: fixed; top: -96pt; left: 0; right: 0; height: 84pt; }
        .letterhead table { width: 100%; border-collapse: collapse; }
        .letterhead td { vertical-align: middle; text-align: center; padding: 0; }
        .letterhead .logo { width: 86px; }
        .letterhead .logo img { height: 78px; }
        .letterhead p { margin: 0; font-size: 10.5px; line-height: 1.25; color: #000; }
        .letterhead .strong { font-weight: bold; }
        .letterhead .unit { font-weight: bold; font-size: 12px; }
        .letterhead .place { font-style: italic; }
        .letterhead .rule { border-top: 2px solid #000; margin: 6px 40px 0 40px; }

        /* The band runs edge to edge: 612pt wide, about 80pt tall. */
        .footer { position: fixed; bottom: -92pt; left: -40pt; width: 612pt; height: 81pt; }
        .footer img { width: 612pt; height: 80.3pt; }

        h1 { margin: 0; text-align: center; font-size: 15px; color: #7a1d2a; letter-spacing: 0.3px; }
        .subtitle { margin: 3px 0 0 0; text-align: center; font-size: 10px; color: #4b5563; }
        .filters { margin: 10px 0 14px 0; padding: 6px 9px; border: 1px solid #e5e7eb; background: #faf7f7; font-size: 10px; color: #374151; }
        .filters strong { color: #111827; }

        .section { margin-bottom: 14px; page-break-inside: avoid; }
        h2 { margin: 0 0 5px 0; font-size: 12px; color: #7a1d2a; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #d1d5db; padding: 4px 7px; text-align: left; }
        table.data thead th { background: #7a1d2a; color: #fff; font-size: 10px; }
        table.data tbody th { background: #f3f4f6; width: 55%; font-weight: normal; }
        table.data .number { text-align: right; width: 22%; }
        .empty { color: #6b7280; font-style: italic; }

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
                    <p class="strong">College of Information and Communications Technology</p>
                    <p class="unit">INFORMATION TECHNOLOGY</p>
                    <p class="strong">Bulan Campus</p>
                    <p class="place">Zone 8, Bulan, Sorsogon</p>
                    <p>Tel. No.; 056 311-0103; Email Address: cict@sorsu.edu.ph</p>
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

    @if ($has('summary'))
        <div class="section">
            <h2>Summary</h2>
            <table class="data">
                <tbody>
                    <tr><th>Total tickets filed</th><td class="number">{{ $reportData['summary']['total_complaints'] }}</td></tr>
                    <tr><th>Tickets in the system</th><td class="number">{{ $reportData['summary']['total_tickets'] }}</td></tr>
                    <tr><th>Resolution rate</th><td class="number">{{ $reportData['summary']['resolution_rate'] }}%</td></tr>
                    <tr><th>Escalated tickets</th><td class="number">{{ $reportData['summary']['escalated_tickets'] }}</td></tr>
                </tbody>
            </table>
        </div>
    @endif

    @foreach ($tables as $key => [$title, $column, $valueColumn, $data])
        @if ($has($key))
            <div class="section">
                <h2>{{ $title }}</h2>
                <table class="data">
                    <thead>
                        <tr><th>{{ $column }}</th><th class="number">{{ $valueColumn }}</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($data as $label => $count)
                            <tr><td>{{ $label }}</td><td class="number">{{ $count }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="empty">No data for this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    @endforeach

    @if ($has('waiting'))
        @php
            $waiting = $reportData['waiting'] ?? [];
            $dayText = fn ($value) => match (true) {
                $value === null => 'No data yet',
                $value < 1 => rtrim(rtrim(number_format($value * 24, 1), '0'), '.') . ' hours',
                default => rtrim(rtrim(number_format((float) $value, 1), '0'), '.') . ' days',
            };
        @endphp
        <div class="section">
            <h2>Waiting Time</h2>
            <table class="data">
                <tbody>
                    <tr><th>Average wait before the first review</th><td class="number">{{ $dayText($waiting['review_days'] ?? null) }}</td></tr>
                    <tr><th>Average wait before staff acknowledge</th><td class="number">{{ $dayText($waiting['acknowledge_days'] ?? null) }}</td></tr>
                    <tr><th>Average time from assignment to resolution</th><td class="number">{{ $dayText($waiting['handling_days'] ?? null) }}</td></tr>
                    <tr><th>Tickets awaiting review now</th><td class="number">{{ $waiting['awaiting_review'] ?? 0 }}</td></tr>
                    <tr><th>Open tickets with no action for {{ \App\Support\TicketProgress::ATTENTION_DAYS }} days or more</th><td class="number">{{ $waiting['needing_attention'] ?? 0 }}</td></tr>
                </tbody>
            </table>
        </div>
    @endif

    @if ($has('satisfaction'))
        <div class="section">
            <h2>Student Satisfaction</h2>
            <table class="data">
                <tbody>
                    <tr><th>Average rating</th><td class="number">{{ ($breakdowns['satisfaction']['average'] ?? null) !== null ? $breakdowns['satisfaction']['average'] . ' out of 5' : 'No ratings yet' }}</td></tr>
                    <tr><th>Tickets rated</th><td class="number">{{ $breakdowns['satisfaction']['count'] ?? 0 }}</td></tr>
                    @foreach ($breakdowns['satisfaction']['distribution'] ?? [] as $rating => $count)
                        <tr><th>{{ $rating }} out of 5</th><td class="number">{{ $count }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if (filled($preparedBy ?? null))
        <div class="signature">
            <p>Prepared by:</p>
            <p class="name">{{ $preparedBy }}</p>
            <p>Student Development and Services</p>
        </div>
    @endif
</body>
</html>
