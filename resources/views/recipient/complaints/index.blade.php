<x-app-layout :role="'recipient'" title="Assigned Tickets">
    <section>
        <form method="GET" action="{{ route('recipient.tickets.index') }}" data-ticket-filter-form>
            <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                <div class="relative min-w-0 flex-1">
                    <x-icons.search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <label for="ticket-search" class="sr-only">Search tickets</label>
                    <input id="ticket-search" name="search" value="{{ request('search') }}" autocomplete="off" placeholder="Search ticket ID or subject title" class="h-9 w-full rounded-md border border-input bg-transparent pl-9 pr-3 text-sm shadow-sm outline-none placeholder:text-muted-foreground focus:ring-1 focus:ring-ring" />
                </div>

                @php
                    $statusLabels = ['' => 'All status', 'in_progress' => 'In Progress', 'escalated' => 'Escalated', 'resolved' => 'Resolved', 'closed' => 'Closed'];
                    $sortLabels = ['newest' => 'Sort by newest date', 'oldest' => 'Sort by oldest date'];
                    $selectedStatus = request('status_filter', '');
                    $selectedSort = request('sort', 'newest');
                @endphp
                <div class="grid grid-cols-[minmax(0,3fr)_minmax(0,2fr)] items-center gap-2 sm:flex">
                    <details x-data="{}" class="group relative min-w-0 sm:w-56 sm:shrink-0" x-on:click.outside="$el.removeAttribute('open')">
                        <summary id="sort-filter" class="flex h-9 w-full cursor-pointer list-none items-center justify-between whitespace-nowrap rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm outline-none ring-offset-background transition-colors hover:bg-muted focus:ring-1 focus:ring-ring [&::-webkit-details-marker]:hidden">
                            <span class="min-w-0 truncate">{{ $sortLabels[$selectedSort] ?? 'Sort by newest date' }}</span>
                            <svg class="h-4 w-4 shrink-0 opacity-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                        </summary>
                        <div class="absolute top-full right-0 z-50 mt-1 w-full overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md outline-none animate-in fade-in-0 zoom-in-95">
                            @foreach ($sortLabels as $value => $label)
                                <a data-ticket-filter-link href="{{ route('recipient.tickets.index', array_filter(['search' => request('search'), 'status_filter' => request('status_filter'), 'sort' => $value])) }}" class="relative flex w-full cursor-default select-none items-center rounded-sm py-1.5 pl-2 pr-8 text-xs outline-none transition-colors {{ $selectedSort === $value ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">
                                    @if ($selectedSort === $value)
                                        <x-icons.check class="absolute right-2 h-4 w-4 text-primary" />
                                    @endif
                                    {{ $label }}
                                </a>
                            @endforeach
                        </div>
                    </details>

                    <details x-data="{}" class="group relative min-w-0 sm:w-32 sm:shrink-0" x-on:click.outside="$el.removeAttribute('open')">
                        <summary id="status-filter" class="flex h-9 w-full cursor-pointer list-none items-center justify-between whitespace-nowrap rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm outline-none ring-offset-background transition-colors hover:bg-muted focus:ring-1 focus:ring-ring [&::-webkit-details-marker]:hidden">
                            <span class="min-w-0 truncate">{{ $statusLabels[$selectedStatus] ?? 'All statuses' }}</span>
                            <svg class="h-4 w-4 shrink-0 opacity-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                        </summary>
                        <div class="absolute top-full right-0 z-50 mt-1 max-h-72 w-full overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md outline-none animate-in fade-in-0 zoom-in-95">
                            @foreach ($statusLabels as $value => $label)
                                <a data-ticket-filter-link href="{{ route('recipient.tickets.index', array_filter(['search' => request('search'), 'status_filter' => $value, 'sort' => request('sort', 'newest')])) }}" class="relative flex w-full cursor-default select-none items-center rounded-sm py-1.5 pl-2 pr-8 text-xs outline-none transition-colors {{ $selectedStatus === $value ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">
                                    @if ($selectedStatus === $value)
                                        <x-icons.check class="absolute right-2 h-4 w-4 text-primary" />
                                    @endif
                                    {{ $label }}
                                </a>
                            @endforeach
                        </div>
                    </details>
                </div>
            </div>
        </form>

        <div data-ticket-results>
    <p class="mb-2 text-xs text-muted-foreground">{{ $complaints->total() }} ticket(s)</p>
        @if ($complaints->count() > 0)
            <ul class="grid gap-0.5">
                @foreach ($complaints as $ticket)
                    <li>
                        <x-ticket-card :item="$ticket" role="recipient" :first="$loop->first" :last="$loop->last" />
                    </li>
                @endforeach
            </ul>

            @if ($complaints->hasPages())
                <div class="mt-6">{{ $complaints->links() }}</div>
            @endif
        @else
            <p class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">No tickets are currently routed to your office.</p>
        @endif
        </div>
    </section>
</x-app-layout>
