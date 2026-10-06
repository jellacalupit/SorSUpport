<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\EmailNotification;
use App\Models\Recipient;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The life of a ticket in one place: which actions exist, who may take each one from which
 * status, and what happens when they do. Controllers call these methods instead of changing a
 * ticket's status themselves.
 */
class TicketWorkflow
{
    public const REQUEST_CLARIFICATION = 'request_clarification';

    public const PROVIDE_CLARIFICATION = 'provide_clarification';

    public const HANDLE_DIRECTLY = 'handle_directly';

    public const ASSIGN = 'assign';

    public const ACKNOWLEDGE = 'acknowledge';

    public const ESCALATE = 'escalate';

    public const REFER = 'refer';

    public const RECORD_OUTCOME = 'record_outcome';

    public const RESOLVE = 'resolve';

    public const REOPEN = 'reopen';

    public const REQUEST_FURTHER_ACTION = 'request_further_action';

    public const ACCEPT_RESOLUTION = 'accept_resolution';

    public const CLOSE = 'close';

    public const WITHDRAW = 'withdraw';

    public const BY_ADMIN = 'admin';

    public const BY_HANDLER = 'handler';

    public const BY_STUDENT = 'student';

    /**
     * action => who may take it, from which statuses, and the status it leads to.
     * "handler" is the admin or recipient the ticket is currently assigned to; "student" is the
     * student who submitted it.
     */
    public const RULES = [
        self::REQUEST_CLARIFICATION => [
            'by' => self::BY_ADMIN,
            'from' => [Ticket::STATUS_SUBMITTED],
            'to' => Ticket::STATUS_NEEDS_CLARIFICATION,
        ],
        self::PROVIDE_CLARIFICATION => [
            'by' => self::BY_STUDENT,
            'from' => [Ticket::STATUS_NEEDS_CLARIFICATION],
            'to' => Ticket::STATUS_SUBMITTED,
        ],
        self::HANDLE_DIRECTLY => [
            'by' => self::BY_ADMIN,
            'from' => [Ticket::STATUS_SUBMITTED, Ticket::STATUS_NEEDS_CLARIFICATION],
            'to' => Ticket::STATUS_IN_PROGRESS,
        ],
        self::ASSIGN => [
            'by' => self::BY_ADMIN,
            'from' => [Ticket::STATUS_SUBMITTED, Ticket::STATUS_NEEDS_CLARIFICATION, Ticket::STATUS_ASSIGNED, Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_ESCALATED],
            'to' => Ticket::STATUS_ASSIGNED,
        ],
        self::ACKNOWLEDGE => [
            'by' => self::BY_HANDLER,
            'from' => [Ticket::STATUS_ASSIGNED],
            'to' => Ticket::STATUS_IN_PROGRESS,
        ],
        self::ESCALATE => [
            'by' => self::BY_ADMIN,
            'from' => [Ticket::STATUS_ASSIGNED, Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_ESCALATED],
            'to' => Ticket::STATUS_ESCALATED,
        ],
        self::REFER => [
            'by' => self::BY_ADMIN,
            'from' => [Ticket::STATUS_ASSIGNED, Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_ESCALATED],
            'to' => Ticket::STATUS_REFERRED,
        ],
        self::RECORD_OUTCOME => [
            'by' => self::BY_ADMIN,
            'from' => [Ticket::STATUS_REFERRED],
            'to' => Ticket::STATUS_RESOLVED,
        ],
        self::RESOLVE => [
            'by' => self::BY_HANDLER,
            'from' => [Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_ESCALATED],
            'to' => Ticket::STATUS_RESOLVED,
        ],
        self::REOPEN => [
            'by' => self::BY_ADMIN,
            'from' => [Ticket::STATUS_RESOLVED],
            'to' => Ticket::STATUS_IN_PROGRESS,
        ],
        self::REQUEST_FURTHER_ACTION => [
            'by' => self::BY_STUDENT,
            'from' => [Ticket::STATUS_RESOLVED],
            'to' => Ticket::STATUS_IN_PROGRESS,
        ],
        self::ACCEPT_RESOLUTION => [
            'by' => self::BY_STUDENT,
            'from' => [Ticket::STATUS_RESOLVED],
            'to' => Ticket::STATUS_CLOSED,
        ],
        self::CLOSE => [
            'by' => self::BY_ADMIN,
            'from' => [Ticket::STATUS_SUBMITTED, Ticket::STATUS_NEEDS_CLARIFICATION, Ticket::STATUS_ASSIGNED, Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_ESCALATED, Ticket::STATUS_REFERRED, Ticket::STATUS_RESOLVED],
            'to' => Ticket::STATUS_CLOSED,
        ],
        self::WITHDRAW => [
            'by' => self::BY_STUDENT,
            'from' => [Ticket::STATUS_SUBMITTED, Ticket::STATUS_NEEDS_CLARIFICATION, Ticket::STATUS_ASSIGNED, Ticket::STATUS_IN_PROGRESS, Ticket::STATUS_ESCALATED, Ticket::STATUS_REFERRED],
            'to' => Ticket::STATUS_CLOSED,
        ],
    ];

