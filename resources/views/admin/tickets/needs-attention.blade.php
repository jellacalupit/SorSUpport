<x-app-layout :role="'admin'" title="Needs Attention">
    <section class="-mt-1 overflow-hidden rounded-xl border border-border bg-white shadow-sm sm:-mt-2">
        <div class="border-b border-border bg-muted/50 px-4 py-3">
            <h1 class="font-display text-base font-semibold text-foreground">Needs attention</h1>
            <p class="mt-0.5 text-xs text-muted-foreground">Tickets with pending student requests, no recent action under the configured system defaults, or an inactive handler account.</p>
        </div>

        <form method="GET" action="{{ route('admin.tickets.needs-attention') }}" data-ticket-filter-form class="grid gap-2 border-b border-border p-3 sm:grid-cols-[minmax(0,1fr)_12rem_12rem_auto_auto] sm:items-center">
            <label class="sr-only" for="attention-search">Search tickets</label>
            <input id="attention-search" name="search" value="{{ request('search') }}" autocomplete="off" placeholder="Search reference, subject, category or handler" class="h-9 min-w-0 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm outline-none placeholder:text-xs placeholder:text-muted-foreground focus:ring-1 focus:ring-ring" />
            <label class="sr-only" for="attention-status">Filter by status</label>
            <select id="attention-status" name="status_filter" class="h-9 rounded-md border border-input bg-white px-3 text-xs shadow-sm outline-none focus:ring-1 focus:ring-ring">
                <option value="">All statuses</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(request('status_filter') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <label class="sr-only" for="attention-category">Filter by category</label>
            <select id="attention-category" name="category_filter" class="h-9 rounded-md border border-input bg-white px-3 text-xs shadow-sm outline-none focus:ring-1 focus:ring-ring">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) request('category_filter') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="h-9 rounded-full bg-primary px-4 text-xs font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Filter</button>
            <a href="{{ route('admin.tickets.needs-attention') }}" class="inline-flex h-9 items-center justify-center rounded-full border border-border px-4 text-xs font-semibold text-foreground transition-colors hover:bg-muted">Clear</a>
        </form>

        <div class="border-b border-border px-4 py-2 text-xs text-muted-foreground">
            {{ $tickets->total() }} {{ \Illuminate\Support\Str::plural('ticket', $tickets->total()) }} may need attention. This list is informational; SDS decides what action to take.
        </div>

        @include('admin.tickets.partials.needs-attention-list', ['tickets' => $tickets])

        @if ($tickets->hasPages())
            <div class="border-t border-border px-4 py-3">{{ $tickets->links() }}</div>
        @endif
    </section>
</x-app-layout>
