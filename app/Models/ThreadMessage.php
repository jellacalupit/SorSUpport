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
     * The sender as the signed-in user may see them: the student behind a hidden-identity
     * ticket appears as Anonymous to everyone else.
     */
    public function getDisplaySenderAttribute(): ?User
    {
        $sender = $this->sender;
        $complaint = $this->thread?->ticket?->complaint;

        if ($sender && $complaint && $complaint->isFiledBy($sender) && $complaint->hidesIdentityFrom(\Illuminate\Support\Facades\Auth::user())) {
            return User::anonymous();
        }

        return $sender;
    }

    /**
     * User who sent the message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
