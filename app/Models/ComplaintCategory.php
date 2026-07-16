<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComplaintCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'resolution_deadline_days',
    ];

    /**
     * Complaints under this category.
     */
    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    /**
     * Escalation hierarchy for this category.
     */
    public function escalationHierarchies(): HasMany
    {
        return $this->hasMany(EscalationHierarchy::class);
    }
}
