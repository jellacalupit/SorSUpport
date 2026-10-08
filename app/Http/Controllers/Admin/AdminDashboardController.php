<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AdminDashboardController extends Controller
{
    /**
     * Display the SDS Administrator dashboard.
     */
    public function index()
    {
        $admin = Auth::user();
        $tickets = Ticket::query()->with('complaint.category')->get();
        $recentTickets = Ticket::query()
            ->with('complaint')
            ->join('complaints', 'complaints.id', '=', 'tickets.complaint_id')
            ->select('tickets.*')
            ->orderByDesc('complaints.created_at')
            ->limit(10)
            ->get();
        $categoryVolume = ComplaintCategory::query()
            ->withCount(['complaints as ticket_count'])
            ->orderByDesc('ticket_count')
            ->get();
        $recentActivity = AuditLog::query()
            ->forAuditTrail()
            ->with(['performer', 'ticket.complaint.category'])
            ->latest()
            ->limit(5)
            ->get();

        $statusBreakdown = collect(array_keys(Ticket::STATUS_LABELS))
            ->mapWithKeys(fn (string $status) => [$status => Ticket::query()->where('status', $status)->count()])
            ->all();

        $classificationBreakdown = [
            'needs_resolution' => Ticket::query()->where('classification', 'needs_resolution')->count(),
            'informational' => Ticket::query()->whereIn('classification', ['informational', 'anonymous'])->count(),
            'invalid' => Ticket::query()->where('classification', 'invalid')->count(),
        ];

        $volumeChartCategories = ComplaintCategory::query()
            ->where(fn ($query) => $query->where('is_active', true)->orWhereHas('complaints'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($category) => [
                'id' => (int) $category->id,
                'name' => $category->name,
                'short' => explode(' ', trim($category->name))[0] ?: $category->name,
            ])
            ->values();

        $volumeChartPoints = Complaint::query()
            ->whereNotNull('category_id')
            ->get(['category_id', 'created_at'])
            ->map(function ($complaint) {
                $createdAt = $complaint->created_at;

                return [
                    'category_id' => (int) $complaint->category_id,
                    'year' => (int) $createdAt->year,
                    'month' => (int) $createdAt->month,
                    'week' => min(4, (int) floor(($createdAt->day - 1) / 7) + 1),
                ];
            })
            ->values();

        $volumeChartYears = $volumeChartPoints->pluck('year')->unique()->sort()->values();
        $currentYear = (int) now()->year;
        if ($volumeChartYears->isEmpty() || ! $volumeChartYears->contains($currentYear)) {
            $volumeChartYears = $volumeChartYears->push($currentYear)->unique()->sort()->values();
        }

        // The five best resolution rates, taken from the same figures the Analytics page shows
        // (all time): tickets each person holds, and how many needing resolution they resolved.
        $performanceRows = collect(app(\App\Services\AnalyticsService::class)->getDashboardData()['recipients']);
        $performanceUsers = User::query()->with('recipient')->whereIn('id', $performanceRows->pluck('user_id'))->get()->keyBy('id');

        $topPerformingRecipients = $performanceRows
            ->map(function (array $row) use ($performanceUsers) {
                $user = $performanceUsers->get($row['user_id']);

                return (object) [
                    'user' => $user,
                    'display_name' => $row['name'],
                    'unit' => $row['unit'],
                    'designation' => $user?->recipient?->designation ?? 'Not assigned',
                    'staff_id' => $user?->recipient?->staff_id ?? $user?->username ?? 'N/A',
                    'role' => $user?->role === User::ROLE_SDS_ADMIN ? 'Admin' : 'Recipient',
                    'performance_percentage' => (int) round($row['resolved'] / max(1, $row['assigned']) * 100),
                    'resolved_count' => $row['resolved'],
                    'assigned_count' => $row['assigned'],
                ];
            })
            ->sort(fn ($first, $second) => [$second->performance_percentage, $second->resolved_count, $second->assigned_count] <=> [$first->performance_percentage, $first->resolved_count, $first->assigned_count])
            ->take(5)
            ->values();

        return view('admin.dashboard', [
            'admin' => $admin,
            'firstName' => collect(explode(' ', trim($admin->name)))->filter()->first() ?? 'Admin',
            'totalTickets' => $tickets->count(),
            'totalStudents' => User::query()->where('role', User::ROLE_STUDENT)->count(),
            'totalRecipients' => User::query()
                ->whereIn('role', [User::ROLE_RECIPIENT, User::ROLE_SDS_ADMIN])
                ->where('is_active', true)
                ->count(),
            'totalCategories' => ComplaintCategory::query()->where('is_active', true)->count(),
            'recentTickets' => $recentTickets,
            'categoryVolume' => $categoryVolume,
            'recentActivity' => $recentActivity,
            'statusBreakdown' => $statusBreakdown,
            'classificationBreakdown' => $classificationBreakdown,
            'volumeChart' => [
                'categories' => $volumeChartCategories,
                'points' => $volumeChartPoints,
                'years' => $volumeChartYears,
                'currentYear' => $currentYear,
            ],
            'topPerformingRecipients' => $topPerformingRecipients,
        ]);
    }
}