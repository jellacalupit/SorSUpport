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
        'performance' => 'Staff and office performance',
        'categories' => 'Tickets by category',
        'resolution_rate' => 'Resolution rate',
        'resolution_time' => 'How long tickets took to resolve',
        'escalation' => 'Escalations by month',
        'statuses' => 'Tickets by status',
        'colleges' => 'Tickets by college',
        'programs' => 'Tickets by program',
        'year_levels' => 'Tickets by year level',
        'blocks' => 'Tickets by block',
        'origin' => 'Identified and anonymous origin',
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
            'status' => ['nullable', 'in:submitted,needs_clarification,assigned,in_progress,escalated,referred,resolved,closed'],
            'unit' => ['nullable', 'string', 'max:255'],
            'recipient_id' => ['nullable', 'exists:users,id'],
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'filter_year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'period' => ['nullable', 'in:all_time,this_month,last_month,last_3_months,this_year,custom_range'],
            'filter_month' => ['nullable', 'in:custom,1,2,3,4,5,6,7,8,9,10,11,12'],
        ]);
        $filters = $this->keepEnabledStatusFilter($filters);

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

        $dashboard = $analytics->getDashboardData($filters);

        return view('admin.analytics.dashboard', [
            'filters' => $filters,
            'categoryOptions' => $categoryOptions,
            'dashboard' => $dashboard,
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
        ])->setPaper([0, 0, 612, 936]);

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
            'status' => ['nullable', 'in:submitted,needs_clarification,assigned,in_progress,escalated,referred,resolved,closed'],
            'unit' => ['nullable', 'string', 'max:255'],
            'recipient_id' => ['nullable', 'exists:users,id'],
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'filter_year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'period' => ['nullable', 'in:all_time,this_month,last_month,last_3_months,this_year,custom_range'],
            'date_range' => ['nullable', 'string'],
        ]);
        $filters = $this->keepEnabledStatusFilter($filters);

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

    /**
     * Status is a display filter only for Needs Resolution and Unclassified tickets.
     */
    protected function keepEnabledStatusFilter(array $filters): array
    {
        $classification = $filters['classification'] ?? null;
        if (! in_array($classification, [\App\Models\Ticket::CLASSIFICATION_NEEDS_RESOLUTION, 'unclassified'], true)
            || ($classification === 'unclassified'
                && filled($filters['status'] ?? null)
                && ! in_array($filters['status'], \App\Models\Ticket::UNCLASSIFIED_DISPLAY_STATUSES, true))) {
            unset($filters['status']);
        }

        return $filters;
    }
}
