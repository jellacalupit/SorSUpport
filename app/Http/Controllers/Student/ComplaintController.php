<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Recipient;
use App\Models\ThreadMessage;
use App\Models\Ticket;
use App\Models\TicketThread;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\EmailNotification;
use App\Services\TicketUnreadService;

use Illuminate\Validation\Rule;
use Illuminate\View\View;


class ComplaintController extends Controller
{
    /**
     * Display all complaints submitted by the authenticated student.
     */
    public function index(Request $request)
    {
        $student = Auth::user()->student;

        if (! $student) {
            abort(403, 'Student profile not found.');
        }

        $status = $request->string('status')->toString();
        $category = $request->string('category')->toString() ?: 'All';
        $search = trim($request->string('search')->toString());

        $complaints = Complaint::query()
            ->where('student_id', $student->id)
            ->with(['category.recipient.user', 'ticket.auditLogs'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('subject_title', 'like', '%' . $search . '%')
                        ->orWhere('reference_number', 'like', '%' . $search . '%');
                });
            })
            ->when($status && $status !== 'All', function ($query) use ($status) {
                $ticketStatuses = match ($status) {
                    'in_progress' => [Ticket::STATUS_ASSIGNED, Ticket::STATUS_IN_PROGRESS],
                    'assigned' => [Ticket::STATUS_ASSIGNED],
                    'resolved' => [Ticket::STATUS_RESOLVED],
                    'closed' => [Ticket::STATUS_CLOSED, Ticket::STATUS_REJECTED],
                    default => [$status],
                };

                $query->where(function ($query) use ($status, $ticketStatuses) {
                    $query->whereHas('ticket', fn ($ticketQuery) => $ticketQuery->whereIn('status', $ticketStatuses))
                        ->orWhere(function ($query) use ($status) {
                            $query->whereDoesntHave('ticket')
                                ->where('status', $status);
                        });
                });
            })
            ->when($category && $category !== 'All', fn ($query) => $query->whereHas('category', fn ($query) => $query->where('name', $category)))
            ->orderByRaw("COALESCE((SELECT MAX(updated_at) FROM tickets WHERE tickets.complaint_id = complaints.id), complaints.created_at) DESC")
            ->paginate(10)
            ->withQueryString();

        $categories = ComplaintCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name');

        return response()
            ->view('student.complaints.index', compact('complaints', 'categories', 'status', 'category', 'search'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Show the complaint submission form.
     */
    public function create(): View
    {
        $categories = ComplaintCategory::query()
            ->where('is_active', true)
            ->with('suggestedRecipients')
            ->orderBy('name')
            ->get();

        $recipients = Recipient::query()
            ->activeVerified()
            ->with('user')
            ->get()
            ->sortBy(fn (Recipient $recipient) => $recipient->user->table_name, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $recipientOptions = $recipients->map(fn (Recipient $recipient) => [
            'id' => (string) $recipient->id,
            'name' => $recipient->user->table_name,
            'detail' => implode(' · ', array_filter([$recipient->designation, $recipient->unit])),
        ]);

        // Recipients the admin configured as suggestions for each category.
        $categoryRecipients = $categories->mapWithKeys(fn (ComplaintCategory $category) => [
            (string) $category->id => $category->suggestedRecipients
                ->pluck('id')
                ->intersect($recipients->pluck('id'))
                ->map(fn ($id) => (string) $id)
                ->values(),
        ]);

        // Categories under which a student may hide their identity.
        $hiddenIdentityCategories = $categories
            ->where('allows_hidden_identity', true)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values();

        return view('student.complaints.create', compact('categories', 'recipientOptions', 'categoryRecipients', 'hiddenIdentityCategories'));
    }

    /**
     * Store a newly submitted complaint.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => [
                'required',
                Rule::exists('complaint_categories', 'id')
                    ->where('is_active', true),
            ],
            'subject_title' => 'required|string|max:50',
            'personnel_involved' => 'nullable|string|max:255',
            'suggested_recipient_id' => 'nullable|integer',
            'description' => 'required|string|max:5000',
            'file_attachment' => 'nullable|array|max:10',
            'file_attachment.*' => 'file|mimes:pdf,docx,jpg,jpeg,png,heic|max:10240',
            'is_anonymous' => 'nullable|boolean',
        ]);

        $student = Auth::user()->student;

        if (! $student) {
            abort(403, 'Student profile not found.');
        }

        $category = ComplaintCategory::with('recipient.user')
            ->findOrFail($validated['category_id']);

        $recipientUserId = $category->recipient?->user_id;

        if ($category->default_jurisdiction === ComplaintCategory::JURISDICTION_RECIPIENT && ! $recipientUserId) {
            return back()
                ->withInput()
                ->withErrors([
                    'category_id' => 'The selected category has no assigned recipient.',
                ]);
        }

        if (($validated['is_anonymous'] ?? false) && ! $category->allows_hidden_identity) {
            return back()
                ->withInput()
                ->withErrors([
                    'is_anonymous' => 'This category needs your name so the office concerned can act on it.',
                ]);
        }

        $suggestedRecipientId = null;

        if (filled($validated['suggested_recipient_id'] ?? null)) {
            $suggestedRecipientId = Recipient::query()
                ->activeVerified()
                ->whereKey($validated['suggested_recipient_id'])
                ->value('id');

            if (! $suggestedRecipientId) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'suggested_recipient_id' => 'The selected recipient is not available.',
                    ]);
            }
        }

        $attachmentPaths = [];

        if ($request->hasFile('file_attachment')) {
            foreach ($request->file('file_attachment') as $file) {
                $attachmentPaths[] = [
                    'path' => $file->store('complaints', 'public'),
                    'name' => $file->getClientOriginalName(),
                ];
            }
        }

        $isAnonymous = (bool) ($validated['is_anonymous'] ?? false);

        $complaint = DB::transaction(function () use (
            $validated,
            $student,
            $category,
            $recipientUserId,
            $suggestedRecipientId,
            $attachmentPaths,
            $isAnonymous
        ) {
            $complaint = Complaint::create([
                'reference_number' => Complaint::generateReferenceNumber(),
                'student_id' => $student->id,
                'category_id' => $category->id,
                'subject_title' => $validated['subject_title'],
                'personnel_involved' => $validated['personnel_involved'] ?? null,
                'suggested_recipient_id' => $suggestedRecipientId,
                'description' => $validated['description'],
                'file_attachment' => $attachmentPaths ? json_encode($attachmentPaths) : null,
                'is_anonymous' => $isAnonymous,
                'status' => Complaint::STATUS_PENDING,
            ]);

            // Every submission, anonymous or not, waits for the SDS admin's review.
            $ticket = Ticket::create([
                'complaint_id' => $complaint->id,
                'status' => Ticket::STATUS_PENDING,
                'current_handler_id' => User::query()->where('role', User::ROLE_SDS_ADMIN)->orderBy('id')->value('id'),
            ]);

            if ($isAnonymous) {
                // Keep the submitter out of the audit trail.
                AuditLog::create([
                    'ticket_id' => $ticket->id,
                    'performed_by' => null,
                    'action' => 'anonymous_complaint_submitted',
                    'details' => "Anonymous complaint {$complaint->reference_number} submitted for review.",
                ]);
            } else {
                AuditLog::log(
                    $ticket->id,
                    'complaint_submitted',
                    Auth::id(),
                    "Complaint {$complaint->reference_number} submitted."
                );
            }

            // Acknowledge complaint submission to the student.
            if (! $isAnonymous && Auth::user()->email) {
                EmailNotification::create([
                    'ticket_id' => $ticket->id,
                    'recipient_email' => Auth::user()->email,
                    'type' => EmailNotification::TYPE_SUBMISSION_ACK,
                    'status' => EmailNotification::STATUS_PENDING,
                ]);
            }

            // In-app notification record for SDS Admin (no real email sending yet)
            $sdsAdminEmail = optional(Auth::user())->email; // fallback; we resolve below from an SDS admin user
            $sdsAdminUser = \App\Models\User::query()
                ->where('role', \App\Models\User::ROLE_SDS_ADMIN)
                ->first();

            if ($sdsAdminUser) {
                $sdsAdminEmail = $sdsAdminUser->email;
            }

            if ($sdsAdminEmail) {
                EmailNotification::create([
                    'ticket_id' => $ticket->id,
                    'recipient_email' => $sdsAdminEmail,
                    'type' => EmailNotification::TYPE_ASSIGNMENT,
                    'status' => EmailNotification::STATUS_PENDING,
                ]);

            }

            return $complaint;

        });

        return redirect()
            ->route('student.complaints.create')
            ->with('submittedComplaint', $this->submittedSummary($complaint));
    }

    /**
     * Details shown in the confirmation after a ticket is submitted.
     */
    protected function submittedSummary(Complaint $complaint): array
    {
        $complaint->loadMissing(['category', 'suggestedRecipient.user']);

        return [
            'id' => $complaint->id,
            'reference_number' => $complaint->reference_number,
            'subject_title' => $complaint->subject_title,
            'category_name' => $complaint->category?->name,
            'suggested_recipient' => $complaint->suggestedRecipient?->user?->table_name,
            'attachment_count' => count($complaint->attachment_files),
            'is_anonymous' => (bool) $complaint->is_anonymous,
            'status' => 'Pending',
        ];
    }

    /**
     * Display the submission confirmation for a ticket.
     */
    public function submitted(Complaint $complaint): RedirectResponse
    {
        $student = Auth::user()->student;

        if (! $student || $complaint->student_id !== $student->id) {
            abort(403);
        }

        $complaint->load('ticket');

        abort_unless($complaint->ticket, 404);

        return redirect()
            ->route('student.complaints.create')
            ->with('submittedComplaint', $this->submittedSummary($complaint));
    }

    /**
     * Display complaint details.
     */
    public function show(Complaint $complaint)
    {
        $student = Auth::user()->student;

        if (! $student || $complaint->student_id !== $student->id) {
            abort(403);
        }

        $complaint->load([
            'category.recipient.user',
            'ticket.assignee',
            'ticket.thread' => function ($q) {
                $q->with(['messages' => function ($q2) {
                    $q2->with('sender')->orderBy('created_at');
                }]);
            },
            'ticket.auditLogs' => function ($q) {
                $q->with('performer')->orderBy('created_at');
            },
        ]);

        $unreadService = app(TicketUnreadService::class);
        $notificationIds = $complaint->ticket
            ? $unreadService->notificationIdsForTicket(Auth::user(), $complaint->ticket)
            : ["complaint-{$complaint->id}"];

        $unreadService->markTicketsViewed(Auth::user(), [(string) $complaint->id], $notificationIds);

        return response()
            ->view('student.complaints.show', compact('complaint'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Store a reply message to the complaint thread.
     */
    public function storeReply(Request $request, Complaint $complaint): RedirectResponse
    {
        Log::debug('Student\\ComplaintController@storeReply called', ['user_id' => Auth::id(), 'complaint_id' => $complaint->id ?? null]);

        $student = Auth::user()->student;

        if (! $student || $complaint->student_id !== $student->id) {
            abort(403, 'You are not authorized to reply to this complaint.');
        }

        // Authorization: ensure the complaint has a ticket and thread
        $ticket = $complaint->ticket;

        if (! $ticket) {
            abort(403, 'This complaint has no associated ticket.');
        }

        // Check if thread is active (not closed)
        $thread = $ticket->thread;

        if (! $thread || ! $thread->is_active) {
            Log::debug('Student\\ComplaintController@storeReply returning closed error', ['user_id' => Auth::id(), 'complaint_id' => $complaint->id ?? null]);
            return redirect()->route('student.complaints.show', $complaint)
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
                'Student posted a reply message.'
            );

            $handler = $ticket->currentHandler ?? $ticket->assignee;
            if ($handler?->email) {
                EmailNotification::create([
                    'ticket_id' => $ticket->id,
                    'recipient_email' => $handler->email,
                    'type' => EmailNotification::TYPE_MESSAGE_POSTED,
                    'status' => EmailNotification::STATUS_PENDING,
                ]);
            }
        });

        Log::debug('Student\\ComplaintController@storeReply returning redirect', ['user_id' => Auth::id()]);
        return redirect()->route('student.complaints.show', $complaint);
    }
}
