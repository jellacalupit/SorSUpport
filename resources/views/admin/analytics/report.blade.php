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
