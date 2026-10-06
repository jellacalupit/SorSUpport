@php
    $complaint = $ticket->complaint;
    $detailsEditable = $ticket->status === 'pending' && $ticket->classification === null;
    $labelClass = 'text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase';
    $valueClass = 'wrap-break-word text-sm font-medium leading-tight text-foreground';
    $selectClass = 'mt-1 h-9 w-full rounded-md border border-input bg-white px-2 text-xs text-foreground outline-none focus:ring-1 focus:ring-ring';
@endphp

<div x-data="{ editingDetails: false }" class="grid gap-2">
    <div x-show="!editingDetails" class="grid gap-2">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="{{ $labelClass }}">Category</p>
                <p class="{{ $valueClass }}">{{ $complaint->category?->name ?? 'Uncategorized' }}</p>
            </div>
            @if ($detailsEditable)
                <button type="button" x-on:click="editingDetails = true" class="inline-flex shrink-0 items-center gap-1 rounded-full border border-primary px-2.5 py-1 text-[11px] font-semibold text-primary transition-colors hover:bg-primary-soft">
                    <x-icons.pencil class="h-3 w-3" />
                    Edit
                </button>
            @endif
        </div>
        <div>
            <p class="{{ $labelClass }}">Suggested recipient</p>
            <p class="{{ $valueClass }}">{{ $complaint->suggestedRecipient?->user?->table_name ?? 'None' }}</p>
        </div>
    </div>

    @if ($detailsEditable)
        <form x-show="editingDetails" x-cloak method="POST" action="{{ route('admin.tickets.update-details', $ticket) }}" class="grid gap-2 rounded-lg border border-border bg-muted/50 p-3">
            @csrf
            @method('PATCH')
            <p class="text-xs text-muted-foreground">Correct these if the student picked the wrong category or recipient.</p>
            <label class="{{ $labelClass }}">
                Category
                <select name="category_id" required class="{{ $selectClass }} normal-case tracking-normal">
                    @foreach ($categories as $categoryOption)
                        <option value="{{ $categoryOption->id }}" @selected((int) $categoryOption->id === (int) $complaint->category_id)>{{ $categoryOption->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="{{ $labelClass }}">
                Suggested recipient
                <select name="suggested_recipient_id" class="{{ $selectClass }} normal-case tracking-normal">
                    <option value="">None</option>
                    @foreach ($recipients as $recipientOption)
                        <option value="{{ $recipientOption->id }}" @selected((int) $recipientOption->id === (int) $complaint->suggested_recipient_id)>{{ $recipientOption->user?->table_name }} · {{ $recipientOption->designation }}, {{ $recipientOption->unit }}</option>
                    @endforeach
                </select>
            </label>
            <div class="mt-1 grid grid-cols-2 gap-2">
                <button type="button" x-on:click="editingDetails = false" class="w-full rounded-full border border-primary bg-white px-2.5 py-1.5 text-center text-xs font-semibold text-primary transition-colors hover:bg-primary-soft">Cancel</button>
                <button type="submit" class="w-full rounded-full border border-primary bg-primary px-2.5 py-1.5 text-center text-xs font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Save Changes</button>
            </div>
        </form>
    @endif
</div>
