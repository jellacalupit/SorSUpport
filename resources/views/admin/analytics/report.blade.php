<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; }
        .section { margin-bottom: 20px; }
        .section h2 { font-size: 16px; margin-bottom: 8px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        .table th { background: #f4f4f4; }
    </style>
</head>
<body>
    <h1>Analytics Report</h1>

    <div class="section">
        <h2>Applied Filters</h2>
        <table class="table">
            <tbody>
                <tr>
                    <th>Start Date</th>
                    <td>{{ $filters['start_date'] ?? 'All' }}</td>
                </tr>
                <tr>
                    <th>End Date</th>
                    <td>{{ $filters['end_date'] ?? 'All' }}</td>
                </tr>
                <tr>
                    <th>Category</th>
                    <td>{{ optional(App\Models\ComplaintCategory::find($filters['category_id'] ?? null))->name ?? 'All' }}</td>
                </tr>
                <tr>
                    <th>Classification</th>
                    <td>{{ match ($filters['classification'] ?? null) {
                        'needs_resolution' => 'Needs Resolution',
                        'informational' => 'Informational',
                        'invalid' => 'Invalid',
                        default => 'All',
                    } }}</td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td>{{ ucfirst(str_replace('_', ' ', $filters['status'] ?? 'All')) }}</td>
                </tr>
                <tr>
                    <th>Department</th>
                    <td>{{ $filters['department'] ?? 'All' }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Summary Statistics</h2>
        <table class="table">
            <tbody>
                <tr><th>Total Complaints</th><td>{{ $reportData['summary']['total_complaints'] }}</td></tr>
                <tr><th>Total Tickets</th><td>{{ $reportData['summary']['total_tickets'] }}</td></tr>
                <tr><th>Resolution Rate</th><td>{{ $reportData['summary']['resolution_rate'] }}%</td></tr>
                <tr><th>Escalated Tickets</th><td>{{ $reportData['summary']['escalated_tickets'] }}</td></tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Complaint Totals by Category</h2>
        <table class="table">
            <thead>
                <tr><th>Category</th><th>Count</th></tr>
            </thead>
            <tbody>
                @foreach ($reportData['category_breakdown']['labels'] as $index => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        <td>{{ $reportData['category_breakdown']['data'][$index] ?? 0 }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Resolution Rate</h2>
        <table class="table">
            <thead>
                <tr><th>Status</th><th>Count</th></tr>
            </thead>
            <tbody>
                @foreach ($reportData['resolution_chart']['labels'] as $index => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        <td>{{ $reportData['resolution_chart']['data'][$index] ?? 0 }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Average Resolution Time by Category</h2>
        <table class="table">
            <thead>
                <tr><th>Category</th><th>Hours</th></tr>
            </thead>
            <tbody>
                @foreach ($reportData['average_resolution_time']['labels'] as $index => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        <td>{{ $reportData['average_resolution_time']['data'][$index] ?? 0 }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Escalation Frequency</h2>
        <table class="table">
            <thead>
                <tr><th>Period</th><th>Count</th></tr>
            </thead>
            <tbody>
                @foreach ($reportData['escalation_frequency']['labels'] as $index => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        <td>{{ $reportData['escalation_frequency']['data'][$index] ?? 0 }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Active Ticket Status Counts</h2>
        <table class="table">
            <thead>
                <tr><th>Status</th><th>Count</th></tr>
            </thead>
            <tbody>
                @foreach ($reportData['status_distribution']['labels'] as $index => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        <td>{{ $reportData['status_distribution']['data'][$index] ?? 0 }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
