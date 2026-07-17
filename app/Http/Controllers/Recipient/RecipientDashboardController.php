<?php

namespace App\Http\Controllers\Recipient;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RecipientDashboardController extends Controller
{
    /**
     * Display the Recipient dashboard.
     */
    public function index(): View
    {
        $recipient = Auth::user()->recipient;

        if (! $recipient) {
            abort(403, 'Recipient profile not found.');
        }

        // Get statistics
        $totalAssigned = Ticket::where('assigned_to', Auth::id())->count();
        $pendingCount = Ticket::where('assigned_to', Auth::id())
            ->where('status', 'pending')
            ->count();
        $inProgressCount = Ticket::where('assigned_to', Auth::id())
            ->where('status', 'in_progress')
            ->count();
        $resolvedCount = Ticket::where('assigned_to', Auth::id())
            ->where('status', 'resolved')
            ->count();

        // Get latest assigned complaints
        $latestComplaints = Ticket::query()
            ->where('assigned_to', Auth::id())
            ->with([
                'complaint.student.user',
                'complaint.category',
            ])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('recipient.dashboard', compact(
            'totalAssigned',
            'pendingCount',
            'inProgressCount',
            'resolvedCount',
            'latestComplaints'
        ));
    }
}