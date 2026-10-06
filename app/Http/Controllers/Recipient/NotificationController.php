<?php

namespace App\Http\Controllers\Recipient;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
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
        $notifications = $this->notifications($user)
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

        return view('recipient.notifications', [
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

        $notifications = $this->notifications($request->user())
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
        $deletedKey = 'recipient_notification_deleted_ids';
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
        $user = $request->user();
        $notifications = $this->notifications($user);
        $notification = $notifications->firstWhere('id', $notificationId);

        abort_unless($notification, 404);

        $this->unreadService->markTicketsViewed(
            $user,
            [(string) $notification['complaint_id']],
            [$notificationId]
        );

        return redirect()->route('recipient.tickets.show', $notification['complaint_id']);
    }

    private function notifications($user)
    {
        return AuditLog::query()
            ->whereHas('ticket', fn ($query) => $query->where('assigned_to', $user->id))
            ->where(function ($query) use ($user) {
                $query->whereIn('action', array_values(array_diff(
                    TicketUnreadService::RECIPIENT_CHANGE_ACTIONS,
                    ['message_posted']
                )))
                    ->orWhere(function ($messageQuery) use ($user) {
                        $messageQuery->where('action', 'message_posted')
                            ->where('performed_by', '!=', $user->id);
                    });
            })
            ->with('ticket.complaint.student.user')
            ->latest()
            ->get()
            ->map(function (AuditLog $log) use ($user) {
                $complaint = $log->ticket?->complaint;
                $studentName = $complaint?->is_anonymous ? 'The student' : ($complaint?->student?->user?->display_name ?? 'The student');

                return [
                    'id' => "audit-{$log->id}",
                    'complaint_id' => $log->ticket?->complaint_id,
                    'performed_by' => $log->performed_by,
                    'title' => $log->ticket?->complaint?->reference_number ?? "Ticket #{$log->ticket_id}",
                    'body' => match (true) {
                        $log->action === 'ticket_assigned' => 'Ticket assigned to you for recipient handling.',
                        $log->action === 'message_posted' && $log->performed_by !== $user->id => $studentName . ' posted a reply message.',
                        default => $log->details ?: 'There is a new update on an assigned ticket.',
                    },
                    'at' => $log->created_at,
                    'displayAt' => $log->created_at?->copy()->setTimezone('Asia/Manila')->format('M d, Y g:i A') ?? 'Unknown',
                ];
            });
    }
}
