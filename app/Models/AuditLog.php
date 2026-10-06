<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
     * Ticket associated with this log.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
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
