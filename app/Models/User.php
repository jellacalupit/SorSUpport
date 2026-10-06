<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, MustVerifyEmailTrait;

    public const ROLE_STUDENT = 'student';
    public const ROLE_RECIPIENT = 'recipient';
    public const ROLE_SDS_ADMIN = 'sds_admin';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'username',
        'email',
        'avatar_path',
        'password',
        'must_change_password',
        'role',
        'is_active',
        'student_notification_read_ids',
        'recipient_notification_read_ids',
        'student_notification_deleted_ids',
        'recipient_notification_deleted_ids',
        'student_ticket_read_ids',
        'admin_ticket_read_ids',
        'recipient_ticket_read_ids',
        'student_ticket_last_read_at',
        'admin_ticket_last_read_at',
        'recipient_ticket_last_read_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'student_notification_read_ids' => 'array',
            'recipient_notification_read_ids' => 'array',
            'student_notification_deleted_ids' => 'array',
            'recipient_notification_deleted_ids' => 'array',
            'student_ticket_read_ids' => 'array',
            'admin_ticket_read_ids' => 'array',
            'recipient_ticket_read_ids' => 'array',
            'student_ticket_last_read_at' => 'array',
            'admin_ticket_last_read_at' => 'array',
            'recipient_ticket_last_read_at' => 'array',
        ];
    }

    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName(): string
    {
        return $this->getKeyName();
    }

    /**
     * Return the numeric primary key for authentication-based foreign keys.
     */
    public function getAuthIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Send the password reset notification using the project's email design.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Send the email verification notification using the project's email design.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }

    /**
     * A stand-in shown in place of a student whose identity is hidden on a ticket.
     */
    public static function anonymous(): self
    {
        return (new self)->forceFill([
            'name' => 'Anonymous',
            'first_name' => 'Anonymous',
            'role' => self::ROLE_STUDENT,
        ]);
    }

    /**
     * Only report a profile photo when its file still exists, so views fall back to initials
     * instead of a broken image when the stored file is gone.
     */
    public function getAvatarPathAttribute(?string $value): ?string
    {
        return filled($value) && Storage::disk('public')->exists($value) ? $value : null;
    }

    /**
     * Display the full name from the separated name fields.
     */
    public function getDisplayNameAttribute(): string
    {
        $nameParts = array_values(array_filter([
            trim((string) $this->first_name),
            trim((string) $this->middle_name),
            trim((string) $this->last_name),
        ], static fn ($part) => $part !== ''));

        return $nameParts !== [] ? implode(' ', $nameParts) : trim((string) $this->name);
    }

    /**
     * Display the name for compact table columns.
     */
    public function getTableNameAttribute(): string
    {
        $firstName = trim((string) $this->first_name);
        $middleName = trim((string) $this->middle_name);
        $lastName = trim((string) $this->last_name);

        if ($firstName !== '' || $middleName !== '' || $lastName !== '') {
            $name = $firstName;

            if ($middleName !== '') {
                $name .= ($name !== '' ? ' ' : '') . strtoupper(substr($middleName, 0, 1)) . '.';
            }

            if ($lastName !== '') {
                $name .= ($name !== '' ? ' ' : '') . $lastName;
            }

            return $name;
        }

        $nameParts = preg_split('/\s+/', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($nameParts) < 3) {
            return implode(' ', $nameParts);
        }

        $givenName = count($nameParts) >= 4 ? implode(' ', array_slice($nameParts, 0, -2)) : $nameParts[0];
        $middleInitial = strtoupper(substr($nameParts[count($nameParts) - 2], 0, 1)) . '.';

        return $givenName . ' ' . $middleInitial . ' ' . $nameParts[count($nameParts) - 1];
    }

    /**
     * Return initials based on the separated name fields.
     */
    public function getNameInitialsAttribute(): string
    {
        $firstName = trim((string) $this->first_name);
        $lastName = trim((string) $this->last_name);

        if ($firstName !== '' || $lastName !== '') {
            return strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
        }

        $initials = collect(preg_split('/\s+/', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($part) => substr($part, 0, 1))
            ->take(2)
            ->join('');

        return $initials !== '' ? $initials : 'A';
    }

    /**
     * Given name(s) without middle name or surname.
     */
    public function getGivenNameAttribute(): string
    {
        if (trim((string) $this->first_name) !== '') {
            return trim((string) $this->first_name);
        }

        $nameParts = preg_split('/\s+/', trim((string) $this->name)) ?: [];

        if ($nameParts === []) {
            return '';
        }

        if (count($nameParts) === 1) {
            return $nameParts[0];
        }

        array_pop($nameParts);

        if (count($nameParts) > 1) {
            array_pop($nameParts);
        }

        return trim(implode(' ', $nameParts));
    }

    /**
     * Student profile.
     */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    /**
     * Recipient profile.
     */
    public function recipient(): HasOne
    {
        return $this->hasOne(Recipient::class);
    }

    public function hasCompleteProfile(): bool
    {
        if ($this->role === self::ROLE_STUDENT) {
            return $this->student !== null
                && collect(['student_id', 'college', 'program', 'year_level'])
                    ->every(fn (string $field) => trim((string) $this->student->{$field}) !== '');
        }

        if ($this->role === self::ROLE_RECIPIENT) {
            return $this->recipient !== null
                && collect(['staff_id', 'unit', 'designation'])
                    ->every(fn (string $field) => trim((string) $this->recipient->{$field}) !== '');
        }

        return true;
    }

    /**
     * Audit logs performed by this user.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'performed_by');
    }

    /**
     * Messages sent by this user.
     */
    public function threadMessages(): HasMany
    {
        return $this->hasMany(ThreadMessage::class, 'sender_id');
    }

    /**
     * Analytics reports generated by this user.
     */
    public function analyticsReports(): HasMany
    {
        return $this->hasMany(AnalyticsReport::class, 'generated_by');
    }

    /**
     * Determine if the user is a student.
     */
    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    /**
     * Determine if the user is a recipient.
     */
    public function isRecipient(): bool
    {
        return $this->role === self::ROLE_RECIPIENT;
    }

    /**
     * Determine if the user is an SDS Administrator.
     */
    public function isSdsAdmin(): bool
    {
        return $this->role === self::ROLE_SDS_ADMIN;
    }
}