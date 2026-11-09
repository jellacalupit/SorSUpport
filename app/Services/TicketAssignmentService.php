<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class TicketAssignmentService
{
    /**
     * Open tickets for which this user is either the assignee or current handler.
     *
     * @return Collection<int, Ticket>
     */
    public function openTicketsHandledBy(User $user): Collection
    {
        return Ticket::query()
            ->whereIn('status', [
                Ticket::STATUS_ASSIGNED,
                Ticket::STATUS_IN_PROGRESS,
                Ticket::STATUS_ESCALATED,
            ])
            ->where(fn ($query) => $query
                ->where('current_handler_id', $user->id)
                ->orWhere('assigned_to', $user->id))
            ->with('complaint')
            ->orderBy('id')
            ->get();
    }
}
