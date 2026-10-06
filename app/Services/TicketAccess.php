<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\Ticket;
use App\Models\User;

/**
 * Who may open a ticket: the SDS admin, the student who filed it, the recipient it is assigned
 * to, and the recipient an informational ticket was forwarded to. Nobody else, which is what
 * keeps sensitive tickets between the SDS Office and the assigned handler.
 */
class TicketAccess
{
    public function canViewComplaint(?User $user, Complaint $complaint): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSdsAdmin() || $complaint->isFiledBy($user)) {
            return true;
        }

        return $complaint->ticket !== null && $this->isRecipientOf($user, $complaint->ticket);
    }

    public function canView(?User $user, Ticket $ticket): bool
    {
        return $ticket->complaint !== null && $this->canViewComplaint($user, $ticket->complaint);
    }

    /**
     * The recipient handling the ticket, or the one an informational ticket was forwarded to.
     */
    public function isRecipientOf(User $user, Ticket $ticket): bool
    {
        if (! $user->isRecipient()) {
            return false;
        }

        return (int) $ticket->assigned_to === (int) $user->id || $this->wasForwardedTo($user, $ticket);
    }

    public function wasForwardedTo(User $user, Ticket $ticket): bool
    {
        return $user->recipient !== null
            && $ticket->forwarded_to !== null
            && (int) $ticket->forwarded_to === (int) $user->recipient->id;
    }
}
