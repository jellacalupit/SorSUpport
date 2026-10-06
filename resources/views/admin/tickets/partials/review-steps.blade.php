{{-- Step 1 (validity) and step 2 (classification and routing) for a ticket awaiting review. --}}
@php
    $category = $ticket->complaint->category;
    $availableRecipients = $category
        ? collect([$category->recipient])
            ->merge($category->suggestedRecipients)
            ->merge($category->escalationHierarchies->pluck('recipient'))
            ->push($ticket->complaint->suggestedRecipient)
            ->filter(fn ($recipient) => $recipient && $recipient->user && $recipient->user->is_active)
            ->unique('id')
            ->values()
        : collect();
    $isAnonymousTicket = (bool) $ticket->complaint->is_anonymous;
@endphp

<x-ticket-actions :ticket="$ticket" role="admin" class="mb-3" />
<div x-data="{ validity: null, classification: null, jurisdiction: null, informationalDisposition: null, selectedRecipientId: null, selectedRecipient: '', invalidReason: '', invalidReasonError: false }">
    <div class="rounded-lg border border-border bg-white p-4 text-foreground shadow-sm">
    <h3 class="flex items-center gap-2 font-display text-base font-bold text-black">
        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-primary text-xs font-bold text-primary-foreground">1</span>
        <span>Validity</span>
    </h3>
    <div class="mt-4 grid grid-cols-2 gap-2">
        <button type="button" x-on:click="validity = 'valid'" class="w-full rounded-full border border-primary px-2.5 py-1.5 text-center text-xs font-semibold transition-colors" x-bind:class="validity === 'valid' ? 'bg-primary text-primary-foreground' : 'bg-white text-primary hover:bg-primary-soft'">Valid</button>
        <button type="button" x-on:click="validity = 'invalid'" class="w-full rounded-full border border-primary px-2.5 py-1.5 text-center text-xs font-semibold transition-colors" x-bind:class="validity === 'invalid' ? 'bg-primary text-primary-foreground' : 'bg-white text-primary hover:bg-primary-soft'">Invalid</button>
    </div>

    <div x-show="validity === 'valid'" x-cloak class="mt-5 border-t border-border pt-4">
        <h3 class="flex items-center gap-2 font-display text-base font-bold text-black">
            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-primary text-xs font-bold text-primary-foreground">2</span>
            <span>Classification</span>
        </h3>
        @if ($isAnonymousTicket)
            <p class="mt-2 text-xs text-muted-foreground">The student chose to hide their identity. Review and route the ticket as usual; their name is not shown to anyone handling it.</p>
        @endif
        <div class="mt-4 grid gap-2">
                <button type="button" x-on:click="classification = 'needs_resolution'" class="w-full rounded-full border border-primary px-2.5 py-1.5 text-center text-xs font-semibold transition-colors" x-bind:class="classification === 'needs_resolution' ? 'bg-primary text-primary-foreground' : 'bg-white text-primary hover:bg-primary-soft'">Needs Resolution</button>
            <button type="button" x-on:click="classification = 'informational'" class="w-full rounded-full border border-primary px-2.5 py-1.5 text-center text-xs font-semibold transition-colors" x-bind:class="classification === 'informational' ? 'bg-primary text-primary-foreground' : 'bg-white text-primary hover:bg-primary-soft'">Informational</button>
        </div>
        <div x-show="classification === 'needs_resolution'" x-cloak class="mt-5 border-t border-border pt-4">
            <p class="text-sm font-semibold text-black">Does this fall under SDS jurisdiction or under a different office?</p>
            <div class="mt-4 grid gap-2">
                <button type="button" x-on:click="jurisdiction = 'sds'" class="w-full rounded-full border border-primary px-2.5 py-1.5 text-center text-xs font-semibold transition-colors" x-bind:class="jurisdiction === 'sds' ? 'bg-primary text-primary-foreground' : 'bg-white text-primary hover:bg-primary-soft'">SDS Jurisdiction</button>
                <button type="button" x-on:click="jurisdiction = 'different_office'" class="w-full rounded-full border border-primary px-2.5 py-1.5 text-center text-xs font-semibold transition-colors" x-bind:class="jurisdiction === 'different_office' ? 'bg-primary text-primary-foreground' : 'bg-white text-primary hover:bg-primary-soft'">Different Office</button>
            </div>
            <details x-show="jurisdiction === 'different_office'" x-cloak x-data="{}" class="group relative mt-4" x-on:click.outside="$el.removeAttribute('open')">
                <summary class="flex h-9 w-full cursor-pointer list-none items-center justify-between rounded-full border border-primary bg-white px-3 py-2 text-xs font-semibold text-primary [&::-webkit-details-marker]:hidden">
                    <span class="truncate" x-text="selectedRecipient || 'Select recipient account'"></span>
                    <svg class="h-4 w-4 shrink-0 opacity-60 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                </summary>
                <div class="absolute top-full z-40 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-border bg-white p-1 text-foreground shadow-lg">
                    @forelse ($availableRecipients as $recipient)
                        @php $recipientName = $recipient->user?->display_name ?? $recipient->user?->name ?? $recipient->unit; @endphp
                        <button type="button" x-on:click="selectedRecipientId = {{ $recipient->id }}; selectedRecipient = @js($recipientName); $el.closest('details').removeAttribute('open')" class="flex w-full items-start rounded-md px-2 py-1.5 text-left text-xs transition-colors hover:bg-primary-soft" x-bind:class="selectedRecipientId === {{ $recipient->id }} ? 'bg-primary-soft text-primary' : ''">
                            <span class="min-w-0">
                                <span class="block truncate font-semibold">{{ $recipientName }}</span>
                                @if ($ticket->complaint->names($recipient->user))<span class="block text-[10px] font-semibold text-red-700">Named in this complaint</span>@endif
                                <span class="block truncate text-muted-foreground">{{ $recipient->staff_id }} · {{ $recipient->unit }} · {{ $recipient->designation }}</span>
                            </span>
                        </button>
                    @empty
                        <span class="block px-2 py-2 text-xs text-muted-foreground">No recipient accounts available.</span>
                    @endforelse
                </div>
            </details>
        </div>
        <div x-show="classification === 'informational'" x-cloak class="mt-5 border-t border-border pt-4">
            <p class="text-sm font-semibold text-black">Should the ticket retain in SDS records or should it be forwarded to a recipient?</p>
            <div class="mt-4 grid gap-2">
                <button type="button" x-on:click="informationalDisposition = 'retain'; selectedRecipientId = null; selectedRecipient = ''" class="w-full rounded-full border border-primary px-2.5 py-1.5 text-center text-xs font-semibold transition-colors" x-bind:class="informationalDisposition === 'retain' ? 'bg-primary text-primary-foreground' : 'bg-white text-primary hover:bg-primary-soft'">Retain in SDS Records</button>
                <button type="button" x-on:click="informationalDisposition = 'forward'" class="w-full rounded-full border border-primary px-2.5 py-1.5 text-center text-xs font-semibold transition-colors" x-bind:class="informationalDisposition === 'forward' ? 'bg-primary text-primary-foreground' : 'bg-white text-primary hover:bg-primary-soft'">Forward to Recipient</button>
            </div>
            <details x-show="informationalDisposition === 'forward'" x-cloak x-data="{}" class="group relative mt-4" x-on:click.outside="$el.removeAttribute('open')">
                <summary class="flex h-9 w-full cursor-pointer list-none items-center justify-between rounded-full border border-primary bg-white px-3 py-2 text-xs font-semibold text-primary [&::-webkit-details-marker]:hidden">
                    <span class="truncate" x-text="selectedRecipient || 'Select recipient account'"></span>
                    <svg class="h-4 w-4 shrink-0 opacity-60 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                </summary>
                <div class="absolute top-full z-40 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-border bg-white p-1 text-foreground shadow-lg">
                    @forelse ($availableRecipients as $recipient)
                        @php $recipientName = $recipient->user?->display_name ?? $recipient->user?->name ?? $recipient->unit; @endphp
                        <button type="button" x-on:click="selectedRecipientId = {{ $recipient->id }}; selectedRecipient = @js($recipientName); $el.closest('details').removeAttribute('open')" class="flex w-full items-start rounded-md px-2 py-1.5 text-left text-xs transition-colors hover:bg-primary-soft">
                            <span class="min-w-0">
                                <span class="block truncate font-semibold">{{ $recipientName }}</span>
                                @if ($ticket->complaint->names($recipient->user))<span class="block text-[10px] font-semibold text-red-700">Named in this complaint</span>@endif
                                <span class="block truncate text-muted-foreground">{{ $recipient->staff_id }} · {{ $recipient->unit }} · {{ $recipient->designation }}</span>
                            </span>
                        </button>
                    @empty
                        <span class="block px-2 py-2 text-xs text-muted-foreground">No recipient accounts available.</span>
                    @endforelse
                </div>
            </details>
        </div>
    </div>

    <form x-show="validity === 'invalid'" x-cloak method="POST" action="{{ route('admin.tickets.reject', $ticket) }}" class="mt-5 border-t border-border pt-4" x-on:submit="if (!invalidReason.trim()) { invalidReasonError = true; $event.preventDefault(); }">
        @csrf
        <label for="closure-type-review-{{ $ticket->id }}" class="text-sm font-semibold text-black">Why can it not be acted on?</label>
        <select id="closure-type-review-{{ $ticket->id }}" name="closure_type" class="mt-2 mb-3 h-9 w-full rounded-md border border-input bg-white px-2 text-xs text-foreground outline-none focus:ring-1 focus:ring-ring">
            @foreach ([\App\Models\Ticket::CLOSURE_INVALID, \App\Models\Ticket::CLOSURE_DUPLICATE, \App\Models\Ticket::CLOSURE_OUT_OF_SCOPE, \App\Models\Ticket::CLOSURE_NO_RESPONSE] as $closureType)
                <option value="{{ $closureType }}">{{ \App\Models\Ticket::CLOSURE_LABELS[$closureType] }}</option>
            @endforeach
        </select>
        <label for="closure-reason-{{ $ticket->id }}" class="text-sm font-semibold text-black">Reason of Invalidity <span class="text-destructive" aria-hidden="true">*</span></label>
        <textarea id="closure-reason-{{ $ticket->id }}" name="closure_reason" rows="4" x-model="invalidReason" x-on:input="invalidReasonError = false" x-bind:class="invalidReasonError ? 'border-destructive' : 'border-input'" class="mt-2 w-full rounded-lg border bg-muted px-3 py-2.5 text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" placeholder="Enter the reason for invalidity."></textarea>
        <p x-show="invalidReasonError" x-cloak class="mt-1 text-xs font-medium text-destructive">This field is required.</p>
        <button type="submit" class="mt-3 w-full rounded-full border border-primary bg-primary px-2.5 py-1.5 text-center text-xs font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Close Ticket</button>
    </form>
    </div>
    <form x-show="validity === 'valid' && classification === 'needs_resolution' && jurisdiction === 'sds'" x-cloak method="POST" action="{{ route('admin.tickets.acknowledge', $ticket) }}" class="mt-3">
        @csrf
        <input type="hidden" name="return_to_pending" value="1">
        <button type="submit" class="w-full rounded-full border border-primary bg-primary px-2.5 py-1.5 text-center text-xs font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Acknowledge Ticket</button>
    </form>
    <form x-show="validity === 'valid' && classification === 'needs_resolution' && jurisdiction === 'different_office' && selectedRecipientId" x-cloak method="POST" action="{{ route('admin.tickets.assign', $ticket) }}" class="mt-3">
        @csrf
        <input type="hidden" name="return_to_pending" value="1">
        <input type="hidden" name="assignment_mode" value="recipient">
        <input type="hidden" name="recipient_id" x-bind:value="selectedRecipientId">
        <button type="submit" class="w-full rounded-full border border-primary bg-primary px-2.5 py-1.5 text-center text-xs font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Assign Ticket to Recipient</button>
    </form>
    <form x-show="validity === 'valid' && classification === 'informational' && informationalDisposition === 'retain'" x-cloak method="POST" action="{{ route('admin.tickets.retain-informational', $ticket) }}" class="mt-3">
        @csrf
        <button type="submit" class="w-full rounded-full border border-primary bg-primary px-2.5 py-1.5 text-center text-xs font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Save &amp; Close Ticket</button>
    </form>
    <form x-show="validity === 'valid' && classification === 'informational' && informationalDisposition === 'forward' && selectedRecipientId" x-cloak method="POST" action="{{ route('admin.tickets.forward-informational-close', $ticket) }}" class="mt-3">
        @csrf
        <input type="hidden" name="recipient_id" x-bind:value="selectedRecipientId">
        <button type="submit" class="w-full rounded-full border border-primary bg-primary px-2.5 py-1.5 text-center text-xs font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Forward &amp; Close Ticket</button>
    </form>
</div>
