<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ThreadMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;

class AdminComplaintController extends Controller
{
    /**
     * Display identified complaints (non-anonymous) queue.
     */
    public function index(Request $request): View
    {
        $query = Complaint::query()
            ->where('is_anonymous', false)
            ->with([
                'category.recipient.user',
                'student.user',
                'ticket',
                'ticket.assignee',
                'ticket.thread',
            ])
            ->orderByDesc('created_at');

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

        if ($request->filled('status_filter')) {
            $query->where('status', $request->input('status_filter'));
        }

        $complaints = $query->paginate(10)->appends($request->query());

        return view('admin.complaints.index', compact('complaints'));
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
            'content' => 'required|string|max:5000',
            'file_attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $attachmentPath = null;

        if ($request->hasFile('file_attachment')) {
            $attachmentPath = $request->file('file_attachment')
                ->store('complaints/replies', 'public');
        }

        try {
            DB::transaction(function () use ($ticket, $validated, $attachmentPath) {
                $thread = $ticket->thread;

                // Create thread message
                ThreadMessage::create([
                    'thread_id' => $thread->id,
                    'sender_id' => Auth::id(),
                    'content' => $validated['content'],
                    'file_attachment' => $attachmentPath,
                ]);

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


