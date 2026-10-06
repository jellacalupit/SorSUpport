<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ComplaintCategory extends Model
{
    public const JURISDICTION_SDS = 'sds';

    public const JURISDICTION_RECIPIENT = 'recipient';

    protected $fillable = [
        'name',
        'description',
        'recipient_id',
        'resolution_deadline_days',
        'default_jurisdiction',
        'is_active',
        'is_sensitive',
        'allows_hidden_identity',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_sensitive' => 'boolean',
            'allows_hidden_identity' => 'boolean',
        ];
    }

    /**
     * Office/recipient assigned to this category.
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    /**
     * Complaints under this category.
     */
    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'category_id');
    }

    /**
     * Ordered escalation hierarchy for this category.
     */
    public function escalationHierarchies(): HasMany
    {
        return $this->hasMany(EscalationHierarchy::class)->orderBy('path_number')->orderBy('level');
    }

    /**
     * Recipients suggested for this category.
     */
    public function suggestedRecipients(): BelongsToMany
    {
        return $this->belongsToMany(
            Recipient::class,
            'complaint_category_suggested_recipients',
            'complaint_category_id',
            'recipient_id'
        )->with('user')->orderBy('unit');
    }
}