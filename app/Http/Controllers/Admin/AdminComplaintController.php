<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ThreadMessage;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use App\Services\TicketUnreadService;

class AdminComplaintController extends Controller
{
    /**
     * Display all ticket-backed complaints, including anonymous submissions.
     */
    public function index(Request $request): View
    {
        $query = Complaint::query()
            ->with([
                'category.recipient.user',
                'student.user',
                'ticket',
                'ticket.assignee',
                'ticket.currentHandler',
                'ticket.thread',
            ])
            ;

        if ($request->filled('search_reference')) {
            $reference = $request->input('search_reference');
            $query->where('reference_number', 'like', '%' . $reference . '%');
        }

        if ($request->filled('search_student')) {
            $student = $request->input('search_student');
            $query->whereHas('student.user', function ($q) use ($student) {
                $q->where('name', 'like', '%' . $student . '%');
            });
        }

        if ($request->filled('search_subject')) {
            $subject = $request->input('search_subject');
            $query->where('subject_title', 'like', '%' . $subject . '%');
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', '%' . $search . '%')
                    ->orWhere('subject_title', 'like', '%' . $search . '%')
                    ->orWhereHas('student.user', fn ($student) => $student->where('name', 'like', '%' . $search . '%'))
                    ->orWhereHas('category.recipient.user', fn ($recipient) => $recipient->where('name', 'like', '%' . $search . '%'));
            });
        }

        if ($request->filled('category_filter')) {
            $query->where('category_id', $request->input('category_filter'));
        }

        if ($request->filled('filed_from')) {
            $query->whereDate('created_at', '>=', $request->input('filed_from'));
        }

        if ($request->filled('filed_to')) {
            $query->whereDate('created_at', '<=', $request->input('filed_to'));
        }

        if ($request->filled('holder')) {
            $holder = $request->input('holder');
            $query->whereHas('ticket.currentHandler', fn ($handler) => $handler->where('name', 'like', '%' . $holder . '%'));
        }

        if ($request->filled('classification_filter')) {
            $query->whereHas('ticket', fn ($ticketQuery) => $ticketQuery->where('classification', $request->input('classification_filter')));
        }

        if ($request->filled('status_filter')) {
            $query->whereHas('ticket', fn ($ticketQuery) => $ticketQuery->where('status', $request->input('status_filter')));
        }

        $sort = $request->input('sort', 'latest_update');
        $ticketUpdatedAt = Ticket::query()
            ->select('updated_at')
            ->whereColumn('tickets.complaint_id', 'complaints.id')
            ->limit(1);
        $ticketDeadline = Ticket::query()
            ->select('deadline')
            ->whereColumn('tickets.complaint_id', 'complaints.id')
            ->limit(1);

        if ($sort === 'oldest_update') {
            $query->orderBy($ticketUpdatedAt, 'asc');
        } elseif ($sort === 'latest_submitted') {
            $query->orderByDesc('complaints.created_at');
        } elseif ($sort === 'oldest_submitted') {
            $query->orderBy('complaints.created_at', 'asc');
        } elseif ($sort === 'deadline_urgency') {
            $query->orderByRaw('CASE WHEN (' . $ticketDeadline->toSql() . ') IS NULL THEN 1 ELSE 0 END ASC', $ticketDeadline->getBindings())
                ->orderBy($ticketDeadline, 'asc')
                ->orderByDesc($ticketUpdatedAt);
        } else {
            $query->orderByDesc($ticketUpdatedAt);
        }

        $complaints = $query->paginate(10)->appends($request->query());

        $categories = ComplaintCategory::query()->where('is_active', true)->orderBy('name')->get();
        $holders = User::query()
            ->whereIn('role', [User::ROLE_RECIPIENT, User::ROLE_SDS_ADMIN])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['name']);

        return view('admin.complaints.index', compact('complaints', 'categories', 'holders'));
    }

    /**
     * Show identified complaint details.
     */
    public function show(Complaint $complaint): View
    {
        abort_if($complaint->is_anonymous, 404);

        $complaint->load([
            'student.user',
            'category.recipient.user',
            'ticket.assignee',
            'ticket.thread.messages.sender',
            'ticket.auditLogs.performer',
        ]);

        app(TicketUnreadService::class)->markTicketsViewed(Auth::user(), [(string) $complaint->id]);

        // Identified complaints should have tickets; if not, still show what we have.
        return view('admin.complaints.show', compact('complaint'));
    }

    /**
     * Store a reply message to the complaint thread.
     */
    public function storeReply(Request $request, Complaint $complaint): RedirectResponse
    {
        Log::debug('Admin\\AdminComplaintController@storeReply called', ['user_id' => Auth::id(), 'complaint_id' => $complaint->id ?? null]);

        Log::debug('Admin\\AdminComplaintController complaint state', [
            'is_anonymous' => $complaint->is_anonymous ?? null,
            'has_ticket' => (bool) $complaint->ticket,
        ]);

        abort_if($complaint->is_anonymous, 404);

        // Authorization: ensure the complaint has a ticket and thread
        $ticket = $complaint->ticket;

        if (! $ticket) {
            abort(403, 'This complaint has no associated ticket.');
        }

        // Check if thread is active (not closed)
        $thread = $ticket->thread;

        if (! $thread || ! $thread->is_active) {
            Log::debug('Admin\\AdminComplaintController@storeReply returning closed error', ['user_id' => Auth::id(), 'complaint_id' => $complaint->id ?? null]);
            return redirect()->route('admin.complaints.show', $complaint)
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

        try {
            DB::transaction(function () use ($ticket, $validated, $attachmentPath, $attachmentName) {
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
                    'Admin posted a reply message.'
                );
            });
        } catch (\Throwable $e) {
            Log::error('Admin\\AdminComplaintController@storeReply exception', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }

        Log::debug('Admin\\AdminComplaintController@storeReply returning redirect', ['user_id' => Auth::id()]);
        return redirect()->route('admin.complaints.show', $complaint)
            ->with('success', 'Your message has been posted successfully.');
    }
}


