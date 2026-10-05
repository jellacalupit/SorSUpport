<?php

namespace App\Http\Controllers\Recipient;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Ticket;
use App\Models\ThreadMessage;
use App\Models\TicketThread;
use App\Models\EmailNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use App\Services\TicketUnreadService;

class ComplaintController extends Controller
{
    /**
     * Display all complaints assigned to the recipient.
     */
    public function index(Request $request)
    {
        $recipient = Auth::user()->recipient;

        if (! $recipient) {
            abort(403, 'Recipient profile not found.');
        }

        $query = Ticket::query()
            ->where('assigned_to', Auth::id())
            ->with([
                'complaint.student.user',
                'complaint.category',
                'assignee',
                'auditLogs',
            ]);

        if ($request->filled('search')) {
            $search = trim($request->input('search'));

            $query->where(function ($query) use ($search) {
                $query->whereHas('complaint', function ($complaintQuery) use ($search) {
                    $complaintQuery->where('reference_number', 'like', '%' . $search . '%')
                        ->orWhere('subject_title', 'like', '%' . $search . '%');
                });
            });
        }

        if ($request->filled('status_filter')) {
            $status = $request->input('status_filter');
            $query->whereIn('status', $status === 'in_progress' ? ['assigned', 'in_progress'] : [$status]);
        }

        $sort = $request->input('sort', 'newest');

        $complaints = $query
            ->when($sort === 'oldest', fn ($query) => $query->orderBy('updated_at', 'asc'))
            ->when($sort !== 'oldest', fn ($query) => $query->orderByDesc('updated_at'))
            ->paginate(10)
            ->appends($request->query());

        return response()
            ->view('recipient.complaints.index', compact('complaints'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Display the complaint details page.
     */
    public function show(Complaint $complaint)
    {
        $recipient = Auth::user()->recipient;

        if (! $recipient) {
            abort(403, 'Recipient profile not found.');
        }

        // Authorization: ensure the complaint is assigned to this recipient (user)
        $ticket = $complaint->ticket;

        if (! $ticket || $ticket->assigned_to !== Auth::id()) {
            abort(403, 'You are not authorized to view this complaint.');
        }

        // Eager load relationships
        $complaint->load([
            'student.user',
            'category.recipient.user',
            'ticket.assignee',
            'ticket.auditLogs.performer',
            'ticket.thread.messages.sender',
        ]);

        $unreadService = app(TicketUnreadService::class);
        $unreadService->markTicketsViewed(
            Auth::user(),
            [(string) $complaint->id],
            $unreadService->notificationIdsForTicket(Auth::user(), $complaint->ticket)
        );

        $thread = $complaint->ticket->thread;
        $messages = $thread?->messages()->orderBy('created_at')->get() ?? collect();
        $auditLogs = $complaint->ticket->auditLogs()->orderBy('created_at')->get();

        return response()
            ->view('recipient.complaints.show', compact('complaint', 'messages', 'auditLogs', 'thread'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Store a reply to a complaint.
     */
    public function storeReply(Request $request, Complaint $complaint): RedirectResponse
    {
        Log::debug('Recipient\\ComplaintController@storeReply called', ['user_id' => Auth::id(), 'complaint_id' => $complaint->id ?? null]);

        $recipient = Auth::user()->recipient;

        if (! $recipient) {
            abort(403, 'Recipient profile not found.');
        }

        // Authorization: ensure the complaint is assigned to this recipient (user)
        $ticket = $complaint->ticket;

        if (! $ticket || $ticket->assigned_to !== Auth::id()) {
            abort(403, 'You are not authorized to reply to this complaint.');
        }

        // Check if thread is active (not closed)
        $thread = $ticket->thread;

        if (! $thread || ! $thread->is_active) {
            Log::debug('Recipient\\ComplaintController@storeReply returning closed error', ['user_id' => Auth::id(), 'complaint_id' => $complaint->id ?? null]);
            return redirect()->route('recipient.complaints.show', $complaint)
                ->withErrors([
                    'content' => 'This conversation has been closed and no new messages can be sent.',
                ]);
        }

        $validated = $request->validate([
            'content' => 'nullable|string|max:5000|required_without:file_attachment',
            'file_attachment' => 'nullable|file|mimes:pdf,docx,jpg,jpeg,png,heic|max:10240',
        ]);

        $attachmentPath = null;
        $attachmentName = null;

        if ($request->hasFile('file_attachment')) {
            $attachment = $request->file('file_attachment');
            $attachmentPath = $attachment->store('complaints/replies', 'public');
            $attachmentName = $attachment->getClientOriginalName();
        }

        DB::transaction(function () use ($complaint, $ticket, $validated, $attachmentPath, $attachmentName) {
            $thread = $ticket->thread;

            // Create thread message
            ThreadMessage::create([
                'thread_id' => $thread->id,
                'sender_id' => Auth::id(),
                'content' => $validated['content'] ?? '',
                'file_attachment' => $attachmentPath,
                'file_attachment_name' => $attachmentName,
            ]);

            $ticket->touch();

            // Log the action
            AuditLog::log(
                $ticket->id,
                'message_posted',
                Auth::id(),
                'Recipient posted a reply message.'
            );

            $studentUser = $ticket->complaint?->student?->user;
            if ($studentUser?->email) {
                EmailNotification::create([
                    'ticket_id' => $ticket->id,
                    'recipient_email' => $studentUser->email,
                    'type' => EmailNotification::TYPE_MESSAGE_POSTED,
                    'status' => EmailNotification::STATUS_PENDING,
                ]);
            }
        });

        Log::debug('Recipient\\ComplaintController@storeReply returning redirect', ['user_id' => Auth::id()]);
        return redirect()->route('recipient.complaints.show', $complaint);
    }

    /**
     * Acknowledge an assigned ticket (Assigned -> In Progress)
     */
    public function acknowledge(Request $request, Complaint $complaint): RedirectResponse
    {
        $recipient = Auth::user()->recipient;

        if (! $recipient) {
            abort(403, 'Recipient profile not found.');
        }

        $ticket = $complaint->ticket;

        if (! $ticket || $ticket->assigned_to !== Auth::id()) {
            abort(403, 'You are not authorized to acknowledge this complaint.');
        }

        // Only acknowledge if assigned
        if ($ticket->status !== Ticket::STATUS_ASSIGNED) {
            return back()->withErrors(['status' => 'Ticket cannot be acknowledged in its current state.']);
        }

        DB::transaction(function () use ($ticket) {
            $ticket->update([
                'status' => Ticket::STATUS_IN_PROGRESS,
                'acknowledged_at' => now(),
                'current_handler_id' => Auth::id(),
            ]);

            AuditLog::log(
                $ticket->id,
                'ticket_acknowledged',
                Auth::id(),
                'Ticket acknowledged by recipient.'
            );

            // Notify student of status update
            if ($ticket->complaint?->student?->user?->email) {
                EmailNotification::create([
                    'ticket_id' => $ticket->id,
                    'recipient_email' => $ticket->complaint->student->user->email,
                    'type' => EmailNotification::TYPE_STUDENT_STATUS_UPDATE,
                    'status' => EmailNotification::STATUS_PENDING,
                ]);
            }
        });

        return redirect()->route('recipient.complaints.show', $complaint)
            ->with('success', 'Ticket acknowledged.');
    }

    /**
     * Update the complaint status.
     */
    public function updateStatus(Request $request, Complaint $complaint): RedirectResponse
    {
        $recipient = Auth::user()->recipient;

        if (! $recipient) {
            abort(403, 'Recipient profile not found.');
        }

        // Authorization: ensure the complaint is assigned to this recipient (user)
        $ticket = $complaint->ticket;

        if (! $ticket || $ticket->assigned_to !== Auth::id()) {
            abort(403, 'You are not authorized to update this complaint.');
        }

        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in(['assigned', 'in_progress', 'resolved', 'rejected', 'closed']),
            ],
            'details' => 'nullable|string|max:1000',
            'resolution_message' => 'nullable|string|max:2000',
        ]);

        $oldStatus = $complaint->status;
        $newStatus = $validated['status'];

        DB::transaction(function () use ($complaint, $ticket, $oldStatus, $newStatus, $validated) {
            // Update complaint status
            $complaint->update(['status' => $newStatus]);

            // Update ticket status
            $ticket->update(['status' => $newStatus]);

            // Close thread if ticket is being closed or resolved
            if (in_array($newStatus, ['closed', 'resolved', 'rejected'])) {
                if ($ticket->thread) {
                    $ticket->thread->update(['is_active' => false]);
                }
            }

            // Determine action for audit log
            $action = match($newStatus) {
                'resolved' => 'complaint_resolved',
                'rejected' => 'complaint_rejected',
                'closed' => 'complaint_closed',
                default => 'status_changed',
            };

            $details = $validated['details'] ?? "Status changed from {$oldStatus} to {$newStatus}";

            // If resolved, record resolved_at and optionally create a thread message with the resolution
            if ($newStatus === 'resolved') {
                $ticket->update(['resolved_at' => now()]);

                if (! empty($validated['resolution_message'])) {
                    ThreadMessage::create([
                        'thread_id' => $ticket->thread?->id,
                        'sender_id' => Auth::id(),
                        'content' => $validated['resolution_message'],
                    ]);
                }

                // Notify admin (SDS admin) that recipient resolved the ticket
                $sdsAdminUser = \App\Models\User::query()->where('role', \App\Models\User::ROLE_SDS_ADMIN)->first();

                if ($sdsAdminUser && $sdsAdminUser->email) {
                    Log::debug('Creating EmailNotification for admin', ['ticket_id' => $ticket->id, 'email' => $sdsAdminUser->email, 'type' => EmailNotification::TYPE_STATUS_UPDATE]);
                    try {
                        $schema = DB::select("SELECT sql FROM sqlite_master WHERE name = 'email_notifications'");
                        Log::debug('email_notifications schema', ['schema' => $schema]);
                    } catch (\Exception $e) {
                        Log::debug('Unable to read sqlite schema', ['error' => $e->getMessage()]);
                    }
                    EmailNotification::create([
                        'ticket_id' => $ticket->id,
                        'recipient_email' => $sdsAdminUser->email,
                        'type' => EmailNotification::TYPE_RECIPIENT_RESOLVED,
                        'status' => EmailNotification::STATUS_PENDING,
                    ]);
                }

                // Notify student of resolution
                if ($ticket->complaint?->student?->user?->email) {
                    EmailNotification::create([
                        'ticket_id' => $ticket->id,
                        'recipient_email' => $ticket->complaint->student->user->email,
                        'type' => EmailNotification::TYPE_STUDENT_STATUS_UPDATE,
                        'status' => EmailNotification::STATUS_PENDING,
                    ]);
                }
            }

            if ($newStatus !== 'resolved' && $ticket->complaint?->student?->user?->email) {
                EmailNotification::create([
                    'ticket_id' => $ticket->id,
                    'recipient_email' => $ticket->complaint->student->user->email,
                    'type' => EmailNotification::TYPE_STUDENT_STATUS_UPDATE,
                    'status' => EmailNotification::STATUS_PENDING,
                ]);
            }

            // Create audit log
            AuditLog::log(
                $ticket->id,
                $action,
                Auth::id(),
                $details
            );
        });

        return back()->with('success', 'Complaint status updated successfully.');
    }
}
