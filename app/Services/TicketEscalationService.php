<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\EmailNotification;
use App\Models\Recipient;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketEscalationService
{
    /**
     * The category's escalation paths that the ticket can be escalated along. Each option is one
     * configured step: ['path' => name, 'level' => n, 'recipient' => Recipient]. Only recipients
     * configured above the current handler on the same path are offered. Inactive accounts are
     * never offered. A ticket handled directly by SDS may enter at any configured step, but a
     * recipient who is not in a path has no comparable higher position and therefore no targets.
     */
    public function escalationOptions(Ticket $ticket): Collection
    {
        $category = $ticket->complaint?->category;

        if (! $category) {
            return collect();
        }

        $currentHandlerUserId = $ticket->assigned_to ?: $ticket->current_handler_id;
        $hierarchy = $category->escalationHierarchies()
            ->with('recipient.user')
            ->get();
        $currentLevelsByPath = $hierarchy
            ->filter(fn ($entry): bool => (int) $entry->recipient?->user_id === (int) $currentHandlerUserId)
            ->groupBy(fn ($entry): int => (int) $entry->path_number)
            ->map(fn (Collection $entries): int => (int) $entries->max('level'));
        $currentHandlerIsRecipient = filled($currentHandlerUserId)
            && Recipient::query()->where('user_id', $currentHandlerUserId)->exists();

        return $hierarchy
            ->filter(function ($entry) use ($currentLevelsByPath, $currentHandlerUserId, $currentHandlerIsRecipient, $ticket): bool {
                if (! $entry->recipient?->user?->is_active
                    || (int) $entry->recipient->user_id === (int) $currentHandlerUserId
                    || $ticket->complaint?->names($entry->recipient->user)) {
                    return false;
                }

                if ($currentLevelsByPath->isEmpty()) {
                    return ! $currentHandlerIsRecipient;
                }

                $currentLevel = $currentLevelsByPath->get((int) $entry->path_number);

                return $currentLevel !== null && (int) $entry->level > $currentLevel;
            })
            ->map(fn ($entry) => [
                'path_number' => (int) $entry->path_number,
                'path' => $entry->path_name ?: 'Path ' . $entry->path_number,
                'level' => (int) $entry->level,
                'recipient' => $entry->recipient,
            ])
            ->values();
    }

    /**
     * Recipients a ticket can be escalated to: only those configured in the category's hierarchy.
     */
    public function escalationTargets(Ticket $ticket): Collection
    {
        return $this->escalationOptions($ticket)->pluck('recipient')->unique('id')->values();
    }

    /**
     * Suggested escalation target: the next level on the path the current handler is on,
     * otherwise the first step of the first path.
     */
    public function getNextRecipient(Ticket $ticket): ?Recipient
    {
        $category = $ticket->complaint?->category;

        if (! $category) {
            return null;
        }

        $options = $this->escalationOptions($ticket);

        if ($options->isEmpty()) {
            return null;
        }

        return $options->first()['recipient'];
    }

    /**
     * Escalate a ticket to the recipient chosen by the SDS admin and record when it happened.
     */
    public function escalate(Ticket $ticket, Recipient $targetRecipient, ?User $performedBy = null, ?string $note = null): void
    {
        if ($targetRecipient->user && $ticket->complaint?->names($targetRecipient->user)) {
            throw ValidationException::withMessages([
                'recipient_id' => 'This ticket cannot be escalated to a person named in the complaint.',
            ]);
        }

        DB::transaction(function () use ($ticket, $targetRecipient, $performedBy, $note): void {
            // Escalating again keeps the Escalated status and only changes who holds the ticket.
            $ticket->update([
                ...($ticket->status === Ticket::STATUS_ESCALATED ? [] : ['status' => Ticket::STATUS_ESCALATED]),
                'assigned_to' => $targetRecipient->user_id,
                'current_handler_id' => $targetRecipient->user_id,
                'escalated_at' => now(),
            ]);


            AuditLog::log(
                $ticket->id,
                'ticket_escalated',
                $performedBy?->id,
                // The admin's explanation is kept with the entry so the next handler can read it.
                trim(sprintf('Ticket escalated to %s.', $targetRecipient->user?->display_name ?? 'the selected recipient') . (filled($note) ? ' Reason: ' . $note : ''))
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
