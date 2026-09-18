<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Complaint extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'reference_number',
        'student_id',
        'category_id',
        'subject_title',
        'personnel_involved',
        'description',
        'file_attachment',
        'is_anonymous',
        'status',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
    ];

    public function getAttachmentPathsAttribute(): array
    {
        if (! $this->file_attachment) {
            return [];
        }

        $paths = json_decode($this->file_attachment, true);

        if (! is_array($paths)) {
            return [$this->file_attachment];
        }

        return array_map(
            fn ($attachment) => is_array($attachment) ? $attachment['path'] : $attachment,
            $paths,
        );
    }

    public function getAttachmentFilesAttribute(): array
    {
        if (! $this->file_attachment) {
            return [];
        }

        $attachments = json_decode($this->file_attachment, true);

        if (! is_array($attachments)) {
            $attachments = [$this->file_attachment];
        }

        return array_map(
            fn ($attachment) => is_array($attachment)
                ? $attachment
                : ['path' => $attachment, 'name' => basename($attachment)],
            $attachments,
        );
    }

    /**
    * Generate the next complaint reference number (e.g. SU-2026-00001).
     */
    public static function generateReferenceNumber(): string
    {
        $year = now()->year;
        $prefix = "SU-{$year}-";

        $latest = static::query()
            ->where('reference_number', 'like', "{$prefix}%")
            ->orderByDesc('reference_number')
            ->lockForUpdate()
            ->value('reference_number');

        $sequence = $latest
            ? ((int) substr($latest, -5)) + 1
            : 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

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
        return $this->belongsTo(ComplaintCategory::class, 'category_id');
    }

    /**
     * Ticket generated from this complaint.
     */
    public function ticket(): HasOne
    {
        return $this->hasOne(Ticket::class);
    }
}
