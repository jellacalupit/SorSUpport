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
        'level',
        'recipient_id',
    ];

    /**
     * Complaint category using this hierarchy.
     */
    public function complaintCategory(): BelongsTo
    {
        return $this->belongsTo(ComplaintCategory::class);
    }

    /**
     * Recipient configured for this escalation level.
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class, 'recipient_id');
    }
}
