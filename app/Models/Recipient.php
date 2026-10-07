<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipient extends Model
{
    protected $fillable = [
        'user_id',
        'staff_id',
        'unit',
        'designation',
    ];

    /**
     * Only recipients whose backing user is active may be surfaced in category workflows.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereHas('user', fn ($user) => $user->where('is_active', true));
    }

    /**
     * Recipient belongs to a User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Tickets assigned to this recipient.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    /**
     * Complaint categories assigned to this recipient.
     */
    public function complaintCategories(): HasMany
    {
        return $this->hasMany(ComplaintCategory::class);
    }
}