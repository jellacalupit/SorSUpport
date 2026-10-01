<x-app-layout :role="'recipient'" title="Ticket Details">
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-4 flex items-center justify-between gap-3">
        <a href="{{ route('recipient.tickets.index') }}" aria-label="Back" onclick="event.preventDefault(); if (window.history.length > 1) { window.history.back(); } else { window.location.href = '{{ route('recipient.tickets.index') }}'; }" class="inline-flex items-center gap-1 rounded-md px-1.5 py-1 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground sm:text-base">
            <span aria-hidden="true" class="scale-y-150 text-lg leading-none">&lt;</span>
            Back
        </a>
        @if (in_array($complaint->ticket->status, ['assigned', 'in_progress'], true))
            <form id="resolve-ticket-form" method="POST" action="{{ route('recipient.complaints.update-status', $complaint) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="resolved">
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-md bg-green-800 px-3 py-2 text-xs font-semibold text-white transition-colors hover:bg-green-900">
                    Mark as Resolved
                </button>
            </form>
        @endif
    </div>

    <div class="grid gap-5">
        <div class="grid content-start gap-3">
            <x-ticket-info-panel :ticket="$complaint->ticket" :show-filed-by="true" />
        </div>

        @if ($complaint->ticket->classification === 'needs_resolution')
            <div class="grid content-start gap-3">
                <x-ticket-thread :ticket="$complaint->ticket" viewerRole="recipient" />
            </div>
        @endif
    </div>
</x-app-layout>
