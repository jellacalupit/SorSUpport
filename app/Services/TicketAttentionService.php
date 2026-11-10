<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Collection;

class TicketAttentionService
{
    public function __construct(
        protected TicketReminderService $reminders,
        protected TicketWorkflow $workflow,
    ) {
    }

    /**
     * Tickets that may need SDS attention, with the reasons and display age for each row.
     *
     * @return Collection<int, array{ticket: Ticket, reasons: list<string>, pending: bool, days_open: int}>
     */
    public function tickets(): Collection
    {
        $settings = $this->reminders->settings();

        return Ticket::query()
            ->whereIn('status', [
                Ticket::STATUS_SUBMITTED,
                Ticket::STATUS_NEEDS_CLARIFICATION,
                Ticket::STATUS_ASSIGNED,
                Ticket::STATUS_IN_PROGRESS,
                Ticket::STATUS_ESCALATED,
                Ticket::STATUS_REFERRED,
            ])
            ->with([
                'complaint.student.user',
                'complaint.category',
                'currentHandler.recipient',
                'assignee.recipient',
                'auditLogs.performer',
            ])
            ->get()
            ->map(function (Ticket $ticket) use ($settings): ?array {
                $reasons = [];
                $pendingEscalation = $this->workflow->pendingEscalationRequest($ticket);
                $pendingUpdate = $this->workflow->pendingUpdateRequest($ticket);
                $pendingReturn = $this->workflow->pendingReturnToSds($ticket);

                if ($pendingEscalation) {
                    $reasons[] = 'Student requested escalation';
                }

                if ($pendingReturn) {
                    $reasons[] = 'Returned by office';
                }

                if ($pendingUpdate) {
                    $reasons[] = 'Student requested an update';
                }

                $stalledStatuses = [
                    Ticket::STATUS_SUBMITTED,
                    Ticket::STATUS_ASSIGNED,
                    Ticket::STATUS_IN_PROGRESS,
                    Ticket::STATUS_ESCALATED,
                    Ticket::STATUS_REFERRED,
                ];
                $threshold = in_array($ticket->status, $stalledStatuses, true)
                    && ! ($ticket->status === Ticket::STATUS_ASSIGNED && $ticket->acknowledged_at)
                    ? $this->reminders->allowedDays($ticket, $settings)
                    : null;
                $daysWithoutAction = $this->reminders->workingDaysWaited($ticket);

                if ($threshold !== null && $daysWithoutAction >= $threshold) {
                    $reasons[] = "No action for {$daysWithoutAction} working days";
                }

                $handlers = collect([$ticket->currentHandler, $ticket->assignee])->filter();
                if ($handlers->contains(fn ($handler): bool => ! $handler->is_active)) {
                    $reasons[] = 'Handler account inactive';
                }

                if ($reasons === []) {
                    return null;
                }

                return [
                    'ticket' => $ticket,
                    'reasons' => $reasons,
                    'pending' => $pendingEscalation !== null || $pendingUpdate !== null || $pendingReturn !== null,
                    'days_open' => $ticket->daysOpen(),
                ];
            })
            ->filter()
            ->sortBy(fn (array $row): array => [
                $row['pending'] ? 0 : 1,
                $row['ticket']->complaint?->created_at?->timestamp ?? $row['ticket']->created_at?->timestamp ?? 0,
            ])
            ->values();
    }
}
