<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Services\TicketUnreadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(private TicketUnreadService $unreadService)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $notifications = $this->notifications($request)
            ->filter(function (array $notification) use ($user) {
                $deletedIds = $user->isStudent()
                    ? ($user->student_notification_deleted_ids ?? [])
                    : ($user->recipient_notification_deleted_ids ?? []);

                return ! in_array((string) $notification['id'], array_map('strval', $deletedIds), true);
            })
            ->map(function (array $notification) use ($user) {
                $notification['read'] = $this->unreadService->isNotificationRead($user, $notification);

                return $notification;
            });

        return view('student.notifications', [
            'notifications' => $notifications,
            'unread' => $notifications->where('read', false)->count(),
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $selectedIds = array_values(array_filter(
            $request->input('notification_ids', []),
            fn ($id) => is_string($id) || is_int($id)
        ));

        if ($selectedIds === []) {
            return back();
        }

        $notifications = $this->notifications($request)
            ->filter(fn (array $notification) => in_array((string) $notification['id'], array_map('strval', $selectedIds), true));

        $this->unreadService->markTicketsViewed(
            $request->user(),
            $notifications->pluck('complaint_id')->all(),
            $notifications->pluck('id')->all()
        );

        return back();
    }

    public function deleteSelected(Request $request): RedirectResponse
    {
        $selectedIds = array_values(array_filter(
            $request->input('notification_ids', []),
            fn ($id) => is_string($id) || is_int($id)
        ));

        if ($selectedIds === []) {
            return back();
        }

        $user = $request->user();
        $deletedKey = 'student_notification_deleted_ids';
        $current = $user->{$deletedKey} ?? [];

        $user->update([
            $deletedKey => array_values(array_unique([
                ...array_map('strval', $current),
                ...array_map('strval', $selectedIds),
            ])),
        ]);

        return back();
    }

    public function open(Request $request, string $notificationId): RedirectResponse
    {
        $notifications = $this->notifications($request);
        $notification = $notifications->firstWhere('id', $notificationId);

        abort_unless($notification, 404);

        $this->unreadService->markTicketsViewed(
            $request->user(),
            [(string) $notification['complaint_id']],
            [$notificationId]
        );

        return redirect()->route('student.complaints.show', $notification['complaint_id']);
    }

    private function notifications(Request $request)
    {
        $student = $request->user()->student;

        if (! $student) {
            abort(403, 'Student profile not found.');
        }

        return Complaint::query()
            ->where('student_id', $student->id)
            ->with(['category', 'ticket.assignee', 'ticket.auditLogs.performer'])
            ->latest()
            ->get()
            ->flatMap(function (Complaint $complaint) use ($student) {
                $ticket = $complaint->ticket;
                $logs = ($ticket?->auditLogs ?? collect())
                    ->whereIn('action', TicketUnreadService::STUDENT_CHANGE_ACTIONS)
                    ->filter(fn (AuditLog $log) => $log->action !== 'message_posted' || $log->performed_by !== $student->user_id)
                    ->values();

                if ($logs->isEmpty()) {
                    return [[
                        'id' => "complaint-{$complaint->id}",
                        'complaint_id' => $complaint->id,
                        'performed_by' => $student->user_id,
                        'title' => $complaint->reference_number,
                        'body' => "We received {$complaint->reference_number}. It is now waiting for review by the SDS Office.",
                        'at' => $complaint->created_at,
                        'displayAt' => $this->formatNotificationTime($complaint->created_at),
                    ]];
                }

                return $logs->map(function (AuditLog $log) use ($ticket, $complaint) {
                    $body = $log->details ?: "There is a new update for {$complaint->reference_number}.";

                    // The admin's reason for escalating or referring is a note for the staff
                    // handling the ticket; the student is told only where the ticket went.
                    if ($log->action === 'ticket_escalated') {
                        $body = \Illuminate\Support\Str::before($body, ' Reason: ');
                    } elseif ($log->action === 'ticket_referred' && $ticket?->referred_to) {
                        $body = "Ticket referred to {$ticket->referred_to}.";
                    }

                    if ($log->action === 'ticket_assigned' && $ticket?->assignee?->isRecipient()) {
                        $body = "SDS Admin assigned the ticket {$complaint->reference_number} to {$ticket->assignee->display_name} for handling.";
                    }

                    if ($log->action === 'message_posted') {
                        $senderName = $log->performer?->display_name ?? 'A user';
                        $body = "{$complaint->reference_number}\n{$senderName} sent you a message.";
                    }

                    return [
                        'id' => "audit-{$log->id}",
                        'complaint_id' => $complaint->id,
                        'performed_by' => $log->performed_by,
                        'action' => $log->action,
                        'title' => $this->titleFor($log->action),
                        'body' => $body,
                        'at' => $log->created_at,
                        'displayAt' => $this->formatNotificationTime($log->created_at),
                    ];
                });
            })
            ->sortByDesc('at')
            ->values();
    }

    private function formatNotificationTime($timestamp): string
    {
        $timestamp = $timestamp->copy()->setTimezone('Asia/Manila');
        $ageInMinutes = $timestamp->diffInMinutes(now());

        if ($ageInMinutes < 60) {
            return $timestamp->diffForHumans();
        }

        if ($ageInMinutes < 1440) {
            return $timestamp->format('g:i A');
        }

        return $timestamp->year === now()->year
            ? $timestamp->format('M d, g:i A')
            : $timestamp->format('M d, Y g:i A');
    }

    private function titleFor(string $action): string
    {
        return match ($action) {
            'complaint_submitted' => 'Complaint received',
            'ticket_classified' => 'Ticket classification updated',
            'ticket_assigned' => 'Ticket assigned',
            'ticket_acknowledged' => 'Ticket acknowledged',
            'ticket_resolved' => 'Ticket resolved',
            'ticket_closed' => 'Ticket closed',
            'ticket_escalated' => 'Ticket escalated',
            'clarification_requested' => 'More details needed',
            'ticket_referred' => 'Ticket referred',
            'referral_outcome_recorded' => 'Outcome recorded',
            'complaint_resolved' => 'Ticket resolved',
            'ticket_reopened_from_resolved' => 'Ticket back in progress',
            'ticket_closed_invalid' => 'Ticket closed',
            'message_posted' => 'New message',
            default => ucfirst(str_replace('_', ' ', $action)),
        };
    }
}
