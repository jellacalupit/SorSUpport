<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'staff_id',
        'department',
        'designation',
    ];

    /**
     * User account of the recipient.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Tickets currently assigned to this recipient.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'recipient_id');
    }

    /**
     * Escalation hierarchy entries for this recipient.
     */
    public function escalationHierarchies(): HasMany
    {
        return $this->hasMany(EscalationHierarchy::class);
    }
}
