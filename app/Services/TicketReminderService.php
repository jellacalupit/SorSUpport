<?php

namespace App\Services;

use App\Models\EmailNotification;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Finds tickets that have waited too long at one step and emails whoever has to act next:
 * the SDS Office for a ticket not yet reviewed, the handler for one not yet acknowledged or
 * with no action recorded. A reminder repeats every few days until someone acts.
 */
class TicketReminderService
{
    public const WAITING_STATUSES = [
        Ticket::STATUS_SUBMITTED,
        Ticket::STATUS_ASSIGNED,
        Ticket::STATUS_IN_PROGRESS,
        Ticket::STATUS_ESCALATED,
    ];

    /**
     * Queue a reminder for every ticket that is waiting too long. Returns how many tickets
     * were reminded about.
     */
    public function queueReminders(): int
    {
        $reminded = 0;

        Ticket::query()
            ->whereIn('status', self::WAITING_STATUSES)
            ->with(['complaint', 'currentHandler', 'assignee', 'auditLogs'])
            ->each(function (Ticket $ticket) use (&$reminded): void {
                if (! $this->isDue($ticket)) {
                    return;
                }

                $people = $this->peopleToRemind($ticket);

                $people->each(fn (User $user) => EmailNotification::create([
                    'ticket_id' => $ticket->id,
                    'recipient_email' => $user->email,
                    'type' => EmailNotification::TYPE_WAITING_REMINDER,
                    'status' => EmailNotification::STATUS_PENDING,
                ]));

                if ($people->isNotEmpty()) {
                    $reminded++;
                }
            });

        return $reminded;
    }

    /**
     * Days a ticket may wait at its current step before a reminder goes out.
     */
    public function allowedDays(Ticket $ticket): ?int
    {
        $days = match ($ticket->status) {
            Ticket::STATUS_SUBMITTED => config('tickets.reminders.unreviewed_days'),
            Ticket::STATUS_ASSIGNED => config('tickets.reminders.unacknowledged_days'),
            Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_ESCALATED => config('tickets.reminders.idle_days'),
            default => null,
        };

        return $days === null ? null : max(1, (int) $days);
    }

    /**
     * Waited past the allowed days since the last action, and not already reminded within them.
     */
    public function isDue(Ticket $ticket): bool
    {
        $days = $this->allowedDays($ticket);
        $lastAction = $ticket->lastActionAt();

        if ($days === null || ! $lastAction) {
            return false;
        }

        $windowStart = now()->subDays($days);

        if ($lastAction->greaterThan($windowStart)) {
            return false;
        }

        $lastReminder = $ticket->emailNotifications()
            ->where('type', EmailNotification::TYPE_WAITING_REMINDER)
            ->max('created_at');

        return ! ($lastReminder
            && Carbon::parse($lastReminder)->greaterThan($lastAction)
            && Carbon::parse($lastReminder)->greaterThan($windowStart));
    }

    /**
     * The SDS Office reviews new tickets; the handler acts on assigned ones. A ticket without a
     * handler falls back to the SDS Office.
     *
     * @return Collection<int, User>
     */
    public function peopleToRemind(Ticket $ticket): Collection
    {
        $handler = $ticket->status === Ticket::STATUS_SUBMITTED
            ? null
            : ($ticket->currentHandler ?? $ticket->assignee);

        $people = $handler && $handler->is_active
            ? collect([$handler])
            : User::query()->where('role', User::ROLE_SDS_ADMIN)->where('is_active', true)->get();

        return $people->filter(fn (User $user) => filled($user->email))->unique('id')->values();
    }
}
