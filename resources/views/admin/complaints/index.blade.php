<x-app-layout :role="'admin'" title="All Tickets">
    <div class="-mt-1 sm:-mt-2">
        <form method="GET" action="{{ route('admin.complaints.index') }}" data-ticket-filter-form class="relative z-20">
            @php
                $selectedCategory = $categories->firstWhere('id', request('category_filter'));
                $query = fn (array $values) => route('admin.complaints.index', array_filter($values, fn ($value) => $value !== null && $value !== ''));
            @endphp
            <div class="mb-4 grid gap-2">
                <div class="flex items-center gap-2">
                    <div class="relative min-w-0 flex-1">
                        <x-icons.search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <input id="f-search" name="search" value="{{ request('search') }}" autocomplete="off" placeholder="Search ticket id, subject title, recipient or student" class="h-9 w-full rounded-md border border-input bg-transparent pl-9 pr-3 text-xs shadow-sm outline-none placeholder:text-muted-foreground focus:ring-1 focus:ring-ring" />
                    </div>
                    <a data-reset-filters href="{{ route('admin.complaints.index') }}" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-primary text-primary-foreground transition-colors hover:bg-primary/90" aria-label="Refresh all tickets" title="Refresh all tickets">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 12a9 9 0 1 1-2.64-6.36L21 8" />
                                <path d="M21 3v5h-5" />
                            </svg>
                        </a>
                </div>
                <div class="grid grid-cols-2 gap-2 lg:grid-cols-4">
                    <details x-data="{}" class="group relative min-w-0 w-full" x-on:click.outside="$el.removeAttribute('open')">
                                <summary id="f-category" class="flex h-9 cursor-pointer list-none items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden">
                                    <span class="truncate">{{ $selectedCategory?->name ?? 'All categories' }}</span><svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                                </summary>
                                <div class="absolute top-full z-50 mt-1 w-full max-h-72 overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                    <a href="{{ $query(array_merge(request()->only(['search','status_filter','classification_filter','filed_from','filed_to','sort']), ['category_filter' => ''])) }}" class="relative flex w-full items-center rounded-sm py-1.5 pl-2 pr-8 text-xs {{ !request('category_filter') ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">@if (!request('category_filter'))<x-icons.check class="absolute right-2 h-4 w-4" />@endif All categories</a>
                                    @foreach ($categories as $category)
                                        <a href="{{ $query(array_merge(request()->only(['search','status_filter','classification_filter','filed_from','filed_to','sort']), ['category_filter' => $category->id])) }}" class="relative flex w-full items-center rounded-sm py-1.5 pl-2 pr-8 text-xs {{ (string) request('category_filter') === (string) $category->id ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">@if ((string) request('category_filter') === (string) $category->id)<x-icons.check class="absolute right-2 h-4 w-4" />@endif<span class="whitespace-normal break-words">{{ $category->name }}</span></a>
                                    @endforeach
                                </div>
                            </details>
                    <details x-data="{}" class="group relative min-w-0 w-full" x-on:click.outside="$el.removeAttribute('open')">
                            <summary id="f-sort" class="flex h-9 cursor-pointer list-none items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden">
                                <span class="truncate">{{ request('sort', 'latest_update') === 'latest_update' ? 'Latest Update' : (request('sort') === 'oldest_update' ? 'Oldest Update' : (request('sort') === 'latest_submitted' ? 'Latest Submitted' : 'Oldest Submitted')) }}</span><svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                            </summary>
                            <div class="absolute top-full z-50 mt-1 w-full min-w-max rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                @foreach (['latest_update' => 'Latest Update', 'oldest_update' => 'Oldest Update', 'latest_submitted' => 'Latest Submitted', 'oldest_submitted' => 'Oldest Submitted'] as $value => $label)
                                    <a href="{{ $query(array_merge(request()->only(['search','category_filter','classification_filter','status_filter','filed_from','filed_to']), ['sort' => $value])) }}" class="relative flex w-full items-center rounded-sm py-1.5 pl-2 pr-8 text-xs {{ request('sort', 'latest_update') === $value ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">@if ((request('sort', 'latest_update') === $value))<x-icons.check class="absolute right-2 h-4 w-4" />@endif{{ $label }}</a>
                                @endforeach
                            </div>
                        </details>
                    <details x-data="{}" class="group relative min-w-0 w-full" x-on:click.outside="$el.removeAttribute('open')">
                                <summary id="f-classification" class="flex h-9 cursor-pointer list-none items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden">
                                    <span class="truncate">{{ request('classification_filter') ? ucfirst(str_replace('_', ' ', request('classification_filter'))) : 'All classifications' }}</span><svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                                </summary>
                                <div class="absolute top-full z-50 mt-1 w-full min-w-max rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                    @foreach (['' => 'All classifications', 'needs_resolution' => 'Needs Resolution', 'informational' => 'Informational', 'invalid' => 'Invalid'] as $value => $label)
                                        <a href="{{ $query(array_merge(request()->only(['search','category_filter','status_filter','filed_from','filed_to','sort']), ['classification_filter' => $value])) }}" class="relative flex w-full items-center rounded-sm py-1.5 pl-2 pr-8 text-xs {{ request('classification_filter', '') === $value ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">@if (request('classification_filter', '') === $value)<x-icons.check class="absolute right-2 h-4 w-4" />@endif{{ $label }}</a>
                                    @endforeach
                                </div>
                            </details>
                    <details x-data="{}" class="group relative min-w-0 w-full" x-on:click.outside="$el.removeAttribute('open')">
                                <summary id="f-status" class="flex h-9 cursor-pointer list-none items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden">
                                    <span class="truncate">{{ request('status_filter') ? ucfirst(str_replace('_', ' ', request('status_filter'))) : 'All statuses' }}</span><svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                                </summary>
                                <div class="absolute top-full z-50 mt-1 w-full min-w-max rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                    @foreach (['' => 'All statuses', 'pending' => 'Pending', 'assigned' => 'Assigned', 'in_progress' => 'In Progress', 'escalated' => 'Escalated', 'resolved' => 'Resolved', 'closed' => 'Closed'] as $value => $label)
                                        <a href="{{ $query(array_merge(request()->only(['search','category_filter','classification_filter','filed_from','filed_to','sort']), ['status_filter' => $value])) }}" class="relative flex w-full items-center rounded-sm py-1.5 pl-2 pr-8 text-xs {{ request('status_filter', '') === $value ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">@if (request('status_filter', '') === $value)<x-icons.check class="absolute right-2 h-4 w-4" />@endif{{ $label }}</a>
                                    @endforeach
                                </div>
                            </details>
                </div>
                <div class="flex items-center gap-2">
                    <label for="f-from" class="shrink-0 text-xs font-medium sm:text-sm">Filed from</label>
                    <input id="f-from" type="date" name="filed_from" value="{{ request('filed_from') }}" lang="en-US" class="h-9 min-w-0 flex-1 rounded-md border border-input bg-transparent px-2 text-xs focus:ring-1 focus:ring-ring sm:w-40 sm:flex-none" />
                    <label for="f-to" class="shrink-0 text-xs font-medium sm:text-sm">to</label>
                    <input id="f-to" type="date" name="filed_to" value="{{ request('filed_to') }}" lang="en-US" class="h-9 min-w-0 flex-1 rounded-md border border-input bg-transparent px-2 text-xs focus:ring-1 focus:ring-ring sm:w-40 sm:flex-none" />
                </div>
            </div>
        </form>

        @php $pendingAdmin = \App\Models\User::query()->where('role', \App\Models\User::ROLE_SDS_ADMIN)->orderBy('id')->first(); @endphp
        <div data-ticket-results>
        <p class="mb-3 mt-5 text-xs text-muted-foreground">{{ $complaints->total() }} ticket(s) shown</p>
        {{-- Below wide-desktop width: the same tappable cards students and recipients get. --}}
        <ul class="grid grid-cols-1 gap-0.5 xl:hidden">
            @forelse ($complaints as $complaint)
                <li>
                    <x-ticket-card :item="$complaint->ticket ?? $complaint" role="admin" :first="$loop->first" :last="$loop->last">
                        Filed by {{ $complaint->is_anonymous ? 'Anonymous' : ($complaint->student?->user?->table_name ?? 'Unknown') }}
                        @if ($complaint->ticket && $complaint->ticket->status !== 'pending' && ($complaint->ticket->currentHandler ?? $complaint->ticket->assignee))
                            <span>· Held by {{ ($complaint->ticket->currentHandler ?? $complaint->ticket->assignee)->table_name }}</span>
                        @endif
                    </x-ticket-card>
                </li>
            @empty
                <li class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">No tickets match these filters.</li>
            @endforelse
        </ul>

        <div class="hidden overflow-visible rounded-lg border xl:block">
            <table class="w-full table-fixed text-[11px]">
                <colgroup>
                    <col style="width: 9.5%;">
                    <col style="width: 18.5%;">
                    <col style="width: 7%;">
                    <col style="width: 10.5%;">
                    <col style="width: 7%;">
                    <col style="width: 6%;">
                    <col style="width: 13%;">
                    <col style="width: 9%;">
                    <col style="width: 8%;">
                    <col style="width: 9.5%;">
                </colgroup>
                <thead class="border-b bg-primary text-white">
                    <tr class="text-left">
                        <th class="whitespace-nowrap rounded-tl-lg px-2 py-2 font-semibold" style="width: 7.5%;">Ticket ID</th>
                        <th class="px-2 py-2 font-semibold" style="width: 20.5%;">Subject Title</th>
                        <th class="py-2 pl-1.5 pr-2 font-semibold" style="width: 13%;">Category</th>
                        <th class="px-2 py-2 font-semibold" style="width: 9%;">Classification</th>
                        <th class="py-2 pl-1.5 pr-2 font-semibold" style="width: 7%;">Status</th>
                        <th class="px-2 py-2 font-semibold" style="width: 6%;">Escalated</th>
                        <th class="px-2 py-2 font-semibold" style="width: 7%;">Current Holder</th>
                        <th class="px-2 py-2 font-semibold" style="width: 10.5%;">Filed By</th>
                        <th class="px-2 py-2 font-semibold" style="width: 8%;">Date Submitted</th>
                        <th class="whitespace-nowrap rounded-tr-lg px-2 py-2 font-semibold" style="width: 9.5%;">Last Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($complaints as $complaint)
                        @php
                            $ticket = $complaint->ticket;
                            $holderUser = $ticket?->status === 'pending'
                                ? ($pendingAdmin ?? $ticket?->currentHandler)
                                : ($ticket?->currentHandler ?? $ticket?->assignee);
                            $holderRecipient = $holderUser?->recipient;
                            $currentHolder = $holderUser?->table_name ?? '—';
                            $filedBy = $complaint->is_anonymous ? 'Anonymous' : ($complaint->student?->user?->table_name ?? 'Unknown');
                            $isUnread = $ticket ? app(\App\Services\TicketUnreadService::class)->unreadCountForTicket(Auth::user(), $ticket) > 0 : false;
                        @endphp
                        <tr data-unread="{{ $isUnread ? 'true' : 'false' }}" class="transition-colors hover:bg-primary-soft {{ $isUnread ? 'bg-primary-soft/70' : '' }}">
                            <td class="whitespace-nowrap px-2 py-1.5 font-mono font-semibold text-primary"><a href="{{ route('admin.complaints.show', $complaint) }}" class="hover:underline">{{ $complaint->reference_number }}</a></td>
                            <td class="truncate px-2 py-1.5 {{ $isUnread ? 'text-black' : 'text-muted-foreground' }}"><a href="{{ route('admin.complaints.show', $complaint) }}" class="block truncate font-medium hover:text-primary">{{ $complaint->subject_title }}</a></td>
                            <td class="wrap-break-word py-1.5 pl-1.5 pr-2 {{ $isUnread ? 'text-black' : 'text-muted-foreground' }}">{{ $complaint->category?->name ?? 'Uncategorized' }}</td>
                            <td class="px-2 py-1.5">@if ($ticket)<x-classification-badge :classification="$ticket->classification" class="text-[10px]" />@else<span class="text-[10px] text-muted-foreground">—</span>@endif</td>
                            <td class="py-1.5 pl-1.5 pr-2"><x-status-badge :status="ucwords(str_replace(['_', '-'], ' ', $ticket?->status ?? $complaint->status))" :show-icon="false" class="px-2 py-0.5 text-[10px]" /></td>
                            <td class="px-2 py-1.5 {{ $ticket?->escalated_at ? 'font-semibold text-red-700' : 'text-muted-foreground' }}">{{ $ticket?->escalated_at?->copy()->setTimezone('Asia/Manila')->format('M d, Y') ?? '—' }}</td>
                            <td class="truncate px-2 py-1.5 {{ $isUnread ? 'text-black' : 'text-muted-foreground' }}"><span class="relative inline-block" x-data="{ holderProfileOpen: false }" x-on:mouseenter="holderProfileOpen = true" x-on:mouseleave="holderProfileOpen = false"><span class="cursor-pointer hover:text-primary hover:underline">{{ $currentHolder }}</span>@if ($holderUser)<span x-show="holderProfileOpen" x-cloak data-keep-in-view class="brand-gradient absolute top-6 left-0 z-30 w-72 max-w-[calc(100vw-2rem)] rounded-xl p-4 text-left text-primary-foreground shadow-lg"><span class="flex items-center gap-3"><span class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-foreground/15 text-sm font-bold ring-2 ring-primary-foreground/30">@if ($holderUser->avatar_path)<img src="{{ asset('storage/' . $holderUser->avatar_path) }}" alt="{{ $currentHolder }}" class="h-full w-full object-cover">@else{{ $holderUser->name_initials }}@endif</span><span class="min-w-0"><span class="block wrap-break-word text-sm font-bold">{{ $currentHolder }}</span><span class="mt-1 block wrap-break-word text-[11px] leading-relaxed opacity-95">ID {{ $holderRecipient?->staff_id ?? $holderUser->username ?? $holderUser->id }} · {{ $holderRecipient?->department ?? 'Department not specified' }} · {{ $holderRecipient?->designation ?? 'Designation not specified' }}</span></span></span><span class="mt-3 inline-flex rounded-full border border-primary-foreground/40 bg-primary-foreground/15 px-3 py-1 text-[10px] font-semibold">{{ $holderUser->role === \App\Models\User::ROLE_SDS_ADMIN ? 'Admin' : 'Recipient' }}</span></span>@endif</span></td>
                            <td class="relative overflow-visible px-2 py-1.5 {{ $isUnread ? 'text-black' : 'text-muted-foreground' }}"><span class="relative inline-block group"><span class="{{ $complaint->is_anonymous ? '' : 'cursor-pointer truncate hover:text-primary hover:underline' }}">{{ $filedBy }}</span>@if ($complaint->student?->user && ! $complaint->is_anonymous)<span data-keep-in-view class="brand-gradient absolute top-6 left-0 z-30 hidden w-72 max-w-[calc(100vw-2rem)] rounded-xl p-4 text-left text-primary-foreground shadow-lg group-hover:block"><span class="flex items-center gap-3"><span class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-foreground/15 text-sm font-bold ring-2 ring-primary-foreground/30">@if ($complaint->student->user->avatar_path)<img src="{{ asset('storage/' . $complaint->student->user->avatar_path) }}" alt="{{ $filedBy }}" class="h-full w-full object-cover">@else{{ $complaint->student->user->name_initials }}@endif</span><span class="min-w-0"><span class="block wrap-break-word text-sm font-bold">{{ $filedBy }}</span><span class="mt-1 block wrap-break-word text-[11px] leading-relaxed opacity-95">ID {{ $complaint->student?->student_id ?? 'N/A' }} · {{ $complaint->student?->department ?? 'Department not specified' }} · {{ trim(($complaint->student?->course ?? 'Course not specified') . ' ' . ($complaint->student?->year_level ?? 'N/A') . (($complaint->student?->block ?? '') !== '' ? '-' . $complaint->student->block : '')) }}</span></span></span><span class="mt-3 inline-flex rounded-full border border-primary-foreground/40 bg-primary-foreground/15 px-3 py-1 text-[10px] font-semibold">Student</span></span>@endif</span></td>
                            <td class="whitespace-nowrap px-2 py-1.5 {{ $isUnread ? 'text-black' : 'text-muted-foreground' }}">{{ $complaint->created_at?->copy()->setTimezone('Asia/Manila')->format('m/d/y h:i A') ?? 'Unknown' }}</td>
                            <td class="whitespace-nowrap px-2 py-1.5 {{ $isUnread ? 'text-black' : 'text-muted-foreground' }}">{{ $ticket?->updated_at?->copy()->setTimezone('Asia/Manila')->format('m/d/y h:i A') ?? $complaint->updated_at?->copy()->setTimezone('Asia/Manila')->format('m/d/y h:i A') ?? 'Unknown' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="py-10 text-center text-muted-foreground">No tickets match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($complaints->hasPages())<div class="mt-5">{{ $complaints->links() }}</div>@endif
        </div>

        <style>
            [data-ticket-results] td:has(.brand-gradient) {
                overflow: visible;
            }

            [data-ticket-results] td .brand-gradient {
                top: 100%;
                bottom: auto;
                z-index: 100;
                margin-top: 0.5rem;
                width: 18rem;
                height: auto;
                min-height: 0;
                white-space: normal;
                overflow-wrap: anywhere;
                border-radius: 0.75rem;
                padding: 1rem;
                box-shadow: 0 10px 24px -8px rgb(0 0 0 / 0.35);
            }

        </style>

        <x-admin-ticket-drawer />
    </div>
</x-app-layout>
