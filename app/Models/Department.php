<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'name',
        'type',
        'description',
    ];

    public function scopeForStudents($query)
    {
        return $query->where('type', 'student');
    }

    public function scopeForRecipients($query)
    {
        return $query->where('type', 'recipient');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(DepartmentPosition::class)->orderBy('name');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(DepartmentCourse::class)
            ->orderBy('course')
            ->orderBy('year_level')
            ->orderBy('block');
    }
}
