<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $fillable = [
        'user_id',
        'student_id',
        'college',
        'program',
        'year_level',
        'block',
    ];


    /**
     * Student belongs to a User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Complaints submitted by this student.
     */
    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }
}