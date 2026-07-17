<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Recipient;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminTicketReviewController extends Controller
{
    /**
     * Display list of pending tickets awaiting validity determination.
     */
    public function index(): View
    {
        $tickets = Ticket::query()
            ->where('status', Ticket::STATUS_PENDING)
            ->whereNull('classification')
            ->with([
                'complaint.student.user',
                'complaint.category.recipient.user',
                'assignee',
            ])
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('admin.tickets.review.index', compact('tickets'));
    }

    /**
     * Show ticket details for review.
     */
    public function show(Ticket $ticket): View
    {
        // Allow viewing unclassified pending tickets or classified informational tickets awaiting forward
        abort_unless(
            ($ticket->status === Ticket::STATUS_PENDING && $ticket->classification === null) ||
            ($ticket->classification === Ticket::CLASSIFICATION_INFORMATIONAL && $ticket->jurisdiction === Ticket::JURISDICTION_RECIPIENT && $ticket->forwarded_at === null),
            404
        );

        $ticket->load([
            'complaint.student.user',
            'complaint.category.recipient.user',
            'assignee',
            'forwardedRecipient',
            'auditLogs.performer',
        ]);

        // Get category default jurisdiction if available
        $defaultJurisdiction = $ticket->complaint->category?->default_jurisdiction;

        // Get all recipients for forward option
        $recipients = Recipient::with('user')->orderBy('id')->get();

        return view('admin.tickets.review.show', compact('ticket', 'defaultJurisdiction', 'recipients'));
    }

    /**
     * Reject (close invalid) a ticket with reason.
     */
    public function reject(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->status === Ticket::STATUS_PENDING && $ticket->classification === null, 404);

        $validated = $request->validate([
            'closure_reason' => 'required|string|max:1000',
        ]);

        $ticket->update([
            'status' => Ticket::STATUS_CLOSED,
            'classification' => Ticket::CLASSIFICATION_INVALID,
            'closed_at' => now(),
            'closure_reason' => $validated['closure_reason'],
        ]);

        // Log the action
        AuditLog::create([
            'ticket_id' => $ticket->id,
            'performed_by' => Auth::id(),
            'action' => 'ticket_closed_invalid',
            'details' => "Ticket marked invalid. Reason: {$validated['closure_reason']}",
        ]);

        return redirect()
            ->route('admin.tickets.review.index')
            ->with('success', 'Ticket rejected and student notified.');
    }

    /**
     * Classify ticket as "Needs Resolution" or "Informational" with jurisdiction.
     */
    public function classify(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->status === Ticket::STATUS_PENDING && $ticket->classification === null, 404);

        $validated = $request->validate([
            'classification' => 'required|in:needs_resolution,informational',
            'jurisdiction' => 'required|in:sds,recipient',
        ]);

        // If "Needs Resolution", activate thread immediately
        if ($validated['classification'] === Ticket::CLASSIFICATION_NEEDS_RESOLUTION) {
            // Thread should already exist (created during complaint submission)
            // Just mark it as active/ready
            $thread = $ticket->thread;
            if (!$thread) {
                // Shouldn't happen, but create one just in case
                $ticket->thread()->create(['is_active' => true]);
            } else {
                $thread->update(['is_active' => true]);
            }
        }

        $ticket->update([
            'classification' => $validated['classification'],
            'jurisdiction' => $validated['jurisdiction'],
        ]);

        // Log the action
        AuditLog::create([
            'ticket_id' => $ticket->id,
            'performed_by' => Auth::id(),
            'action' => 'ticket_classified',
            'details' => "Classified as {$validated['classification']} under {$validated['jurisdiction']} jurisdiction.",
        ]);

        return redirect()
            ->route('admin.tickets.review.index')
            ->with('success', 'Ticket classified successfully.');
    }

    /**
     * Forward an informational ticket to a recipient (one-time only).
     */
    public function forward(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless(
            $ticket->status === Ticket::STATUS_PENDING &&
            $ticket->classification === Ticket::CLASSIFICATION_INFORMATIONAL &&
            $ticket->jurisdiction === Ticket::JURISDICTION_RECIPIENT &&
            $ticket->forwarded_at === null,
            404,
            'Ticket cannot be forwarded.'
        );

        $validated = $request->validate([
            'recipient_id' => 'required|exists:recipients,id',
        ]);

        $recipient = Recipient::findOrFail($validated['recipient_id']);

        $ticket->update([
            'forwarded_to' => $validated['recipient_id'],
            'forwarded_at' => now(),
        ]);

        // Log the action
        AuditLog::create([
            'ticket_id' => $ticket->id,
            'performed_by' => Auth::id(),
            'action' => 'ticket_forwarded_to_recipient',
            'details' => "Informational ticket forwarded to {$recipient->user->name} ({$recipient->user->email}).",
        ]);

        // TODO: Send InformationalForwardedMail to recipient (Module 4)

        return redirect()
            ->route('admin.tickets.review.show', $ticket)
            ->with('success', 'Ticket forwarded to recipient.');
    }
}
