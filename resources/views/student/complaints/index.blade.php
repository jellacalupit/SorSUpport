<x-app-layout :role="'student'" title="My Tickets">
    <section>
        @php
            $statusLabels = ['' => 'All statuses'] + \App\Models\Ticket::STATUS_LABELS;
            $selectedStatus = $status ?: '';
        @endphp

        <form method="GET" action="{{ route('student.complaints.index') }}" data-ticket-filter-form class="mb-3 grid grid-cols-[minmax(0,3fr)_minmax(0,2fr)] gap-2 sm:grid-cols-[minmax(12rem,1fr)_minmax(15rem,auto)_10rem] sm:items-end">
            <div class="relative col-span-2 min-w-0 sm:col-span-1">
                <x-icons.search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <label for="student-ticket-search" class="sr-only">Search tickets</label>
                <input id="student-ticket-search" name="search" value="{{ $search }}" autocomplete="off" placeholder="Search ticket ID or subject title" class="h-9 w-full rounded-md border border-input bg-transparent pl-9 pr-3 text-xs shadow-sm outline-none placeholder:text-muted-foreground focus:ring-1 focus:ring-ring" />
            </div>

            <div class="grid min-w-0 gap-1.5">
            <details x-data="{}" class="group relative min-w-0" x-on:click.outside="$el.removeAttribute('open')">
                <summary id="category-filter" class="flex h-9 w-full min-w-0 cursor-pointer list-none items-center justify-between gap-2 whitespace-nowrap rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm outline-none ring-offset-background transition-colors hover:bg-muted focus:ring-1 focus:ring-ring [&::-webkit-details-marker]:hidden">
                    <span class="min-w-0 truncate">{{ $category !== 'All' ? $category : 'All categories' }}</span>
                    <svg class="h-4 w-4 shrink-0 opacity-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m6 9 6 6 6-6" />
                    </svg>
                </summary>
                <div class="absolute top-full z-50 mt-1 max-h-72 w-full min-w-32 overflow-y-auto overflow-x-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-md outline-none animate-in fade-in-0 zoom-in-95">
                    <a data-ticket-filter-link href="{{ route('student.complaints.index', array_filter(['search' => $search, 'status' => $selectedStatus])) }}" class="relative flex w-full cursor-default select-none items-center rounded-sm py-1.5 pl-2 pr-8 text-xs outline-none transition-colors {{ $category === 'All' ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">
                        @if ($category === 'All')
                            <x-icons.check class="absolute right-2 h-4 w-4 text-primary" />
                        @endif
                        All categories
                    </a>
                    @foreach ($categories as $categoryName)
                        <a data-ticket-filter-link href="{{ route('student.complaints.index', array_filter(['search' => $search, 'category' => $categoryName, 'status' => $selectedStatus])) }}" class="relative flex w-full cursor-default select-none items-center rounded-sm py-1.5 pl-2 pr-8 text-xs outline-none transition-colors {{ $category === $categoryName ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">
                            @if ($category === $categoryName)
                                <x-icons.check class="absolute right-2 h-4 w-4 text-primary" />
                            @endif
                            <span class="whitespace-normal break-words">{{ $categoryName }}</span>
                        </a>
                    @endforeach
                </div>
            </details>
            </div>

            <div class="grid min-w-0 gap-1.5">
                <details x-data="{}" class="group relative min-w-0" x-on:click.outside="$el.removeAttribute('open')">
                    <summary id="status-filter" class="flex h-9 w-full min-w-0 cursor-pointer list-none items-center justify-between gap-2 whitespace-nowrap rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm outline-none ring-offset-background transition-colors hover:bg-muted focus:ring-1 focus:ring-ring [&::-webkit-details-marker]:hidden">
                        <span class="min-w-0 truncate">{{ $statusLabels[$selectedStatus] ?? 'All statuses' }}</span>
                        <svg class="h-4 w-4 shrink-0 opacity-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                    </summary>
                    <div class="absolute top-full right-0 z-50 mt-1 max-h-72 w-full overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md outline-none">
                        @foreach ($statusLabels as $value => $label)
                            <a data-ticket-filter-link href="{{ route('student.complaints.index', array_filter(['search' => $search, 'category' => $category !== 'All' ? $category : null, 'status' => $value])) }}" class="relative flex w-full items-center rounded-sm py-1.5 pl-2 pr-8 text-xs transition-colors {{ $selectedStatus === $value ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">
                                @if ($selectedStatus === $value)
                                    <x-icons.check class="absolute right-2 h-4 w-4 text-primary" />
                                @endif
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </details>
            </div>
        </form>

        <div data-ticket-results>
        <p class="mb-2 text-xs text-muted-foreground">{{ $complaints->total() }} ticket(s)</p>
        <!-- Tickets List -->
    @if ($complaints->count() > 0)
        <div class="space-y-0.5">
            @foreach ($complaints as $complaint)
                <x-ticket-card :item="$complaint" role="student" :first="$loop->first" :last="$loop->last" />
            @endforeach
        </div>

        <!-- Pagination -->
        @if ($complaints->hasPages())
            <div class="mt-6">
                {{ $complaints->links() }}
            </div>
        @endif
    @else
        <div class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">
            No tickets match this filter.
        </div>
    @endif
        </div>
    </section>
</x-app-layout>
