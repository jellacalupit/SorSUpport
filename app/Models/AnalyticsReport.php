<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'generated_by',
        'date_range',
        'data',
        'generated_at',
    ];

    protected $casts = [
        'data' => 'array',
        'generated_at' => 'datetime',
    ];

    /**
     * SDS Administrator who generated the report.
     */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
