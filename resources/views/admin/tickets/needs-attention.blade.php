<x-app-layout :role="'admin'" title="Needs Attention">
    <section class="-mt-1 sm:-mt-2">
        <p class="mb-3 text-xs text-muted-foreground">This list highlights tickets with pending student requests, no recent action under the configured system defaults, or an inactive handler account.</p>

        <form method="GET" action="{{ route('admin.tickets.needs-attention') }}" data-ticket-filter-form class="relative z-20 mb-2">
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <div class="w-full sm:w-auto sm:min-w-[280px] sm:flex-1">
                    <div class="relative min-w-0">
                        <x-icons.search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <label for="attention-search" class="sr-only">Search tickets</label>
                        <input id="attention-search" name="search" value="{{ request('search') }}" autocomplete="off" placeholder="Search ticket ID, subject, category or handler" class="h-9 w-full rounded-md border border-input bg-transparent pl-9 pr-3 text-sm shadow-sm outline-none placeholder:text-xs placeholder:text-muted-foreground focus:ring-1 focus:ring-ring" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-row sm:flex-wrap sm:items-center sm:gap-3">
                    <div class="min-w-0 sm:flex sm:items-center sm:gap-2">
                        <label for="attention-status" class="hidden shrink-0 text-sm font-medium sm:block">Filter by status</label>
                        @php $selectedStatus = (string) request('status_filter', ''); @endphp
                        <details x-data="{}" class="group relative w-full sm:w-36 sm:shrink-0" x-on:click.outside="$el.removeAttribute('open')">
                            <span aria-hidden="true" class="invisible hidden h-0 whitespace-nowrap sm:block">All statuses</span>
                            <summary id="attention-status" class="flex h-9 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden">
                                <span class="truncate">{{ $selectedStatus === '' ? 'All statuses' : ($statuses[$selectedStatus] ?? 'All statuses') }}</span>
                                <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                            </summary>
                            <div class="absolute top-full z-50 mt-1 w-full rounded-md border bg-popover p-1 text-popover-foreground shadow-md max-sm:min-w-max">
                                <a href="{{ route('admin.tickets.needs-attention', array_filter(['search' => request('search'), 'status_filter' => '', 'category_filter' => request('category_filter')])) }}" class="relative flex w-full items-center whitespace-nowrap rounded-sm py-1.5 px-2 text-xs {{ $selectedStatus === '' ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">All statuses</a>
                                @foreach ($statuses as $value => $label)
                                    <a href="{{ route('admin.tickets.needs-attention', array_filter(['search' => request('search'), 'status_filter' => $value, 'category_filter' => request('category_filter')])) }}" class="relative flex w-full items-center whitespace-nowrap rounded-sm py-1.5 px-2 text-xs {{ $selectedStatus === (string) $value ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">{{ $label }}</a>
                                @endforeach
                            </div>
                        </details>
                    </div>

                    <div class="min-w-0 sm:flex sm:items-center sm:gap-2">
                        <label for="attention-category" class="hidden shrink-0 text-sm font-medium sm:block">Filter by category</label>
                        @php
                            $selectedCategoryId = (string) request('category_filter', '');
                            $selectedCategory = $categories->first(fn ($category) => (string) $category->id === $selectedCategoryId);
                            $longestCategory = $categories->sortByDesc(fn ($category) => strlen($category->name))->first()?->name ?? 'All categories';
                        @endphp
                        <details x-data="{}" class="group relative w-full sm:w-56 sm:shrink-0" x-on:click.outside="$el.removeAttribute('open')">
                            <span aria-hidden="true" class="invisible hidden h-0 whitespace-nowrap sm:block">{{ $longestCategory }}</span>
                            <summary id="attention-category" class="flex h-9 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden">
                                <span class="truncate">{{ $selectedCategory?->name ?? 'All categories' }}</span>
                                <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                            </summary>
                            <div class="absolute top-full z-50 mt-1 max-h-72 w-full overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                <a href="{{ route('admin.tickets.needs-attention', array_filter(['search' => request('search'), 'status_filter' => request('status_filter'), 'category_filter' => ''])) }}" class="relative flex w-full items-center rounded-sm py-1.5 px-2 text-xs {{ $selectedCategoryId === '' ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">All categories</a>
                                @foreach ($categories as $category)
                                    <a href="{{ route('admin.tickets.needs-attention', array_filter(['search' => request('search'), 'status_filter' => request('status_filter'), 'category_filter' => $category->id])) }}" class="relative flex w-full items-center rounded-sm py-1.5 px-2 text-xs {{ $selectedCategoryId === (string) $category->id ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">
                                        <span class="whitespace-normal break-words">{{ $category->name }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </details>
                    </div>
                </div>
            </div>
        </form>

        <div data-ticket-results>
            <p class="mb-2 text-xs text-muted-foreground">{{ $tickets->total() }} {{ \Illuminate\Support\Str::plural('ticket', $tickets->total()) }}</p>

            @if ($tickets->isEmpty())
                <p class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">No tickets match these filters.</p>
            @else
                <div class="w-full overflow-x-auto rounded-lg border">
                    <table class="w-full min-w-[700px] table-auto text-xs">
                        <thead class="border-b bg-primary text-white">
                            <tr class="text-left">
                                <th class="whitespace-nowrap rounded-tl-lg px-2 py-2 font-semibold sm:px-3">Ticket ID</th>
                                <th class="w-[20%] px-2 py-2 font-semibold sm:px-3">Subject</th>
                                <th class="hidden px-2 py-2 font-semibold sm:px-3 lg:table-cell">Category</th>
                                <th class="px-2 py-2 font-semibold sm:px-3">Status</th>
                                <th class="hidden px-2 py-2 font-semibold sm:px-3 md:table-cell">Filed by</th>
                                <th class="hidden px-2 py-2 font-semibold sm:px-3 xl:table-cell">Handler</th>
                                <th class="hidden whitespace-nowrap px-2 py-2 font-semibold sm:px-3 xl:table-cell">Age</th>
                                <th class="hidden whitespace-nowrap rounded-tr-lg px-2 py-2 font-semibold sm:px-3 lg:table-cell">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($tickets as $row)
                                @php
                                    $ticket = $row['ticket'];
                                    $complaint = $ticket->complaint;
                                    $handler = $ticket->currentHandler ?? $ticket->assignee;
                                    $handlerName = $handler?->isSdsAdmin() ? 'SDS Office' : ($handler?->display_name ?? 'SDS Office');
                                @endphp
                                <tr class="align-top transition-colors hover:bg-primary-soft">
                                    <td class="whitespace-nowrap px-2 py-2 font-mono font-semibold text-primary sm:px-3">
                                        <a href="{{ route('admin.complaints.show', $complaint) }}" data-follow-up-ticket-link="follow-up-ticket-{{ $ticket->id }}" class="hover:underline">{{ $complaint?->reference_number ?? $ticket->id }}</a>
                                    </td>
                                    <td class="px-2 py-2 sm:px-3">
                                        <a href="{{ route('admin.complaints.show', $complaint) }}" data-follow-up-ticket-link="follow-up-ticket-{{ $ticket->id }}" class="block truncate font-medium text-foreground hover:text-primary hover:underline">{{ $complaint?->public_subject ?? 'Untitled' }}</a>
                                    </td>
                                    <td class="hidden px-2 py-2 text-muted-foreground sm:px-3 lg:table-cell">{{ $complaint?->category?->name ?? 'Uncategorized' }}</td>
                                    <td class="px-2 py-2 sm:px-3">@if ($ticket->displayStatus())<x-status-badge :status="$ticket->displayStatus()" :show-icon="false" class="px-2 py-0.5 text-[11px]" />@else<span class="text-muted-foreground">—</span>@endif</td>
                                    <td class="hidden px-2 py-2 text-muted-foreground sm:px-3 md:table-cell">{{ $complaint?->filerNameFor(Auth::user()) ?? 'Unknown' }}</td>
                                    <td class="hidden px-2 py-2 text-muted-foreground sm:px-3 xl:table-cell">{{ $handlerName }}</td>
                                    <td class="hidden whitespace-nowrap px-2 py-2 text-muted-foreground sm:px-3 xl:table-cell">{{ $row['age'] }} working {{ \Illuminate\Support\Str::plural('day', $row['age']) }}</td>
                                    <td class="hidden px-2 py-2 sm:px-3 lg:table-cell">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($row['reasons'] as $reason)
                                                <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-900">{{ $reason }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($tickets->hasPages())
                <div class="mt-5">{{ $tickets->links() }}</div>
            @endif
        <div id="follow-up-ticket-drawer" data-follow-up-ticket-drawer class="pointer-events-none invisible fixed inset-0 z-50" aria-hidden="true">
            <div data-follow-up-backdrop class="absolute inset-0 bg-black/35 opacity-0 transition-opacity"></div>
            <aside data-follow-up-panel class="absolute top-0 right-0 flex h-full w-full max-w-full translate-x-full flex-col overflow-y-auto bg-background shadow-2xl transition-transform duration-200 sm:max-w-[60vw]" role="dialog" aria-modal="true" aria-label="Ticket details">
                <div class="sticky top-0 z-10 flex items-start justify-between border-b border-border bg-card px-4 py-3">
                    <h2 class="font-display text-lg font-bold text-primary">Ticket Details</h2>
                    <button type="button" data-follow-up-close class="grid h-8 w-8 shrink-0 place-items-center rounded-md text-xl leading-none text-muted-foreground hover:bg-muted hover:text-primary" aria-label="Close ticket details">&times;</button>
                </div>
                <div data-follow-up-body class="p-4"></div>
            </aside>
        </div>

        @foreach ($tickets as $row)
            @php
                $ticket = $row['ticket'];
                $auditLogs = $ticket->auditLogs()
                    ->with('performer')
                    ->orderByDesc('created_at')
                    ->limit(5)
                    ->get()
                    ->sortByDesc(fn ($log) => $log->created_at?->timestamp ?? 0)
                    ->values();
            @endphp
            <template id="follow-up-ticket-{{ $ticket->id }}">
                <div class="grid items-start gap-4 sm:grid-cols-[1.35fr_1fr]">
                    <div class="min-w-0">
                        <x-ticket-info-panel :ticket="$ticket" :show-closed-at="true" />
                        <x-ticket-actions :ticket="$ticket" role="admin" class="mt-3" />
                    </div>
                    <div class="flex flex-col gap-4 overflow-hidden sm:h-[calc(100vh-6rem)]">
                        <div class="admin-ticket-thread flex min-h-0 flex-1 flex-col">
                            <x-ticket-thread :ticket="$ticket" viewerRole="admin" />
                        </div>
                        <div class="h-[15.5rem] shrink-0 rounded-lg border border-border bg-card p-3">
                            <h3 class="font-display text-sm font-semibold leading-tight text-foreground">Audit Activity</h3>
                            <ol class="mt-1.5 space-y-2 overflow-hidden">
                                @forelse ($auditLogs as $log)
                                    @php
                                        $activityLabel = match ($log->action) {
                                            'ticket_assigned' => 'Ticket assigned',
                                            'ticket_started' => 'Ticket started',
                                            'message_posted' => 'Message posted',
                                            'ticket_resolved' => 'Ticket resolved',
                                            'ticket_escalated' => 'Ticket escalated',
                                            'ticket_classified' => 'Ticket classified',
                                            'ticket_closed' => 'Ticket closed',
                                            'status_updated' => 'Status updated',
                                            'classification_changed' => 'Classification changed',
                                            default => ucfirst(str_replace('_', ' ', (string) ($log->action ?? 'Action'))),
                                        };
                                    @endphp
                                    <li class="flex min-w-0 gap-2">
                                        <span class="mt-0.5 h-8 w-0.5 shrink-0 rounded-full bg-primary"></span>
                                        <div class="mt-0.5 min-w-0 flex-1">
                                            <p class="truncate text-xs font-semibold leading-tight text-foreground">{{ $activityLabel }}</p>
                                            <p class="mt-0.5 truncate text-[11px] leading-snug text-muted-foreground">
                                                {{ $log->display_performer?->table_name ?? 'System' }} · {{ $log->created_at?->copy()->setTimezone('Asia/Manila')->format('M d, Y · g:i A') }}
                                            </p>
                                        </div>
                                    </li>
                                @empty
                                    <li class="py-4 text-center text-xs text-muted-foreground">No recent audit activity.</li>
                                @endforelse
                            </ol>
                        </div>
                    </div>
                </div>
            </template>
        @endforeach
        </div>

        <script>
            (() => {
                if (document.documentElement.dataset.followUpDrawerReady) return;
                document.documentElement.dataset.followUpDrawerReady = 'true';

                function closeTicket() {
                    const drawer = document.getElementById('follow-up-ticket-drawer');
                    if (!drawer) return;

                    const panel = drawer.querySelector('[data-follow-up-panel]');
                    const backdrop = drawer.querySelector('[data-follow-up-backdrop]');
                    drawer.classList.add('pointer-events-none', 'invisible');
                    drawer.setAttribute('aria-hidden', 'true');
                    backdrop.classList.remove('opacity-100');
                    backdrop.classList.add('opacity-0');
                    panel.classList.remove('translate-x-0');
                    panel.classList.add('translate-x-full');
                }

                function openTicket(ticketId) {
                    const drawer = document.getElementById('follow-up-ticket-drawer');
                    const template = document.getElementById(ticketId);
                    if (!drawer || !template) {
                        console.error(`Ticket details template not found: ${ticketId}`);
                        return;
                    }

                    const panel = drawer.querySelector('[data-follow-up-panel]');
                    const body = drawer.querySelector('[data-follow-up-body]');
                    const backdrop = drawer.querySelector('[data-follow-up-backdrop]');
                    body.replaceChildren(template.content.cloneNode(true));
                    drawer.classList.remove('pointer-events-none', 'invisible');
                    drawer.setAttribute('aria-hidden', 'false');
                    backdrop.classList.add('opacity-100');
                    backdrop.classList.remove('opacity-0');
                    panel.classList.add('translate-x-0');
                    panel.classList.remove('translate-x-full');
                    window.Alpine?.initTree(body);
                    window.requestAnimationFrame(() => window.requestAnimationFrame(() => {
                        const messageList = body.querySelector('[data-ticket-message-list]');
                        if (messageList) messageList.scrollTop = messageList.scrollHeight;
                    }));
                }

                document.addEventListener('click', (event) => {
                    if (event.target.closest('[data-follow-up-close], [data-follow-up-backdrop]')) {
                        closeTicket();
                        return;
                    }

                    const link = event.target.closest('[data-follow-up-ticket-link]');
                    if (!link || window.innerWidth < 1024 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

                    event.preventDefault();
                    openTicket(link.getAttribute('data-follow-up-ticket-link'));
                });
            })();
        </script>
    </section>
</x-app-layout>