    public function can(User $user, Ticket $ticket, string $action): bool
    {
        $rule = self::RULES[$action] ?? null;

        if (! $rule || ! in_array($ticket->status, $rule['from'], true)) {
            return false;
        }

        $allowed = match ($rule['by']) {
            self::BY_ADMIN => $user->isSdsAdmin(),
            self::BY_HANDLER => $this->isHandler($user, $ticket),
            self::BY_STUDENT => $this->isOwner($user, $ticket),
            default => false,
        };

        if (! $allowed) {
            return false;
        }

        if ($action === self::REQUEST_FURTHER_ACTION) {
            return $this->withinFurtherActionWindow($ticket);
        }

        return true;
    }

    /**
     * Stop the request when the user may not take the action on this ticket.
     */
    public function authorize(User $user, Ticket $ticket, string $action): void
    {
        abort_unless($this->can($user, $ticket, $action), 403, 'This action is not available for the ticket in its current status.');
    }

    /**
     * Actions the user can take on the ticket right now.
     *
     * @return list<string>
     */
    public function availableActions(User $user, Ticket $ticket): array
    {
        return array_values(array_filter(
            array_keys(self::RULES),
            fn (string $action): bool => $this->can($user, $ticket, $action)
        ));
    }

    public function isHandler(User $user, Ticket $ticket): bool
    {
        if (! $user->isSdsAdmin() && ! $user->isRecipient()) {
            return false;
        }

        return in_array((int) $user->id, array_filter([(int) $ticket->current_handler_id, (int) $ticket->assigned_to]), true);
    }

    public function isOwner(User $user, Ticket $ticket): bool
    {
        return $user->isStudent()
            && $user->student
            && (int) $ticket->complaint?->student_id === (int) $user->student->id;
    }

    public function withinFurtherActionWindow(Ticket $ticket): bool
    {
        $deadline = $ticket->furtherActionDeadline();

        return $deadline === null || now()->lessThanOrEqualTo($deadline);
    }

    // ------------------------------------------------------------------ actions

    /**
     * The SDS Office asks the student for more details before reviewing the ticket.
     */
    public function requestClarification(Ticket $ticket, User $admin, string $question): void
    {
        $this->authorize($admin, $ticket, self::REQUEST_CLARIFICATION);

        DB::transaction(function () use ($ticket, $admin, $question): void {
            $ticket->update([
                'status' => Ticket::STATUS_NEEDS_CLARIFICATION,
                'clarification_requested_at' => now(),
            ]);

            $this->postMessage($ticket, $admin, $question);
            AuditLog::log($ticket->id, 'clarification_requested', $admin->id, 'The SDS Office asked the student for more details: ' . $question);
            $this->notify($ticket, [$this->student($ticket)], EmailNotification::TYPE_CLARIFICATION_REQUESTED);
        });
    }

    /**
     * The student answered, so the ticket returns to the SDS Office for review. The reply
     * itself is already posted in the conversation.
     */
    public function provideClarification(Ticket $ticket, User $student): void
    {
        $this->authorize($student, $ticket, self::PROVIDE_CLARIFICATION);

        DB::transaction(function () use ($ticket, $student): void {
            $ticket->update(['status' => Ticket::STATUS_SUBMITTED]);

            AuditLog::log($ticket->id, 'clarification_provided', $student->id, 'The student replied to the request for more details.');
            $this->notify($ticket, $this->admins(), EmailNotification::TYPE_CLARIFICATION_PROVIDED);
        });
    }

