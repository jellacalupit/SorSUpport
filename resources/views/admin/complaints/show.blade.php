<x-app-layout :role="'admin'" title="Ticket Details">
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="mb-4 flex items-center justify-between gap-3">
        <a href="{{ route('admin.tickets.my') }}" aria-label="Back" onclick="event.preventDefault(); if (window.history.length > 1) { window.history.back(); } else { window.location.href = '{{ route('admin.tickets.my') }}'; }" class="inline-flex items-center gap-1 rounded-md px-1.5 py-1 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground sm:text-base"><span aria-hidden="true" class="scale-y-150 text-lg leading-none">&lt;</span> Back</a>
    </div>

    @if ($complaint->ticket)
        <div class="grid gap-5">
            <div class="grid content-start gap-3"><x-ticket-info-panel :ticket="$complaint->ticket" :show-filed-by="true" :show-closed-at="true" /></div>

            @if ($complaint->ticket->classification === 'needs_resolution' && $complaint->ticket->current_handler_id === Auth::id())
                <div class="surface p-4 sm:p-6">
                    <h3 class="mb-4 font-display text-base font-bold">My ticket actions</h3>
                    @if ($complaint->ticket->status === 'assigned')
                        <form method="POST" action="{{ route('admin.tickets.acknowledge', $complaint->ticket) }}" class="grid gap-3">
                            @csrf
                            <button type="submit" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-green-800 px-4 text-sm font-semibold text-white transition-colors hover:bg-green-900"><x-icons.check class="h-4 w-4" /> Acknowledge Ticket</button>
                        </form>
                    @elseif ($complaint->ticket->status === 'in_progress')
                        <form method="POST" action="{{ route('admin.tickets.resolve', $complaint->ticket) }}" class="grid gap-3">@csrf<textarea name="resolution_message" rows="4" required placeholder="Describe the resolution provided to the student." class="w-full rounded-xl border border-input bg-muted px-3 py-2.5 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"></textarea><button type="submit" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Mark as Resolved</button></form>
                    @elseif ($complaint->ticket->status === 'resolved')
                        <div class="grid gap-2 sm:grid-cols-2">
                            <form method="POST" action="{{ route('admin.tickets.not-yet-resolved', $complaint->ticket) }}">@csrf<button type="submit" class="inline-flex h-10 w-full items-center justify-center rounded-md border border-primary px-4 text-sm font-semibold text-primary transition-colors hover:bg-primary-soft">Not yet Resolved</button></form>
                            <form method="POST" action="{{ route('admin.tickets.close', $complaint->ticket) }}">@csrf<button type="submit" class="inline-flex h-10 w-full items-center justify-center rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Close Ticket</button></form>
                        </div>
                    @else
                        <p class="text-sm text-muted-foreground">This ticket is closed. No further action is available.</p>
                    @endif
                </div>
            @endif

            <x-ticket-escalate-form :ticket="$complaint->ticket" class="surface p-4 sm:p-6" />

            @if ($complaint->ticket->classification === 'needs_resolution')
                <div class="grid content-start gap-3"><x-ticket-thread :ticket="$complaint->ticket" viewerRole="admin" /></div>
            @endif

            <div class="surface p-4 sm:p-6">
                <h3 class="mb-4 font-display text-lg font-bold">Audit Trail</h3>
                @if ($complaint->ticket->auditLogs && $complaint->ticket->auditLogs->count())
                    <div class="space-y-3">
                        @foreach ($complaint->ticket->auditLogs as $log)
                            <div class="flex items-start gap-4"><div class="w-36 text-sm text-gray-500"><div>{{ $log->created_at->copy()->setTimezone('Asia/Manila')->format('M d, Y') }}</div><div class="mt-1">{{ $log->created_at->copy()->setTimezone('Asia/Manila')->format('h:i A') }}</div></div><div class="flex-1 rounded-lg border bg-gray-50 p-3"><div class="flex items-center justify-between"><div class="font-semibold text-gray-900">{{ str_replace('_', ' ', $log->action) }}</div><div class="text-sm text-gray-500">{{ $log->performer?->display_name ?? 'System' }}</div></div><div class="mt-2 text-sm text-gray-700">{{ $log->details }}</div></div></div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500">No activity recorded.</p>
                @endif
            </div>
        </div>
    @else
        <div class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">No ticket has been generated for this complaint yet.</div>
    @endif
</x-app-layout>