<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Complaint extends Model
{
    use HasFactory;

    // A complaint mirrors the status of its ticket; see Ticket::STATUS_LABELS.
    public const STATUS_SUBMITTED = Ticket::STATUS_SUBMITTED;

    protected $fillable = [
        'reference_number',
        'student_id',
        'category_id',
        'subject_title',
        'personnel_involved',
        'suggested_recipient_id',
        'description',
        'file_attachment',
        'is_anonymous',
        'declared_at',
        'status',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'declared_at' => 'datetime',
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

        $attachments = array_values(array_map(
            fn ($attachment) => is_array($attachment)
                ? $attachment
                : ['path' => $attachment, 'name' => basename($attachment)],
            $attachments,
        ));

        // Attachments are private: each is downloaded through a permission-checked link.
        return array_map(
            fn (array $attachment, int $index) => $attachment + [
                'url' => $this->exists ? route('attachments.complaint', [$this->id, $index]) : null,
            ],
            $attachments,
            array_keys($attachments),
        );
    }

    /**
     * Whether the complaint falls under a sensitive category, such as harassment.
     */
    public function isSensitive(): bool
    {
        return (bool) $this->category?->is_sensitive;
    }

    /**
     * Subject safe to show on dashboards, reports and exports.
     */
    public function getPublicSubjectAttribute(): string
    {
        return $this->isSensitive() ? 'Confidential concern' : ($this->subject_title ?: 'Untitled');
    }

    public function isFiledBy(?User $user): bool
    {
        return $user !== null && $this->student !== null && (int) $this->student->user_id === (int) $user->id;
    }

    /**
     * A hidden-identity complaint shows who filed it to nobody but the student, the admin included.
     */
    public function hidesIdentityFrom(?User $viewer): bool
    {
        return (bool) $this->is_anonymous && ! $this->isFiledBy($viewer);
    }

    /**
     * Name of the student as the given viewer may see it.
     */
    public function filerNameFor(?User $viewer): string
    {
        if ($this->hidesIdentityFrom($viewer)) {
            return 'Anonymous';
        }

        return $this->student?->user?->table_name ?? 'Unknown';
    }

    /**
     * Whether the complaint appears to name this person, which would make them the wrong
     * handler for it. Checks the "person involved" field and the description for the full name.
     */
    public function names(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $first = mb_strtolower(trim((string) $user->first_name));
        $last = mb_strtolower(trim((string) $user->last_name));
        $full = mb_strtolower(trim((string) ($user->display_name ?: $user->name)));
        $involved = mb_strtolower((string) $this->personnel_involved);
        $text = $involved . ' ' . mb_strtolower((string) $this->description) . ' ' . mb_strtolower((string) $this->subject_title);

        if ($full !== '' && str_contains($text, $full)) {
            return true;
        }

        if ($first !== '' && $last !== '' && str_contains($text, $first) && str_contains($text, $last)) {
            return true;
        }

        // In the "person involved" field a surname alone is enough, e.g. "Prof. Santos".
        return mb_strlen($last) >= 3 && preg_match('/\b' . preg_quote($last, '/') . '\b/u', $involved) === 1;
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
     * Recipient the student suggested to handle the complaint.
     */
    public function suggestedRecipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class, 'suggested_recipient_id');
    }

    /**
     * Ticket generated from this complaint.
     */
    public function ticket(): HasOne
    {
        return $this->hasOne(Ticket::class);
    }
}
