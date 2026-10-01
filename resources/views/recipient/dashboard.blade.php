<x-app-layout :role="'recipient'" title="Dashboard">
    <div class="-mt-1 mb-4 sm:-mt-2">
        <h2 class="font-display text-lg font-bold text-primary sm:text-xl">Welcome back, {{ $firstName }}!</h2>
        <p class="-mt-1 text-sm text-muted-foreground">
            ID {{ $recipient->staff_id }} · {{ $recipient->department }} · {{ $recipient->designation }}
        </p>
    </div>

    <div class="grid grid-cols-5 gap-2 sm:gap-3">
        <x-stat-card compact label="Total" :value="$totalCount" />
        <x-stat-card compact label="In Progress" :value="$inProgressCount" value-tone="blue" />
        <x-stat-card compact label="Escalated" :value="$escalatedCount" value-tone="red" />
        <x-stat-card compact label="Resolved" :value="$resolvedCount" value-tone="green" />
        <x-stat-card compact label="Closed" :value="$closedCount" />
    </div>

    <section class="mt-5">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="font-display text-base font-bold">Recent Tickets</h2>
            <a href="{{ route('recipient.tickets.index') }}" class="inline-flex h-8 items-center rounded-lg border border-border bg-background px-3 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted">
                View all
            </a>
        </div>

        <ul class="grid min-w-0 gap-0.5">
            @forelse($latestComplaints as $ticket)
                <li class="min-w-0">
                    <x-ticket-card :item="$ticket" role="recipient" :first="$loop->first" :last="$loop->last" />
                </li>
            @empty
                <li class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                    No tickets are currently routed to your office.
                </li>
            @endforelse
        </ul>
    </section>
</x-app-layout>
