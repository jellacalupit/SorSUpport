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
        $user = Auth::user();
        $recipient = $user->recipient;

        if (! $recipient) {
            abort(403, 'Recipient profile not found.');
        }

        $firstName = $user->given_name;

        // Get statistics
        $totalCount = Ticket::where('assigned_to', Auth::id())->count();
        $inProgressCount = Ticket::where('assigned_to', Auth::id())
            ->whereIn('status', ['assigned', 'in_progress', 'referred'])
            ->count();
        $resolvedCount = Ticket::where('assigned_to', Auth::id())
            ->where('status', 'resolved')
            ->count();
        $escalatedCount = Ticket::where('assigned_to', Auth::id())
            ->where('status', 'escalated')
            ->count();
        $closedCount = Ticket::where('assigned_to', Auth::id())
            ->where('status', 'closed')
            ->count();

        // Show the five most recently updated tickets assigned to or forwarded to this recipient.
        $latestComplaints = Ticket::query()
            ->where(fn ($query) => $query
                ->where('assigned_to', $user->id)
                ->orWhere('forwarded_to', $recipient->id))
            ->with([
                'complaint.student.user',
                'complaint.category',
                'auditLogs',
                'forwardedRecipient',
            ])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        return view('recipient.dashboard', compact(
            'firstName',
            'recipient',
            'totalCount',
            'inProgressCount',
            'resolvedCount',
            'escalatedCount',
            'closedCount',
            'latestComplaints'
        ));
    }
}