<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThreadMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_thread_id',
        'sender_id',
        'message',
        'attachment',
    ];

    /**
     * Thread this message belongs to.
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(TicketThread::class, 'ticket_thread_id');
    }

    /**
     * User who sent the message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