    /**
     * The SDS Office takes the ticket itself.
     */
    public function handleDirectly(Ticket $ticket, User $admin): void
    {
        $this->authorize($admin, $ticket, self::HANDLE_DIRECTLY);

        DB::transaction(function () use ($ticket, $admin): void {
            $ticket->update([
                'status' => Ticket::STATUS_IN_PROGRESS,
                'classification' => Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
                'jurisdiction' => Ticket::JURISDICTION_SDS,
                'assigned_to' => null,
                'current_handler_id' => $admin->id,
                'deadline' => null,
                'acknowledged_at' => now(),
            ]);

            AuditLog::log($ticket->id, 'ticket_acknowledged', $admin->id, 'The SDS Office is handling the ticket directly.');
            $this->notify($ticket, [$this->student($ticket)], EmailNotification::TYPE_STUDENT_STATUS_UPDATE);
        });
    }

    /**
     * Assign (or reassign) the ticket. With a recipient it goes to that office; without one the
     * SDS admin keeps it. Either way it waits as Assigned until the handler acknowledges it.
     */
    public function assign(Ticket $ticket, User $admin, ?Recipient $recipient = null): void
    {
        $this->authorize($admin, $ticket, self::ASSIGN);

        DB::transaction(function () use ($ticket, $admin, $recipient): void {
            $handlerId = $recipient ? $recipient->user_id : $admin->id;

            // Reassigning a ticket already waiting on someone keeps the Assigned status.
            $ticket->fill([
                'classification' => $ticket->classification ?? Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
                'jurisdiction' => $ticket->jurisdiction ?? ($recipient ? Ticket::JURISDICTION_RECIPIENT : Ticket::JURISDICTION_SDS),
                'assigned_to' => $recipient ? $handlerId : null,
                'current_handler_id' => $handlerId,
                'deadline' => null,
                'acknowledged_at' => null,
            ]);

            if ($ticket->status !== Ticket::STATUS_ASSIGNED) {
                $ticket->status = Ticket::STATUS_ASSIGNED;
            }

            $ticket->save();

            AuditLog::log(
                $ticket->id,
                'ticket_assigned',
                $admin->id,
                $recipient
                    ? "SDS Admin assigned the ticket {$ticket->complaint->reference_number} to {$recipient->user->display_name} for handling."
                    : 'Ticket assigned to SDS admin for direct handling.'
            );

            if ($recipient?->user) {
                $this->notify($ticket, [$recipient->user], EmailNotification::TYPE_RECIPIENT_ASSIGNMENT);
            }

            $this->notify($ticket, [$this->student($ticket)], EmailNotification::TYPE_STUDENT_STATUS_UPDATE);
        });
    }

    /**
     * The assigned handler confirms they have taken the ticket.
     */
    public function acknowledge(Ticket $ticket, User $handler): void
    {
        $this->authorize($handler, $ticket, self::ACKNOWLEDGE);

        DB::transaction(function () use ($ticket, $handler): void {
            $ticket->update([
                'status' => Ticket::STATUS_IN_PROGRESS,
                'acknowledged_at' => now(),
                'current_handler_id' => $handler->id,
            ]);

            AuditLog::log(
                $ticket->id,
                'ticket_acknowledged',
                $handler->id,
                $handler->isSdsAdmin() ? 'Ticket acknowledged by admin handler.' : 'Ticket acknowledged by recipient.'
            );
            $this->notify($ticket, [$this->student($ticket)], EmailNotification::TYPE_STUDENT_STATUS_UPDATE);
        });
    }

    /**
     * Send the ticket to a committee or board outside the system (for example the Committee on
     * Decorum and Investigation). The SDS Office records the outcome when it is decided.
     */
    public function refer(Ticket $ticket, User $admin, string $referredTo, ?string $note = null): void
    {
        $this->authorize($admin, $ticket, self::REFER);

        DB::transaction(function () use ($ticket, $admin, $referredTo, $note): void {
            $ticket->update([
                'status' => Ticket::STATUS_REFERRED,
                'referred_to' => $referredTo,
                'referred_at' => now(),
            ]);

            AuditLog::log($ticket->id, 'ticket_referred', $admin->id, trim("Ticket referred to {$referredTo}. " . ($note ?? '')));
            $this->notify($ticket, [$this->student($ticket), $this->handler($ticket)], EmailNotification::TYPE_REFERRED, except: $admin);
        });
    }

