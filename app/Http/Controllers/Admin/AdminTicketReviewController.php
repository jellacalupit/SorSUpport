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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminTicketReviewController extends Controller
{
    public function __construct(protected TicketEscalationService $escalationService)
    {
    }

    public function markRead(Ticket $ticket, TicketUnreadService $unreadService): Response
    {
        $unreadService->markTicketsViewed(Auth::user(), [(string) $ticket->complaint_id]);

        return response()->noContent();
    }

    public function notYetResolved(Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->status === Ticket::STATUS_RESOLVED, 404);

        DB::transaction(function () use ($ticket) {
            $ticket->update([
                'status' => Ticket::STATUS_IN_PROGRESS,
                'resolved_at' => null,
                'closed_at' => null,
            ]);

            if ($ticket->thread) {
                $ticket->thread->update(['is_active' => true]);
            }

            AuditLog::log(
                $ticket->id,
                'ticket_reopened_from_resolved',
                Auth::id(),
                'Ticket reopened from resolved by admin and moved back to in-progress.'
            );
        });

        return redirect()->route('admin.complaints.show', $ticket->complaint)
            ->with('success', 'Ticket moved back to in-progress.');
    }

    /**
     * Display list of pending tickets awaiting validity determination.
     */
    public function index(Request $request): Response
    {
        $tickets = Ticket::query()
            ->whereIn('status', [Ticket::STATUS_PENDING, Ticket::STATUS_RESOLVED])
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
                        ->orWhereHas('complaint.student.user', fn ($userQuery) => $userQuery->where('name', 'like', '%' . $search . '%'));
                });
            })
            ->when($request->filled('category_filter'), function ($query) use ($request) {
                $query->whereHas('complaint', function ($complaints) use ($request) {
                    $complaints->where('category_id', $request->input('category_filter'));
                });
            })
            ->orderBy('updated_at', $request->input('sort', 'newest') === 'oldest' ? 'asc' : 'desc')
            ->paginate(10)
            ->appends($request->query());

        $categories = ComplaintCategory::query()->where('is_active', true)->orderBy('name')->get();
        $recipients = Recipient::query()
            ->with('user')
            ->whereHas('user', fn ($query) => $query
                ->where('is_active', true)
                ->whereNotNull('email_verified_at'))
            ->orderBy('department')
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
                        ->orWhereHas('complaint.student.user', fn ($userQuery) => $userQuery->where('name', 'like', '%' . $search . '%'));
                });
            })
            ->when($request->filled('category_filter'), function ($query) use ($request) {
                $query->whereHas('complaint', function ($complaints) use ($request) {
                    $complaints->where('category_id', $request->input('category_filter'));
                });
            });

        $statusCounts = [
            '' => (clone $baseQuery)->count(),
            'in_progress' => (clone $baseQuery)->whereIn('status', [Ticket::STATUS_ASSIGNED, Ticket::STATUS_IN_PROGRESS])->count(),
            'escalated' => (clone $baseQuery)->where('status', Ticket::STATUS_ESCALATED)->count(),
            'closed' => (clone $baseQuery)->where('status', Ticket::STATUS_CLOSED)->count(),
        ];

        $tickets = (clone $baseQuery)
            ->when($classification !== null && $classification !== '', fn ($query) => $query->where('classification', $classification))
            ->when($status === 'in_progress', fn ($query) => $query->whereIn('status', [Ticket::STATUS_ASSIGNED, Ticket::STATUS_IN_PROGRESS]))
            ->when($status && $status !== 'in_progress', fn ($query) => $query->where('status', $status))
            ->when($sort === 'oldest', fn ($query) => $query->orderBy('updated_at', 'asc'))
            ->when($sort !== 'oldest', fn ($query) => $query->orderByDesc('updated_at'))
            ->paginate(10)
            ->appends($request->query());

        return response()
            ->view('admin.tickets.my-index', compact('tickets', 'status', 'classification', 'statusCounts', 'categories'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
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

        if (! $ticket->complaint?->is_anonymous && $ticket->complaint?->student?->user?->email) {
            EmailNotification::create([
                'ticket_id' => $ticket->id,
                'recipient_email' => $ticket->complaint->student->user->email,
                'type' => EmailNotification::TYPE_INVALID_CLOSURE,
                'status' => EmailNotification::STATUS_PENDING,
            ]);
        }

        // Log the action
        AuditLog::log(
            $ticket->id,
            'ticket_closed_invalid',
            Auth::id(),
            "Ticket marked invalid. Reason: {$validated['closure_reason']}"
        );

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

        abort_if(
            $ticket->complaint?->is_anonymous && $validated['classification'] !== Ticket::CLASSIFICATION_INFORMATIONAL,
            422,
            'Anonymous submissions can only be kept as informational records.'
        );

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

        // TODO: Send InformationalForwardedMail to recipient (Module 4)

        return redirect()
            ->route('admin.tickets.review.index')
            ->with('success', 'Ticket forwarded to recipient.');
    }

    /**
     * Retain an informational ticket in SDS records and close it.
     */
    public function retainInformational(Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->status === Ticket::STATUS_PENDING && $ticket->classification === null, 404);

        $ticket->update([
            'classification' => Ticket::CLASSIFICATION_INFORMATIONAL,
            'jurisdiction' => Ticket::JURISDICTION_SDS,
            'current_handler_id' => Auth::id(),
            'status' => Ticket::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        AuditLog::log($ticket->id, 'ticket_retained_in_sds_records', Auth::id(), 'Informational ticket retained in SDS records and closed.');

        if (! $ticket->complaint?->is_anonymous && $ticket->complaint?->student?->user?->email) {
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
        abort_unless($ticket->status === Ticket::STATUS_PENDING && $ticket->classification === null, 404);

        $validated = $request->validate(['recipient_id' => 'required|exists:recipients,id']);
        $recipient = Recipient::with('user')->findOrFail($validated['recipient_id']);
        abort_unless($this->recipientIsConfiguredForTicketCategory($ticket, $recipient), 422, 'This recipient is not configured for the ticket category.');

        $ticket->update([
            'classification' => Ticket::CLASSIFICATION_INFORMATIONAL,
            'jurisdiction' => Ticket::JURISDICTION_RECIPIENT,
            'forwarded_to' => $recipient->id,
            'forwarded_at' => now(),
            'status' => Ticket::STATUS_CLOSED,
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
            $ticket->classification === Ticket::CLASSIFICATION_NEEDS_RESOLUTION ||
            ($ticket->status === Ticket::STATUS_PENDING && $ticket->classification === null),
            404,
            'Only needs-resolution tickets can be assigned.'
        );
        abort_if($ticket->complaint?->is_anonymous, 422, 'Anonymous submissions can only be kept as informational records.');

        $validated = $request->validate([
            'assignment_mode' => 'required|in:direct,recipient',
            'recipient_id' => 'required_if:assignment_mode,recipient|nullable|exists:recipients,id',
        ]);

        $recipient = null;
        $assignedUserId = Auth::id();

        if ($validated['assignment_mode'] === 'recipient') {
            $recipient = Recipient::findOrFail($validated['recipient_id']);
            if ($ticket->classification === null) {
                abort_unless($this->recipientIsConfiguredForTicketCategory($ticket, $recipient), 422, 'This recipient is not configured for the ticket category.');
            }
            $assignedUserId = $recipient->user_id;
        }

        DB::transaction(function () use ($ticket, $recipient, $assignedUserId): void {
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
                'status' => $recipient ? Ticket::STATUS_IN_PROGRESS : Ticket::STATUS_ASSIGNED,
                'classification' => $ticket->classification ?? Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
                'jurisdiction' => $ticket->jurisdiction ?? Ticket::JURISDICTION_RECIPIENT,
                'assigned_to' => $recipient ? $assignedUserId : null,
                'current_handler_id' => $assignedUserId,
                'deadline' => null,
            ]);

            $action = 'ticket_assigned';
            $details = $recipient
                ? "SDS Admin assigned the ticket {$ticket->complaint->reference_number} to {$recipient->user->display_name} for handling."
                : 'Ticket assigned to SDS admin for direct handling.';

            AuditLog::log(
                $ticket->id,
                $action,
                Auth::id(),
                $details
            );

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
     * Acknowledge an assigned ticket by admin handler (Assigned -> In Progress)
     */
    public function acknowledge(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless(
            ($ticket->status === Ticket::STATUS_PENDING &&
                in_array($ticket->classification, [null, Ticket::CLASSIFICATION_NEEDS_RESOLUTION], true) &&
                ($ticket->classification === null || $ticket->jurisdiction === Ticket::JURISDICTION_SDS)) ||
            ($ticket->status === Ticket::STATUS_ASSIGNED && $ticket->current_handler_id === Auth::id()),
            404
        );
        abort_if($ticket->complaint?->is_anonymous, 422, 'Anonymous submissions can only be kept as informational records.');

        DB::transaction(function () use ($ticket) {
            $thread = $ticket->thread;
            if (! $thread) {
                $ticket->thread()->create(['is_active' => true]);
            } else {
                $thread->update(['is_active' => true]);
            }

            $ticket->update([
                'status' => Ticket::STATUS_IN_PROGRESS,
                'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
                'jurisdiction' => Ticket::JURISDICTION_SDS,
                'assigned_to' => null,
                'current_handler_id' => Auth::id(),
                'deadline' => null,
                'acknowledged_at' => now(),
            ]);

            AuditLog::log(
                $ticket->id,
                'ticket_acknowledged',
                Auth::id(),
                'Ticket acknowledged by admin handler.'
            );

            if ($ticket->complaint?->student?->user?->email) {
                EmailNotification::create([
                    'ticket_id' => $ticket->id,
                    'recipient_email' => $ticket->complaint->student->user->email,
                    'type' => EmailNotification::TYPE_STUDENT_STATUS_UPDATE,
                    'status' => EmailNotification::STATUS_PENDING,
                ]);
            }
        });

        return redirect()->route('admin.tickets.review.index')
            ->with('success', 'Ticket acknowledged.');
    }

    /**
     * Mark an SDS-owned ticket as resolved.
     */
    public function resolve(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless(
            $ticket->current_handler_id === Auth::id() &&
            $ticket->classification === Ticket::CLASSIFICATION_NEEDS_RESOLUTION &&
            $ticket->status === Ticket::STATUS_IN_PROGRESS,
            403,
            'You are not authorized to resolve this ticket.'
        );

        $validated = $request->validate([
            'resolution_message' => 'required|string|max:2000',
        ]);

        DB::transaction(function () use ($ticket, $validated): void {
            $ticket->update([
                'status' => Ticket::STATUS_RESOLVED,
                'resolved_at' => now(),
            ]);

            if ($ticket->thread) {
                $ticket->thread->update(['is_active' => false]);
                $ticket->thread->messages()->create([
                    'sender_id' => Auth::id(),
                    'content' => $validated['resolution_message'],
                ]);
            }

            AuditLog::log($ticket->id, 'complaint_resolved', Auth::id(), $validated['resolution_message']);

            if ($ticket->complaint?->student?->user?->email) {
                EmailNotification::create([
                    'ticket_id' => $ticket->id,
                    'recipient_email' => $ticket->complaint->student->user->email,
                    'type' => EmailNotification::TYPE_STUDENT_STATUS_UPDATE,
                    'status' => EmailNotification::STATUS_PENDING,
                ]);
            }
        });

        return redirect()->route('admin.complaints.show', $ticket->complaint)
            ->with('success', 'Ticket marked as resolved.');
    }

    /**
     * Escalate an active ticket to the recipient chosen by the admin.
     */
    public function escalate(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless(
            $ticket->classification === Ticket::CLASSIFICATION_NEEDS_RESOLUTION &&
            in_array($ticket->status, [Ticket::STATUS_ASSIGNED, Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_ESCALATED], true),
            404,
            'Only active tickets can be escalated.'
        );

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
        abort_unless($ticket->status === Ticket::STATUS_PENDING && $ticket->classification === null, 404);

        $validated = $request->validate([
            'category_id' => ['required', Rule::exists('complaint_categories', 'id')->where('is_active', true)],
            'suggested_recipient_id' => 'nullable|integer',
        ]);

        $complaint = $ticket->complaint;
        $category = ComplaintCategory::findOrFail($validated['category_id']);
        $suggestedRecipient = null;

        if (filled($validated['suggested_recipient_id'] ?? null)) {
            $suggestedRecipient = Recipient::query()
                ->activeVerified()
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
     * Close a ticket (Admin-only) after resolution.
     */
    public function close(Request $request, Ticket $ticket): RedirectResponse
    {
        // Only allow closing tickets that are resolved or in_progress
        if (! in_array($ticket->status, [Ticket::STATUS_RESOLVED, Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_ASSIGNED, Ticket::STATUS_ESCALATED])) {
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

            AuditLog::log(
                $ticket->id,
                'ticket_closed',
                Auth::id(),
                'Ticket closed by admin.'
            );

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

        return redirect()->route('admin.tickets.review.index')
            ->with('success', 'Ticket closed successfully.');
    }
}
