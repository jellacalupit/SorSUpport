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
                        <label for="my-classification" class="shrink-0 text-sm font-medium">Filter by classification</label>
                        @php
                            $classificationLabels = ['' => 'All', 'needs_resolution' => 'Needs Resolution', 'informational' => 'Informational', 'invalid' => 'Invalid'];
                            $selectedClassification = $classification ?? '';
                        @endphp
                        <details x-data="{}" class="group relative w-40 shrink-0" x-on:click.outside="$el.removeAttribute('open')">
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
                        <label for="my-status" class="shrink-0 text-sm font-medium">Filter by status</label>
                        @php
                            $statusLabels = ['' => 'All', 'in_progress' => 'In Progress', 'escalated' => 'Escalated', 'closed' => 'Closed'];
                            $selectedStatus = $status ?? '';
                        @endphp
                        <details x-data="{}" class="group relative w-32 shrink-0" x-on:click.outside="$el.removeAttribute('open')">
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
                        <label for="my-sort" class="shrink-0 text-sm font-medium">Sort by</label>
                        @php $sortValue = request('sort', 'newest'); @endphp
                        <details x-data="{}" class="group relative w-36 shrink-0" x-on:click.outside="$el.removeAttribute('open')">
                            <span aria-hidden="true" class="invisible block h-0 whitespace-nowrap">Newest First</span>
                            <summary id="my-sort" class="flex h-9 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden">
                                <span class="truncate">{{ $sortValue === 'oldest' ? 'Oldest First' : ($sortValue === 'deadline_urgency' ? 'Deadline Urgency' : 'Newest First') }}</span>
                                <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                            </summary>
                            <div class="absolute top-full z-50 mt-1 w-full rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                @foreach (['newest' => 'Newest First', 'oldest' => 'Oldest First', 'deadline_urgency' => 'Deadline Urgency'] as $sortOption => $sortLabel)
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
            <div class="w-full rounded-lg border">
                <table class="w-full table-auto text-xs">
                    <thead class="border-b bg-primary text-white">
                        <tr class="text-left">
                            <th class="whitespace-nowrap rounded-tl-lg px-2 py-2 font-semibold sm:px-3">Ticket ID</th>
                            <th class="px-2 py-2 font-semibold sm:px-3">Subject Title</th>
                            <th class="hidden px-2 py-2 font-semibold sm:px-3 lg:table-cell">Classification</th>
                            <th class="px-2 py-2 font-semibold sm:px-3">Status</th>
                            <th class="hidden whitespace-nowrap px-2 py-2 font-semibold sm:px-3 md:table-cell">SLA</th>
                            <th class="hidden px-2 py-2 font-semibold sm:px-3 xl:table-cell">Filed by</th>
                            <th class="hidden whitespace-nowrap px-2 py-2 font-semibold sm:px-3 xl:table-cell">Date Submitted</th>
                            <th class="hidden whitespace-nowrap rounded-tr-lg px-2 py-2 font-semibold sm:px-3 xl:table-cell">Last Updated</th>
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
                                $hasDeadline = $ticket->status !== 'pending' && ! in_array($ticket->classification, ['informational', 'invalid'], true) && $ticket->deadline;
                                $daysLeft = $hasDeadline ? now()->setTimezone('Asia/Manila')->startOfDay()->diffInDays($ticket->deadline->copy()->setTimezone('Asia/Manila')->startOfDay(), false) : null;
                                $slaText = $daysLeft === null ? '—' : ($daysLeft < 0 ? 'Overdue' : ($daysLeft === 0 ? 'Due Today' : $daysLeft . ' days left'));
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
                                <td class="hidden whitespace-nowrap px-2 py-2 sm:px-3 md:table-cell {{ $hasDeadline ? 'font-semibold text-red-700' : 'text-muted-foreground' }}">{{ $slaText }}</td>
                                <td class="hidden px-2 py-2 sm:px-3 xl:table-cell {{ $isUnread ? 'text-black' : 'text-muted-foreground' }}">
                                    <span class="relative inline-block" x-data="{ studentProfileOpen: false }" x-on:mouseenter="studentProfileOpen = true" x-on:mouseleave="studentProfileOpen = false">
                                        <span class="{{ $ticket->complaint?->is_anonymous ? '' : 'cursor-pointer hover:text-primary hover:underline' }}">{{ $studentTableName }}</span>
                                        @if ($studentUser && ! $ticket->complaint?->is_anonymous)
                                            <span x-show="studentProfileOpen" x-cloak class="brand-gradient absolute top-6 left-0 z-30 w-72 max-w-[calc(100vw-2rem)] rounded-xl p-4 text-left text-primary-foreground shadow-lg">
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
            <aside data-my-ticket-panel class="absolute top-0 right-0 flex h-full w-full max-w-full translate-x-full flex-col overflow-y-auto bg-background shadow-2xl transition-transform duration-200 sm:max-w-[50vw]" role="dialog" aria-modal="true" aria-label="Ticket details">
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
            @php
                $student = $ticket->complaint?->student;
                $studentUser = $student?->user;
                $studentDisplayName = $ticket->complaint?->is_anonymous ? 'Anonymous' : ($studentUser?->table_name ?? 'Anonymous');
                $studentCourseYearBlock = trim(($student?->course ?? 'Course not specified') . ' ' . ($student?->year_level ?? 'N/A') . (($student?->block ?? '') !== '' ? '-' . $student->block : ''));
                $resolutionDeadline = $ticket->deadline
                    ? $ticket->deadline->copy()->setTimezone('Asia/Manila')->format('M d, Y · g:i A')
                    : ($ticket->resolved_at ? 'Paused' : '—');
                $auditLogs = $ticket->auditLogs()
                    ->orderByDesc('created_at')
                    ->limit(5)
                    ->get()
                    ->sortByDesc(fn ($log) => $log->created_at?->timestamp ?? 0)
                    ->values();
            @endphp
            <template id="ticket-{{ $ticket->id }}">
                <div class="grid items-start gap-4 sm:grid-cols-[1.35fr_1fr]">
                    <!-- Left Side: Ticket Details -->
                    <div class="min-w-0 rounded-lg border border-border bg-card p-4 sm:min-h-[calc(100vh-6rem)]">
                        <div class="flex items-center justify-between gap-3 mb-1">
                            <p class="font-mono text-[11px] font-semibold leading-tight text-primary">{{ $ticket->complaint?->reference_number ?? $ticket->id }}</p>
                            <div class="shrink-0"><x-status-badge :status="match($ticket->status) { 'assigned', 'in_progress' => 'In Progress', 'resolved' => 'Resolved', 'escalated' => 'Escalated', 'rejected', 'closed' => 'Closed', default => ucfirst(str_replace('_', ' ', $ticket->status)), }" :classification="$ticket->classification" :show-icon="false" class="px-2 py-0.5 text-[11px] leading-tight w-fit" /></div>
                        </div>
                        <h3 class="mt-0 wrap-break-word font-display text-lg font-bold leading-tight text-foreground">{{ $ticket->complaint?->subject_title ?? 'Untitled' }}</h3>
                        <div class="mt-2 flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground">
                            <span class="grid h-6 w-6 shrink-0 place-items-center overflow-hidden rounded-full bg-primary text-[9px] font-bold text-primary-foreground">
                                @if ($studentUser?->avatar_path && ! $ticket->complaint?->is_anonymous)
                                    <img src="{{ asset('storage/' . $studentUser->avatar_path) }}" alt="{{ $studentUser->display_name }}" class="h-full w-full object-cover">
                                @else
                                    {{ $studentUser?->name_initials ?: 'A' }}
                                @endif
                            </span>
                            <span class="relative min-w-0" x-data="{ studentProfileOpen: false }" x-on:mouseenter="studentProfileOpen = true" x-on:mouseleave="studentProfileOpen = false">
                                <span class="{{ $ticket->complaint?->is_anonymous ? '' : 'cursor-pointer truncate font-semibold text-foreground hover:text-primary hover:underline' }}">{{ $studentDisplayName }}</span>
                                @if ($studentUser && ! $ticket->complaint?->is_anonymous)
                                    <span x-show="studentProfileOpen" x-cloak class="brand-gradient absolute top-6 left-0 z-[100] w-72 max-w-[calc(100vw-2rem)] rounded-xl p-4 text-left text-primary-foreground shadow-lg">
                                        <span class="flex items-center gap-3">
                                            <span class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-foreground/15 text-sm font-bold ring-2 ring-primary-foreground/30">
                                                @if ($studentUser->avatar_path)
                                                    <img src="{{ asset('storage/' . $studentUser->avatar_path) }}" alt="{{ $studentUser->display_name }}" class="h-full w-full object-cover">
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
                            <span class="shrink-0">{{ $ticket->complaint?->created_at?->copy()->setTimezone('Asia/Manila')->format('M d, Y · g:i A') ?? 'Unknown' }}</span>
                        </div>
                        <div class="mt-3 grid gap-2">
                            <div><p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Category</p><p class="wrap-break-word text-sm font-medium leading-tight text-foreground">{{ $ticket->complaint?->category?->name ?? 'Uncategorized' }}</p></div>
                            <div><p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Person involved</p><p class="wrap-break-word text-sm font-medium leading-tight text-foreground">{{ $ticket->complaint?->personnel_involved ?: 'Not specified' }}</p></div>
                            <div><p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Description</p><p class="whitespace-pre-line wrap-break-word text-sm leading-relaxed text-foreground">{{ $ticket->complaint?->description ?? 'No description' }}</p></div>
                            @if ($ticket->complaint?->attachment_files)
                                <div><p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Attachment</p>
                                @foreach ($ticket->complaint->attachment_files as $attachment)
                                    <a href="{{ Storage::url($attachment['path']) }}" target="_blank" rel="noopener" class="mt-2 inline-flex max-w-full items-center gap-2 rounded-lg border border-border bg-muted px-3 py-2 text-xs font-semibold text-foreground hover:border-primary hover:bg-primary-soft">
                                        <x-icons.paperclip class="h-3.5 w-3.5 shrink-0" />
                                        <span class="truncate">{{ $attachment['name'] }}</span>
                                    </a>
                                @endforeach
                                </div>
                            @endif
                            <div class="border-t border-border pt-2"><p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Classification</p><p class="text-sm font-medium leading-tight text-foreground">{{ $ticket->classification ? ucwords(str_replace('_', ' ', $ticket->classification)) : '—' }}</p></div>
                            <div><p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Resolution Deadline</p><p class="text-sm font-medium leading-tight text-foreground">{{ $resolutionDeadline }}</p></div>
                            <div><p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Last Updated</p><p class="text-sm font-medium leading-tight text-foreground">{{ $ticket->updated_at?->copy()->setTimezone('Asia/Manila')->format('M d, Y · g:i A') ?? 'Unknown' }}</p></div>
                        </div>
                    </div>

                    <!-- Right Side: Split into Communication (Top) and Audit Activity (Bottom) -->
                    <div class="flex flex-col gap-4 overflow-hidden sm:h-[calc(100vh-6rem)]">
                        <!-- Communication Section (Top) -->
                        <div class="admin-ticket-thread flex-1 flex flex-col min-h-0">
                            <x-ticket-thread :ticket="$ticket" viewerRole="admin" />
                        </div>

                        <!-- Audit Activity Section (Bottom) -->
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
                                        $formattedPerformerName = $log->performer?->table_name ?? 'System';
                                    @endphp
                                    <li class="flex min-w-0 gap-2">
                                        <span class="mt-0.5 h-8 w-0.5 shrink-0 rounded-full" style="background-color: #800000;"></span>
                                        <div class="min-w-0 flex-1 mt-0.5">
                                            <p class="truncate text-xs font-semibold leading-tight text-foreground">{{ $activityLabel }}</p>
                                            <p class="mt-0.5 truncate text-[11px] leading-snug text-muted-foreground">
                                                <span>{{ $formattedPerformerName }}</span>
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
                height: 2px !important;
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
                min-height: 1.75rem;
                padding: 0.25rem 0.5rem;
                font-size: 12px;
                line-height: 1.25rem;
            }

            .admin-ticket-thread #ticket-reply-form label,
            .admin-ticket-thread #ticket-reply-form button {
                height: 1.75rem;
                width: 1.75rem;
            }

            .admin-ticket-thread #ticket-reply-form label svg,
            .admin-ticket-thread #ticket-reply-form button svg {
                height: 0.875rem;
                width: 0.875rem;
            }
        </style>

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
