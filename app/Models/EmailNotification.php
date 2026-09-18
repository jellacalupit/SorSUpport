<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailNotification extends Model
{
    use HasFactory;

    // Email notification types
    public const TYPE_VERIFICATION = 'verification';
    public const TYPE_SUBMISSION_ACK = 'submission_ack';
    public const TYPE_INVALID_CLOSURE = 'invalid_closure';
    public const TYPE_ASSIGNMENT = 'assignment';
    public const TYPE_RECIPIENT_ASSIGNMENT = 'recipient_assignment';
    public const TYPE_STATUS_UPDATE = 'status_update';
    public const TYPE_STUDENT_STATUS_UPDATE = 'student_status_update';
    public const TYPE_ACKNOWLEDGED = 'acknowledged';
    public const TYPE_RESOLVED = 'resolved';
    public const TYPE_RECIPIENT_RESOLVED = 'recipient_resolved';
    public const TYPE_CLOSED = 'closed';
    public const TYPE_COMPLAINT_CLOSED = 'complaint_closed';
    public const TYPE_ESCALATED = 'escalated';
    public const TYPE_DAILY_REMINDER = 'daily_reminder';
    public const TYPE_INFORMATIONAL_FORWARD = 'informational_forward';
    public const TYPE_MESSAGE_POSTED = 'message_posted';

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    protected $fillable = [
        'ticket_id',
        'recipient_email',
        'type',
        'status',
        'sent_at',
    ];

    /**
     * Ticket related to this notification.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
