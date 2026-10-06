<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TicketUnreadService
{
    public const STUDENT_CHANGE_ACTIONS = [
        'complaint_submitted',
        'ticket_assigned',
        'ticket_acknowledged',
        'ticket_resolved',
        'complaint_resolved',
        'ticket_closed',
        'complaint_closed',
        'ticket_closed_invalid',
        'ticket_escalated',
        'complaint_rejected',
        'clarification_requested',
        'ticket_referred',
        'referral_outcome_recorded',
        'ticket_reopened_from_resolved',
        'message_posted',
    ];

    public const RECIPIENT_CHANGE_ACTIONS = [
        'ticket_assigned',
        'ticket_classified',
        'ticket_forwarded_to_recipient',
        'ticket_resolved',
        'complaint_resolved',
        'ticket_closed',
        'ticket_escalated',
        'ticket_referred',
        'referral_outcome_recorded',
        'ticket_reopened_from_resolved',
        'further_action_requested',
        'resolution_accepted',
        'ticket_withdrawn',
        'message_posted',
    ];

    public const ADMIN_CHANGE_ACTIONS = [
        'complaint_submitted',
        'anonymous_complaint_submitted',
        'ticket_assigned',
        'ticket_acknowledged',
        'ticket_classified',
        'ticket_forwarded_to_recipient',
        'ticket_forwarded_and_closed',
        'ticket_retained_in_sds_records',
        'ticket_resolved',
        'complaint_resolved',
        'ticket_closed',
        'ticket_closed_invalid',
        'ticket_escalated',
        'complaint_rejected',
        'clarification_provided',
        'further_action_requested',
        'resolution_accepted',
        'ticket_withdrawn',
        'message_posted',
    ];

    public function changeActionsFor(User $user): ?array
    {
        if ($user->isStudent()) {
            return self::STUDENT_CHANGE_ACTIONS;
        }

        if ($user->isRecipient()) {
            return self::RECIPIENT_CHANGE_ACTIONS;
        }

        if ($user->isSdsAdmin()) {
            return self::ADMIN_CHANGE_ACTIONS;
        }

        return null;
    }

    public function lastReadAt(User $user, string $complaintId): ?string
    {
        $map = match (true) {
            $user->isStudent() => $user->student_ticket_last_read_at ?? [],
            $user->isRecipient() => $user->recipient_ticket_last_read_at ?? [],
            $user->isSdsAdmin() => $user->admin_ticket_last_read_at ?? [],
            default => [],
        };

        $value = data_get($map, $complaintId);

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function isExplicitlyRead(User $user, string $complaintId): bool
    {
        $ids = match (true) {
            $user->isStudent() => $user->student_ticket_read_ids ?? [],
            $user->isRecipient() => $user->recipient_ticket_read_ids ?? [],
            $user->isSdsAdmin() => $user->admin_ticket_read_ids ?? [],
            default => [],
        };

        return in_array($complaintId, array_map('strval', $ids), true);
    }

    public function unreadCountForTicket(User $user, ?Ticket $ticket): int
    {
        if (! $ticket) {
            return 0;
        }

        $logs = $ticket->relationLoaded('auditLogs')
            ? $ticket->auditLogs
            : $ticket->auditLogs()->get();

        return $this->unreadCountFromLogs($user, $logs, (string) $ticket->complaint_id);
    }

    public function unreadCountFromLogs(User $user, Collection $logs, string $complaintId): int
    {
        $lastReadAt = $this->lastReadAt($user, $complaintId);

        if (! $lastReadAt && $this->isExplicitlyRead($user, $complaintId)) {
            return 0;
        }

        $actions = $this->changeActionsFor($user);
        $lastReadTimestamp = $lastReadAt ? Carbon::parse($lastReadAt) : null;

        return $logs
            ->when($actions !== null, fn (Collection $items) => $items->whereIn('action', $actions))
            ->filter(function ($log) use ($user, $lastReadTimestamp) {
                if ((int) $log->performed_by === (int) $user->id && $log->action !== 'complaint_submitted') {
                    return false;
                }

                if (! $lastReadTimestamp) {
                    return true;
                }

                return $log->created_at->greaterThan($lastReadTimestamp);
            })
            ->count();
    }

    public function isNotificationRead(User $user, array $notification): bool
    {
        $readIds = $user->isStudent()
            ? ($user->student_notification_read_ids ?? [])
            : ($user->recipient_notification_read_ids ?? []);

        if (in_array($notification['id'], $readIds, true)) {
            return true;
        }

        $deletedIds = $user->isStudent()
            ? ($user->student_notification_deleted_ids ?? [])
            : ($user->recipient_notification_deleted_ids ?? []);

        if (in_array($notification['id'], $deletedIds, true)) {
            return true;
        }

        if (($notification['performed_by'] ?? null) === $user->id && ($notification['action'] ?? null) !== 'complaint_submitted') {
            return true;
        }

        $complaintId = (string) ($notification['complaint_id'] ?? '');

        if (str_starts_with((string) $notification['id'], 'complaint-')) {
            return true;
        }

        $lastReadAt = $this->lastReadAt($user, $complaintId);

        if (! $lastReadAt && $this->isExplicitlyRead($user, $complaintId)) {
            return true;
        }

        if (! $lastReadAt) {
            return false;
        }

        $at = $notification['at'] ?? null;

        if (! $at) {
            return false;
        }

        return Carbon::parse($at)->lessThanOrEqualTo(Carbon::parse($lastReadAt));
    }

    public function markTicketsViewed(User $user, array $complaintIds, array $notificationIds = []): void
    {
        $complaintIds = array_values(array_unique(array_map(
            'strval',
            array_filter($complaintIds, fn ($id) => $id !== null && $id !== '')
        )));

        $ticketReadKey = match (true) {
            $user->isStudent() => 'student_ticket_read_ids',
            $user->isRecipient() => 'recipient_ticket_read_ids',
            $user->isSdsAdmin() => 'admin_ticket_read_ids',
            default => null,
        };
        $lastReadKey = match (true) {
            $user->isStudent() => 'student_ticket_last_read_at',
            $user->isRecipient() => 'recipient_ticket_last_read_at',
            $user->isSdsAdmin() => 'admin_ticket_last_read_at',
            default => null,
        };
        $notificationReadKey = match (true) {
            $user->isStudent() => 'student_notification_read_ids',
            $user->isRecipient() => 'recipient_notification_read_ids',
            default => null,
        };

        $payload = [];
        $now = now()->toDateTimeString();

        if ($ticketReadKey) {
            $payload[$ticketReadKey] = array_values(array_unique([
                ...array_map('strval', $user->{$ticketReadKey} ?? []),
                ...$complaintIds,
            ]));
        }

        if ($lastReadKey) {
            $lastReadMap = $user->{$lastReadKey} ?? [];
            foreach ($complaintIds as $complaintId) {
                $lastReadMap[$complaintId] = $now;
            }
            $payload[$lastReadKey] = $lastReadMap;
        }

        if ($notificationReadKey) {
            $payload[$notificationReadKey] = array_values(array_unique([
                ...($user->{$notificationReadKey} ?? []),
                ...$notificationIds,
            ]));
        }

        if ($payload !== []) {
            $user->update($payload);
        }
    }

    public function notificationIdsForTicket(User $user, Ticket $ticket): array
    {
        $logs = $ticket->relationLoaded('auditLogs')
            ? $ticket->auditLogs
            : $ticket->auditLogs()->get();
        $actions = $this->changeActionsFor($user);

        return $logs
            ->when($actions !== null, fn (Collection $items) => $items->whereIn('action', $actions))
            ->filter(fn ($log) => $log->action !== 'message_posted' || (int) $log->performed_by !== (int) $user->id)
            ->map(fn ($log) => "audit-{$log->id}")
            ->values()
            ->all();
    }
}
