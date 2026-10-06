<x-app-layout :role="'admin'" title="My Tickets">
    <section class="-mt-1 sm:-mt-2">
        <form method="GET" action="{{ route('admin.tickets.my') }}" data-ticket-filter-form class="relative z-20">
            <div class="mb-2 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <div class="w-full sm:w-auto sm:min-w-[280px] sm:flex-1">
                    <div class="relative min-w-0">
                        <x-icons.search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <label for="my-ticket-search" class="sr-only">Search tickets</label>
                        <input id="my-ticket-search" name="search" value="{{ request('search') }}" autocomplete="off" placeholder="Search ticket id, subject title or student name" class="h-9 w-full rounded-md border border-input bg-transparent pl-9 pr-3 text-sm shadow-sm outline-none placeholder:text-xs placeholder:text-muted-foreground focus:ring-1 focus:ring-ring" />
                    </div>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:gap-3">
                    <div class="flex items-center gap-2">
                        <label for="my-classification" class="w-40 shrink-0 text-sm font-medium sm:w-auto">Filter by classification</label>
                        @php
                            $classificationLabels = ['' => 'All', 'needs_resolution' => 'Needs Resolution', 'informational' => 'Informational', 'invalid' => 'Invalid'];
                            $selectedClassification = $classification ?? '';
                        @endphp
                        <details x-data="{}" class="group relative min-w-0 flex-1 sm:w-40 sm:flex-none" x-on:click.outside="$el.removeAttribute('open')">
                            <span aria-hidden="true" class="invisible block h-0 whitespace-nowrap">All</span>
                            <summary id="my-classification" class="flex h-9 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden">
                                <span class="truncate">{{ $classificationLabels[$selectedClassification] ?? 'All' }}</span>
                                <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                            </summary>
                            <div class="absolute top-full z-50 mt-1 w-full rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                @foreach ($classificationLabels as $value => $label)
                                    <a href="{{ route('admin.tickets.my', array_filter(['search' => request('search'), 'classification_filter' => $value, 'status_filter' => request('status_filter'), 'sort' => request('sort')])) }}" class="relative flex w-full items-center whitespace-nowrap rounded-sm py-1.5 pl-2 pr-8 text-xs {{ $selectedClassification === $value ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">
                                        @if ($selectedClassification === $value) <x-icons.check class="absolute right-2 h-4 w-4" /> @endif
                                        {{ $label }}
                                    </a>
                                @endforeach
                            </div>
                        </details>
                    </div>

                    <div class="flex items-center gap-2">
                        <label for="my-status" class="w-40 shrink-0 text-sm font-medium sm:w-auto">Filter by status</label>
                        @php
                            $statusLabels = ['' => 'All', 'in_progress' => 'In Progress', 'escalated' => 'Escalated', 'closed' => 'Closed'];
                            $selectedStatus = $status ?? '';
                        @endphp
                        <details x-data="{}" class="group relative min-w-0 flex-1 sm:w-32 sm:flex-none" x-on:click.outside="$el.removeAttribute('open')">
                            <span aria-hidden="true" class="invisible block h-0 whitespace-nowrap">All</span>
                            <summary id="my-status" class="flex h-9 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden">
                                <span class="truncate">{{ $statusLabels[$selectedStatus] ?? 'All' }}</span>
                                <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                            </summary>
                            <div class="absolute top-full z-50 mt-1 w-full rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                @foreach ($statusLabels as $value => $label)
                                    <a href="{{ route('admin.tickets.my', array_filter(['search' => request('search'), 'classification_filter' => request('classification_filter'), 'status_filter' => $value, 'sort' => request('sort')])) }}" class="relative flex w-full items-center whitespace-nowrap rounded-sm py-1.5 pl-2 pr-8 text-xs {{ $selectedStatus === $value ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">
                                        @if ($selectedStatus === $value) <x-icons.check class="absolute right-2 h-4 w-4" /> @endif
                                        {{ $label }}
                                    </a>
                                @endforeach
                            </div>
                        </details>
                    </div>

                    <div class="flex items-center gap-2">
                        <label for="my-sort" class="w-40 shrink-0 text-sm font-medium sm:w-auto">Sort by</label>
                        @php $sortValue = request('sort', 'newest'); @endphp
                        <details x-data="{}" class="group relative min-w-0 flex-1 sm:w-36 sm:flex-none" x-on:click.outside="$el.removeAttribute('open')">
                            <span aria-hidden="true" class="invisible block h-0 whitespace-nowrap">Newest First</span>
                            <summary id="my-sort" class="flex h-9 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden">
                                <span class="truncate">{{ $sortValue === 'oldest' ? 'Oldest First' : 'Newest First' }}</span>
                                <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                            </summary>
                            <div class="absolute top-full z-50 mt-1 w-full rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                @foreach (['newest' => 'Newest First', 'oldest' => 'Oldest First'] as $sortOption => $sortLabel)
                                    <a href="{{ route('admin.tickets.my', array_filter(['search' => request('search'), 'status_filter' => request('status_filter'), 'sort' => $sortOption])) }}" class="relative flex w-full items-center whitespace-nowrap rounded-sm py-1.5 pl-2 pr-8 text-xs {{ $sortValue === $sortOption ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">
                                        @if ($sortValue === $sortOption) <x-icons.check class="absolute right-2 h-4 w-4" /> @endif
                                        {{ $sortLabel }}
                                    </a>
                                @endforeach
                            </div>
                        </details>
                    </div>
                </div>
            </div>
        </form>

        <div data-ticket-results>
        <p class="mb-3 text-xs text-muted-foreground">{{ $tickets->total() }} ticket(s)</p>

        @if ($tickets->count() > 0)
            {{-- Phones: the same tappable cards students and recipients get. --}}
            <ul class="grid grid-cols-1 gap-0.5 md:hidden">
                @foreach ($tickets as $ticket)
                    @php $isUnread = app(\App\Services\TicketUnreadService::class)->unreadCountForTicket(Auth::user(), $ticket) > 0; @endphp
                    <li data-my-ticket-row data-read-url="{{ route('admin.tickets.read', $ticket) }}" data-unread="{{ $isUnread ? 'true' : 'false' }}">
                        <x-ticket-card :item="$ticket" role="admin" :first="$loop->first" :last="$loop->last" data-my-ticket-link="ticket-{{ $ticket->id }}">
                            Filed by {{ $ticket->complaint?->is_anonymous ? 'Anonymous' : ($ticket->complaint?->student?->user?->table_name ?? 'Anonymous') }}
                            @if ($ticket->escalated_at)
                                <span class="font-semibold text-red-700">· Escalated {{ $ticket->escalated_at->copy()->setTimezone('Asia/Manila')->format('M d') }}</span>
                            @endif
                        </x-ticket-card>
                    </li>
                @endforeach
            </ul>

            <div class="hidden w-full rounded-lg border md:block">
                <table class="w-full table-auto text-xs">
                    <thead class="border-b bg-primary text-white">
                        <tr class="text-left">
                            <th class="whitespace-nowrap rounded-tl-lg px-2 py-2 font-semibold sm:px-3">Ticket ID</th>
                            <th class="w-[20%] px-2 py-2 font-semibold sm:px-3">Subject Title</th>
                            <th class="hidden px-2 py-2 font-semibold sm:px-3 lg:table-cell">Classification</th>
                            <th class="px-2 py-2 font-semibold sm:px-3">Status</th>
                            <th class="hidden whitespace-nowrap px-2 py-2 font-semibold sm:px-3 md:table-cell">Escalated</th>
                            <th class="hidden px-2 py-2 font-semibold sm:px-3 xl:table-cell">Filed by</th>
                            <th class="hidden whitespace-nowrap px-2 py-2 font-semibold sm:px-3 xl:table-cell">Date Submitted</th>
                            <th class="hidden w-[10%] whitespace-nowrap rounded-tr-lg px-2 py-2 font-semibold sm:px-3 xl:table-cell">Last Updated</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($tickets as $ticket)
                            @php
                                $student = $ticket->complaint?->student;
                                $studentUser = $student?->user;
                                $studentTableName = $ticket->complaint?->is_anonymous ? 'Anonymous' : ($studentUser?->table_name ?? 'Anon');
                                $statusDisplay = match ($ticket->status) {
                                    'assigned', 'in_progress' => 'In Progress',
                                    'resolved' => 'Resolved',
                                    'escalated' => 'Escalated',
                                    'rejected', 'closed' => 'Closed',
                                    default => ucfirst(str_replace('_', ' ', $ticket->status)),
                                };
                                $classification = $ticket->classification ?? ($ticket->complaint?->is_anonymous ? 'anonymous' : null);
                                $classificationLabel = $classification ? ucwords(str_replace('_', ' ', $classification)) : '—';
                                $classificationColor = match ($classification) {
                                    'needs_resolution' => 'text-[#800000]',
                                    'informational' => 'text-black',
                                    'anonymous' => 'text-gray-500',
                                    'invalid' => 'text-red-600',
                                    default => 'text-muted-foreground',
                                };
                                $isUnread = app(\App\Services\TicketUnreadService::class)->unreadCountForTicket(Auth::user(), $ticket) > 0;
                                $studentCourseYearBlock = trim(($student?->course ?? 'Course not specified') . ' ' . ($student?->year_level ?? 'N/A') . (($student?->block ?? '') !== '' ? '-' . $student->block : ''));
                            @endphp
                            <tr data-my-ticket-row data-read-url="{{ route('admin.tickets.read', $ticket) }}" data-unread="{{ $isUnread ? 'true' : 'false' }}" class="align-top transition-colors hover:bg-primary-soft {{ $isUnread ? 'bg-primary-soft/70' : '' }}">
                                <td class="whitespace-nowrap px-2 py-2 font-mono font-semibold text-primary sm:px-3">
                                    <a href="#" data-my-ticket-link="ticket-{{ $ticket->id }}" class="hover:underline">{{ $ticket->complaint?->reference_number ?? $ticket->id }}</a>
                                </td>
                                <td class="px-2 py-2 sm:px-3 {{ $isUnread ? 'text-black' : 'text-muted-foreground' }}">
                                    <a href="#" data-my-ticket-link="ticket-{{ $ticket->id }}" class="block truncate hover:text-primary hover:underline">{{ $ticket->complaint?->subject_title ?? 'Untitled' }}</a>
                                </td>
                                <td class="hidden px-2 py-2 sm:px-3 lg:table-cell"><x-classification-badge :classification="$classification" class="text-[10px]" /></td>
                                <td class="px-2 py-2 sm:px-3"><x-status-badge :status="$statusDisplay" :classification="$ticket->classification" :show-icon="false" class="px-2 py-0.5 text-[11px]" /></td>
                                <td class="hidden whitespace-nowrap px-2 py-2 sm:px-3 md:table-cell {{ $ticket->escalated_at ? 'font-semibold text-red-700' : 'text-muted-foreground' }}">{{ $ticket->escalated_at?->copy()->setTimezone('Asia/Manila')->format('M d, Y') ?? '—' }}</td>
                                <td class="hidden px-2 py-2 sm:px-3 xl:table-cell {{ $isUnread ? 'text-black' : 'text-muted-foreground' }}">
                                    <span class="relative inline-block" x-data="{ studentProfileOpen: false }" x-on:mouseenter="studentProfileOpen = true" x-on:mouseleave="studentProfileOpen = false">
                                        <span class="{{ $ticket->complaint?->is_anonymous ? '' : 'cursor-pointer hover:text-primary hover:underline' }}">{{ $studentTableName }}</span>
                                        @if ($studentUser && ! $ticket->complaint?->is_anonymous)
                                            <span x-show="studentProfileOpen" x-cloak data-keep-in-view class="brand-gradient absolute top-6 left-0 z-30 w-72 max-w-[calc(100vw-2rem)] rounded-xl p-4 text-left text-primary-foreground shadow-lg">
                                                <span class="flex items-center gap-3">
                                                    <span class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-foreground/15 text-sm font-bold ring-2 ring-primary-foreground/30">
                                                        @if ($studentUser->avatar_path)
                                                            <img src="{{ asset('storage/' . $studentUser->avatar_path) }}" alt="{{ $studentTableName }}" class="h-full w-full object-cover">
                                                        @else
                                                            {{ $studentUser->name_initials }}
                                                        @endif
                                                    </span>
                                                    <span class="min-w-0">
                                                        <span class="block wrap-break-word text-sm font-bold">{{ $studentTableName }}</span>
                                                        <span class="mt-1 block wrap-break-word text-[11px] leading-relaxed opacity-95">ID {{ $student?->student_id ?? 'N/A' }} · {{ $student?->department ?? 'Department not specified' }} · {{ $studentCourseYearBlock }}</span>
                                                    </span>
                                                </span>
                                                <span class="mt-3 inline-flex rounded-full border border-primary-foreground/40 bg-primary-foreground/15 px-3 py-1 text-[10px] font-semibold">Student</span>
                                            </span>
                                        @endif
                                    </span>
                                </td>
                                <td class="hidden whitespace-nowrap px-2 py-2 sm:px-3 xl:table-cell {{ $isUnread ? 'text-black' : 'text-muted-foreground' }}">{{ $ticket->complaint?->created_at?->copy()->setTimezone('Asia/Manila')->format('m/d/y h:i A') ?? 'Unknown' }}</td>
                                <td class="hidden whitespace-nowrap px-2 py-2 sm:px-3 xl:table-cell {{ $isUnread ? 'text-black' : 'text-muted-foreground' }}">{{ $ticket->updated_at?->copy()->setTimezone('Asia/Manila')->format('m/d/y h:i A') ?? 'Unknown' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">No tickets are currently assigned to you.</p>
        @endif
        @if ($tickets->hasPages())
            <div class="mt-6">{{ $tickets->links() }}</div>
        @endif
        </div>

        <div id="my-ticket-drawer" class="pointer-events-none invisible fixed inset-0 z-50" aria-hidden="true">
            <div data-my-ticket-backdrop class="absolute inset-0 bg-black/35 opacity-0 transition-opacity"></div>
            <aside data-my-ticket-panel class="absolute top-0 right-0 flex h-full w-full max-w-full translate-x-full flex-col overflow-y-auto bg-background shadow-2xl transition-transform duration-200 lg:max-w-[72vw] xl:max-w-[58vw]" role="dialog" aria-modal="true" aria-label="Ticket details">
                <div class="sticky top-0 z-10 flex items-start justify-between border-b border-border bg-card px-4 py-3">
                    <div>
                        <h2 class="font-display text-lg font-bold text-primary">Ticket Details</h2>
                    </div>
                    <button type="button" data-my-ticket-close class="grid h-8 w-8 shrink-0 place-items-center rounded-md text-xl leading-none text-muted-foreground hover:bg-muted hover:text-primary" aria-label="Close ticket details">&times;</button>
                </div>
                <div data-my-ticket-body class="p-4"></div>
            </aside>
        </div>

        @foreach ($tickets as $ticket)
            <template id="ticket-{{ $ticket->id }}">
                <x-admin-ticket-view :ticket="$ticket" />
            </template>
        @endforeach

        <script>
            (() => {
                const drawer = document.getElementById('my-ticket-drawer');
                if (!drawer) {
                    console.error('Drawer not found');
                    return;
                }
                if (drawer.dataset.ready) return;
                drawer.dataset.ready = 'true';

                const panel = drawer.querySelector('[data-my-ticket-panel]');
                const body = drawer.querySelector('[data-my-ticket-body]');
                const backdrop = drawer.querySelector('[data-my-ticket-backdrop]');
                const closeButton = drawer.querySelector('[data-my-ticket-close]');

                console.log('Drawer initialized:', { drawer, panel, body, backdrop, closeButton });

                function openTicket(ticketId) {
                    console.log('Opening ticket:', ticketId);
                    const template = document.getElementById(ticketId);
                    console.log('Template found:', !!template);
                    if (!template) {
                        console.error('Template not found for:', ticketId);
                        return;
                    }

                    body.innerHTML = '';
                    const content = template.content.cloneNode(true);
                    body.appendChild(content);

                    console.log('Before opening - drawer classes:', drawer.className);
                    drawer.classList.remove('pointer-events-none', 'invisible');
                    drawer.setAttribute('aria-hidden', 'false');
                    backdrop.classList.add('opacity-100');
                    backdrop.classList.remove('opacity-0');
                    panel.classList.add('translate-x-0');
                    panel.classList.remove('translate-x-full');
                    window.requestAnimationFrame(() => window.requestAnimationFrame(() => {
                        const messageList = body.querySelector('[data-ticket-message-list]');
                        if (messageList) messageList.scrollTop = messageList.scrollHeight;
                    }));
                    console.log('After opening - drawer classes:', drawer.className);
                }

                function closeTicket() {
                    drawer.classList.add('pointer-events-none', 'invisible');
                    drawer.setAttribute('aria-hidden', 'true');
                    backdrop.classList.remove('opacity-100');
                    backdrop.classList.add('opacity-0');
                    panel.classList.remove('translate-x-0');
                    panel.classList.add('translate-x-full');
                }

                closeButton.addEventListener('click', closeTicket);
                backdrop.addEventListener('click', closeTicket);

                document.addEventListener('click', (event) => {
                    const link = event.target.closest('[data-my-ticket-link]');
                    if (link) {
                        console.log('Ticket link clicked:', link);
                        event.preventDefault();
                        const ticketId = link.getAttribute('data-my-ticket-link');
                        const row = link.closest('[data-my-ticket-row]');
                        if (row?.dataset.unread === 'true') {
                            row.dataset.unread = 'false';
                            row.classList.remove('bg-primary-soft/70');
                            row.querySelector('[data-unread-badge]')?.remove();
                            row.querySelector('a.surface')?.classList.replace('bg-primary-soft', 'bg-card');
                            row.querySelector('td:first-child')?.classList.remove('font-bold');
                            row.querySelector('td:nth-child(3)')?.classList.remove('font-bold');
                            [2, 6, 7, 8].forEach((column) => {
                                const cell = row.querySelector(`td:nth-child(${column})`);
                                cell?.classList.remove('text-black');
                                cell?.classList.add('text-muted-foreground');
                            });
                        }
                        if (row?.dataset.readUrl) {
                            fetch(row.dataset.readUrl, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            }).catch(() => {});
                        }
                        openTicket(ticketId);
                    }
                });
            })();
        </script>
    </section>
</x-app-layout>
