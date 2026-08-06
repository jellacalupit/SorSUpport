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

    /**
     * Ticket associated with this log.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * User who performed the action.
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
