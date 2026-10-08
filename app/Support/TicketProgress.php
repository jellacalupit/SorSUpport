<?php

namespace App\Support;

use App\Models\Ticket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Says where a ticket is waiting and how long each step took, from the ticket's own history.
 * Nothing here moves a ticket: delays are shown, never acted on automatically.
 */
class TicketProgress
{
    /** Open tickets with no action for this many days are pointed out to the SDS admin. */
    public const ATTENTION_DAYS = 3;

    /** The first thing the SDS admin does with a new ticket. */
    public const REVIEW_ACTIONS = [
        'clarification_requested',
        'ticket_assigned',
        'ticket_acknowledged',
        'ticket_closed',
        'ticket_closed_invalid',
        'ticket_forwarded_and_closed',
        'ticket_retained_in_sds_records',
    ];

    /**
     * Who the ticket is waiting on now and since when, or null once it is closed.
     *
     * @return array{text: string, since: ?Carbon, days: int}|null
     */
    public static function waitingOn(Ticket $ticket): ?array
    {
        if ($ticket->status === Ticket::STATUS_CLOSED) {
            return null;
        }

        $holder = self::holder($ticket);
        $lastAction = $ticket->lastActionAt();

        [$text, $since] = match ($ticket->status) {
            Ticket::STATUS_SUBMITTED => ['Waiting for review by the SDS Office', $lastAction],
            Ticket::STATUS_NEEDS_CLARIFICATION => ['Waiting for the student\'s reply', $ticket->clarification_requested_at ?? $lastAction],
            Ticket::STATUS_ASSIGNED => ["Waiting for {$holder} to acknowledge it", $lastAction],
            Ticket::STATUS_IN_PROGRESS => ["Being handled by {$holder}", $lastAction],
            Ticket::STATUS_ESCALATED => ["Escalated to {$holder}", $lastAction],
            Ticket::STATUS_REFERRED => ['With the ' . ($ticket->referred_to ?: 'committee') . ' for a decision', $ticket->referred_at ?? $lastAction],
            Ticket::STATUS_RESOLVED => ['Waiting for the student to accept the resolution', $ticket->resolved_at ?? $lastAction],
            default => ['Open', $lastAction],
        };

        return [
            'text' => $text,
            'since' => $since,
            'days' => $since ? max(0, (int) floor($since->diffInDays(now()))) : 0,
        ];
    }

    /**
     * The person or office holding the ticket, as it may be shown to anyone on the ticket.
     */
    public static function holder(Ticket $ticket): string
    {
        $handler = $ticket->assignee ?? $ticket->currentHandler;

        if (! $handler || $handler->isSdsAdmin()) {
            return 'the SDS Office';
        }

        $office = trim(implode(', ', array_filter([$handler->recipient?->designation, $handler->recipient?->unit])));

        return $handler->table_name . ($office !== '' ? " ({$office})" : '');
    }

    /**
     * How long tickets wait at each stage, in days, and the open tickets waiting longest.
     *
     * @param  Collection<int, Ticket>  $tickets  with complaint, auditLogs, assignee.recipient and currentHandler.recipient loaded
     */
    public static function waitingTimes(Collection $tickets): array
    {
        $firstLog = fn (Ticket $ticket, array $actions, ?Carbon $after = null) => $ticket->auditLogs
            ->filter(fn ($log) => in_array($log->action, $actions, true) && (! $after || $log->created_at->greaterThanOrEqualTo($after)))
            ->sortBy('created_at')
            ->first()?->created_at;
        $days = fn (?Carbon $from, ?Carbon $to) => $from && $to ? max(0, $from->floatDiffInDays($to)) : null;
        $average = function (Collection $values): ?float {
            $found = $values->filter(fn ($value) => $value !== null);

            return $found->isEmpty() ? null : round($found->avg(), 2);
        };

        $review = $tickets->map(fn (Ticket $ticket) => $days($ticket->complaint?->created_at ?? $ticket->created_at, $firstLog($ticket, self::REVIEW_ACTIONS)));
        $acknowledge = $tickets->map(function (Ticket $ticket) use ($firstLog, $days) {
            $assigned = $firstLog($ticket, ['ticket_assigned']);

            return $assigned ? $days($assigned, $firstLog($ticket, ['ticket_acknowledged'], $assigned)) : null;
        });
        $handling = $tickets->map(function (Ticket $ticket) use ($firstLog, $days) {
            $started = $firstLog($ticket, ['ticket_assigned', 'ticket_acknowledged']);

            return $started ? $days($started, $ticket->resolved_at) : null;
        });

        $open = $tickets->where('status', '!=', Ticket::STATUS_CLOSED);
        $awaitingReview = $open->where('status', Ticket::STATUS_SUBMITTED);

        $longest = $open
            ->map(fn (Ticket $ticket) => [
                'ticket' => $ticket,
                'reference' => $ticket->complaint?->reference_number ?? (string) $ticket->id,
                'subject' => $ticket->complaint?->public_subject ?? 'Untitled',
                'status' => $ticket->status_label,
                'waiting' => self::waitingOn($ticket)['text'] ?? '',
                'days_open' => $ticket->daysOpen(),
                'idle' => (int) ($ticket->daysSinceLastAction() ?? 0),
            ])
            ->sortByDesc(fn (array $row) => [$row['idle'], $row['days_open']])
            ->take(8)
            ->values();

        return [
            'review_days' => $average($review),
            'reviewed' => $review->filter(fn ($value) => $value !== null)->count(),
            'acknowledge_days' => $average($acknowledge),
            'handling_days' => $average($handling),
            'awaiting_review' => $awaitingReview->count(),
            'oldest_review_days' => (int) ($awaitingReview->max(fn (Ticket $ticket) => $ticket->daysSinceLastAction() ?? 0) ?? 0),
            // Waiting on the SDS admin's review, and waiting on the staff handling them.
            'review_overdue' => $awaitingReview->filter(fn (Ticket $ticket) => ($ticket->daysSinceLastAction() ?? 0) >= self::ATTENTION_DAYS)->count(),
            'stalled' => $open->whereIn('status', [Ticket::STATUS_ASSIGNED, Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_ESCALATED])
                ->filter(fn (Ticket $ticket) => ($ticket->daysSinceLastAction() ?? 0) >= self::ATTENTION_DAYS)->count(),
            'longest' => $longest,
        ];
    }
}
