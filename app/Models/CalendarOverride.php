<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarOverride extends Model
{
    public const TYPE_NON_WORKING = 'non_working';

    public const TYPE_WORKING = 'working';

    protected $fillable = [
        'date',
        'type',
        'description',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
