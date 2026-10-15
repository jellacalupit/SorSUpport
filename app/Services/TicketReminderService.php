<?php

namespace App\Services;

use App\Models\EmailNotification;
use App\Models\SystemSetting;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Finds tickets that have waited too long at one step and emails whoever has to act next:
 * the SDS Office for a ticket not yet reviewed, referred to a committee, or past the student's
 * days to respond; the handler for one not yet acknowledged or with no action recorded.
 * Waiting is counted in working days, and a reminder repeats until someone acts.
 */
class TicketReminderService
{
    public const SETTINGS = [
        'enabled',
        'unreviewed_days',
        'sensitive_unreviewed_days',
        'unacknowledged_days',
        'idle_days',
        'referral_days',
        'student_response_days',
    ];

    public const WAITING_STATUSES = [
        Ticket::STATUS_SUBMITTED,
        Ticket::STATUS_ASSIGNED,
        Ticket::STATUS_IN_PROGRESS,
        Ticket::STATUS_ESCALATED,
        Ticket::STATUS_REFERRED,
        Ticket::STATUS_RESOLVED,
    ];

    /**
     * The reminder settings: what the SDS admin saved in System Settings, otherwise the defaults.
     *
     * @return array{enabled: bool, unreviewed_days: int, sensitive_unreviewed_days: int, unacknowledged_days: int, idle_days: int, referral_days: int, student_response_days: int}
     */
    public function settings(): array
    {
        $saved = SystemSetting::query()
            ->whereIn('key', array_map(fn (string $key) => "reminders.{$key}", self::SETTINGS))
            ->pluck('value', 'key');

        $settings = [];

        foreach (self::SETTINGS as $key) {
            $value = $saved["reminders.{$key}"] ?? config("tickets.reminders.{$key}");
            $settings[$key] = $key === 'enabled' ? (bool) $value : max(1, (int) $value);
        }

        return $settings;
    }

    public function saveSettings(array $settings): void
    {
        foreach (self::SETTINGS as $key) {
            if (array_key_exists($key, $settings)) {
                SystemSetting::put("reminders.{$key}", $key === 'enabled' ? (int) (bool) $settings[$key] : (int) $settings[$key]);
            }
        }
    }

    /**
     * Queue a reminder for every ticket that is waiting too long. Returns how many tickets
     * were reminded about.
     */
    public function queueReminders(): int
    {
        $settings = $this->settings();

        if (! $settings['enabled']) {
            return 0;
        }

        $reminded = 0;

        Ticket::query()
            ->whereIn('status', self::WAITING_STATUSES)
            ->with(['complaint.category', 'currentHandler', 'assignee', 'auditLogs'])
            ->each(function (Ticket $ticket) use (&$reminded, $settings): void {
                if (! $this->isDue($ticket, $settings)) {
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
     * Working days a ticket may wait at its current step before a reminder goes out. A resolved
     * ticket is not counted this way: it waits for the student's days to respond instead.
     */
    public function allowedDays(Ticket $ticket, ?array $settings = null): ?int
    {
        $settings ??= $this->settings();

        return match ($ticket->status) {
            Ticket::STATUS_SUBMITTED => $ticket->complaint?->isSensitive()
                ? $settings['sensitive_unreviewed_days']
                : $settings['unreviewed_days'],
            Ticket::STATUS_ASSIGNED => $settings['unacknowledged_days'],
            Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_ESCALATED => $settings['idle_days'],
            Ticket::STATUS_REFERRED => $settings['referral_days'],
            default => null,
        };
    }

    public function isDue(Ticket $ticket, ?array $settings = null): bool
    {
        if ($ticket->status === Ticket::STATUS_RESOLVED) {
            return $this->studentDaysHavePassed($ticket);
        }

        $days = $this->allowedDays($ticket, $settings);
        $lastAction = $ticket->lastActionAt();

        if ($days === null || ! $lastAction || self::workingDaysBetween($lastAction, now()) < $days) {
            return false;
        }

        // Already reminded since the last action: wait the same number of days before the next.
        $lastReminder = $this->lastReminderAt($ticket);

        return ! ($lastReminder
            && $lastReminder->greaterThan($lastAction)
            && self::workingDaysBetween($lastReminder, now()) < $days);
    }

    /**
     * The student's days to accept or ask for further action are over without an answer. The
     * SDS Office is told once, so it can close the ticket.
     */
    protected function studentDaysHavePassed(Ticket $ticket): bool
    {
        $deadline = $ticket->furtherActionDeadline();

        if (! $deadline || now()->lessThanOrEqualTo($deadline)) {
            return false;
        }

        $lastReminder = $this->lastReminderAt($ticket);

        return ! ($lastReminder && $lastReminder->greaterThan($ticket->resolved_at));
    }

    /**
     * Whole working days the ticket has waited since the last action.
     */
    public function workingDaysWaited(Ticket $ticket): int
    {
        $lastAction = $ticket->lastActionAt();

        return $lastAction ? (int) floor(self::workingDaysBetween($lastAction, now())) : 0;
    }

    /**
     * The SDS Office reviews new tickets, follows up referrals and closes tickets the student
     * left unanswered; the handler acts on assigned ones. A ticket without an active handler
     * falls back to the SDS Office.
     *
     * @return Collection<int, User>
     */
    public function peopleToRemind(Ticket $ticket): Collection
    {
        $forSdsOffice = in_array($ticket->status, [Ticket::STATUS_SUBMITTED, Ticket::STATUS_REFERRED, Ticket::STATUS_RESOLVED], true);
        $handler = $forSdsOffice ? null : ($ticket->currentHandler ?? $ticket->assignee);

        $people = $handler && $handler->is_active
            ? collect([$handler])
            : User::query()->where('role', User::ROLE_SDS_ADMIN)->where('is_active', true)->get();

        return $people->filter(fn (User $user) => filled($user->email))->unique('id')->values();
    }

    /**
     * Days, with fractions, between two moments, counting only Monday to Friday.
     */
    public static function workingDaysBetween(Carbon $from, Carbon $to): float
    {
        $seconds = 0;
        $cursor = $from->copy();

        while ($cursor->lessThan($to)) {
            $nextDay = $cursor->copy()->startOfDay()->addDay();
            $segmentEnd = $nextDay->lessThan($to) ? $nextDay : $to;

            if (! $cursor->isWeekend()) {
                $seconds += $cursor->diffInSeconds($segmentEnd);
            }

            $cursor = $segmentEnd;
        }

        return $seconds / 86400;
    }

    protected function lastReminderAt(Ticket $ticket): ?Carbon
    {
        $latest = $ticket->emailNotifications()
            ->where('type', EmailNotification::TYPE_WAITING_REMINDER)
            ->max('created_at');

        return $latest ? Carbon::parse($latest) : null;
    }
}
