<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscalationHierarchy extends Model
{
    use HasFactory;

    protected $fillable = [
        'complaint_category_id',
        'current_recipient_id',
        'next_recipient_id',
    ];

    /**
     * Complaint category using this hierarchy.
     */
    public function complaintCategory(): BelongsTo
    {
        return $this->belongsTo(ComplaintCategory::class);
    }

    /**
     * Current recipient.
     */
    public function currentRecipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class, 'current_recipient_id');
    }

    /**
     * Next recipient after escalation.
     */
    public function nextRecipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class, 'next_recipient_id');
    }
}
