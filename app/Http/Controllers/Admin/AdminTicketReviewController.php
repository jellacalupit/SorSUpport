<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\EmailNotification;
use App\Models\Recipient;
use App\Models\Ticket;
use App\Models\TicketThread;
use App\Services\TicketEscalationService;
use App\Services\TicketUnreadService;
use App\Services\TicketWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminTicketReviewController extends Controller
{
    public function __construct(
        protected TicketEscalationService $escalationService,
        protected TicketWorkflow $workflow,
    ) {
    }

    public function markRead(Ticket $ticket, TicketUnreadService $unreadService): Response
    {
        $unreadService->markTicketsViewed(Auth::user(), [(string) $ticket->complaint_id]);

        return response()->noContent();
    }

    /**
     * Send a resolved ticket back to its handler.
     */
    public function notYetResolved(Ticket $ticket): RedirectResponse
    {
        $this->workflow->reopen($ticket, Auth::user());

        return redirect()->route('admin.complaints.show', $ticket->complaint)
            ->with('success', 'Ticket moved back to in-progress.');
    }

    /**
     * Display list of pending tickets awaiting validity determination.
     */
    public function index(Request $request): Response
    {
        $tickets = Ticket::query()
            ->whereIn('status', [Ticket::STATUS_SUBMITTED, Ticket::STATUS_NEEDS_CLARIFICATION, Ticket::STATUS_RESOLVED])
            ->with([
                'complaint.student.user',
                'complaint.suggestedRecipient.user',
                'complaint.category.recipient.user',
                'complaint.category.suggestedRecipients.user',
                'complaint.category.escalationHierarchies.recipient.user',
                'assignee',
                'currentHandler.recipient',
            ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->input('search'));

                $query->where(function ($query) use ($search) {
                    $query->whereHas('complaint', function ($complaintQuery) use ($search) {
                            $complaintQuery->where('subject_title', 'like', '%' . $search . '%')
                                ->orWhere('reference_number', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('complaint', fn ($complaintQuery) => $complaintQuery->where('is_anonymous', false)->whereHas('student.user', fn ($userQuery) => $userQuery->where('name', 'like', '%' . $search . '%')));
                });
            })
            ->when($request->filled('category_filter'), function ($query) use ($request) {
                $query->whereHas('complaint', function ($complaints) use ($request) {
                    $complaints->where('category_id', $request->input('category_filter'));
                });
            })
            ->orderBy('updated_at', $request->input('sort', 'newest') === 'oldest' ? 'asc' : 'desc')
            ->paginate(15)
            ->appends($request->query());

        $categories = ComplaintCategory::query()->where('is_active', true)->orderBy('name')->get();
        $recipients = Recipient::query()
            ->with('user')
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->orderBy('unit')
            ->get();

        return response()
            ->view('admin.tickets.review.index', compact('tickets', 'categories', 'recipients'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Display needs-resolution tickets currently held by the signed-in admin.
     */
    public function myTickets(Request $request): Response
    {
        $status = $request->input('status_filter');
        $classification = $request->input('classification_filter');
        $sort = $request->input('sort', 'newest');
        $categories = ComplaintCategory::query()->where('is_active', true)->orderBy('name')->get();

        $baseQuery = Ticket::query()
            ->where('current_handler_id', Auth::id())
            ->whereIn('status', [
                Ticket::STATUS_ASSIGNED,
                Ticket::STATUS_IN_PROGRESS,
                Ticket::STATUS_ESCALATED,
                Ticket::STATUS_REFERRED,
                Ticket::STATUS_RESOLVED,
                Ticket::STATUS_CLOSED,
            ])
            ->with(['complaint.student.user', 'complaint.category', 'currentHandler', 'thread', 'auditLogs'])
            ->when($classification !== null && $classification !== '', function ($query) use ($classification) {
                $query->where('classification', $classification);
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->input('search'));

                $query->where(function ($query) use ($search) {
                    $query->whereHas('complaint', function ($complaintQuery) use ($search) {
                            $complaintQuery->where('subject_title', 'like', '%' . $search . '%')
                                ->orWhere('reference_number', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('complaint', fn ($complaintQuery) => $complaintQuery->where('is_anonymous', false)->whereHas('student.user', fn ($userQuery) => $userQuery->where('name', 'like', '%' . $search . '%')));
                });
            })
            ->when($request->filled('category_filter'), function ($query) use ($request) {
                $query->whereHas('complaint', function ($complaints) use ($request) {
                    $complaints->where('category_id', $request->input('category_filter'));
                });
            });

        $statusCounts = [
            '' => (clone $baseQuery)->count(),
            'assigned' => (clone $baseQuery)->where('status', Ticket::STATUS_ASSIGNED)->count(),
            'in_progress' => (clone $baseQuery)->where('status', Ticket::STATUS_IN_PROGRESS)->count(),
            'escalated' => (clone $baseQuery)->where('status', Ticket::STATUS_ESCALATED)->count(),
            'referred' => (clone $baseQuery)->where('status', Ticket::STATUS_REFERRED)->count(),
            'resolved' => (clone $baseQuery)->where('status', Ticket::STATUS_RESOLVED)->count(),
            'closed' => (clone $baseQuery)->where('status', Ticket::STATUS_CLOSED)->count(),
        ];

        $tickets = (clone $baseQuery)
            ->when($classification !== null && $classification !== '', fn ($query) => $query->where('classification', $classification))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($sort === 'oldest', fn ($query) => $query->orderBy('updated_at', 'asc'))
            ->when($sort !== 'oldest', fn ($query) => $query->orderByDesc('updated_at'))
            ->paginate(15)
            ->appends($request->query());

        return response()
            ->view('admin.tickets.my-index', compact('tickets', 'status', 'classification', 'statusCounts', 'categories'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Close a ticket under review that cannot be acted on, with the reason.
     */
    public function reject(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->isAwaitingReview(), 404);

        $validated = $request->validate([
            'closure_reason' => 'required|string|max:1000',
            'closure_type' => ['nullable', Rule::in([Ticket::CLOSURE_INVALID, Ticket::CLOSURE_DUPLICATE, Ticket::CLOSURE_OUT_OF_SCOPE, Ticket::CLOSURE_NO_RESPONSE])],
        ]);

        $this->workflow->close(
            $ticket,
            Auth::user(),
            $validated['closure_type'] ?? Ticket::CLOSURE_INVALID,
            $validated['closure_reason'],
            ['classification' => Ticket::CLASSIFICATION_INVALID]
        );

        return redirect()
            ->route('admin.tickets.review.index')
            ->with('success', 'Ticket closed and student notified.');
    }

    /**
     * Ask the student for more details before reviewing the ticket.
     */
    public function requestClarification(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->classification === null, 404);

        $validated = $request->validate([
            'clarification_message' => 'required|string|max:2000',
        ], [
            'clarification_message.required' => 'Write what the student needs to clarify.',
        ]);

        $this->workflow->requestClarification($ticket, Auth::user(), $validated['clarification_message']);

        return back()->with('success', 'The student was asked for more details.');
    }

    /**
     * Classify ticket as "Needs Resolution" or "Informational" with jurisdiction.
     */
    public function classify(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->isAwaitingReview(), 404);

        $validated = $request->validate([
            'classification' => 'required|in:needs_resolution,informational',
            'jurisdiction' => 'required|in:sds,recipient',
        ]);



        $ticket->update([
            'classification' => $validated['classification'],
            'jurisdiction' => $validated['jurisdiction'],
        ]);

        // Log the action
        AuditLog::log(
            $ticket->id,
            'ticket_classified',
            Auth::id(),
            "Classified as {$validated['classification']} under {$validated['jurisdiction']} jurisdiction."
        );

        if ($ticket->complaint?->student?->user?->email) {
            EmailNotification::create([
                'ticket_id' => $ticket->id,
                'recipient_email' => $ticket->complaint->student->user->email,
                'type' => EmailNotification::TYPE_STUDENT_STATUS_UPDATE,
                'status' => EmailNotification::STATUS_PENDING,
            ]);
        }

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
            $ticket->status === Ticket::STATUS_SUBMITTED &&
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
        AuditLog::log(
            $ticket->id,
            'ticket_forwarded_to_recipient',
            Auth::id(),
            "Informational ticket forwarded to {$recipient->user->name} ({$recipient->user->email})."
        );

        if ($recipient->user?->email) {
            EmailNotification::create([
                'ticket_id' => $ticket->id,
                'recipient_email' => $recipient->user->email,
                'type' => EmailNotification::TYPE_INFORMATIONAL_FORWARD,
                'status' => EmailNotification::STATUS_PENDING,
            ]);
        }

        return redirect()
            ->route('admin.tickets.review.index')
            ->with('success', 'Ticket forwarded to recipient.');
    }

    /**
     * Retain an informational ticket in SDS records and close it.
     */
    public function retainInformational(Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->isAwaitingReview(), 404);

        $this->workflow->authorize(Auth::user(), $ticket, TicketWorkflow::CLOSE);

        $ticket->update([
            'classification' => Ticket::CLASSIFICATION_INFORMATIONAL,
            'jurisdiction' => Ticket::JURISDICTION_SDS,
            'current_handler_id' => Auth::id(),
            'status' => Ticket::STATUS_CLOSED,
            'closure_type' => Ticket::CLOSURE_INFORMATIONAL,
            'closed_at' => now(),
        ]);

        AuditLog::log($ticket->id, 'ticket_retained_in_sds_records', Auth::id(), 'Informational ticket retained in SDS records and closed.');

        if ($ticket->complaint?->student?->user?->email) {
            EmailNotification::create([
                'ticket_id' => $ticket->id,
                'recipient_email' => $ticket->complaint->student->user->email,
                'type' => EmailNotification::TYPE_COMPLAINT_CLOSED,
                'status' => EmailNotification::STATUS_PENDING,
            ]);
        }

        return redirect()->route('admin.tickets.review.index')->with('success', 'Informational ticket saved and closed.');
    }

    /**
     * Forward an informational ticket to a recipient and close it.
     */
    public function forwardInformationalAndClose(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->isAwaitingReview(), 404);
        $this->workflow->authorize(Auth::user(), $ticket, TicketWorkflow::CLOSE);

        $validated = $request->validate(['recipient_id' => 'required|exists:recipients,id']);
        $recipient = Recipient::with('user')->findOrFail($validated['recipient_id']);
        abort_unless($this->recipientIsConfiguredForTicketCategory($ticket, $recipient), 422, 'This recipient is not configured for the ticket category.');

        $ticket->update([
            'classification' => Ticket::CLASSIFICATION_INFORMATIONAL,
            'jurisdiction' => Ticket::JURISDICTION_RECIPIENT,
            'forwarded_to' => $recipient->id,
            'forwarded_at' => now(),
            'status' => Ticket::STATUS_CLOSED,
            'closure_type' => Ticket::CLOSURE_INFORMATIONAL,
            'closed_at' => now(),
        ]);

        AuditLog::log($ticket->id, 'ticket_forwarded_and_closed', Auth::id(), "Informational ticket forwarded to {$recipient->user->name} and closed.");

        if ($recipient->user?->email) {
            EmailNotification::create([
                'ticket_id' => $ticket->id,
                'recipient_email' => $recipient->user->email,
                'type' => EmailNotification::TYPE_INFORMATIONAL_FORWARD,
                'status' => EmailNotification::STATUS_PENDING,
            ]);
        }

        return redirect()->route('admin.tickets.review.index')->with('success', 'Ticket forwarded and closed.');
    }

    /**
     * Assign or reassign a needs-resolution ticket to an admin or recipient handler.
     */
    public function assign(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless(
            $ticket->classification === Ticket::CLASSIFICATION_NEEDS_RESOLUTION || $ticket->isAwaitingReview(),
            404,
            'Only needs-resolution tickets can be assigned.'
        );

        $validated = $request->validate([
            'assignment_mode' => 'required|in:direct,recipient',
            'recipient_id' => 'required_if:assignment_mode,recipient|nullable|exists:recipients,id',
        ]);

        $recipient = null;

        if ($validated['assignment_mode'] === 'recipient') {
            $recipient = Recipient::with('user')->findOrFail($validated['recipient_id']);

            if ($ticket->classification === null) {
                abort_unless($this->recipientIsConfiguredForTicketCategory($ticket, $recipient), 422, 'This recipient is not configured for the ticket category.');
            }
        }

        $this->workflow->assign($ticket, Auth::user(), $recipient);

        return redirect()
            ->route('admin.tickets.review.index')
            ->with('success', $recipient ? 'Ticket assigned to recipient.' : 'Ticket assigned for direct handling.');
    }

    protected function recipientIsConfiguredForTicketCategory(Ticket $ticket, Recipient $recipient): bool
    {
        $category = $ticket->complaint?->category;

        if (! $category) {
            return false;
        }

        $configuredRecipientIds = collect([$category->recipient_id, $ticket->complaint->suggested_recipient_id])
            ->merge($category->suggestedRecipients()->pluck('recipients.id'))
            ->merge($category->escalationHierarchies()->pluck('recipient_id'))
            ->filter()
            ->unique()
            ->values();

        return $configuredRecipientIds->contains((int) $recipient->id);
    }

    /**
     * Take a ticket under review for direct handling, or acknowledge one assigned to this admin.
     */
    public function acknowledge(Request $request, Ticket $ticket): RedirectResponse
    {

        if ($ticket->status === Ticket::STATUS_ASSIGNED) {
            $this->workflow->acknowledge($ticket, Auth::user());
        } else {
            abort_unless($ticket->classification === null || $ticket->jurisdiction === Ticket::JURISDICTION_SDS, 404);

            $this->workflow->handleDirectly($ticket, Auth::user());
        }

        return redirect()->route('admin.tickets.review.index')
            ->with('success', 'Ticket acknowledged.');
    }

    /**
     * Mark a ticket this admin is handling as resolved.
     */
    public function resolve(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->workflow->authorize(Auth::user(), $ticket, TicketWorkflow::RESOLVE);

        $validated = $request->validate([
            'resolution_type' => ['required', Rule::in(array_keys(Ticket::RESOLUTION_LABELS))],
            'resolution_message' => 'required|string|max:2000',
        ], [
            'resolution_type.required' => 'Select how the ticket was resolved.',
            'resolution_message.required' => 'Describe the resolution for the student.',
        ]);

        $this->workflow->resolve($ticket, Auth::user(), $validated['resolution_type'], $validated['resolution_message']);

        return redirect()->route('admin.complaints.show', $ticket->complaint)
            ->with('success', 'Ticket marked as resolved.');
    }

    /**
     * Refer a ticket to a committee or board that decides it outside the system.
     */
    public function refer(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->workflow->authorize(Auth::user(), $ticket, TicketWorkflow::REFER);

        $validated = $request->validate([
            'referred_to' => 'required|string|max:255',
            'referral_note' => 'nullable|string|max:1000',
        ], [
            'referred_to.required' => 'Enter the committee or board the ticket is referred to.',
        ]);

        $this->workflow->refer($ticket, Auth::user(), $validated['referred_to'], $validated['referral_note'] ?? null);

        return back()->with('success', sprintf('Ticket referred to %s.', $validated['referred_to']));
    }

    /**
     * Record the outcome of a referred ticket.
     */
    public function recordOutcome(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->workflow->authorize(Auth::user(), $ticket, TicketWorkflow::RECORD_OUTCOME);

        $validated = $request->validate([
            'outcome' => 'required|string|max:2000',
            'resolution_type' => ['nullable', Rule::in(array_keys(Ticket::RESOLUTION_LABELS))],
        ], [
            'outcome.required' => 'Describe the outcome for the student.',
        ]);

        $this->workflow->recordOutcome(
            $ticket,
            Auth::user(),
            $validated['outcome'],
            $validated['resolution_type'] ?? Ticket::RESOLUTION_COMMITTEE_DECISION
        );

        return back()->with('success', 'Outcome recorded. The ticket is now resolved.');
    }

    /**
     * Escalate an active ticket to the recipient chosen by the admin.
     */
    public function escalate(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->classification === Ticket::CLASSIFICATION_NEEDS_RESOLUTION, 404, 'Only active tickets can be escalated.');
        $this->workflow->authorize(Auth::user(), $ticket, TicketWorkflow::ESCALATE);

        $validated = $request->validate([
            'recipient_id' => 'required|exists:recipients,id',
        ], [
            'recipient_id.required' => 'Select who the ticket should be escalated to.',
        ]);

        $recipient = $this->escalationService
            ->escalationTargets($ticket)
            ->firstWhere('id', (int) $validated['recipient_id']);

        if (! $recipient) {
            return back()->withErrors(['recipient_id' => 'The ticket cannot be escalated to the selected recipient.']);
        }

        $this->escalationService->escalate($ticket, $recipient, Auth::user());

        return back()->with('success', sprintf('Ticket escalated to %s.', $recipient->user->display_name));
    }

    /**
     * Correct the category or suggested recipient a student picked, before the ticket is classified.
     */
    public function updateDetails(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->isAwaitingReview(), 404);

        $validated = $request->validate([
            'category_id' => ['required', Rule::exists('complaint_categories', 'id')->where('is_active', true)],
            'suggested_recipient_id' => 'nullable|integer',
        ]);

        $complaint = $ticket->complaint;
        $category = ComplaintCategory::findOrFail($validated['category_id']);
        $suggestedRecipient = null;

        if (filled($validated['suggested_recipient_id'] ?? null)) {
            $suggestedRecipient = Recipient::query()
                ->active()
                ->with('user')
                ->find($validated['suggested_recipient_id']);

            if (! $suggestedRecipient) {
                return back()->withErrors(['suggested_recipient_id' => 'The selected recipient is not available.']);
            }
        }

        $changes = [];

        if ((int) $complaint->category_id !== (int) $category->id) {
            $changes[] = sprintf('Category changed from "%s" to "%s".', $complaint->category?->name ?? 'Uncategorized', $category->name);
        }

        if ((int) $complaint->suggested_recipient_id !== (int) $suggestedRecipient?->id) {
            $changes[] = sprintf(
                'Suggested recipient changed from %s to %s.',
                $complaint->suggestedRecipient?->user?->display_name ?? 'none',
                $suggestedRecipient?->user?->display_name ?? 'none'
            );
        }

        $fromTicketPage = str_contains((string) url()->previous(), '/admin/complaints/');
        $destination = $fromTicketPage
            ? redirect()->route('admin.complaints.show', $ticket->complaint)
            : redirect()->route('admin.tickets.review.index');

        if ($changes === []) {
            return $destination;
        }

        DB::transaction(function () use ($ticket, $complaint, $category, $suggestedRecipient, $changes): void {
            $complaint->update([
                'category_id' => $category->id,
                'suggested_recipient_id' => $suggestedRecipient?->id,
            ]);

            $ticket->touch();

            AuditLog::log($ticket->id, 'ticket_details_updated', Auth::id(), implode(' ', $changes));
        });

        return $destination->with('success', 'Ticket details updated.');
    }

    /**
     * Close a ticket. A resolved ticket is simply confirmed; any other needs the reason it is
     * being closed without a resolution.
     */
    public function close(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->workflow->authorize(Auth::user(), $ticket, TicketWorkflow::CLOSE);
        abort_if($ticket->isAwaitingReview(), 404);

        if ($ticket->status === Ticket::STATUS_RESOLVED) {
            $this->workflow->close($ticket, Auth::user(), Ticket::CLOSURE_RESOLVED);
        } else {
            $validated = $request->validate([
                'closure_type' => ['required', Rule::in(Ticket::ADMIN_CLOSURE_TYPES)],
                'closure_reason' => 'required|string|max:1000',
            ], [
                'closure_type.required' => 'Select why the ticket is being closed.',
                'closure_reason.required' => 'Explain why the ticket is being closed.',
            ]);

            $this->workflow->close($ticket, Auth::user(), $validated['closure_type'], $validated['closure_reason']);
        }

        return redirect()->route('admin.tickets.review.index')
            ->with('success', 'Ticket closed successfully.');
    }
}
