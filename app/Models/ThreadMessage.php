<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThreadMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'thread_id',
        'sender_id',
        'content',
        'file_attachment',
        'file_attachment_name',
    ];

    /**
     * Thread this message belongs to.
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(TicketThread::class, 'thread_id');
    }

    /**
     * User who sent the message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
