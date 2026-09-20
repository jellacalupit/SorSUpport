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

    public function escalate(Ticket $ticket, ?User $performedBy = null): bool
    {
        $targetRecipient = $this->getNextRecipient($ticket);

        if (! $targetRecipient) {
            return false;
        }

        $category = $ticket->complaint?->category;

        if (! $category) {
            return false;
        }

        $deadline = $ticket->deadline?->isFuture()
            ? $ticket->deadline
            : now()->addDays((int) $category->resolution_deadline_days);

        DB::transaction(function () use ($ticket, $targetRecipient, $performedBy, $deadline): void {
            $performedById = $performedBy?->id
                ?? $ticket->current_handler_id
                ?? $ticket->assigned_to;

        $ticket->update([
                'status' => Ticket::STATUS_ESCALATED,
                'assigned_to' => $targetRecipient->user_id,
                'current_handler_id' => $targetRecipient->user_id,
                'deadline' => $deadline,
            ]);

            AuditLog::log(
                $ticket->id,
                'ticket_escalated',
                $performedById,
                'Ticket escalated to the next configured recipient authority.'
            );

            $usersToNotify = $this->getUsersToNotify($ticket, $targetRecipient, $performedBy);

            foreach ($usersToNotify as $user) {
                if (! $user?->email) {
                    continue;
                }

                EmailNotification::create([
                    'ticket_id' => $ticket->id,
                    'recipient_email' => $user->email,
                    'type' => EmailNotification::TYPE_ESCALATED,
                    'status' => EmailNotification::STATUS_PENDING,
                ]);
            }
        });

        return true;
    }

    protected function getUsersToNotify(Ticket $ticket, Recipient $targetRecipient, ?User $performedBy = null): Collection
    {
        $users = collect();

        if ($performedBy?->isSdsAdmin()) {
            $users->push($performedBy);
        }

        if (! $performedBy) {
            $users->push(User::query()->where('role', User::ROLE_SDS_ADMIN)->first());
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
