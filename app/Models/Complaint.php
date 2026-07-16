<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Complaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'category_id',
        'subject_title',
        'personnel_involved',
        'description',
        'file_attachment',
        'is_anonymous',
    ];

    /**
     * Student who submitted the complaint.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Complaint category.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ComplaintCategory::class);
    }

    /**
     * Ticket generated from this complaint.
     */
    public function ticket(): HasOne
    {
        return $this->hasOne(Ticket::class);
    }
}
