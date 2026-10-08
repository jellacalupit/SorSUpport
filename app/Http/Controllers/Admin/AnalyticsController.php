<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ComplaintCategory;
use App\Models\Complaint;
use App\Models\Ticket;
use App\Exports\AnalyticsReportExport;
use App\Services\AnalyticsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AnalyticsController extends Controller
{
    /**
     * The parts of the analytics report the admin can choose to generate, in printing order.
     */
    public const REPORT_SECTIONS = [
        'summary' => 'Summary',
        'categories' => 'Tickets by category',
        'resolution_rate' => 'Resolution rate',
        'resolution_time' => 'Average resolution time by category',
        'escalation' => 'Escalation frequency',
        'statuses' => 'Tickets by status',
        'colleges' => 'Tickets by college',
        'programs' => 'Tickets by program',
        'resolution_types' => 'How tickets were resolved',
        'closure_reasons' => 'Why tickets were closed',
        'escalation_levels' => 'Escalation level',
        'satisfaction' => 'Student satisfaction',
    ];

    public function index(Request $request, AnalyticsService $analytics)
    {
        $request->mergeIfMissing([
            'year' => now()->year,
            'filter_year' => now()->year,
            'period' => 'all_time',
        ]);

        $filters = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'category_id' => ['nullable', 'exists:complaint_categories,id'],
            'classification' => ['nullable', 'in:needs_resolution,informational,invalid,unclassified'],
            'status' => ['nullable', 'string'],
            'unit' => ['nullable', 'string', 'max:255'],
            'recipient_id' => ['nullable', 'exists:users,id'],
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'filter_year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'period' => ['nullable', 'in:all_time,this_month,last_month,last_3_months,this_year,custom_range'],
            'filter_month' => ['nullable', 'in:custom,1,2,3,4,5,6,7,8,9,10,11,12'],
        ]);

        $filters['period'] = $request->input('period', 'all_time');
        $filters['year'] = (int) ($request->input('year', $request->input('filter_year', now()->year)));
        $filters['filter_year'] = $filters['year'];

        if (! $request->filled('start_date') && ! $request->filled('end_date')) {
            $period = $filters['period'];
            $year = $filters['year'];

            if ($period === 'this_month') {
                $start = now()->setYear($year)->startOfMonth();
                $end = now()->setYear($year)->endOfMonth();
            } elseif ($period === 'last_month') {
                $start = now()->setYear($year)->subMonth()->startOfMonth();
                $end = now()->setYear($year)->subMonth()->endOfMonth();
            } elseif ($period === 'last_3_months') {
                $start = now()->setYear($year)->subMonths(2)->startOfMonth();
                $end = now()->setYear($year)->endOfMonth();
            } elseif ($period === 'this_year') {
                $start = now()->setYear($year)->startOfYear();
                $end = now()->setYear($year)->endOfYear();
            } else {
                $start = null;
                $end = null;
            }

            if ($start && $end) {
                $filters['start_date'] = $start->toDateString();
                $filters['end_date'] = $end->toDateString();
            }
        }

        $categoryOptions = ComplaintCategory::query()
            ->orderBy('name')
            ->get();

        $volumeChartCategories = ComplaintCategory::query()
            ->where(fn ($query) => $query->where('is_active', true)->orWhereHas('complaints'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($category) => ['id' => (int) $category->id, 'name' => $category->name])
            ->values();
        $activeTickets = Ticket::query()
            ->with(['complaint', 'currentHandler', 'assignee'])
            ->whereIn('status', \App\Models\Ticket::ACTIVE_STATUSES)
            ->when(! empty($filters['category_id']), fn ($query) => $query->whereHas('complaint', fn ($complaint) => $complaint->where('category_id', $filters['category_id'])))
            ->when(! empty($filters['start_date']), fn ($query) => $query->whereHas('complaint', fn ($complaint) => $complaint->whereDate('created_at', '>=', $filters['start_date'])))
            ->when(! empty($filters['end_date']), fn ($query) => $query->whereHas('complaint', fn ($complaint) => $complaint->whereDate('created_at', '<=', $filters['end_date'])))
            ->orderByDesc('updated_at')
            ->paginate(10)
            ->withQueryString();

        $dashboard = $analytics->getDashboardData($filters);
        $volumeChartPoints = $dashboard['tickets']
            ->filter(fn ($ticket) => $ticket->complaint?->created_at && $ticket->complaint?->category_id)
            ->map(fn ($ticket) => [
                'category_id' => (int) $ticket->complaint->category_id,
                'year' => (int) $ticket->complaint->created_at->year,
                'month' => (int) $ticket->complaint->created_at->month,
                'week' => min(4, (int) floor(($ticket->complaint->created_at->day - 1) / 7) + 1),
            ])->values();
        $currentYear = (int) ($filters['filter_year'] ?? now()->year);
        $volumeChartYears = $volumeChartPoints->pluck('year')->push($currentYear)->unique()->sort()->values();

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
            'volumeChart' => ['categories' => $volumeChartCategories, 'points' => $volumeChartPoints, 'years' => $volumeChartYears, 'currentYear' => $currentYear],
            'recipientOptions' => \App\Models\User::query()
                ->whereIn('role', [\App\Models\User::ROLE_RECIPIENT, \App\Models\User::ROLE_SDS_ADMIN])
                ->where('is_active', true)
                ->with('recipient')
                ->orderBy('first_name')
                ->get(),
            'unitOptions' => \App\Models\Recipient::query()
                ->whereNotNull('unit')
                ->where('unit', '<>', '')
                ->distinct()
                ->orderBy('unit')
                ->pluck('unit'),
        ]);
    }

    public function exportPdf(Request $request, AnalyticsService $analytics)
    {
        $filters = $this->validatedExportFilters($request);

        $reportData = $analytics->getReportData($filters);
        $fileName = 'analytics-report-' . now()->format('YmdHis') . '.pdf';

        // Only the parts the admin ticked are printed; a plain link prints all of them.
        $sections = array_values(array_intersect(array_keys(self::REPORT_SECTIONS), (array) $request->input('sections', array_keys(self::REPORT_SECTIONS))));

        if ($sections === []) {
            return back()->withErrors(['sections' => 'Choose at least one part of the report to generate.']);
        }

        $pdf = Pdf::loadView('admin.analytics.report', [
            'reportData' => $reportData,
            'filters' => $filters,
            'sections' => $sections,
            'preparedBy' => $request->user()?->table_name,
        ])->setPaper('letter');

        return $pdf->download($fileName);
    }

    public function exportExcel(Request $request, AnalyticsService $analytics)
    {
        $filters = $this->validatedExportFilters($request);

        $reportData = $analytics->getReportData($filters);
        $fileName = 'analytics-report-' . now()->format('YmdHis') . '.xlsx';

        return Excel::download(new AnalyticsReportExport($reportData), $fileName);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedExportFilters(Request $request): array
    {
        $request->mergeIfMissing([
            'year' => now()->year,
            'filter_year' => now()->year,
            'period' => 'all_time',
        ]);

        $filters = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'category_id' => ['nullable', 'exists:complaint_categories,id'],
            'classification' => ['nullable', 'in:needs_resolution,informational,invalid,unclassified'],
            'status' => ['nullable', 'string'],
            'unit' => ['nullable', 'string', 'max:255'],
            'recipient_id' => ['nullable', 'exists:users,id'],
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'filter_year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'period' => ['nullable', 'in:all_time,this_month,last_month,last_3_months,this_year,custom_range'],
            'date_range' => ['nullable', 'string'],
        ]);

        $period = $request->input('period', $request->input('date_range', 'all_time'));
        $year = (int) $request->input('year', $request->input('filter_year', now()->year));

        $filters['period'] = $period;
        $filters['year'] = $year;
        $filters['filter_year'] = $year;

        if (! $request->filled('start_date') && ! $request->filled('end_date') && $period !== 'all_time') {
            if ($period === 'this_month') {
                $start = now()->setYear($year)->startOfMonth();
                $end = now()->setYear($year)->endOfMonth();
            } elseif ($period === 'last_month') {
                $start = now()->setYear($year)->subMonth()->startOfMonth();
                $end = now()->setYear($year)->subMonth()->endOfMonth();
            } elseif ($period === 'last_3_months') {
                $start = now()->setYear($year)->subMonths(2)->startOfMonth();
                $end = now()->setYear($year)->endOfMonth();
            } else {
                $start = now()->setYear($year)->startOfYear();
                $end = now()->setYear($year)->endOfYear();
            }

            $filters['start_date'] = $start->toDateString();
            $filters['end_date'] = $end->toDateString();
        }

        return $filters;
    }
}
