<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A degree program offered by a college. year_level and block hold how many of each the program has.
 */
class Program extends Model
{
    protected $fillable = [
        'unit_id',
        'name',
        'year_level',
        'block',
        'description',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
