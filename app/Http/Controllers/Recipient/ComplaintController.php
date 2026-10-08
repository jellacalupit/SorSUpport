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
use App\Services\TicketAccess;
use App\Services\TicketWorkflow;

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

        // Tickets assigned to this recipient, and informational ones forwarded to them to read.
        $query = Ticket::query()
            ->where(fn ($tickets) => $tickets
                ->where('assigned_to', Auth::id())
                ->orWhere('forwarded_to', $recipient->id))
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
            $query->where('status', $status);
        }

        $sort = $request->input('sort', 'newest');

        $complaints = $query
            ->when($sort === 'oldest', fn ($query) => $query->orderBy('updated_at', 'asc'))
            ->when($sort !== 'oldest', fn ($query) => $query->orderByDesc('updated_at'))
            ->paginate(15)
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

        // Open to the recipient handling the ticket, or the one it was forwarded to for information.
        $ticket = $complaint->ticket;

        if (! $ticket || ! app(TicketAccess::class)->isRecipientOf(Auth::user(), $ticket)) {
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
            $attachmentPath = $attachment->store('complaints/replies', 'local');
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
    public function acknowledge(Request $request, Complaint $complaint, TicketWorkflow $workflow): RedirectResponse
    {
        $ticket = $this->assignedTicket($complaint);

        if (! $workflow->can(Auth::user(), $ticket, TicketWorkflow::ACKNOWLEDGE)) {
            return back()->withErrors(['status' => 'Ticket cannot be acknowledged in its current state.']);
        }

        $workflow->acknowledge($ticket, Auth::user());

        return redirect()->route('recipient.complaints.show', $complaint)
            ->with('success', 'Ticket acknowledged.');
    }

    /**
     * Mark the ticket resolved. A recipient cannot close or reject a ticket: the student accepts
     * the resolution, or the SDS Office closes it.
     */
    public function updateStatus(Request $request, Complaint $complaint, TicketWorkflow $workflow): RedirectResponse
    {
        $ticket = $this->assignedTicket($complaint);

        $validated = $request->validate([
            'status' => ['required', Rule::in([Ticket::STATUS_RESOLVED])],
            'resolution_type' => ['required', Rule::in(array_keys(Ticket::RESOLUTION_LABELS))],
            'resolution_message' => 'required|string|max:2000',
        ], [
            'status.in' => 'Recipients can only mark a ticket as resolved.',
            'resolution_type.required' => 'Select how the ticket was resolved.',
            'resolution_message.required' => 'Describe the resolution for the student.',
        ]);

        $workflow->resolve($ticket, Auth::user(), $validated['resolution_type'], $validated['resolution_message']);

        return back()->with('success', 'Ticket marked as resolved.');
    }

    /**
     * The ticket of a complaint assigned to the signed-in recipient.
     */
    protected function assignedTicket(Complaint $complaint): Ticket
    {
        abort_unless(Auth::user()->recipient, 403, 'Recipient profile not found.');

        $ticket = $complaint->ticket;

        abort_unless($ticket && (int) $ticket->assigned_to === (int) Auth::id(), 403, 'You are not authorized to update this complaint.');

        return $ticket;
    }
}
