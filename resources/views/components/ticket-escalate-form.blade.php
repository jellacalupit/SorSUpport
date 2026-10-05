@props(['ticket'])

@php
    $canEscalate = $ticket->classification === \App\Models\Ticket::CLASSIFICATION_NEEDS_RESOLUTION
        && in_array($ticket->status, [\App\Models\Ticket::STATUS_ASSIGNED, \App\Models\Ticket::STATUS_IN_PROGRESS, \App\Models\Ticket::STATUS_ESCALATED], true);
    $escalationService = app(\App\Services\TicketEscalationService::class);
    $escalationTargets = $canEscalate ? $escalationService->escalationTargets($ticket) : collect();
    $suggestedTargetId = $canEscalate ? $escalationService->getNextRecipient($ticket)?->id : null;
@endphp

@if ($canEscalate)
    <form method="POST" action="{{ route('admin.tickets.escalate', $ticket) }}" {{ $attributes->merge(['class' => 'min-w-0 rounded-lg border border-border bg-card p-3']) }}>
        @csrf
        <p class="text-sm font-semibold text-foreground">Escalate ticket</p>
        <p class="mt-0.5 text-xs text-muted-foreground">
            Hand this ticket to another recipient. The date of escalation is recorded on the ticket.
            @if ($ticket->escalated_at)
                Last escalated {{ $ticket->escalated_at->copy()->setTimezone('Asia/Manila')->format('M d, Y · g:i A') }}.
            @endif
        </p>
        @if ($escalationTargets->isEmpty())
            <p class="mt-2 text-xs text-muted-foreground">No other active recipient accounts are available.</p>
        @else
            <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                <label class="sr-only" for="escalate-recipient-{{ $ticket->id }}">Escalate to</label>
                <select id="escalate-recipient-{{ $ticket->id }}" name="recipient_id" required class="h-9 w-full min-w-0 flex-1 rounded-md border border-input bg-white px-2 text-xs text-foreground outline-none focus:ring-1 focus:ring-ring">
                    <option value="">Select who to escalate to</option>
                    @foreach ($escalationTargets as $target)
                        <option value="{{ $target->id }}" @selected((int) $target->id === (int) $suggestedTargetId)>{{ $target->user->table_name }} · {{ $target->designation }}, {{ $target->department }}</option>
                    @endforeach
                </select>
                <button type="submit" class="inline-flex h-9 shrink-0 items-center justify-center rounded-md bg-red-800 px-4 text-xs font-semibold text-white transition-colors hover:bg-red-900">Escalate</button>
            </div>
            @error('recipient_id')
                <p class="mt-1 text-xs font-medium text-destructive">{{ $message }}</p>
            @enderror
        @endif
    </form>
@endif
