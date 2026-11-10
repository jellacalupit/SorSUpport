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
    <div x-show="!editingDetails" class="grid gap-2">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="{{ $labelClass }}">Category</p>
                <p class="flex items-center gap-1.5 {{ $valueClass }}">
                    <span>{{ $complaint->category?->name ?? 'Uncategorized' }}</span>
                    @if ($detailsEditable)
                        <button type="button" x-show="!editingDetails" x-on:click="editingDetails = true" aria-label="Edit category" title="Edit category" class="inline-grid h-6 w-6 shrink-0 place-items-center rounded-full text-primary transition-colors hover:bg-primary-soft">
                            <x-icons.pencil class="h-3.5 w-3.5" />
                        </button>
                    @endif
                </p>
            </div>
        </div>
        <div>
            <p class="{{ $labelClass }}">{{ $assignedRecipient ? 'Assigned Recipient' : ($assignedUser ? 'Assigned To' : 'Suggested Recipient') }}</p>
            <p class="{{ $valueClass }}">{{ $assignedUser?->table_name ?? $suggestedRecipientName ?? 'None' }}</p>
        </div>
    </div>

    @if ($detailsEditable)
        <form x-show="editingDetails" x-cloak method="POST" action="{{ route('admin.tickets.update-details', $ticket) }}" class="grid gap-2 rounded-lg border border-border bg-muted/50 p-3">
            @csrf
            @method('PATCH')
            <p class="text-xs text-muted-foreground">Correct the category if the student selected the wrong one.</p>
            <div class="{{ $labelClass }}">
                Category
                <x-picker name="category_id" required label="Category" class="mt-1" :selected="$complaint->category_id" :options="$categories->map(fn ($categoryOption) => ['value' => $categoryOption->id, 'label' => $categoryOption->name])->all()" />
            </div>
            <div class="mt-1 grid grid-cols-2 gap-2">
                <button type="button" x-on:click="editingDetails = false" class="w-full rounded-full border border-primary bg-white px-2.5 py-1.5 text-center text-xs font-semibold text-primary transition-colors hover:bg-primary-soft">Cancel</button>
                <button type="submit" class="w-full rounded-full border border-primary bg-primary px-2.5 py-1.5 text-center text-xs font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Save Changes</button>
            </div>
        </form>
    @endif
</div>
