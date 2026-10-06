@props(['ticket'])

{{-- Shown when the admin taps Escalate. Only recipients configured in the category's escalation
     hierarchy are offered, grouped by path and labelled with their level. Expects an Alpine
     `action` variable on a parent so Cancel can fold it away. --}}

@php
    $escalationService = app(\App\Services\TicketEscalationService::class);
    $escalationPaths = $escalationService->escalationOptions($ticket)->groupBy('path');
    $suggestedTargetId = $escalationService->getNextRecipient($ticket)?->id;
    $suggestionUsed = false;
@endphp

<form method="POST" action="{{ route('admin.tickets.escalate', $ticket) }}" {{ $attributes->merge(['class' => 'min-w-0']) }}>
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
        <button type="button" x-on:click="action = null" class="mt-3 h-9 w-full rounded-full border border-border bg-white px-3 text-xs font-semibold text-foreground transition-colors hover:bg-muted">Back</button>
    @else
        <label class="sr-only" for="escalate-recipient-{{ $ticket->id }}">Escalate to</label>
        <select id="escalate-recipient-{{ $ticket->id }}" name="recipient_id" required class="mt-2 h-9 w-full min-w-0 rounded-md border border-input bg-white px-2 text-xs text-foreground outline-none focus:ring-1 focus:ring-ring">
            <option value="">Select who to escalate to</option>
            @foreach ($escalationPaths as $pathName => $steps)
                <optgroup label="{{ $pathName }}">
                    @foreach ($steps as $step)
                        @php
                            $isSuggested = ! $suggestionUsed && (int) $step['recipient']->id === (int) $suggestedTargetId;
                            $suggestionUsed = $suggestionUsed || $isSuggested;
                        @endphp
                        <option value="{{ $step['recipient']->id }}" @selected($isSuggested)>Level {{ $step['level'] }} · {{ $step['recipient']->user->table_name }} · {{ $step['recipient']->designation }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        @error('recipient_id')
            <p class="mt-1 text-xs font-medium text-destructive">{{ $message }}</p>
        @enderror
        <div class="mt-3 grid grid-cols-2 gap-2">
            <button type="button" x-on:click="action = null" class="h-9 w-full rounded-full border border-border bg-white px-3 text-xs font-semibold text-foreground transition-colors hover:bg-muted">Cancel</button>
            <button type="submit" class="h-9 w-full rounded-full bg-red-800 px-3 text-xs font-semibold text-white transition-colors hover:bg-red-900">Escalate</button>
        </div>
    @endif
</form>
