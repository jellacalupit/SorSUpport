@php
    $complaint = $ticket->complaint;
    $detailsEditable = $ticket->isAwaitingReview();
    $assignedUser = $ticket->currentHandler ?? $ticket->assignee;
    $assignedRecipient = $assignedUser?->recipient;
    $suggestedRecipientName = $complaint->suggestedRecipient?->user?->table_name;
    $labelClass = 'text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase';
    $valueClass = 'wrap-break-word text-sm font-medium leading-tight text-foreground';
@endphp

<div x-data="{ editingDetails: false }" class="grid gap-2">
    <div class="min-w-0">
        <p class="{{ $labelClass }}">Category</p>
        <span x-show="!editingDetails" class="inline-flex max-w-full items-center gap-1 {{ $valueClass }}">
            <span>{{ $complaint->category?->name ?? 'Uncategorized' }}</span>
            @if ($detailsEditable)
                <button type="button" x-show="!editingDetails" x-on:click="editingDetails = true; $nextTick(() => $refs.detailsEditor?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }))" aria-label="Edit category and recipient" title="Edit category and recipient" class="inline-grid h-6 w-6 shrink-0 place-items-center rounded-full text-primary transition-colors hover:bg-primary-soft">
                    <x-icons.pencil class="h-3.5 w-3.5" />
                </button>
            @endif
        </span>
    </div>
    <div>
        <p class="{{ $labelClass }}">{{ $assignedRecipient ? 'Assigned Recipient' : ($assignedUser ? 'Assigned To' : 'Suggested Recipient') }}</p>
        <p class="{{ $valueClass }}">{{ $assignedUser?->table_name ?? $suggestedRecipientName ?? 'None' }}</p>
    </div>
    @if ($detailsEditable)
        <form x-ref="detailsEditor" x-show="editingDetails" x-cloak method="POST" action="{{ route('admin.tickets.update-details', $ticket) }}" class="mt-2 grid gap-3 rounded-lg border border-border bg-muted/50 p-3" data-ticket-details-editor>
            @csrf
            @method('PATCH')
            <div>
                <h3 class="text-sm font-semibold text-foreground">Edit ticket details</h3>
                <p class="mt-0.5 text-xs text-muted-foreground">Correct the category and choose the recipient to use during classification and routing.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="min-w-0">
                    <p class="mb-1 text-xs font-semibold text-muted-foreground">Category</p>
                    <x-picker name="category_id" required label="Category" :selected="$complaint->category_id" :options="$categories->map(fn ($categoryOption) => ['value' => $categoryOption->id, 'label' => $categoryOption->name])->all()" />
                </div>
                <div class="min-w-0">
                    <p class="mb-1 text-xs font-semibold text-muted-foreground">Suggested recipient</p>
                    <x-picker name="suggested_recipient_id" label="Suggested recipient" placeholder="None" :selected="$complaint->suggested_recipient_id ?? ''" :options="collect([['value' => '', 'label' => 'None']])->merge($recipients->map(fn ($recipientOption) => ['value' => $recipientOption->id, 'label' => $recipientOption->user?->table_name ?? 'Recipient', 'detail' => trim($recipientOption->designation . ', ' . $recipientOption->unit, ', ')]))->all()" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" x-on:click="editingDetails = false" class="w-full rounded-full border border-primary bg-white px-3 py-2 text-xs font-semibold text-primary transition-colors hover:bg-primary-soft">Cancel</button>
                <button type="submit" class="w-full rounded-full border border-primary bg-primary px-3 py-2 text-xs font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Save changes</button>
            </div>
        </form>
    @endif
</div>
