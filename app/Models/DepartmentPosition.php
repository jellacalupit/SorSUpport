<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentPosition extends Model
{
    protected $fillable = [
        'department_id',
        'name',
        'description',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
