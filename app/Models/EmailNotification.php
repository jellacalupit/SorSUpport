<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailNotification extends Model
{
    use HasFactory;

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
