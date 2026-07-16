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

    protected $fillable = [
        'complaint_id',
        'recipient_id',
        'status',
        'classification',
        'deadline',
        'acknowledged_at',
        'resolved_at',
        'closed_at',
    ];

    /**
     * Complaint that generated this ticket.
     */
    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    /**
     * Recipient currently assigned to this ticket.
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
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
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Email notifications.
     */
    public function emailNotifications(): HasMany
    {
        return $this->hasMany(EmailNotification::class);
    }
}
