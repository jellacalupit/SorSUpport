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
            ->with(['performer', 'ticket.complaint.category'])
            ->latest()
            ->limit(5)
            ->get();

        $statusBreakdown = [
            'pending' => Ticket::query()->where('status', Ticket::STATUS_PENDING)->count(),
            'assigned' => Ticket::query()->where('status', Ticket::STATUS_ASSIGNED)->count(),
            'in_progress' => Ticket::query()->where('status', Ticket::STATUS_IN_PROGRESS)->count(),
            'escalated' => Ticket::query()->where('status', Ticket::STATUS_ESCALATED)->count(),
            'resolved' => Ticket::query()->where('status', Ticket::STATUS_RESOLVED)->count(),
            'rejected' => Ticket::query()->where('status', Ticket::STATUS_REJECTED)->count(),
            'closed' => Ticket::query()->where('status', Ticket::STATUS_CLOSED)->count(),
        ];

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

        // Get top performing recipients based on resolved/assigned tickets ratio
        $topPerformingRecipients = User::query()
            ->whereIn('role', [User::ROLE_RECIPIENT, User::ROLE_SDS_ADMIN])
            ->where('is_active', true)
            ->whereNotNull('email_verified_at')
            ->with('recipient')
            ->get()
            ->map(function ($user) {
                $assignedCount = Ticket::query()
                    ->where('assigned_to', $user->id)
                    ->count();
                $resolvedCount = Ticket::query()
                    ->where('assigned_to', $user->id)
                    ->where('status', Ticket::STATUS_RESOLVED)
                    ->count();
                
                $performancePercentage = $assignedCount > 0 ? round(($resolvedCount / $assignedCount) * 100) : 0;
                
                $department = $user->recipient?->department ?? 'Not assigned';
                $designation = $user->recipient?->designation ?? 'Not assigned';
                $staffId = $user->recipient?->staff_id ?? $user->username ?? 'N/A';
                
                // Parse name exactly like the profile page does
                $parsedNameParts = array_values(array_filter(preg_split('/\s+/', trim((string) ($user->name ?? ''))) ?: [], static fn ($part) => $part !== ''));
                
                $profileFirstName = '';
                $profileMiddleName = '';
                $profileLastName = '';
                
                if (!empty($parsedNameParts)) {
                    if (count($parsedNameParts) >= 3) {
                        $profileFirstName = implode(' ', array_slice($parsedNameParts, 0, -2));
                        $profileMiddleName = $parsedNameParts[count($parsedNameParts) - 2] ?? '';
                        $profileLastName = $parsedNameParts[count($parsedNameParts) - 1] ?? '';
                    } elseif (count($parsedNameParts) === 2) {
                        $profileFirstName = $parsedNameParts[0] ?? '';
                        $profileLastName = $parsedNameParts[1] ?? '';
                    } else {
                        $profileFirstName = $parsedNameParts[0] ?? '';
                    }
                }
                
                $profileDisplayMiddleInitial = $profileMiddleName !== '' ? strtoupper(substr($profileMiddleName, 0, 1)) . '.' : '';
                $displayName = trim(implode(' ', array_filter([
                    $profileFirstName,
                    $profileDisplayMiddleInitial,
                    $profileLastName,
                ], static fn ($part) => $part !== null && $part !== '')));
                
                return (object) [
                    'user' => $user,
                    'display_name' => $displayName,
                    'department' => $department,
                    'designation' => $designation,
                    'staff_id' => $staffId,
                    'role' => $user->role === User::ROLE_SDS_ADMIN ? 'Admin' : 'Recipient',
                    'performance_percentage' => $performancePercentage,
                    'resolved_count' => $resolvedCount,
                ];
            })
            ->sortByDesc('performance_percentage')
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
                ->whereNotNull('email_verified_at')
                ->count(),
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