<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ticket extends Model
{
    use HasFactory;

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_NEEDS_CLARIFICATION = 'needs_clarification';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_ESCALATED = 'escalated';

    public const STATUS_REFERRED = 'referred';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    /**
     * Every status in the order a ticket normally moves through them, with its display label.
     */
    public const STATUS_LABELS = [
        self::STATUS_SUBMITTED => 'Submitted',
        self::STATUS_NEEDS_CLARIFICATION => 'Needs Clarification',
        self::STATUS_ASSIGNED => 'Assigned',
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_ESCALATED => 'Escalated',
        self::STATUS_REFERRED => 'Referred',
        self::STATUS_RESOLVED => 'Resolved',
        self::STATUS_CLOSED => 'Closed',
    ];

    /**
     * The only status changes a ticket may make. Who may make each one is decided by
     * App\Services\TicketWorkflow; this map is enforced whenever a ticket is saved.
     */
    public const TRANSITIONS = [
        self::STATUS_SUBMITTED => [self::STATUS_NEEDS_CLARIFICATION, self::STATUS_ASSIGNED, self::STATUS_IN_PROGRESS, self::STATUS_CLOSED],
        self::STATUS_NEEDS_CLARIFICATION => [self::STATUS_SUBMITTED, self::STATUS_ASSIGNED, self::STATUS_IN_PROGRESS, self::STATUS_CLOSED],
        self::STATUS_ASSIGNED => [self::STATUS_IN_PROGRESS, self::STATUS_ESCALATED, self::STATUS_REFERRED, self::STATUS_CLOSED],
        self::STATUS_IN_PROGRESS => [self::STATUS_ASSIGNED, self::STATUS_ESCALATED, self::STATUS_REFERRED, self::STATUS_RESOLVED, self::STATUS_CLOSED],
        self::STATUS_ESCALATED => [self::STATUS_ASSIGNED, self::STATUS_REFERRED, self::STATUS_RESOLVED, self::STATUS_CLOSED],
        self::STATUS_REFERRED => [self::STATUS_RESOLVED, self::STATUS_CLOSED],
        self::STATUS_RESOLVED => [self::STATUS_IN_PROGRESS, self::STATUS_CLOSED],
        self::STATUS_CLOSED => [],
    ];

    /** Statuses in which someone is working on the ticket. */
    public const ACTIVE_STATUSES = [self::STATUS_ASSIGNED, self::STATUS_IN_PROGRESS, self::STATUS_ESCALATED, self::STATUS_REFERRED];

    /** Statuses in which the conversation accepts new messages. */
    public const CONVERSATION_STATUSES = [self::STATUS_NEEDS_CLARIFICATION, self::STATUS_ASSIGNED, self::STATUS_IN_PROGRESS, self::STATUS_ESCALATED, self::STATUS_REFERRED];

    public const CLOSURE_RESOLVED = 'resolved';

    public const CLOSURE_RESOLVED_ACCEPTED = 'resolved_accepted';

    public const CLOSURE_INFORMATIONAL = 'informational';

    public const CLOSURE_INVALID = 'invalid';

    public const CLOSURE_DUPLICATE = 'duplicate';

    public const CLOSURE_OUT_OF_SCOPE = 'out_of_scope';

    public const CLOSURE_WITHDRAWN = 'withdrawn';

    public const CLOSURE_NO_RESPONSE = 'no_response';

    /** Why a ticket was closed. */
    public const CLOSURE_LABELS = [
        self::CLOSURE_RESOLVED_ACCEPTED => 'Resolution accepted by the student',
        self::CLOSURE_RESOLVED => 'Resolved and confirmed by the SDS Office',
        self::CLOSURE_INFORMATIONAL => 'Recorded for information',
        self::CLOSURE_INVALID => 'Not a valid complaint',
        self::CLOSURE_DUPLICATE => 'Duplicate of another ticket',
        self::CLOSURE_OUT_OF_SCOPE => 'Outside the scope of the university',
        self::CLOSURE_WITHDRAWN => 'Withdrawn by the student',
        self::CLOSURE_NO_RESPONSE => 'No response from the student',
    ];

    /** Closure types the admin may pick when closing a ticket that was not resolved. */
    public const ADMIN_CLOSURE_TYPES = [
        self::CLOSURE_INVALID,
        self::CLOSURE_DUPLICATE,
        self::CLOSURE_OUT_OF_SCOPE,
        self::CLOSURE_NO_RESPONSE,
        self::CLOSURE_WITHDRAWN,
    ];

    public const RESOLUTION_ACTION_TAKEN = 'action_taken';

    public const RESOLUTION_EXPLANATION = 'explanation_provided';

    public const RESOLUTION_SETTLED = 'settled';

    public const RESOLUTION_COMMITTEE_DECISION = 'committee_decision';

    public const RESOLUTION_NO_BASIS = 'no_basis';

    /** How a ticket was resolved. */
    public const RESOLUTION_LABELS = [
        self::RESOLUTION_ACTION_TAKEN => 'Corrective action taken',
        self::RESOLUTION_EXPLANATION => 'Explanation or information provided',
        self::RESOLUTION_SETTLED => 'Settled between the parties',
        self::RESOLUTION_COMMITTEE_DECISION => 'Decided by a committee or board',
        self::RESOLUTION_NO_BASIS => 'No basis found after review',
    ];

    /** Days a student has to ask for further action after a ticket is resolved. */
    public const FURTHER_ACTION_DAYS = 15;

    public const CLASSIFICATION_NEEDS_RESOLUTION = 'needs_resolution';

    public const CLASSIFICATION_INFORMATIONAL = 'informational';

    public const CLASSIFICATION_INVALID = 'invalid';

    public const JURISDICTION_SDS = 'sds';

    public const JURISDICTION_RECIPIENT = 'recipient';

    protected $fillable = [
        'complaint_id',
        'assigned_to',
        'current_handler_id',
        'status',
        'classification',
        'jurisdiction',
        'closure_reason',
        'closure_type',
        'resolution_type',
        'referred_to',
        'referred_at',
        'clarification_requested_at',
        'deadline',
        'acknowledged_at',
        'escalated_at',
        'resolved_at',
        'closed_at',
        'forwarded_at',
        'forwarded_to',
        'satisfaction_rating',
        'satisfaction_comment',
        'rated_at',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'datetime',
            'acknowledged_at' => 'datetime',
            'escalated_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'forwarded_at' => 'datetime',
            'referred_at' => 'datetime',
            'clarification_requested_at' => 'datetime',
            'rated_at' => 'datetime',
            'satisfaction_rating' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Ticket $ticket): void {
            if (! $ticket->isDirty('status')) {
                return;
            }

            $from = (string) $ticket->getOriginal('status');
            $to = (string) $ticket->status;

            if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
                throw new \DomainException(sprintf(
                    'A ticket cannot move from %s to %s.',
                    self::STATUS_LABELS[$from] ?? $from,
                    self::STATUS_LABELS[$to] ?? $to
                ));
            }
        });

        static::created(fn (Ticket $ticket) => $ticket->syncWithStatus());

        static::updated(function (Ticket $ticket): void {
            if ($ticket->wasChanged('status')) {
                $ticket->syncWithStatus();
            }
        });
    }

    /**
     * The complaint mirrors its ticket, and the conversation opens and locks with the status.
     */
    protected function syncWithStatus(): void
    {
        $this->complaint()->update(['status' => $this->status]);

        if ($this->acceptsMessages()) {
            $this->thread()->firstOrCreate([], ['is_active' => true]);
        }

        $this->thread()->update(['is_active' => $this->acceptsMessages()]);
        $this->unsetRelation('thread');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucwords(str_replace('_', ' ', (string) $this->status));
    }

    public function getClosureLabelAttribute(): ?string
    {
        return $this->closure_type ? (self::CLOSURE_LABELS[$this->closure_type] ?? ucfirst(str_replace('_', ' ', $this->closure_type))) : null;
    }

    public function getResolutionLabelAttribute(): ?string
    {
        return $this->resolution_type ? (self::RESOLUTION_LABELS[$this->resolution_type] ?? ucfirst(str_replace('_', ' ', $this->resolution_type))) : null;
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * Submitted (or waiting on the student's clarification) and not yet reviewed by the SDS Office.
     */
    public function isAwaitingReview(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_NEEDS_CLARIFICATION], true)
            && $this->classification === null;
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function acceptsMessages(): bool
    {
        return in_array($this->status, self::CONVERSATION_STATUSES, true);
    }

    /**
     * Whether the ticket has a conversation to show: one being worked on, or one where the
     * SDS Office asked the student to clarify before review.
     */
    public function hasConversation(): bool
    {
        return $this->classification === self::CLASSIFICATION_NEEDS_RESOLUTION
            || $this->status === self::STATUS_NEEDS_CLARIFICATION
            || $this->clarification_requested_at !== null;
    }

    /**
     * Whole days from submission until now, or until the ticket was closed.
     */
    public function daysOpen(): int
    {
        $from = $this->complaint?->created_at ?? $this->created_at;
        $until = $this->closed_at ?? now();

        return $from ? max(0, (int) floor($from->diffInDays($until))) : 0;
    }

    /**
     * When someone last did something on the ticket. Emails the system sent do not count.
     */
    public function lastActionAt(): ?\Illuminate\Support\Carbon
    {
        $latest = $this->relationLoaded('auditLogs')
            ? $this->auditLogs->where('action', '!=', 'email_notification_sent')->max('created_at')
            : $this->auditLogs()->where('action', '!=', 'email_notification_sent')->max('created_at');

        return $latest ? \Illuminate\Support\Carbon::parse($latest) : ($this->updated_at ?? $this->created_at);
    }

    /**
     * Whole days since the last action, for tickets that are still open.
     */
    public function daysSinceLastAction(): ?int
    {
        if ($this->status === self::STATUS_CLOSED) {
            return null;
        }

        $last = $this->lastActionAt();

        return $last ? max(0, (int) floor($last->diffInDays(now()))) : null;
    }

    /**
     * A closed ticket that was actually resolved can be rated once by the student.
     */
    public function canBeRated(): bool
    {
        return $this->status === self::STATUS_CLOSED
            && in_array($this->closure_type, [self::CLOSURE_RESOLVED, self::CLOSURE_RESOLVED_ACCEPTED], true)
            && $this->satisfaction_rating === null;
    }

    /**
     * Last day the student may ask for further action on a resolved ticket.
     */
    public function furtherActionDeadline(): ?\Illuminate\Support\Carbon
    {
        return $this->resolved_at?->copy()->addDays(self::FURTHER_ACTION_DAYS);
    }

    /**
     * Complaint that generated this ticket.
     */
    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    /**
     * User currently assigned to this ticket.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Current handler responsible for this ticket.
     */
    public function currentHandler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_handler_id');
    }

    /**
     * Communication thread.
     */
    public function thread(): HasOne
    {
        return $this->hasOne(TicketThread::class);
    }

    /**
     * Audit trail.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class)->orderBy('created_at');
    }

    public function remainingDays(): ?int
    {
        if (! $this->deadline) {
            return null;
        }

        if ($this->status === self::STATUS_CLOSED) {
            return null;
        }

        $today = now()->copy()->setTimezone('Asia/Manila')->startOfDay();
        $deadline = $this->deadline->copy()->setTimezone('Asia/Manila')->startOfDay();

        if ($today->greaterThanOrEqualTo($deadline)) {
            return 0;
        }

        return max(0, (int) $today->diffInDays($deadline, false));
    }

    /**
     * Email notifications.
     */
    public function emailNotifications(): HasMany
    {
        return $this->hasMany(EmailNotification::class);
    }

    /**
     * Recipient this informational ticket was forwarded to.
     */
    public function forwardedRecipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class, 'forwarded_to');
    }
}
