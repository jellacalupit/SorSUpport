<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ComplaintCategory;
use App\Models\Ticket;
use App\Exports\AnalyticsReportExport;
use App\Services\AnalyticsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AnalyticsController extends Controller
{
    public function reports()
    {
        return view('admin.reports', [
            'categoryOptions' => ComplaintCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function index(Request $request, AnalyticsService $analytics)
    {
        $filters = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'category_id' => ['nullable', 'exists:complaint_categories,id'],
            'status' => ['nullable', 'string'],
            'recipient_id' => ['nullable', 'exists:users,id'],
            'period' => ['nullable', 'in:daily,weekly,monthly'],
        ]);

        if ($request->filled('date_range') && ! $request->filled('start_date')) {
            $filters['date_range'] = $request->input('date_range');
            [$start, $end] = match ($filters['date_range']) {
                'today' => [now()->startOfDay(), now()->endOfDay()],
                'week' => [now()->startOfWeek(), now()->endOfWeek()],
                'month' => [now()->startOfMonth(), now()->endOfMonth()],
                'year' => [now()->startOfYear(), now()->endOfYear()],
                default => [null, null],
            };
            if ($start && $end) { $filters['start_date'] = $start->toDateString(); $filters['end_date'] = $end->toDateString(); }
        }

        $categoryOptions = ComplaintCategory::query()
            ->orderBy('name')
            ->get();

        $activeTickets = Ticket::query()
            ->with(['complaint', 'currentHandler', 'assignee'])
            ->whereIn('status', ['assigned', 'in_progress', 'escalated'])
            ->when(! empty($filters['category_id']), fn ($query) => $query->whereHas('complaint', fn ($complaint) => $complaint->where('category_id', $filters['category_id'])))
            ->when(! empty($filters['start_date']), fn ($query) => $query->whereHas('complaint', fn ($complaint) => $complaint->whereDate('created_at', '>=', $filters['start_date'])))
            ->when(! empty($filters['end_date']), fn ($query) => $query->whereHas('complaint', fn ($complaint) => $complaint->whereDate('created_at', '<=', $filters['end_date'])))
            ->orderBy('deadline')
            ->paginate(10)
            ->withQueryString();

        $dashboard = $analytics->getDashboardData($filters);

        return view('admin.analytics.dashboard', [
            'filters' => $filters,
            'categoryOptions' => $categoryOptions,
            'totalComplaints' => $analytics->getTotalComplaints($filters),
            'totalTickets' => $analytics->getTotalTickets($filters),
            'statusCounts' => $analytics->getTotalTicketsByStatus($filters),
            'complaintVolumeDaily' => $analytics->getComplaintVolumeDaily($filters),
            'complaintVolumeWeekly' => $analytics->getComplaintVolumeWeekly($filters),
            'complaintVolumeMonthly' => $analytics->getComplaintVolumeMonthly($filters),
            'categoryDistribution' => $analytics->getComplaintDistributionByCategory($filters),
            'resolutionRate' => $analytics->getResolutionRate($filters),
            'averageResolutionTime' => $analytics->getAverageResolutionTimePerCategory($filters),
            'escalationFrequency' => $analytics->getEscalationFrequency($filters),
            'activeTicketStatuses' => $analytics->getActiveTicketStatuses($filters),
            'activeTickets' => $activeTickets,
            'dashboard' => $dashboard,
            'recipientOptions' => \App\Models\User::query()
                ->where('role', \App\Models\User::ROLE_RECIPIENT)
                ->where('is_active', true)
                ->whereNotNull('email_verified_at')
                ->with('recipient')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function exportPdf(Request $request, AnalyticsService $analytics)
    {
        $filters = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'category_id' => ['nullable', 'exists:complaint_categories,id'],
        ]);

        $reportData = $analytics->getReportData($filters);
        $fileName = 'analytics-report-' . now()->format('YmdHis') . '.pdf';

        $pdf = Pdf::loadView('admin.analytics.report', [
            'reportData' => $reportData,
            'filters' => $filters,
        ]);

        return $pdf->download($fileName);
    }

    public function exportExcel(Request $request, AnalyticsService $analytics)
    {
        $filters = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'category_id' => ['nullable', 'exists:complaint_categories,id'],
        ]);

        $reportData = $analytics->getReportData($filters);
        $fileName = 'analytics-report-' . now()->format('YmdHis') . '.xlsx';

        return Excel::download(new AnalyticsReportExport($reportData), $fileName);
    }
}
