<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketThread extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Ticket this thread belongs to.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Messages inside this thread.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ThreadMessage::class, 'thread_id');
    }
}
