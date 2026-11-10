<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'performed_by',
        'action',
        'details',
    ];

    public static function log(int $ticketId, string $action, ?int $userId = null, ?string $details = null): self
    {
        return self::create([
            'ticket_id' => $ticketId,
            'performed_by' => $userId ?? Auth::id(),
            'action' => $action,
            'details' => $details,
        ]);
    }

    public static function activity(string $action, ?int $userId = null, ?string $details = null): self
    {
        return self::create([
            'ticket_id' => null,
            'performed_by' => $userId ?? Auth::id(),
            'action' => $action,
            'details' => $details,
        ]);
    }

    /**
     * Older range submissions wrote one calendar entry per changed date. Keep those immutable
     * database rows, but present entries from the same submission as one range in the audit UI.
     *
     * @param  Collection<int, self>  $logs
     * @return Collection<int, self>
     */
    public static function groupLegacyCalendarRanges(Collection $logs): Collection
    {
        $parsed = [];
        $groups = [];

        foreach ($logs as $log) {
            if (! in_array($log->action, ['calendar_override_created', 'calendar_override_updated'], true)
                || ! preg_match('/\ASet (working|non_working) override for (\d{4}-\d{2}-\d{2})\. Description: (.*)\z/s', (string) $log->details, $matches)) {
                continue;
            }

            $key = implode('|', [
                $log->performed_by ?? 'system',
                $log->created_at?->format('Y-m-d H:i:s') ?? '',
                $matches[1],
                $matches[3],
            ]);
            $parsed[$log->id] = ['key' => $key, 'type' => $matches[1], 'date' => $matches[2], 'description' => $matches[3]];
            $groups[$key][] = $log->id;
        }

        $renderedGroups = [];

        return $logs->filter(function (AuditLog $log) use ($parsed, $groups, &$renderedGroups): bool {
            $entry = $parsed[$log->id] ?? null;
            if (! $entry || count($groups[$entry['key']] ?? []) < 2) {
                return true;
            }

            if (isset($renderedGroups[$entry['key']])) {
                return false;
            }

            $renderedGroups[$entry['key']] = true;
            $dates = collect($groups[$entry['key']])
                ->map(fn (int $id) => $parsed[$id]['date'])
                ->unique()
                ->sort()
                ->values();
            $log->action = 'calendar_override_range_saved';
            $log->details = sprintf(
                'Set %s overrides for %s through %s (%d %s changed). Description: %s',
                $entry['type'],
                $dates->first(),
                $dates->last(),
                $dates->count(),
                $dates->count() === 1 ? 'date' : 'dates',
                $entry['description'],
            );

            return true;
        })->values();
    }

    /**
     * Ticket associated with this log.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Entries shown in the audit trail. Student sign-ins are left out: listed next to a
     * hidden-identity submission made a minute later, they would give the student away.
     */
    public function scopeForAuditTrail($query)
    {
        return $query->where(fn ($entries) => $entries
            ->where('action', '!=', 'user_logged_in')
            ->orWhereHas('performer', fn ($performer) => $performer->where('role', '!=', User::ROLE_STUDENT)));
    }

    /**
     * The performer as the signed-in user may see them: the student behind a hidden-identity
     * ticket appears as Anonymous to everyone else.
     */
    public function getDisplayPerformerAttribute(): ?User
    {
        $performer = $this->performer;
        $complaint = $this->ticket?->complaint;

        if ($performer && $complaint && $complaint->isFiledBy($performer) && $complaint->hidesIdentityFrom(Auth::user())) {
            return User::anonymous();
        }

        return $performer;
    }

    /**
     * User who performed the action.
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
