<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\EmailNotification;
use App\Models\Recipient;
use App\Models\Ticket;
use App\Models\TicketThread;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            ($ticket->classification === Ticket::CLASSIFICATION_INFORMATIONAL && $ticket->jurisdiction === Ticket::JURISDICTION_RECIPIENT && $ticket->forwarded_at === null) ||
            ($ticket->classification === Ticket::CLASSIFICATION_NEEDS_RESOLUTION),
            404
        );

        $ticket->load([
            'complaint.student.user',
            'complaint.category.recipient.user',
            'assignee',
            'currentHandler',
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

    /**
     * Assign or reassign a needs-resolution ticket to an admin or recipient handler.
     */
    public function assign(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless(
            $ticket->classification === Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
            404,
            'Only needs-resolution tickets can be assigned.'
        );

        $validated = $request->validate([
            'assignment_mode' => 'required|in:direct,recipient',
            'recipient_id' => 'nullable|exists:recipients,id',
        ]);

        $recipient = null;
        $assignedUserId = Auth::id();

        if ($validated['assignment_mode'] === 'recipient') {
            $recipient = Recipient::findOrFail($validated['recipient_id']);
            $assignedUserId = $recipient->user_id;
        }

        $deadline = null;
        $category = $ticket->complaint?->category;

        if ($category) {
            $deadline = now()->addDays((int) $category->resolution_deadline_days);
        }

        DB::transaction(function () use ($ticket, $recipient, $assignedUserId, $deadline): void {
            $thread = $ticket->thread;

            if (! $thread) {
                $thread = TicketThread::create([
                    'ticket_id' => $ticket->id,
                    'is_active' => true,
                ]);
            } else {
                $thread->update(['is_active' => true]);
            }

            $ticket->update([
                'status' => Ticket::STATUS_ASSIGNED,
                'assigned_to' => $recipient ? $assignedUserId : null,
                'current_handler_id' => $assignedUserId,
                'deadline' => $deadline,
            ]);

            $action = $recipient ? 'ticket_assigned' : 'ticket_assigned';
            $details = $recipient
                ? "Ticket assigned to {$recipient->user->name} for recipient handling."
                : 'Ticket assigned to SDS admin for direct handling.';

            AuditLog::create([
                'ticket_id' => $ticket->id,
                'performed_by' => Auth::id(),
                'action' => $action,
                'details' => $details,
            ]);

            if ($recipient && $recipient->user?->email) {
                EmailNotification::create([
                    'ticket_id' => $ticket->id,
                    'recipient_email' => $recipient->user->email,
                    'type' => EmailNotification::TYPE_RECIPIENT_ASSIGNMENT,
                    'status' => EmailNotification::STATUS_PENDING,
                ]);
            }

            if ($ticket->complaint?->student?->user?->email) {
                EmailNotification::create([
                    'ticket_id' => $ticket->id,
                    'recipient_email' => $ticket->complaint->student->user->email,
                    'type' => EmailNotification::TYPE_STUDENT_STATUS_UPDATE,
                    'status' => EmailNotification::STATUS_PENDING,
                ]);
            }
        });

        return redirect()
            ->route('admin.tickets.review.show', $ticket)
            ->with('success', $recipient ? 'Ticket assigned to recipient.' : 'Ticket assigned for direct handling.');
    }

    /**
     * Acknowledge an assigned ticket by admin handler (Assigned -> In Progress)
     */
    public function acknowledge(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->status === Ticket::STATUS_ASSIGNED, 404);

        // Only current handler may acknowledge
        if ($ticket->current_handler_id !== Auth::id()) {
            abort(403, 'You are not authorized to acknowledge this ticket.');
        }

        DB::transaction(function () use ($ticket) {
            $ticket->update([
                'status' => Ticket::STATUS_IN_PROGRESS,
                'acknowledged_at' => now(),
            ]);

            AuditLog::create([
                'ticket_id' => $ticket->id,
                'performed_by' => Auth::id(),
                'action' => 'ticket_acknowledged',
                'details' => 'Ticket acknowledged by admin handler.',
            ]);

            if ($ticket->complaint?->student?->user?->email) {
                EmailNotification::create([
                    'ticket_id' => $ticket->id,
                    'recipient_email' => $ticket->complaint->student->user->email,
                    'type' => EmailNotification::TYPE_STUDENT_STATUS_UPDATE,
                    'status' => EmailNotification::STATUS_PENDING,
                ]);
            }
        });

        return redirect()->route('admin.tickets.review.show', $ticket)
            ->with('success', 'Ticket acknowledged.');
    }

    /**
     * Close a ticket (Admin-only) after resolution.
     */
    public function close(Request $request, Ticket $ticket): RedirectResponse
    {
        // Only allow closing tickets that are resolved or in_progress
        if (! in_array($ticket->status, [Ticket::STATUS_RESOLVED, Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_ASSIGNED])) {
            abort(404);
        }

        DB::transaction(function () use ($ticket) {
            $ticket->update([
                'status' => Ticket::STATUS_CLOSED,
                'closed_at' => now(),
            ]);

            if ($ticket->thread) {
                $ticket->thread->update(['is_active' => false]);
            }

            AuditLog::create([
                'ticket_id' => $ticket->id,
                'performed_by' => Auth::id(),
                'action' => 'ticket_closed',
                'details' => 'Ticket closed by admin.',
            ]);

            // Notify student
            if ($ticket->complaint?->student?->user?->email) {
                EmailNotification::create([
                    'ticket_id' => $ticket->id,
                    'recipient_email' => $ticket->complaint->student->user->email,
                    'type' => EmailNotification::TYPE_COMPLAINT_CLOSED,
                    'status' => EmailNotification::STATUS_PENDING,
                ]);
            }

            // Notify recipient if assigned and is a recipient user
            if ($ticket->assigned_to) {
                $recipientModel = \App\Models\Recipient::query()->where('user_id', $ticket->assigned_to)->first();
                if ($recipientModel && $recipientModel->user?->email) {
                    EmailNotification::create([
                        'ticket_id' => $ticket->id,
                        'recipient_email' => $recipientModel->user->email,
                        'type' => EmailNotification::TYPE_STATUS_UPDATE,
                        'status' => EmailNotification::STATUS_PENDING,
                    ]);
                }
            }
        });

        return redirect()->route('admin.tickets.review.show', $ticket)
            ->with('success', 'Ticket closed successfully.');
    }
}
