<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\EmailNotification;
use App\Models\Recipient;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TicketEscalationService
{
    /**
     * Next recipient in the category's configured hierarchy, used as the suggested escalation target.
     */
    public function getNextRecipient(Ticket $ticket): ?Recipient
    {
        $category = $ticket->complaint?->category;

        if (! $category) {
            return null;
        }

        $hierarchy = $category->escalationHierarchies()->with('recipient.user')->get();

        if ($hierarchy->isEmpty()) {
            return null;
        }

        $currentHandlerUserId = $ticket->assigned_to ?: $ticket->current_handler_id;

        if (! $currentHandlerUserId) {
            return $hierarchy->first()?->recipient;
        }

        $levels = $hierarchy->groupBy('level')->sortKeys();
        $currentLevel = $levels->first(function ($entries) use ($currentHandlerUserId): bool {
            return $entries->contains(fn ($entry): bool => (int) $entry->recipient?->user_id === (int) $currentHandlerUserId);
        });

        if ($currentLevel) {
            $currentLevelNumber = (int) $currentLevel->first()->level;
            $nextLevel = $levels->first(fn ($entries, $level): bool => (int) $level > $currentLevelNumber);

            return $nextLevel?->first()?->recipient;
        }

        return $levels->first()?->first()?->recipient;
    }

    /**
     * Recipients a ticket can be escalated to: every active recipient except the current handler,
     * with the category's hierarchy listed first in level order.
     */
    public function escalationTargets(Ticket $ticket): Collection
    {
        $currentHandlerUserId = $ticket->assigned_to ?: $ticket->current_handler_id;

        $hierarchy = $ticket->complaint?->category
            ? $ticket->complaint->category->escalationHierarchies()->with('recipient.user')->get()->pluck('recipient')
            : collect();

        $others = Recipient::query()
            ->activeVerified()
            ->with('user')
            ->orderBy('department')
            ->get();

        return $hierarchy
            ->merge($others)
            ->filter(fn ($recipient) => $recipient?->user?->is_active
                && $recipient->user->email_verified_at
                && (int) $recipient->user_id !== (int) $currentHandlerUserId)
            ->unique('id')
            ->values();
    }

    /**
     * Escalate a ticket to the recipient chosen by the SDS admin and record when it happened.
     */
    public function escalate(Ticket $ticket, Recipient $targetRecipient, ?User $performedBy = null): void
    {
        DB::transaction(function () use ($ticket, $targetRecipient, $performedBy): void {
            $ticket->update([
                'status' => Ticket::STATUS_ESCALATED,
                'assigned_to' => $targetRecipient->user_id,
                'current_handler_id' => $targetRecipient->user_id,
                'escalated_at' => now(),
            ]);

            if ($ticket->thread) {
                $ticket->thread->update(['is_active' => true]);
            }

            AuditLog::log(
                $ticket->id,
                'ticket_escalated',
                $performedBy?->id,
                sprintf('Ticket escalated to %s.', $targetRecipient->user?->display_name ?? 'the selected recipient')
            );

            foreach ($this->getUsersToNotify($ticket, $targetRecipient, $performedBy) as $user) {
                EmailNotification::create([
                    'ticket_id' => $ticket->id,
                    'recipient_email' => $user->email,
                    'type' => EmailNotification::TYPE_ESCALATED,
                    'status' => EmailNotification::STATUS_PENDING,
                ]);
            }
        });
    }

    protected function getUsersToNotify(Ticket $ticket, Recipient $targetRecipient, ?User $performedBy = null): Collection
    {
        $users = collect();

        if ($performedBy?->isSdsAdmin()) {
            $users->push($performedBy);
        }

        if ($targetRecipient->user) {
            $users->push($targetRecipient->user);
        }

        if ($ticket->complaint?->student?->user) {
            $users->push($ticket->complaint->student->user);
        }

        return $users->filter(fn ($user) => $user instanceof User && filled($user->email))->unique('email');
    }
}
