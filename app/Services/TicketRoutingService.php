<?php

namespace App\Services;

use App\Models\Recipient;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TicketRoutingService
{
    /**
     * Category configuration grants eligibility; a student's suggestion does not.
     */
    protected function configuredRecipientIds(Ticket $ticket): Collection
    {
        $category = $ticket->complaint?->category;

        if (! $category) {
            return collect();
        }

        return collect([$category->recipient_id])
            ->merge($category->suggestedRecipients()->pluck('recipients.id'))
            ->merge($category->escalationHierarchies()->pluck('recipient_id'))
            ->filter()
            ->unique()
            ->values();
    }

    public function availableRecipients(Ticket $ticket): Collection
    {
        return Recipient::query()
            ->active()
            ->whereHas('user', fn ($query) => $query->where('role', User::ROLE_RECIPIENT))
            ->whereIn('id', $this->configuredRecipientIds($ticket))
            ->with('user')
            ->orderBy('unit')
            ->get()
            ->reject(fn (Recipient $recipient) => $ticket->complaint?->names($recipient->user))
            ->values();
    }

    public function validateRecipient(Ticket $ticket, Recipient $recipient, string $action = 'assigned'): void
    {
        // Query current account state rather than trusting a previously loaded relation.
        $user = $recipient->user()->first();

        if (! $user?->is_active || ! $user->isRecipient()) {
            throw ValidationException::withMessages([
                'recipient_id' => 'Select an active recipient account.',
            ]);
        }

        if (! $this->configuredRecipientIds($ticket)->contains($recipient->id)) {
            throw ValidationException::withMessages([
                'recipient_id' => 'This recipient is not configured for the ticket category.',
            ]);
        }

        if ($ticket->complaint?->names($user)) {
            throw ValidationException::withMessages([
                'recipient_id' => "This ticket cannot be {$action} to a person named in the complaint.",
            ]);
        }
    }
}
