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

    public const STATUS_PENDING = 'pending';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CLOSED = 'closed';

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
        'deadline',
        'acknowledged_at',
        'resolved_at',
        'closed_at',
        'forwarded_at',
        'forwarded_to',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'forwarded_at' => 'datetime',
        ];
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
