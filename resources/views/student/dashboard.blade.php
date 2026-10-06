<x-app-layout :role="'student'" title="Dashboard">
    <!-- Welcome Section -->
    <div class="-mt-1 grid grid-cols-[minmax(0,1fr)_auto] items-center gap-4 sm:-mt-2">
        <div class="min-w-0">
            <h2 class="font-display text-xl font-bold text-primary sm:text-2xl">
                Welcome back, {{ $firstName }}!
            </h2>
            <p class="-mt-1 text-sm text-muted-foreground">
                ID {{ $studentId }} · {{ $college }} · {{ $programYearBlock }}
            </p>
        </div>
        <a href="{{ route('student.complaints.create') }}" class="hidden h-10 items-center justify-center gap-2 rounded-md bg-primary px-8 text-sm font-medium text-primary-foreground shadow transition-colors hover:bg-primary/90 sm:inline-flex">
            <x-icons.plus class="h-4 w-4" />
            <span>Create Ticket</span>
        </a>
    </div>

    <!-- Stats Grid -->
    <div class="mt-5 grid grid-cols-1 gap-2 min-[220px]:grid-cols-2 min-[360px]:grid-cols-3 sm:grid-cols-6 sm:gap-3">
        <x-stat-card compact inline label="Total" :value="$totalCount" />
        <x-stat-card compact inline label="Pending" :value="$pendingCount" value-tone="yellow" />
        <x-stat-card
            compact
            inline
            label="In Progress"
            :value="$inProgressCount"
            value-tone="blue"
        />
        <x-stat-card compact inline label="Escalated" :value="$escalatedCount" value-tone="red" />
        <x-stat-card compact inline label="Resolved" :value="$resolvedCount" value-tone="green" />
        <x-stat-card compact inline label="Closed" :value="$closedCount" />
    </div>

    <!-- Recent Tickets Section -->
    <section class="mt-5">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="font-display text-base font-bold">Recent tickets</h2>
            <a href="{{ route('student.complaints.index') }}" class="inline-flex h-8 items-center rounded-lg border border-border bg-background px-3 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted">
                View all
            </a>
        </div>

        <ul class="grid min-w-0 gap-0.5">
            @forelse($recentTickets as $complaint)
                <li class="min-w-0">
                    <x-ticket-card :item="$complaint" role="student" :first="$loop->first" :last="$loop->last" />
                </li>
            @empty
                <li class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                    No filed complaints yet.
                </li>
            @endforelse
        </ul>
    </section>
</x-app-layout>