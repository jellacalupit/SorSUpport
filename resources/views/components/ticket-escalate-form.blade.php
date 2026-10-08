@props(['ticket', 'cancellable' => false])

{{-- Shown when the admin taps Escalate. Only recipients configured in the category's escalation
     hierarchy are offered, grouped by path and labelled with their level. With `cancellable`, a
     parent Alpine `action` variable is expected so Cancel can fold the form away. --}}

@php
    $escalationService = app(\App\Services\TicketEscalationService::class);
    $escalationPaths = $escalationService->escalationOptions($ticket)->groupBy('path');
    $suggestedTargetId = $escalationService->getNextRecipient($ticket)?->id;
    $canEscalate = $ticket->classification === \App\Models\Ticket::CLASSIFICATION_NEEDS_RESOLUTION
        && in_array($ticket->status, [\App\Models\Ticket::STATUS_ASSIGNED, \App\Models\Ticket::STATUS_IN_PROGRESS, \App\Models\Ticket::STATUS_ESCALATED], true);
@endphp

@if ($canEscalate)
<form method="POST" action="{{ route('admin.tickets.escalate', $ticket) }}" {{ $attributes->merge(['class' => 'min-w-0 rounded-lg border border-border bg-card p-3']) }}>
    @csrf
    <p class="text-sm font-semibold text-foreground">Escalate ticket</p>
    <p class="mt-0.5 text-xs text-muted-foreground">
        Hand this ticket to the next person in this category's escalation hierarchy. The date of escalation is recorded on the ticket.
        @if ($ticket->escalated_at)
            Last escalated {{ $ticket->escalated_at->copy()->setTimezone('Asia/Manila')->format('M d, Y · g:i A') }}.
        @endif
    </p>

    @if ($escalationPaths->isEmpty())
        <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
            No one else is set in the escalation hierarchy of “{{ $ticket->complaint?->category?->name ?? 'this category' }}”.
            <a href="{{ route('admin.settings', ['settings_tab' => 'escalation', 'category' => $ticket->complaint?->category_id]) }}" class="font-semibold underline">Set it up in System Settings</a>.
        </p>
        @if ($cancellable)
            <button type="button" x-on:click="action = null" class="mt-3 h-9 w-full rounded-full border border-border bg-white px-3 text-xs font-semibold text-foreground transition-colors hover:bg-muted">Back</button>
        @endif
    @else
        @php
            // One option per person on each path, labelled with their level; the suggested next
            // person is chosen to start with.
            $escalationOptions = [];
            foreach ($escalationPaths as $pathName => $steps) {
                foreach ($steps as $step) {
                    $escalationOptions[] = [
                        'value' => $step['recipient']->id,
                        'label' => 'Level ' . $step['level'] . ' · ' . $step['recipient']->user->table_name,
                        'detail' => trim($step['recipient']->designation . ', ' . $step['recipient']->unit, ', ') . ($ticket->complaint?->names($step['recipient']->user) ? ' · named in this complaint' : ''),
                        'group' => $pathName,
                    ];
                }
            }
        @endphp
        <x-picker name="recipient_id" required id="escalate-recipient-{{ $ticket->id }}" label="Escalate to" class="mt-2" placeholder="Select who to escalate to" :selected="$suggestedTargetId ?? ''" :options="$escalationOptions" />
        @error('recipient_id')
            <p class="mt-1 text-xs font-medium text-destructive">{{ $message }}</p>
        @enderror
        <label class="sr-only" for="escalation-note-{{ $ticket->id }}">Reason for escalating</label>
        <textarea id="escalation-note-{{ $ticket->id }}" name="escalation_note" rows="3" required maxlength="1000" class="mt-2 w-full rounded-md border border-input bg-white px-3 py-2 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring" placeholder="Why is this ticket being escalated? This is recorded on the ticket.">{{ old('escalation_note') }}</textarea>
        @error('escalation_note')
            <p class="mt-1 text-xs font-medium text-destructive">{{ $message }}</p>
        @enderror
        <div class="mt-3 grid gap-2 {{ $cancellable ? 'grid-cols-2' : '' }}">
            @if ($cancellable)
                <button type="button" x-on:click="action = null" class="h-9 w-full rounded-full border border-border bg-white px-3 text-xs font-semibold text-foreground transition-colors hover:bg-muted">Cancel</button>
            @endif
            <button type="submit" class="h-9 w-full rounded-full bg-red-800 px-3 text-xs font-semibold text-white transition-colors hover:bg-red-900">Escalate</button>
        </div>
    @endif
</form>
@endif
