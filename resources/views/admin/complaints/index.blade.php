<x-app-layout :role="'admin'" title="All Tickets">
    <div class="-mt-1 sm:-mt-2">
        <form method="GET" action="{{ route('admin.complaints.index') }}" data-ticket-filter-form class="relative z-20">
            @php
                $selectedCategory = $categories->firstWhere('id', request('category_filter'));
                $query = fn (array $values) => route('admin.complaints.index', array_filter($values, fn ($value) => $value !== null && $value !== ''));
            @endphp
            <div class="mb-4 space-y-3">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-center">
                    <div class="relative flex-1">
                        <x-icons.search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <input id="f-search" name="search" value="{{ request('search') }}" autocomplete="off" placeholder="Search ticket id, subject title, recipient or student" class="h-9 w-full rounded-md border border-input bg-transparent pl-9 pr-3 text-xs shadow-sm outline-none placeholder:text-muted-foreground focus:ring-1 focus:ring-ring" />
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium">Filter by</span>
                            <details x-data="{}" class="group relative w-56" x-on:click.outside="$el.removeAttribute('open')">
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
                        </div>
                        <div class="flex items-center gap-2">
                            <details x-data="{}" class="group relative w-40" x-on:click.outside="$el.removeAttribute('open')">
                                <summary id="f-classification" class="flex h-9 cursor-pointer list-none items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden">
                                    <span class="truncate">{{ request('classification_filter') ? ucfirst(str_replace('_', ' ', request('classification_filter'))) : 'All classifications' }}</span><svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                                </summary>
                                <div class="absolute top-full z-50 mt-1 w-full rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                    @foreach (['' => 'All classifications', 'needs_resolution' => 'Needs Resolution', 'informational' => 'Informational', 'invalid' => 'Invalid'] as $value => $label)
                                        <a href="{{ $query(array_merge(request()->only(['search','category_filter','status_filter','filed_from','filed_to','sort']), ['classification_filter' => $value])) }}" class="relative flex w-full items-center rounded-sm py-1.5 pl-2 pr-8 text-xs {{ request('classification_filter', '') === $value ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">@if (request('classification_filter', '') === $value)<x-icons.check class="absolute right-2 h-4 w-4" />@endif{{ $label }}</a>
                                    @endforeach
                                </div>
                            </details>
                        </div>

                        <div class="flex items-center gap-2">
                            <details x-data="{}" class="group relative w-40" x-on:click.outside="$el.removeAttribute('open')">
                                <summary id="f-status" class="flex h-9 cursor-pointer list-none items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden">
                                    <span>{{ request('status_filter') ? ucfirst(str_replace('_', ' ', request('status_filter'))) : 'All statuses' }}</span><svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                                </summary>
                                <div class="absolute top-full z-50 mt-1 w-full rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                    @foreach (['' => 'All statuses', 'pending' => 'Pending', 'assigned' => 'Assigned', 'in_progress' => 'In Progress', 'escalated' => 'Escalated', 'resolved' => 'Resolved', 'closed' => 'Closed'] as $value => $label)
                                        <a href="{{ $query(array_merge(request()->only(['search','category_filter','classification_filter','filed_from','filed_to','sort']), ['status_filter' => $value])) }}" class="relative flex w-full items-center rounded-sm py-1.5 pl-2 pr-8 text-xs {{ request('status_filter', '') === $value ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">@if (request('status_filter', '') === $value)<x-icons.check class="absolute right-2 h-4 w-4" />@endif{{ $label }}</a>
                                    @endforeach
                                </div>
                            </details>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:flex-wrap">
                    <div class="flex items-center gap-2">
                        <label for="f-sort" class="text-sm font-medium">Sort by</label>
                        <details x-data="{}" class="group relative w-52" x-on:click.outside="$el.removeAttribute('open')">
                            <summary id="f-sort" class="flex h-9 cursor-pointer list-none items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden">
                                <span>{{ request('sort', 'latest_update') === 'latest_update' ? 'Latest Update' : (request('sort') === 'oldest_update' ? 'Oldest Update' : (request('sort') === 'latest_submitted' ? 'Latest Submitted' : 'Oldest Submitted')) }}</span><svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                            </summary>
                            <div class="absolute top-full z-50 mt-1 w-full rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                @foreach (['latest_update' => 'Latest Update', 'oldest_update' => 'Oldest Update', 'latest_submitted' => 'Latest Submitted', 'oldest_submitted' => 'Oldest Submitted'] as $value => $label)
                                    <a href="{{ $query(array_merge(request()->only(['search','category_filter','classification_filter','status_filter','filed_from','filed_to']), ['sort' => $value])) }}" class="relative flex w-full items-center rounded-sm py-1.5 pl-2 pr-8 text-xs {{ request('sort', 'latest_update') === $value ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">@if ((request('sort', 'latest_update') === $value))<x-icons.check class="absolute right-2 h-4 w-4" />@endif{{ $label }}</a>
                                @endforeach
                            </div>
                        </details>
                    </div>

                    <div class="flex items-center gap-2">
                        <label for="f-from" class="text-sm font-medium">Filed from</label>
                        <input id="f-from" type="date" name="filed_from" value="{{ request('filed_from') }}" lang="en-US" class="h-9 min-w-[10.5rem] rounded-md border border-input bg-transparent px-3 text-xs focus:ring-1 focus:ring-ring" />
                    </div>

                    <div class="flex items-center gap-2">
                        <label for="f-to" class="text-sm font-medium">Filed to</label>
                        <input id="f-to" type="date" name="filed_to" value="{{ request('filed_to') }}" lang="en-US" class="h-9 min-w-[10.5rem] rounded-md border border-input bg-transparent px-3 text-xs focus:ring-1 focus:ring-ring" />
                    </div>

                    <div class="flex flex-wrap gap-2 pt-5 sm:pt-0">
                        <a data-reset-filters href="{{ route('admin.complaints.index') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md bg-primary text-primary-foreground transition-colors hover:bg-primary/90" aria-label="Refresh all tickets" title="Refresh all tickets">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 12a9 9 0 1 1-2.64-6.36L21 8" />
                                <path d="M21 3v5h-5" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </form>

        @php $pendingAdmin = \App\Models\User::query()->where('role', \App\Models\User::ROLE_SDS_ADMIN)->orderBy('id')->first(); @endphp
        <div data-ticket-results>
        <p class="mb-3 mt-5 text-xs text-muted-foreground">{{ $complaints->total() }} ticket(s) shown</p>
        <div class="overflow-visible rounded-lg border">
            <table class="w-full table-fixed text-[11px]">
                <colgroup>
                    <col style="width: 7.5%;">
                    <col style="width: 20.5%;">
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
                            <td class="whitespace-nowrap px-2 py-1.5 font-mono font-semibold text-primary"><a href="#" data-admin-ticket-link="complaint-{{ $complaint->id }}" class="hover:underline">{{ $complaint->reference_number }}</a></td>
                            <td class="truncate px-2 py-1.5 {{ $isUnread ? 'text-black' : 'text-muted-foreground' }}"><a href="#" data-admin-ticket-link="complaint-{{ $complaint->id }}" class="block truncate font-medium hover:text-primary">{{ $complaint->subject_title }}</a></td>
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

        <div id="admin-ticket-drawer" class="pointer-events-none invisible fixed inset-0 z-50" aria-hidden="true">
            <div data-admin-ticket-backdrop class="absolute inset-0 bg-black/35 opacity-0 transition-opacity"></div>
            <aside data-admin-ticket-panel class="absolute top-0 right-0 flex h-full w-full max-w-full translate-x-full flex-col overflow-y-auto bg-background shadow-2xl transition-transform duration-200 sm:max-w-[50vw]" role="dialog" aria-modal="true" aria-label="Ticket details">
                <div class="sticky top-0 z-10 flex items-start justify-between border-b border-border bg-card px-4 py-3">
                    <div>
                        <h2 class="font-display text-lg font-bold text-primary">Ticket Details</h2>
                    </div>
                    <button type="button" data-admin-ticket-close class="grid h-8 w-8 shrink-0 place-items-center rounded-md text-xl leading-none text-muted-foreground hover:bg-muted hover:text-primary" aria-label="Close ticket details">&times;</button>
                </div>
                <div data-admin-ticket-body class="p-4"></div>
            </aside>
        </div>

        @foreach ($complaints as $complaint)
            @php
                $ticket = $complaint->ticket;
                $student = $complaint->student;
                $studentUser = $student?->user;
                $studentDisplayName = $complaint->is_anonymous ? 'Anonymous' : ($studentUser?->table_name ?? 'Anonymous');
                $studentCourseYearBlock = trim(($student?->course ?? 'Course not specified') . ' ' . ($student?->year_level ?? 'N/A') . (($student?->block ?? '') !== '' ? '-' . $student->block : ''));
                $holderUser = $ticket?->status === 'pending'
                    ? ($pendingAdmin ?? $ticket?->currentHandler)
                    : ($ticket?->currentHandler ?? $ticket?->assignee);
                $currentHolderName = $holderUser?->table_name ?? '—';
                $holderRecipient = $holderUser?->recipient;
                $auditLogs = $ticket?->auditLogs()
                    ->orderByDesc('created_at')
                    ->limit(5)
                    ->get()
                    ->sortByDesc(fn ($log) => $log->created_at?->timestamp ?? 0)
                    ->values() ?? collect();
            @endphp
            <template id="complaint-{{ $complaint->id }}">
                <div class="grid items-start gap-4 sm:grid-cols-[1.35fr_1fr]">
                    <div class="min-w-0 rounded-lg border border-border bg-card p-4 sm:min-h-[calc(100vh-6rem)]">
                        <div class="mb-1 flex items-center justify-between gap-3">
                            <p class="font-mono text-[11px] font-semibold leading-tight text-primary">{{ $complaint->reference_number }}</p>
                            <div class="shrink-0">
                                <x-status-badge :status="match($ticket?->status ?? 'pending') { 'assigned', 'in_progress' => 'In Progress', 'resolved' => 'Resolved', 'escalated' => 'Escalated', 'rejected', 'closed' => 'Closed', default => ucfirst(str_replace('_', ' ', ($ticket?->status ?? 'pending'))), }" :classification="$ticket?->classification" :show-icon="false" class="px-2 py-0.5 text-[11px] leading-tight w-fit" />
                            </div>
                        </div>
                        <h3 class="mt-0 wrap-break-word font-display text-lg font-bold leading-tight text-foreground">{{ $complaint->subject_title ?? 'Untitled' }}</h3>
                        <div class="mt-2 flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground">
                            @if (! $complaint->is_anonymous)
                                <span class="grid h-6 w-6 shrink-0 place-items-center overflow-hidden rounded-full bg-primary text-[9px] font-bold text-primary-foreground">
                                    @if ($studentUser?->avatar_path)
                                        <img src="{{ asset('storage/' . $studentUser->avatar_path) }}" alt="{{ $studentDisplayName }}" class="h-full w-full object-cover">
                                    @else
                                        {{ $studentUser?->name_initials ?: 'A' }}
                                    @endif
                                </span>
                            @endif
                            <span class="relative min-w-0" x-data="{ studentProfileOpen: false }" x-on:mouseenter="studentProfileOpen = true" x-on:mouseleave="studentProfileOpen = false">
                                <span class="{{ $complaint->is_anonymous ? '' : 'cursor-pointer truncate font-semibold text-foreground hover:text-primary hover:underline' }}">{{ $studentDisplayName }}</span>
                                @if ($studentUser && ! $complaint->is_anonymous)
                                    <span x-show="studentProfileOpen" x-cloak data-keep-in-view class="brand-gradient absolute top-6 left-0 z-[100] w-72 max-w-[calc(100vw-2rem)] rounded-xl p-4 text-left text-primary-foreground shadow-lg">
                                        <span class="flex items-center gap-3">
                                            <span class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-foreground/15 text-sm font-bold ring-2 ring-primary-foreground/30">
                                                @if ($studentUser->avatar_path)
                                                    <img src="{{ asset('storage/' . $studentUser->avatar_path) }}" alt="{{ $studentDisplayName }}" class="h-full w-full object-cover">
                                                @else
                                                    {{ $studentUser->name_initials }}
                                                @endif
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block wrap-break-word text-sm font-bold">{{ $studentDisplayName }}</span>
                                                <span class="mt-1 block wrap-break-word text-[11px] leading-relaxed opacity-95">ID {{ $student?->student_id ?? 'N/A' }} · {{ $student?->department ?? 'Department not specified' }} · {{ $studentCourseYearBlock }}</span>
                                            </span>
                                        </span>
                                        <span class="mt-3 inline-flex rounded-full border border-primary-foreground/40 bg-primary-foreground/15 px-3 py-1 text-[10px] font-semibold">Student</span>
                                    </span>
                                @endif
                            </span>
                            <span class="shrink-0">at</span>
                            <span class="shrink-0">{{ $complaint->created_at?->copy()->setTimezone('Asia/Manila')->format('M d, Y · g:i A') ?? 'Unknown' }}</span>
                        </div>
                        <div class="mt-3 grid gap-2">
                            <div><p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Category</p><p class="wrap-break-word text-sm font-medium leading-tight text-foreground">{{ $complaint->category?->name ?? 'Uncategorized' }}</p></div>
                            @if ($complaint->suggestedRecipient?->user)
                                <div><p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Suggested recipient</p><p class="wrap-break-word text-sm font-medium leading-tight text-foreground">{{ $complaint->suggestedRecipient->user->table_name }}</p></div>
                            @endif
                            <div><p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Description</p><p class="whitespace-pre-line wrap-break-word text-sm leading-relaxed text-foreground">{{ $complaint->description ?? 'No description' }}</p></div>
                            @if ($complaint->attachment_files)
                                <div><p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Attachment</p>
                                    @foreach ($complaint->attachment_files as $attachment)
                                        <a href="{{ Storage::url($attachment['path']) }}" target="_blank" rel="noopener" class="mt-2 inline-flex max-w-full items-center gap-2 rounded-lg border border-border bg-muted px-3 py-2 text-xs font-semibold text-foreground hover:border-primary hover:bg-primary-soft">
                                            <x-icons.paperclip class="h-3.5 w-3.5 shrink-0" />
                                            <span class="truncate">{{ $attachment['name'] }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                            <div class="border-t border-border pt-2"><p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Current Holder</p><p class="text-sm font-medium leading-tight text-foreground"><span class="relative inline-block" x-data="{ holderProfileOpen: false }" x-on:mouseenter="holderProfileOpen = true" x-on:mouseleave="holderProfileOpen = false"><span class="cursor-pointer hover:text-primary hover:underline">{{ $currentHolderName }}</span>@if ($holderUser)<span x-show="holderProfileOpen" x-cloak data-keep-in-view class="brand-gradient absolute top-6 left-0 z-[100] w-72 max-w-[calc(100vw-2rem)] rounded-xl p-4 text-left text-primary-foreground shadow-lg"><span class="flex items-center gap-3"><span class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-foreground/15 text-sm font-bold ring-2 ring-primary-foreground/30">@if ($holderUser->avatar_path)<img src="{{ asset('storage/' . $holderUser->avatar_path) }}" alt="{{ $currentHolderName }}" class="h-full w-full object-cover">@else{{ $holderUser->name_initials }}@endif</span><span class="min-w-0"><span class="block wrap-break-word text-sm font-bold">{{ $currentHolderName }}</span><span class="mt-1 block wrap-break-word text-[11px] leading-relaxed opacity-95">ID {{ $holderRecipient?->staff_id ?? $holderUser->username ?? $holderUser->id }} · {{ $holderRecipient?->department ?? 'Department not specified' }} · {{ $holderRecipient?->designation ?? 'Designation not specified' }}</span></span></span><span class="mt-3 inline-flex rounded-full border border-primary-foreground/40 bg-primary-foreground/15 px-3 py-1 text-[10px] font-semibold">{{ $holderUser->role === \App\Models\User::ROLE_SDS_ADMIN ? 'Admin' : 'Recipient' }}</span></span>@endif</span></p></div>
                            <div><p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Classification</p><p class="text-sm font-medium leading-tight text-foreground">{{ $ticket?->classification ? ucwords(str_replace('_', ' ', $ticket->classification)) : '—' }}</p></div>
                            <div><p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Escalated On</p><p class="text-sm font-medium leading-tight text-foreground">{{ $ticket?->escalated_at?->copy()->setTimezone('Asia/Manila')->format('M d, Y · g:i A') ?? '—' }}</p></div>
                            @if ($ticket)
                                <x-ticket-escalate-form :ticket="$ticket" class="mt-1" />
                            @endif
                            <div><p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Last Updated</p><p class="text-sm font-medium leading-tight text-foreground">{{ $ticket?->updated_at?->copy()->setTimezone('Asia/Manila')->format('M d, Y · g:i A') ?? $complaint->updated_at?->copy()->setTimezone('Asia/Manila')->format('M d, Y · g:i A') ?? 'Unknown' }}</p></div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-4 overflow-hidden sm:h-[calc(100vh-6rem)]">
                        <div class="admin-ticket-thread flex-1 flex flex-col min-h-0">
                            @if ($ticket)
                                <x-ticket-thread :ticket="$ticket" viewerRole="admin" />
                            @else
                                <div class="surface flex min-h-[10rem] flex-1 items-center justify-center p-4 text-center text-sm text-muted-foreground">
                                    No ticket has been generated for this complaint yet.
                                </div>
                            @endif
                        </div>

                        <div class="h-[15.5rem] shrink-0 rounded-lg border border-border bg-card p-3 flex flex-col">
                            <h3 class="font-display text-sm font-semibold leading-tight text-foreground">Audit Activity</h3>
                            <ol class="mt-1.5 overflow-hidden space-y-2">
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
                                        $performerName = $log->performer?->name ?? 'System';
                                    @endphp
                                    <li class="flex min-w-0 gap-2">
                                        <span class="mt-0.5 h-8 w-0.5 shrink-0 rounded-full" style="background-color: #800000;"></span>
                                        <div class="min-w-0 flex-1 mt-0.5">
                                            <p class="truncate text-xs font-semibold leading-tight text-foreground">{{ $activityLabel }}</p>
                                            <p class="mt-0.5 truncate text-[11px] leading-snug text-muted-foreground">
                                                <span>{{ $performerName }}</span>
                                                <span class="text-border"> · </span>
                                                <span>{{ $log->created_at?->copy()->setTimezone('Asia/Manila')->format('M d, Y') }} · {{ $log->created_at?->copy()->setTimezone('Asia/Manila')->format('g:i A') }}</span>
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

            .admin-ticket-thread {
                min-height: 0;
                overflow: hidden;
            }

            .admin-ticket-thread #in-ticket-communication {
                margin-bottom: 0.25rem;
            }

            .admin-ticket-thread .surface {
                padding: 0.75rem;
                border-radius: 0.75rem;
                border: 1px solid var(--border, rgb(229 231 235));
                background: var(--card, #ffffff);
                min-height: 0;
                overflow: hidden;
            }

            .admin-ticket-thread .admin-communication-card:hover [data-ticket-message-list].admin-message-scrollbar,
            .admin-ticket-thread .admin-communication-card:focus [data-ticket-message-list].admin-message-scrollbar,
            .admin-ticket-thread .admin-communication-card:focus-within [data-ticket-message-list].admin-message-scrollbar {
                scrollbar-color: oklch(0.72 0 0) transparent;
                scrollbar-width: thin;
            }

            .admin-ticket-thread [data-ticket-message-list].admin-message-scrollbar::-webkit-scrollbar {
                width: 0 !important;
                height: 0 !important;
            }

            .admin-ticket-thread .admin-communication-card:hover [data-ticket-message-list].admin-message-scrollbar::-webkit-scrollbar,
            .admin-ticket-thread .admin-communication-card:focus [data-ticket-message-list].admin-message-scrollbar::-webkit-scrollbar,
            .admin-ticket-thread .admin-communication-card:focus-within [data-ticket-message-list].admin-message-scrollbar::-webkit-scrollbar {
                width: 2px !important;
            }

            .admin-ticket-thread [data-ticket-message-list].admin-message-scrollbar::-webkit-scrollbar-button {
                display: none !important;
                height: 0 !important;
                width: 0 !important;
                background: transparent !important;
                border: 0 !important;
                -webkit-appearance: none;
            }

            .admin-ticket-thread [data-ticket-message-list].admin-message-scrollbar::-webkit-scrollbar-button:start:decrement,
            .admin-ticket-thread [data-ticket-message-list].admin-message-scrollbar::-webkit-scrollbar-button:end:increment,
            .admin-ticket-thread [data-ticket-message-list].admin-message-scrollbar::-webkit-scrollbar-button:vertical:start:decrement,
            .admin-ticket-thread [data-ticket-message-list].admin-message-scrollbar::-webkit-scrollbar-button:vertical:end:increment {
                display: none !important;
                height: 0 !important;
                width: 0 !important;
            }

            .admin-ticket-thread [data-ticket-message-list].admin-message-scrollbar::-webkit-scrollbar-track,
            .admin-ticket-thread [data-ticket-message-list].admin-message-scrollbar::-webkit-scrollbar-corner {
                background: oklch(0.92 0 0) !important;
                border-radius: 999px !important;
            }

            .admin-ticket-thread [data-ticket-message-list].admin-message-scrollbar::-webkit-scrollbar-thumb {
                min-height: 24px !important;
                border-radius: 999px !important;
                background: oklch(0.62 0 0) !important;
            }

            .admin-ticket-thread #ticket-reply-form {
                gap: 0.5rem;
            }

            .admin-ticket-thread #ticket-reply-form textarea {
                min-height: 2.25rem;
                padding: 0.5rem 0.875rem;
                font-size: 14px;
                line-height: 1.25rem;
            }

            .admin-ticket-thread #ticket-reply-form > label,
            .admin-ticket-thread #ticket-reply-form > button {
                height: 2.25rem;
                width: 2.25rem;
            }

            .admin-ticket-thread #ticket-reply-form > label svg,
            .admin-ticket-thread #ticket-reply-form > button svg {
                height: 1rem;
                width: 1rem;
            }
        </style>

        <script>
            (() => {
                const drawer = document.getElementById('admin-ticket-drawer');
                if (!drawer) return;
                if (drawer.dataset.ready) return;
                drawer.dataset.ready = 'true';

                const panel = drawer.querySelector('[data-admin-ticket-panel]');
                const body = drawer.querySelector('[data-admin-ticket-body]');
                const backdrop = drawer.querySelector('[data-admin-ticket-backdrop]');
                const closeButton = drawer.querySelector('[data-admin-ticket-close]');

                function openTicket(complaintId) {
                    const template = document.getElementById(complaintId);
                    if (!template) return;

                    body.innerHTML = '';
                    body.appendChild(template.content.cloneNode(true));
                    if (window.Alpine) window.Alpine.initTree(body);

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
                    const link = event.target.closest('[data-admin-ticket-link]');
                    if (!link) return;
                    event.preventDefault();
                    const complaintId = link.getAttribute('data-admin-ticket-link');
                    if (complaintId) openTicket(complaintId);
                });
            })();
        </script>
    </div>
</x-app-layout>