    /**
     * Record what the committee decided. The ticket becomes Resolved so the student can accept
     * the outcome or ask for further action.
     */
    public function recordOutcome(Ticket $ticket, User $admin, string $outcome, string $resolutionType = Ticket::RESOLUTION_COMMITTEE_DECISION): void
    {
        $this->authorize($admin, $ticket, self::RECORD_OUTCOME);

        DB::transaction(function () use ($ticket, $admin, $outcome, $resolutionType): void {
            $ticket->update([
                'status' => Ticket::STATUS_RESOLVED,
                'resolution_type' => $resolutionType,
                'resolved_at' => now(),
            ]);

            $this->postMessage($ticket, $admin, $outcome);
            AuditLog::log($ticket->id, 'referral_outcome_recorded', $admin->id, "Outcome from {$ticket->referred_to}: {$outcome}");
            $this->notify($ticket, [$this->student($ticket), $this->handler($ticket)], EmailNotification::TYPE_STUDENT_STATUS_UPDATE, except: $admin);
        });
    }

    /**
     * The handler marks the ticket resolved, saying how. The student then accepts it or asks
     * for further action.
     */
    public function resolve(Ticket $ticket, User $handler, string $resolutionType, string $message): void
    {
        $this->authorize($handler, $ticket, self::RESOLVE);

        DB::transaction(function () use ($ticket, $handler, $resolutionType, $message): void {
            $ticket->update([
                'status' => Ticket::STATUS_RESOLVED,
                'resolution_type' => $resolutionType,
                'resolved_at' => now(),
            ]);

            $this->postMessage($ticket, $handler, $message);
            AuditLog::log($ticket->id, 'complaint_resolved', $handler->id, $message);

            $this->notify($ticket, [$this->student($ticket)], EmailNotification::TYPE_STUDENT_STATUS_UPDATE);

            if (! $handler->isSdsAdmin()) {
                $this->notify($ticket, $this->admins(), EmailNotification::TYPE_RECIPIENT_RESOLVED);
            }
        });
    }

    /**
     * The SDS Office sends a resolved ticket back to its handler.
     */
    public function reopen(Ticket $ticket, User $admin): void
    {
        $this->authorize($admin, $ticket, self::REOPEN);

        DB::transaction(function () use ($ticket, $admin): void {
            $ticket->update([
                'status' => Ticket::STATUS_IN_PROGRESS,
                'resolution_type' => null,
                'resolved_at' => null,
                'closed_at' => null,
            ]);

            AuditLog::log($ticket->id, 'ticket_reopened_from_resolved', $admin->id, 'Ticket reopened from resolved by admin and moved back to in-progress.');
            $this->notify($ticket, [$this->student($ticket), $this->handler($ticket)], EmailNotification::TYPE_STUDENT_STATUS_UPDATE, except: $admin);
        });
    }

    /**
     * The student is not satisfied with the resolution and asks for more, within the allowed days.
     */
    public function requestFurtherAction(Ticket $ticket, User $student, string $reason): void
    {
        $this->authorize($student, $ticket, self::REQUEST_FURTHER_ACTION);

        DB::transaction(function () use ($ticket, $student, $reason): void {
            $ticket->update([
                'status' => Ticket::STATUS_IN_PROGRESS,
                'resolution_type' => null,
                'resolved_at' => null,
            ]);

            $this->postMessage($ticket, $student, $reason);
            AuditLog::log($ticket->id, 'further_action_requested', $student->id, 'The student asked for further action: ' . $reason);
            $this->notify($ticket, [$this->handler($ticket), ...$this->admins()], EmailNotification::TYPE_FURTHER_ACTION_REQUESTED);
        });
    }

