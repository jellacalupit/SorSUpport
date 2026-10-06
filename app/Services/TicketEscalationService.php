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
     * The category's escalation paths that the ticket can be escalated along. Each option is one
     * configured step: ['path' => name, 'level' => n, 'recipient' => Recipient]. Only recipients
     * configured in the hierarchy are offered, minus the current handler and inactive accounts.
     */
    public function escalationOptions(Ticket $ticket): Collection
    {
        $category = $ticket->complaint?->category;

        if (! $category) {
            return collect();
        }

        $currentHandlerUserId = $ticket->assigned_to ?: $ticket->current_handler_id;

        return $category->escalationHierarchies()
            ->with('recipient.user')
            ->get()
            ->filter(fn ($entry) => $entry->recipient?->user?->is_active
                && $entry->recipient->user->email_verified_at
                && (int) $entry->recipient->user_id !== (int) $currentHandlerUserId)
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

        $hierarchy = $category->escalationHierarchies()->with('recipient.user')->get();
        $options = $this->escalationOptions($ticket);

        if ($options->isEmpty()) {
            return null;
        }

        $currentHandlerUserId = $ticket->assigned_to ?: $ticket->current_handler_id;
        $current = $hierarchy->first(fn ($entry): bool => (int) $entry->recipient?->user_id === (int) $currentHandlerUserId);

        if ($current) {
            $next = $options
                ->where('path_number', (int) $current->path_number)
                ->first(fn (array $option): bool => $option['level'] > (int) $current->level);

            if ($next) {
                return $next['recipient'];
            }
        }

        return $options->first()['recipient'];
    }

    /**
     * Escalate a ticket to the recipient chosen by the SDS admin and record when it happened.
     */
    public function escalate(Ticket $ticket, Recipient $targetRecipient, ?User $performedBy = null): void
    {
        DB::transaction(function () use ($ticket, $targetRecipient, $performedBy): void {
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
                sprintf('Ticket escalated to %s.', $targetRecipient->user?->display_name ?? 'the selected recipient')
            );

            if ($ticket->complaint?->names($targetRecipient->user)) {
                AuditLog::log($ticket->id, 'conflict_of_interest_flagged', $performedBy?->id, 'The ticket was escalated to a person who appears to be named in the complaint.');
            }

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
