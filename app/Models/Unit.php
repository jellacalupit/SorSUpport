<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A college or an office of the campus. Students belong to a college; recipients belong to either.
 */
class Unit extends Model
{
    public const TYPE_COLLEGE = 'college';

    public const TYPE_OFFICE = 'office';

    protected $fillable = [
        'name',
        'type',
        'description',
    ];

    public function scopeColleges($query)
    {
        return $query->where('type', self::TYPE_COLLEGE);
    }

    public function scopeOffices($query)
    {
        return $query->where('type', self::TYPE_OFFICE);
    }

    public function isCollege(): bool
    {
        return $this->type === self::TYPE_COLLEGE;
    }

    public function designations(): HasMany
    {
        return $this->hasMany(UnitDesignation::class)->orderBy('name');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class)->orderBy('name');
    }
}