    /**
     * The student accepts the resolution, which closes the ticket.
     */
    public function acceptResolution(Ticket $ticket, User $student): void
    {
        $this->authorize($student, $ticket, self::ACCEPT_RESOLUTION);

        DB::transaction(function () use ($ticket, $student): void {
            $ticket->update([
                'status' => Ticket::STATUS_CLOSED,
                'closure_type' => Ticket::CLOSURE_RESOLVED_ACCEPTED,
                'closed_at' => now(),
            ]);

            AuditLog::log($ticket->id, 'resolution_accepted', $student->id, 'The student accepted the resolution. Ticket closed.');
            $this->notify($ticket, [$this->handler($ticket), ...$this->admins()], EmailNotification::TYPE_RESOLUTION_ACCEPTED);
        });
    }

    /**
     * The SDS Office closes the ticket, recording why.
     */
    public function close(Ticket $ticket, User $admin, string $closureType, ?string $reason = null, array $attributes = []): void
    {
        $this->authorize($admin, $ticket, self::CLOSE);

        DB::transaction(function () use ($ticket, $admin, $closureType, $reason, $attributes): void {
            $ticket->update($attributes + [
                'status' => Ticket::STATUS_CLOSED,
                'closure_type' => $closureType,
                'closure_reason' => $reason ?: $ticket->closure_reason,
                'closed_at' => now(),
            ]);

            $label = Ticket::CLOSURE_LABELS[$closureType] ?? $closureType;
            $isInvalid = $closureType === Ticket::CLOSURE_INVALID;

            AuditLog::log(
                $ticket->id,
                $isInvalid ? 'ticket_closed_invalid' : 'ticket_closed',
                $admin->id,
                $isInvalid
                    ? "Ticket marked invalid. Reason: {$reason}"
                    : trim("Ticket closed by admin. {$label}." . ($reason ? " {$reason}" : ''))
            );

            $this->notify($ticket, [$this->student($ticket)], $isInvalid ? EmailNotification::TYPE_INVALID_CLOSURE : EmailNotification::TYPE_COMPLAINT_CLOSED);
            $this->notify($ticket, [$this->handler($ticket)], EmailNotification::TYPE_STATUS_UPDATE, except: $admin);
        });
    }

    /**
     * The student withdraws the ticket before it is resolved.
     */
    public function withdraw(Ticket $ticket, User $student, ?string $reason = null): void
    {
        $this->authorize($student, $ticket, self::WITHDRAW);

        DB::transaction(function () use ($ticket, $student, $reason): void {
            $ticket->update([
                'status' => Ticket::STATUS_CLOSED,
                'closure_type' => Ticket::CLOSURE_WITHDRAWN,
                'closure_reason' => $reason,
                'closed_at' => now(),
            ]);

            AuditLog::log($ticket->id, 'ticket_withdrawn', $student->id, trim('The student withdrew the ticket. ' . ($reason ?? '')));
            $this->notify($ticket, [$this->handler($ticket), ...$this->admins()], EmailNotification::TYPE_WITHDRAWN);
        });
    }

    // ------------------------------------------------------------------ helpers

    protected function postMessage(Ticket $ticket, User $sender, string $content): void
    {
        $thread = $ticket->thread()->firstOrCreate([], ['is_active' => $ticket->acceptsMessages()]);

        $thread->messages()->create([
            'sender_id' => $sender->id,
            'content' => $content,
        ]);
    }

    protected function student(Ticket $ticket): ?User
    {
        return $ticket->complaint?->student?->user;
    }

    protected function handler(Ticket $ticket): ?User
    {
        return $ticket->currentHandler ?? $ticket->assignee;
    }

    /**
     * @return list<User>
     */
    protected function admins(): array
    {
        return User::query()->where('role', User::ROLE_SDS_ADMIN)->where('is_active', true)->get()->all();
    }

    /**
     * Queue one email per person, skipping whoever just took the action.
     *
     * @param  iterable<User|null>  $users
     */
    protected function notify(Ticket $ticket, iterable $users, string $type, ?User $except = null): void
    {
        Collection::make($users)
            ->filter(fn ($user) => $user instanceof User && filled($user->email))
            ->reject(fn (User $user) => $except && (int) $user->id === (int) $except->id)
            ->unique('id')
            ->each(fn (User $user) => EmailNotification::create([
                'ticket_id' => $ticket->id,
                'recipient_email' => $user->email,
                'type' => $type,
                'status' => EmailNotification::STATUS_PENDING,
            ]));
    }
}
