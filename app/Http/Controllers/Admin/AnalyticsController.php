<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ComplaintCategory;
use App\Exports\AnalyticsReportExport;
use App\Services\AnalyticsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AnalyticsController extends Controller
{
    public function index(Request $request, AnalyticsService $analytics)
    {
        $filters = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'category_id' => ['nullable', 'exists:complaint_categories,id'],
        ]);

        $categoryOptions = ComplaintCategory::query()
            ->orderBy('name')
            ->get();

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
