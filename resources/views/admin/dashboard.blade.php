<x-app-layout :role="'admin'" title="Dashboard">
    @php
        $statusSegments = [
            ['label' => 'Submitted', 'color' => '#facc15', 'count' => (int) (($statusBreakdown['submitted'] ?? 0) + ($statusBreakdown['needs_clarification'] ?? 0))],
            ['label' => 'In Progress', 'color' => '#2563eb', 'count' => (int) (($statusBreakdown['assigned'] ?? 0) + ($statusBreakdown['in_progress'] ?? 0) + ($statusBreakdown['referred'] ?? 0))],
            ['label' => 'Escalated', 'color' => '#dc2626', 'count' => (int) ($statusBreakdown['escalated'] ?? 0)],
            ['label' => 'Resolved', 'color' => '#16a34a', 'count' => (int) ($statusBreakdown['resolved'] ?? 0)],
            ['label' => 'Closed', 'color' => '#9ca3af', 'count' => (int) ($statusBreakdown['closed'] ?? 0)],
        ];
        $classificationSegments = [
            ['label' => 'Needs Resolution', 'color' => '#7a1d2a', 'count' => (int) ($classificationBreakdown['needs_resolution'] ?? 0)],
            ['label' => 'Informational', 'color' => '#111111', 'count' => (int) ($classificationBreakdown['informational'] ?? 0)],
            ['label' => 'Invalid', 'color' => '#dc2626', 'count' => (int) ($classificationBreakdown['invalid'] ?? 0)],
        ];
        $monthOptions = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
            7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];
    @endphp

    {{-- Phones and tablets --}}
    <div class="lg:hidden">
@if ($waitingNotice['review'] + $waitingNotice['stalled'] > 0)
    <div class="mb-2 flex flex-wrap items-center justify-between gap-x-3 gap-y-1 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900" data-waiting-notice>
        <p class="min-w-0">
            <span class="font-semibold">Waiting {{ \App\Support\TicketProgress::ATTENTION_DAYS }} days or more:</span>
            @if ($waitingNotice['review'])
                {{ $waitingNotice['review'] }} {{ \Illuminate\Support\Str::plural('ticket', $waitingNotice['review']) }} for your review{{ $waitingNotice['stalled'] ? ',' : '.' }}
            @endif
            @if ($waitingNotice['stalled'])
                {{ $waitingNotice['stalled'] }} with staff and no action.
            @endif
        </p>
        <a data-admin-page-nav href="{{ $waitingNotice['review'] ? route('admin.tickets.review.index') : route('admin.complaints.index') }}" class="shrink-0 font-semibold text-amber-900 underline">View tickets</a>
    </div>
@endif
    <div class="grid min-w-0 gap-3">
        <!-- Totals -->
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
            <x-stat-card compact label="Tickets" :value="$totalTickets" tone="primary" />
            <x-stat-card compact label="Students" :value="$totalStudents" />
            <x-stat-card compact label="Recipients" :value="$totalRecipients" tone="danger" />
            <x-stat-card compact label="Categories" :value="$totalCategories" />
        </div>

        <!-- Status and classification -->
        <div class="grid grid-cols-2 gap-2 sm:gap-3">
            <x-donut-chart title="Status" :segments="$statusSegments" stack />
            <x-donut-chart title="Classification" :segments="$classificationSegments" stack />
        </div>

        <!-- Volume -->
        <div data-volume-chart class="min-w-0 rounded-[20px] border border-border bg-white p-3 shadow-sm">
            <div class="relative z-40 grid gap-2 lg:flex lg:items-center lg:justify-between">
                <div class="flex items-center justify-between gap-3">
                    <h3 data-volume-title class="min-w-0 font-display text-sm font-semibold leading-tight text-foreground">Volume by category</h3>
                    <a data-admin-page-nav href="{{ route('admin.analytics.index') }}" class="shrink-0 text-xs font-medium text-primary hover:underline lg:hidden">View All</a>
                </div>
                <div class="grid min-w-0 grid-cols-2 gap-1.5 sm:grid-cols-[minmax(0,1fr)_8rem_6rem] lg:flex lg:items-center">
                    <details x-data="{}" class="group relative col-span-2 min-w-0 sm:col-span-1 lg:w-64 lg:shrink-0" x-on:click.outside="$el.removeAttribute('open')" data-volume-category-wrapper>
                        <summary class="flex h-8 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-transparent px-3 py-1.5 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden" data-volume-category-summary>
                            <span class="truncate" data-volume-category-label>All categories</span>
                            <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                        </summary>
                        <div class="absolute top-full right-0 z-50 mt-1 max-h-72 w-full min-w-56 overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                            <button type="button" class="relative flex w-full items-center rounded-sm py-1 px-2 text-xs hover:bg-accent hover:text-accent-foreground bg-primary-soft text-primary" data-category-option="" data-selected="true">
                                All categories
                            </button>
                            @foreach ($volumeChart['categories'] as $category)
                                <button type="button" class="relative flex w-full items-center rounded-sm py-1 px-2 text-left text-xs leading-snug hover:bg-accent hover:text-accent-foreground" data-category-option="{{ $category['id'] }}">
                                    <span class="min-w-0 wrap-break-word">{{ $category['name'] }}</span>
                                </button>
                            @endforeach
                        </div>
                        <select id="volume-chart-category" data-volume-category class="hidden" aria-hidden="true"></select>
                    </details>

                    <details x-data="{}" class="group relative min-w-0 lg:w-32 lg:shrink-0" x-on:click.outside="$el.removeAttribute('open')" data-volume-month-wrapper>
                        <summary class="flex h-8 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-transparent px-3 py-1.5 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden" data-volume-month-summary>
                            <span class="truncate" data-volume-month-label>All months</span>
                            <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                        </summary>
                        <div class="absolute top-full right-0 z-50 mt-1 max-h-72 w-full overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                            <button type="button" class="relative flex w-full items-center whitespace-nowrap rounded-sm py-1 px-2 text-xs hover:bg-accent hover:text-accent-foreground bg-primary-soft text-primary" data-month-option="" data-selected="true">
                                All months
                            </button>
                            @foreach ($monthOptions as $monthNumber => $monthLabel)
                                <button type="button" class="relative flex w-full items-center whitespace-nowrap rounded-sm py-1 px-2 text-xs hover:bg-accent hover:text-accent-foreground" data-month-option="{{ $monthNumber }}">
                                    {{ $monthLabel }}
                                </button>
                            @endforeach
                        </div>
                        <select id="volume-chart-month" data-volume-month class="hidden" aria-hidden="true"></select>
                    </details>

                    <details x-data="{}" class="group relative min-w-0 lg:w-24 lg:shrink-0" x-on:click.outside="$el.removeAttribute('open')" data-volume-year-wrapper>
                        <summary class="flex h-8 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-transparent px-3 py-1.5 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden" data-volume-year-summary>
                            <span class="truncate" data-volume-year-label>{{ (int) $volumeChart['currentYear'] }}</span>
                            <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                        </summary>
                        <div class="absolute top-full right-0 z-50 mt-1 max-h-72 w-full overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                            @foreach ($volumeChart['years'] as $year)
                                <button type="button" class="relative flex w-full items-center rounded-sm py-1 px-2 text-xs whitespace-nowrap hover:bg-accent hover:text-accent-foreground{{ (int) $year === (int) $volumeChart['currentYear'] ? ' bg-primary-soft text-primary' : '' }}" data-year-option="{{ $year }}"{{ (int) $year === (int) $volumeChart['currentYear'] ? ' data-selected="true"' : '' }}>
                                    {{ $year }}
                                </button>
                            @endforeach
                        </div>
                        <select id="volume-chart-year" data-volume-year class="hidden" aria-hidden="true"></select>
                    </details>

                    <a data-admin-page-nav href="{{ route('admin.analytics.index') }}" class="hidden shrink-0 text-xs font-medium text-primary hover:underline lg:inline">View All</a>
                </div>
            </div>
            {{-- Each bar has its own colour; the category names are listed below the chart. --}}
            <div data-volume-canvas-box class="relative z-0 mt-2 h-56 w-full">
                <canvas class="absolute inset-0" data-volume-canvas aria-label="Complaint volume chart"></canvas>
            </div>
            <ul data-volume-legend class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-[11px] text-foreground sm:grid-cols-3" aria-label="Categories"></ul>
        </div>

        <!-- Recent tickets -->
        <section class="min-w-0 overflow-hidden rounded-xl border border-border bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-border bg-muted/50 px-4 py-2.5">
                <h3 class="text-sm font-semibold text-foreground">Recent Tickets</h3>
                <a data-admin-page-nav href="{{ route('admin.complaints.index') }}" class="text-xs font-medium text-primary hover:underline">View All</a>
            </div>

            <table class="w-full table-fixed border-collapse text-left text-sm">
                <thead class="border-b bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="w-[6.5rem] px-3 py-2 font-semibold">Ticket ID</th>
                        <th class="px-3 py-2 font-semibold">Subject Title</th>
                        <th class="w-[6.5rem] px-3 py-2 text-center font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentTickets as $ticket)
                        <tr class="border-t border-border transition-colors hover:bg-muted/30">
                            <td class="truncate px-3 py-2 font-mono text-[11px] font-semibold text-primary">
                                {{ $ticket->complaint?->reference_number ?? $ticket->id }}
                            </td>
                            <td class="truncate px-3 py-2 text-xs text-foreground">
                                {{ $ticket->complaint?->public_subject ?? 'No subject' }}
                            </td>
                            <td class="px-3 py-2 text-center">
                                @php
                                    $recentStatusLabel = $ticket->status_label;
                                @endphp
                                <x-status-badge :status="$recentStatusLabel" :show-icon="false" class="px-2 py-0 text-[11px]" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-3 py-6 text-center text-sm text-muted-foreground">No recent tickets.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <!-- Recipient performance -->
        <section class="min-w-0 rounded-[20px] border border-border bg-white p-3 shadow-sm">
            <div class="flex items-center justify-between gap-2">
                <h3 class="font-display text-sm font-semibold leading-tight text-foreground">Recipient Performance</h3>
                <a data-admin-page-nav href="{{ route('admin.analytics.index') }}" class="text-xs font-medium text-primary hover:underline">View All</a>
            </div>

            <div class="mt-2 grid gap-1">
                @forelse ($topPerformingRecipients as $recipient)
                    <div class="flex items-center gap-2.5 rounded-lg p-1.5 transition-colors hover:bg-muted/50">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border bg-sidebar-primary text-[10px] font-bold text-sidebar-primary-foreground">
                            @if ($recipient->user?->avatar_path)
                                <img src="{{ asset('storage/' . $recipient->user->avatar_path) }}" alt="{{ $recipient->display_name }}" class="h-full w-full object-cover">
                            @else
                                {{ collect(explode(' ', $recipient->display_name ?? 'U'))->filter()->map(fn ($part) => substr($part, 0, 1))->take(2)->join('') }}
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <span class="relative inline-block max-w-full" x-data="{ recipientProfileOpen: false }" x-on:mouseenter="recipientProfileOpen = true" x-on:mouseleave="recipientProfileOpen = false" x-on:click="recipientProfileOpen = !recipientProfileOpen" x-on:click.outside="recipientProfileOpen = false">
                                <p class="cursor-pointer truncate text-[12px] font-semibold text-foreground hover:text-primary hover:underline">
                                    {{ $recipient->display_name ?? 'Unknown' }}
                                </p>

                                <!-- Profile Tooltip -->
                                <span x-show="recipientProfileOpen" x-cloak data-keep-in-view class="brand-gradient absolute top-6 left-0 z-50 w-56 max-w-[calc(100vw-2rem)] rounded-xl p-3 text-left text-primary-foreground shadow-lg">
                                    <span class="flex items-center gap-2">
                                        <span class="grid h-10 w-10 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-foreground/15 text-xs font-bold ring-2 ring-primary-foreground/30">
                                            @if ($recipient->user?->avatar_path)
                                                <img src="{{ asset('storage/' . $recipient->user->avatar_path) }}" alt="{{ $recipient->display_name }}" class="h-full w-full object-cover">
                                            @else
                                                {{ collect(explode(' ', $recipient->display_name ?? 'U'))->filter()->map(fn ($part) => substr($part, 0, 1))->take(2)->join('') }}
                                            @endif
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block wrap-break-word text-sm font-bold">{{ $recipient->display_name ?? 'Unknown' }}</span>
                                            <span class="mt-0.5 block wrap-break-word text-[10px] leading-relaxed opacity-95">ID {{ $recipient->staff_id }} · {{ $recipient->unit }} · {{ $recipient->designation }}</span>
                                        </span>
                                    </span>
                                    <span class="mt-2 inline-flex rounded-full border border-primary-foreground/40 bg-primary-foreground/15 px-2 py-0.5 text-[9px] font-semibold">{{ $recipient->role }}</span>
                                </span>
                            </span>

                            <div class="mt-0.5 flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-200">
                                    <div class="h-full rounded-full bg-green-500" style="width: {{ $recipient->performance_percentage ?? 0 }}%"></div>
                                </div>
                                <span class="w-8 shrink-0 text-right text-[10px] font-semibold text-green-700">{{ $recipient->performance_percentage ?? 0 }}%</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-[11px] text-muted-foreground">No recipient data available.</p>
                @endforelse
            </div>
        </section>

        <!-- Recent audit activity -->
        <section class="min-w-0 rounded-[20px] border border-border bg-white p-3 shadow-sm">
            <div class="flex items-center justify-between gap-2">
                <h3 class="font-display text-sm font-semibold leading-tight text-foreground">Recent audit activity</h3>
                <a data-admin-page-nav href="{{ route('admin.audit') }}" class="text-xs font-medium text-primary hover:underline">View All</a>
            </div>

            <ol class="relative mt-2 grid gap-2.5">
                @forelse ($recentActivity as $log)
                    @php
                        $activityLabel = match ($log->action) {
                            'complaint_submitted' => 'Complaint received',
                            'message_posted' => 'Message posted',
                            default => ucfirst(str_replace('_', ' ', (string) $log->action)),
                        };
                        $referenceNumber = $log->ticket?->complaint?->reference_number;
                        $activityIcon = match ($log->action) {
                            'complaint_submitted' => 'plus',
                            'message_posted' => 'message',
                            'ticket_escalated' => 'alert',
                            'ticket_resolved', 'ticket_closed' => 'check',
                            default => 'activity',
                        };
                    @endphp
                    <li class="relative grid min-w-0 grid-cols-[2rem_minmax(0,1fr)_auto] items-start gap-2.5">
                        @unless ($loop->last)
                            <span class="absolute top-8 -bottom-2.5 left-4 w-px bg-[#7a1d2a]/20" aria-hidden="true"></span>
                        @endunless
                        <span class="relative grid h-8 w-8 shrink-0 place-items-center overflow-hidden rounded-full bg-[#7a1d2a] text-white shadow-sm ring-2 ring-[#7a1d2a]/10">
                            @if ($log->display_performer?->avatar_path)
                                <img src="{{ asset('storage/' . $log->display_performer->avatar_path) }}" alt="{{ $log->display_performer->name }}" class="h-full w-full object-cover">
                            @else
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    @if ($activityIcon === 'plus')
                                        <path d="M12 5v14M5 12h14" />
                                    @elseif ($activityIcon === 'message')
                                        <path d="M20 11.5a7.5 7.5 0 0 1-8 7.5 8.4 8.4 0 0 1-3.5-.8L4 20l1.8-3.7A7.3 7.3 0 0 1 4.5 12 7.5 7.5 0 0 1 12 4.5a7.5 7.5 0 0 1 8 7Z" />
                                    @elseif ($activityIcon === 'alert')
                                        <path d="m12 4 8 15H4L12 4Z" /><path d="M12 9v4M12 16h.01" />
                                    @elseif ($activityIcon === 'check')
                                        <path d="m5 12 4 4L19 6" />
                                    @else
                                        <path d="M8 6h8M8 12h8M8 18h8" /><circle cx="4" cy="6" r="1" fill="currentColor" stroke="none" /><circle cx="4" cy="12" r="1" fill="currentColor" stroke="none" /><circle cx="4" cy="18" r="1" fill="currentColor" stroke="none" />
                                    @endif
                                </svg>
                            @endif
                        </span>
                        <div class="min-w-0 pt-0.5">
                            <p class="truncate text-[12px] font-semibold leading-tight text-foreground">{{ $activityLabel }}</p>
                            <p class="mt-0.5 truncate text-[11px] leading-snug text-muted-foreground">
                                <span>{{ $log->display_performer?->given_name ?: 'System' }} updated activity</span>
                                @if ($referenceNumber)
                                    <span class="text-border"> · </span>
                                    <span class="font-mono font-semibold text-[#7a1d2a]">{{ $referenceNumber }}</span>
                                @endif
                            </p>
                            <p class="mt-0.5 truncate text-[10px] leading-snug text-muted-foreground">
                                {{ $log->created_at?->copy()->setTimezone('Asia/Manila')->format('M d, Y g:i A') }}
                            </p>
                        </div>
                        <span class="shrink-0 pt-1 text-[11px] text-muted-foreground">{{ $log->created_at?->diffForHumans() ?? 'Recently' }}</span>
                    </li>
                @empty
                    <li class="py-6 text-center text-[11px] text-muted-foreground">No recent activity.</li>
                @endforelse
            </ol>
        </section>
    </div>
    </div>

    {{-- Desktop: the original dashboard --}}
    <div class="hidden lg:block">
@if ($waitingNotice['review'] + $waitingNotice['stalled'] > 0)
    <div class="mb-2 flex flex-wrap items-center justify-between gap-x-3 gap-y-1 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900" data-waiting-notice>
        <p class="min-w-0">
            <span class="font-semibold">Waiting {{ \App\Support\TicketProgress::ATTENTION_DAYS }} days or more:</span>
            @if ($waitingNotice['review'])
                {{ $waitingNotice['review'] }} {{ \Illuminate\Support\Str::plural('ticket', $waitingNotice['review']) }} for your review{{ $waitingNotice['stalled'] ? ',' : '.' }}
            @endif
            @if ($waitingNotice['stalled'])
                {{ $waitingNotice['stalled'] }} with staff and no action.
            @endif
        </p>
        <a data-admin-page-nav href="{{ $waitingNotice['review'] ? route('admin.tickets.review.index') : route('admin.complaints.index') }}" class="shrink-0 font-semibold text-amber-900 underline">View tickets</a>
    </div>
@endif
    <div class="flex items-stretch gap-2 pt-0">
        <div class="self-start flex flex-col gap-2">
            <div class="grid w-[526px] grid-cols-4 items-stretch gap-2">
                <div>
                    <x-stat-card compact label="Tickets" :value="$totalTickets" tone="primary" />
                </div>
                <div>
                    <x-stat-card compact label="Students" :value="$totalStudents" />
                </div>
                <div>
                    <x-stat-card compact label="Recipients" :value="$totalRecipients" tone="danger" />
                </div>
                <div>
                    <x-stat-card compact label="Categories" :value="$totalCategories" />
                </div>
            </div>

            <div class="flex w-[526px] items-stretch gap-2">
                <div class="w-[257px] shrink-0">
                    @php
                        $statusLegend = [
                            'submitted' => ['label' => 'Submitted', 'color' => '#facc15', 'count' => (int) (($statusBreakdown['submitted'] ?? 0) + ($statusBreakdown['needs_clarification'] ?? 0))],
                            'in_progress' => ['label' => 'In Progress', 'color' => '#2563eb', 'count' => (int) (($statusBreakdown['assigned'] ?? 0) + ($statusBreakdown['in_progress'] ?? 0) + ($statusBreakdown['referred'] ?? 0))],
                            'escalated' => ['label' => 'Escalated', 'color' => '#dc2626', 'count' => (int) ($statusBreakdown['escalated'] ?? 0)],
                            'resolved' => ['label' => 'Resolved', 'color' => '#16a34a', 'count' => (int) ($statusBreakdown['resolved'] ?? 0)],
                            'closed' => ['label' => 'Closed', 'color' => '#9ca3af', 'count' => (int) ($statusBreakdown['closed'] ?? 0)],
                        ];
                        $statusSegments = collect($statusLegend)->values()->all();
                        $statusTotal = array_sum(array_column($statusSegments, 'count')) ?: 0;
                        $statusCircumference = 2 * pi() * 38;
                        $statusOffset = 0;
                    @endphp

                    <div class="mt-0 h-[128px] rounded-[20px] border border-border bg-white p-1.5 shadow-sm sm:h-[134px]">
                        <div class="flex items-start gap-1.5 pl-0.5 sm:gap-2 sm:pl-1">
                            <div class="shrink-0">
                                <div class="relative h-28 w-28 sm:h-30 sm:w-30">
                                    <x-donut-ring :segments="$statusSegments" class="h-full w-full drop-shadow-sm" />
                                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                                        <span class="text-lg font-bold text-foreground sm:text-xl">{{ $statusTotal }}</span>
                                        <span class="text-[8px] text-muted-foreground">Tickets</span>
                                    </div>
                                </div>
                            </div>

                            <div class="min-w-0 flex-1 pt-0.5">
                                <div class="text-left text-xs font-semibold text-black sm:text-sm">Status</div>
                                <div class="mt-1.5 flex flex-col gap-0.5 text-[9px] text-foreground sm:text-[10px]">
                                    @foreach ($statusSegments as $segment)
                                        <div class="flex items-center gap-1.5">
                                            <span class="h-2.5 w-2.5 rounded-sm" style="background-color: {{ $segment['color'] }};"></span>
                                            <span>{{ $segment['label'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="w-[257px] shrink-0">
                    @php
                        $classificationLegend = [
                            'needs_resolution' => ['label' => 'Needs Resolution', 'color' => '#7a1d2a', 'count' => (int) ($classificationBreakdown['needs_resolution'] ?? 0)],
                            'informational' => ['label' => 'Informational', 'color' => '#111111', 'count' => (int) ($classificationBreakdown['informational'] ?? 0)],
                            'invalid' => ['label' => 'Invalid', 'color' => '#dc2626', 'count' => (int) ($classificationBreakdown['invalid'] ?? 0)],
                        ];
                        $classificationSegments = collect($classificationLegend)->values()->all();
                        $classificationTotal = array_sum(array_column($classificationSegments, 'count')) ?: 0;
                        $classificationCircumference = 2 * pi() * 38;
                        $classificationOffset = 0;
                    @endphp

                    <div class="mt-0 h-[128px] rounded-[20px] border border-border bg-white p-1.5 shadow-sm sm:h-[134px]">
                        <div class="flex items-start gap-1.5 pl-0.5 sm:gap-2 sm:pl-1">
                            <div class="shrink-0">
                                <div class="relative h-28 w-28 sm:h-30 sm:w-30">
                                    <x-donut-ring :segments="$classificationSegments" class="h-full w-full drop-shadow-sm" />
                                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                                        <span class="text-lg font-bold text-foreground sm:text-xl">{{ $classificationTotal }}</span>
                                        <span class="text-[8px] text-muted-foreground">Tickets</span>
                                    </div>
                                </div>
                            </div>

                            <div class="min-w-0 flex-1 pt-0.5">
                                <div class="text-left text-xs font-semibold text-black sm:text-sm">Classification</div>
                                <div class="mt-1.5 flex flex-col gap-0.5 text-[9px] text-foreground sm:text-[10px]">
                                    @foreach ($classificationSegments as $segment)
                                        <div class="flex items-center gap-1.5">
                                            <span class="h-2.5 w-2.5 rounded-sm" style="background-color: {{ $segment['color'] }};"></span>
                                            <span>{{ $segment['label'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <section class="w-[526px] overflow-hidden rounded-xl border border-border bg-white shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b border-border bg-muted/50 px-4 py-2.5">
                    <h3 class="text-sm font-semibold text-foreground">Recent Tickets</h3>
                    <a data-admin-page-nav href="{{ route('admin.complaints.index') }}" class="text-xs font-medium text-primary hover:underline">View All</a>
                </div>

                <div class="overflow-hidden">
                    <table class="min-w-full border-collapse text-left text-sm">
                        <thead class="border-b bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="w-[110px] px-3 py-2 font-semibold">Ticket ID</th>
                                <th class="min-w-[220px] px-3 py-2 font-semibold">Subject Title</th>
                                <th class="w-[110px] px-3 py-2 text-center font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentTickets as $ticket)
                                <tr class="border-t border-border transition-colors hover:bg-muted/30">
                                    <td class="whitespace-nowrap px-3 py-2 font-mono text-[11px] font-semibold text-primary">
                                        {{ $ticket->complaint?->reference_number ?? $ticket->id }}
                                    </td>
                                    <td class="max-w-[220px] truncate px-3 py-2 text-xs text-foreground">
                                        {{ $ticket->complaint?->public_subject ?? 'No subject' }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        @php
                                            $recentStatusLabel = $ticket->status_label;
                                        @endphp
                                        <x-status-badge :status="$recentStatusLabel" :show-icon="false" class="px-2 py-0 text-[11px]" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-3 py-6 text-center text-sm text-muted-foreground">No recent tickets.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="grid w-[360px] flex-1 grid-rows-[auto_380px] gap-2 self-stretch overflow-visible pt-0">
            @php
                $monthOptions = [
                    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
                    7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
                ];
            @endphp
            <div
                data-volume-chart
                class="flex min-h-0 flex-col overflow-visible rounded-[20px] border border-border bg-white p-3 shadow-sm"
            >
                <div class="relative z-40 flex items-center justify-between gap-2">
                    <h3 data-volume-title class="shrink-0 whitespace-nowrap font-display text-sm font-semibold leading-tight text-foreground">Volume by category</h3>
                    <div class="flex min-w-0 flex-1 items-center justify-end gap-1.5">
                        <details x-data="{}" class="group relative min-w-0 max-w-80 flex-1" x-on:click.outside="$el.removeAttribute('open')" data-volume-category-wrapper>
                            <summary class="flex h-8 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-transparent px-3 py-1.5 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden" data-volume-category-summary>
                                <span class="truncate" data-volume-category-label>All categories</span>
                                <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                            </summary>
                            <div class="absolute top-full right-0 z-50 mt-1 max-h-72 w-full min-w-56 overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                <button type="button" class="relative flex w-full items-center rounded-sm py-1 px-2 text-xs hover:bg-accent hover:text-accent-foreground bg-primary-soft text-primary" data-category-option="" data-selected="true">
                                    All categories
                                </button>
                                @foreach ($volumeChart['categories'] as $category)
                                    <button type="button" class="relative flex w-full items-center rounded-sm py-1 px-2 text-left text-xs leading-snug hover:bg-accent hover:text-accent-foreground" data-category-option="{{ $category['id'] }}">
                                        <span class="min-w-0 wrap-break-word">{{ $category['name'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                            <select id="volume-chart-category" data-volume-category class="hidden" aria-hidden="true"></select>
                        </details>

                        <details x-data="{}" class="group relative w-36 shrink-0" x-on:click.outside="$el.removeAttribute('open')" data-volume-month-wrapper>
                            <span aria-hidden="true" class="invisible block h-0 whitespace-nowrap">All months</span>
                            <summary class="flex h-8 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-transparent px-3 py-1.5 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden" data-volume-month-summary>
                                <span class="truncate" data-volume-month-label>All months</span>
                                <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                            </summary>
                            <div class="absolute top-full right-0 z-50 mt-1 max-h-72 w-full overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                <button type="button" class="relative flex w-full items-center whitespace-nowrap rounded-sm py-1 px-2 text-xs hover:bg-accent hover:text-accent-foreground bg-primary-soft text-primary" data-month-option="" data-selected="true">
                                    All months
                                </button>
                                @foreach ($monthOptions as $monthNumber => $monthLabel)
                                    <button type="button" class="relative flex w-full items-center whitespace-nowrap rounded-sm py-1 px-2 text-xs hover:bg-accent hover:text-accent-foreground" data-month-option="{{ $monthNumber }}">
                                        {{ $monthLabel }}
                                    </button>
                                @endforeach
                            </div>
                            <select id="volume-chart-month" data-volume-month class="hidden" aria-hidden="true"></select>
                        </details>

                        <details x-data="{}" class="group relative w-20 shrink-0" x-on:click.outside="$el.removeAttribute('open')" data-volume-year-wrapper>
                            <span aria-hidden="true" class="invisible block h-0 whitespace-nowrap">2026</span>
                            <summary class="flex h-8 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-transparent px-3 py-1.5 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden" data-volume-year-summary>
                                <span class="truncate" data-volume-year-label>{{ (int) $volumeChart['currentYear'] }}</span>
                                <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                            </summary>
                            <div class="absolute top-full right-0 z-50 mt-1 max-h-72 w-full overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                @foreach ($volumeChart['years'] as $year)
                                    <button type="button" class="relative flex w-full items-center rounded-sm py-1 px-2 text-xs whitespace-nowrap hover:bg-accent hover:text-accent-foreground{{ (int) $year === (int) $volumeChart['currentYear'] ? ' bg-primary-soft text-primary' : '' }}" data-year-option="{{ $year }}"{{ (int) $year === (int) $volumeChart['currentYear'] ? ' data-selected="true"' : '' }}>
                                        {{ $year }}
                                    </button>
                                @endforeach
                            </div>
                            <select id="volume-chart-year" data-volume-year class="hidden" aria-hidden="true"></select>
                        </details>

                        <a data-admin-page-nav href="{{ route('admin.analytics.index') }}" class="shrink-0 whitespace-nowrap text-xs font-medium text-primary hover:underline">View All</a>
                    </div>
                </div>
                <div class="relative z-0 mt-2 h-40 w-full">
                    <canvas class="absolute inset-0" data-volume-canvas aria-label="Complaint volume chart"></canvas>
                </div>
                <ul data-volume-legend class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-[11px] text-foreground" aria-label="Categories"></ul>
            </div>
            <div class="grid min-h-95 grid-cols-2 gap-2">
                <div class="flex h-full min-h-0 flex-col overflow-hidden rounded-[20px] border border-border bg-white p-2 shadow-sm">
                    <div class="flex items-center justify-between gap-2 px-1">
                        <h3 class="font-display text-sm font-semibold leading-tight text-foreground">Recipient Performance</h3>
                        <a data-admin-page-nav href="{{ route('admin.analytics.index') }}" class="text-xs font-medium text-primary hover:underline">View All</a>
                    </div>

                    <div class="mt-2 grid min-h-0 flex-1 grid-rows-5 gap-1.5 overflow-hidden">
                        @forelse ($topPerformingRecipients as $recipient)
                            <div class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-muted/50 transition-colors">
                                <div class="h-10 w-10 shrink-0 rounded-full border border-border bg-sidebar-primary overflow-hidden flex items-center justify-center text-[10px] font-bold text-sidebar-primary-foreground">
                                    @if ($recipient->user?->avatar_path)
                                        <img src="{{ asset('storage/' . $recipient->user->avatar_path) }}" alt="{{ $recipient->display_name }}" class="h-full w-full object-cover">
                                    @else
                                        {{ collect(explode(' ', $recipient->display_name ?? 'U'))->filter()->map(fn ($part) => substr($part, 0, 1))->take(2)->join('') }}
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <span class="relative inline-block" x-data="{ recipientProfileOpen: false }" x-on:mouseenter="recipientProfileOpen = true" x-on:mouseleave="recipientProfileOpen = false">
                                        <p class="truncate text-[12px] font-semibold text-foreground cursor-pointer hover:text-primary hover:underline">
                                            {{ $recipient->display_name ?? 'Unknown' }}
                                        </p>
                                        
                                        <!-- Profile Tooltip -->
                                        <span x-show="recipientProfileOpen" x-cloak data-keep-in-view class="brand-gradient absolute top-8 left-0 z-50 w-56 max-w-[calc(100vw-2rem)] rounded-xl p-3 text-left text-primary-foreground shadow-lg">
                                            <span class="flex items-center gap-2">
                                                <span class="grid h-10 w-10 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-foreground/15 text-xs font-bold ring-2 ring-primary-foreground/30">
                                                    @if ($recipient->user?->avatar_path)
                                                        <img src="{{ asset('storage/' . $recipient->user->avatar_path) }}" alt="{{ $recipient->display_name }}" class="h-full w-full object-cover">
                                                    @else
                                                        {{ collect(explode(' ', $recipient->display_name ?? 'U'))->filter()->map(fn ($part) => substr($part, 0, 1))->take(2)->join('') }}
                                                    @endif
                                                </span>
                                                <span class="min-w-0">
                                                    <span class="block wrap-break-word text-sm font-bold">{{ $recipient->display_name ?? 'Unknown' }}</span>
                                                    <span class="mt-0.5 block wrap-break-word text-[10px] leading-relaxed opacity-95">ID {{ $recipient->staff_id }} · {{ $recipient->unit }} · {{ $recipient->designation }}</span>
                                                </span>
                                            </span>
                                            <span class="mt-2 inline-flex rounded-full border border-primary-foreground/40 bg-primary-foreground/15 px-2 py-0.5 text-[9px] font-semibold">{{ $recipient->role }}</span>
                                        </span>
                                    </span>
                                    
                                    <div class="mt-1 flex items-center gap-1">
                                        <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                                            <div class="h-full bg-green-500 rounded-full" style="width: {{ $recipient->performance_percentage ?? 0 }}%"></div>
                                        </div>
                                        <span class="text-[8px] font-semibold text-green-700 shrink-0">{{ $recipient->performance_percentage ?? 0 }}%</span>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="flex items-center justify-center h-full text-center text-[11px] text-muted-foreground">
                                No recipient data available.
                            </div>
                        @endforelse
                    </div>
                </div>
                <div class="flex h-full min-h-0 flex-col overflow-hidden rounded-[20px] border border-border bg-white p-3 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="font-display text-sm font-semibold leading-tight text-foreground">Recent audit activity</h3>
                        <a data-admin-page-nav href="{{ route('admin.audit') }}" class="text-xs font-medium text-primary hover:underline">View All</a>
                    </div>

                    <ol class="relative mt-2 grid min-h-0 flex-1 grid-rows-5 gap-2.5 overflow-hidden">
                        <span class="absolute top-4 bottom-4 left-[calc(25%+1.5rem)] w-px bg-[#7a1d2a]/20" aria-hidden="true"></span>
                        @forelse ($recentActivity as $log)
                            @php
                                $activityLabel = match ($log->action) {
                                    'complaint_submitted' => 'Complaint received',
                                    'message_posted' => 'Message posted',
                                    default => ucfirst(str_replace('_', ' ', (string) $log->action)),
                                };
                                $referenceNumber = $log->ticket?->complaint?->reference_number;
                                $activityIcon = match ($log->action) {
                                    'complaint_submitted' => 'plus',
                                    'message_posted' => 'message',
                                    'ticket_escalated' => 'alert',
                                    'ticket_resolved', 'ticket_closed' => 'check',
                                    default => 'activity',
                                };
                            @endphp
                            <li class="relative z-10 grid min-w-0 grid-cols-[25%_2rem_minmax(0,1fr)] items-start gap-2">
                                <span class="flex h-8 items-center text-[11px] text-black">{{ $log->created_at?->diffForHumans() ?? 'Recently' }}</span>
                                <span class="grid h-8 w-8 shrink-0 place-items-center overflow-hidden rounded-full bg-[#7a1d2a] text-white shadow-sm ring-2 ring-[#7a1d2a]/10">
                                    @if ($log->display_performer?->avatar_path)
                                        <img src="{{ asset('storage/' . $log->display_performer->avatar_path) }}" alt="{{ $log->display_performer->name }}" class="h-full w-full object-cover">
                                    @else
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            @if ($activityIcon === 'plus')
                                                <path d="M12 5v14M5 12h14" />
                                            @elseif ($activityIcon === 'message')
                                                <path d="M20 11.5a7.5 7.5 0 0 1-8 7.5 8.4 8.4 0 0 1-3.5-.8L4 20l1.8-3.7A7.3 7.3 0 0 1 4.5 12 7.5 7.5 0 0 1 12 4.5a7.5 7.5 0 0 1 8 7Z" />
                                            @elseif ($activityIcon === 'alert')
                                                <path d="m12 4 8 15H4L12 4Z" /><path d="M12 9v4M12 16h.01" />
                                            @elseif ($activityIcon === 'check')
                                                <path d="m5 12 4 4L19 6" />
                                            @else
                                                <path d="M8 6h8M8 12h8M8 18h8" /><circle cx="4" cy="6" r="1" fill="currentColor" stroke="none" /><circle cx="4" cy="12" r="1" fill="currentColor" stroke="none" /><circle cx="4" cy="18" r="1" fill="currentColor" stroke="none" />
                                            @endif
                                        </svg>
                                    @endif
                                </span>
                                <div class="min-w-0 pt-0.5">
                                    <p class="truncate text-[11px] font-semibold leading-tight text-foreground">{{ $activityLabel }}</p>
                                    <p class="mt-0.5 truncate text-[10px] leading-snug text-muted-foreground">
                                        <span>{{ $log->display_performer?->given_name ?: 'System' }} updated activity</span>
                                        @if ($referenceNumber)
                                            <span class="text-border"> · </span>
                                            <span class="font-mono font-semibold text-[#7a1d2a]">{{ $referenceNumber }}</span>
                                        @endif
                                    </p>
                                    <p class="mt-0.5 truncate text-[9px] leading-snug text-muted-foreground">
                                        {{ $log->created_at?->copy()->setTimezone('Asia/Manila')->format('M d, Y g:i A') }}
                                    </p>
                                </div>
                            </li>
                        @empty
                            <li class="grid place-items-center text-center text-[11px] text-muted-foreground">No recent activity.</li>
                        @endforelse
                    </ol>
                </div>
            </div>
        </div>
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // The phone layout and the desktop layout each have their own volume card.
        document.querySelectorAll('[data-volume-chart]').forEach((root) => {
            if (typeof Chart === 'undefined') return;

            const payload = @json($volumeChart);
            const categories = payload.categories || [];
            const points = payload.points || [];
            const canvas = root.querySelector('[data-volume-canvas]');
            const titleEl = root.querySelector('[data-volume-title]');
            
            // Track selected values
            let selectedCategory = '';
            let selectedMonth = '';
            let selectedYear = String({{ (int) $volumeChart['currentYear'] }}) || '';
            
            const categoryHiddenSelect = root.querySelector('[data-volume-category]');
            const monthHiddenSelect = root.querySelector('[data-volume-month]');
            const yearHiddenSelect = root.querySelector('[data-volume-year]');
            
            const monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const barColors = ['#7a1d2a', '#c4785a', '#2d7a74', '#6b7a3a', '#6b5a8a', '#b8862b', '#3a5f8a', '#9a3a42', '#4f8a5b', '#8a4f7d', '#5a6470', '#c25b3f', '#2f6f8f', '#7d6b2a', '#a0526b', '#3d7d6a'];
            // Past the palette, spread further colours evenly round the colour wheel.
            const colorFor = (index) => barColors[index] ?? `hsl(${Math.round((index * 137.5) % 360)} 45% 42%)`;
            const legendEl = root.querySelector('[data-volume-legend]');
            const lineColor = '#7a1d2a';
            const muted = '#9ca3af';
            const fontSans = "'Source Sans 3', ui-sans-serif, system-ui, sans-serif";
            let chart;

            const niceMax = (values) => {
                const max = Math.max(0, ...values);
                if (max <= 4) return 4;
                const magnitude = Math.pow(10, Math.floor(Math.log10(max)));
                const padded = Math.ceil((max * 1.15) / magnitude) * magnitude;
                return padded;
            };

            const wrapChartLabel = (label, maxLineLength) => {
                const words = String(label || '').split(/\s+/).filter(Boolean);
                const lines = [];
                let line = '';

                words.forEach((word) => {
                    if (word.length > maxLineLength) {
                        if (line) {
                            lines.push(line);
                            line = '';
                        }
                        for (let index = 0; index < word.length; index += maxLineLength) {
                            lines.push(word.slice(index, index + maxLineLength));
                        }
                        return;
                    }

                    const candidate = line ? `${line} ${word}` : word;
                    if (line && candidate.length > maxLineLength) {
                        lines.push(line);
                        line = word;
                    } else {
                        line = candidate;
                    }
                });

                if (line) lines.push(line);
                return lines.length ? lines : [''];
            };

            const filterPoints = () => {
                const year = Number(selectedYear);
                const month = selectedMonth === '' ? null : Number(selectedMonth);
                const categoryId = selectedCategory === '' ? null : Number(selectedCategory);

                return points.filter((point) => {
                    if (point.year !== year) return false;
                    if (month !== null && point.month !== month) return false;
                    if (categoryId !== null && point.category_id !== categoryId) return false;
                    return true;
                });
            };

            const getSelectedCategory = () => categories.find((category) => String(category.id) === selectedCategory);

            const buildChartModel = () => {
                const filtered = filterPoints();
                const category = getSelectedCategory();

                if (!category) {
                    const values = categories.map((item) => filtered.filter((point) => point.category_id === item.id).length);
                    return {
                        type: 'bar',
                        title: 'Volume by category',
                        labels: categories.map((item) => item.name),
                        tooltipLabels: categories.map((item) => item.name),
                        values,
                        colors: categories.map((_, index) => colorFor(index)),
                    };
                }

                if (selectedMonth === '') {
                    const values = monthLabels.map((_, index) => filtered.filter((point) => point.month === index + 1).length);
                    return {
                        type: 'line',
                        title: 'Volume over time',
                        labels: monthLabels,
                        tooltipLabels: monthLabels,
                        values,
                        colors: lineColor,
                    };
                }

                const daysInMonth = new Date(Number(selectedYear), Number(selectedMonth), 0).getDate();
                const labels = ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
                const values = labels.map((_, index) => filtered.filter((point) => point.week === index + 1).length);

                return {
                    type: 'line',
                    title: 'Volume over time',
                    labels,
                    tooltipLabels: labels,
                    values,
                    colors: lineColor,
                };
            };

            const updateOptionSelected = (wrapper, optionValue) => {
                wrapper.querySelectorAll('[data-category-option], [data-month-option], [data-year-option]').forEach((btn) => {
                    const btnValue = btn.getAttribute('data-category-option') || btn.getAttribute('data-month-option') || btn.getAttribute('data-year-option');
                    const checkIcon = btn.querySelector('[data-selected], x-icons.check, .hidden');
                    if (btnValue === optionValue) {
                        btn.classList.add('bg-primary-soft', 'text-primary');
                        btn.classList.remove('hover:bg-accent', 'hover:text-accent-foreground');
                        btn.setAttribute('data-selected', 'true');
                        if (btn.querySelector('svg')) btn.querySelector('svg').classList.remove('hidden');
                    } else {
                        btn.classList.remove('bg-primary-soft', 'text-primary');
                        btn.classList.add('hover:bg-accent', 'hover:text-accent-foreground');
                        btn.removeAttribute('data-selected');
                        const svg = btn.querySelector('svg');
                        if (svg) svg.classList.add('hidden');
                    }
                });
            };

            // Category options
            root.querySelectorAll('[data-category-option]').forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    selectedCategory = btn.getAttribute('data-category-option');
                    const categoryWrapper = root.querySelector('[data-volume-category-wrapper]');
                    const categoryLabel = root.querySelector('[data-volume-category-label]');
                    const selectedOption = categoryWrapper.querySelector(`[data-category-option="${selectedCategory}"]`);
                    categoryLabel.textContent = selectedOption?.textContent?.trim() || 'All categories';
                    updateOptionSelected(categoryWrapper, selectedCategory);
                    categoryWrapper.removeAttribute('open');
                    categoryHiddenSelect.value = selectedCategory;
                    render();
                });
            });

            // Month options
            root.querySelectorAll('[data-month-option]').forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    selectedMonth = btn.getAttribute('data-month-option');
                    const monthWrapper = root.querySelector('[data-volume-month-wrapper]');
                    const monthLabel = root.querySelector('[data-volume-month-label]');
                    const selectedOption = monthWrapper.querySelector(`[data-month-option="${selectedMonth}"]`);
                    monthLabel.textContent = selectedOption?.textContent?.trim() || 'All months';
                    updateOptionSelected(monthWrapper, selectedMonth);
                    monthWrapper.removeAttribute('open');
                    monthHiddenSelect.value = selectedMonth;
                    render();
                });
            });

            // Year options
            root.querySelectorAll('[data-year-option]').forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    selectedYear = btn.getAttribute('data-year-option');
                    const yearWrapper = root.querySelector('[data-volume-year-wrapper]');
                    const yearLabel = root.querySelector('[data-volume-year-label]');
                    yearLabel.textContent = selectedYear;
                    updateOptionSelected(yearWrapper, selectedYear);
                    yearWrapper.removeAttribute('open');
                    yearHiddenSelect.value = selectedYear;
                    render();
                });
            });

            const columnHighlight = {
                id: 'volumeColumnHighlight',
                beforeDatasetsDraw(instance) {
                    const active = instance.getActiveElements()[0];
                    if (!active) return;

                    const { ctx, chartArea, scales } = instance;
                    const meta = instance.getDatasetMeta(active.datasetIndex);
                    const element = meta.data[active.index];
                    if (!element) return;

                    ctx.save();
                    if (instance.config.type === 'bar' && instance.options.indexAxis === 'y') {
                        const slot = scales.y.getPixelForValue(active.index + 0.5) - scales.y.getPixelForValue(active.index - 0.5);
                        const height = Math.max(element.height + 8, slot * 0.9);
                        ctx.fillStyle = 'rgba(122, 29, 42, 0.05)';
                        ctx.fillRect(chartArea.left, element.y - height / 2, chartArea.right - chartArea.left, height);
                    } else if (instance.config.type === 'bar') {
                        const slot = scales.x.getPixelForValue(active.index + 0.5) - scales.x.getPixelForValue(active.index - 0.5);
                        const width = Math.max(element.width + 12, slot * 0.9);
                        ctx.fillStyle = 'rgba(122, 29, 42, 0.05)';
                        ctx.fillRect(element.x - width / 2, chartArea.top, width, chartArea.bottom - chartArea.top);
                    } else {
                        ctx.strokeStyle = 'rgba(156, 163, 175, 0.7)';
                        ctx.lineWidth = 1;
                        ctx.beginPath();
                        ctx.moveTo(element.x, chartArea.top);
                        ctx.lineTo(element.x, chartArea.bottom);
                        ctx.stroke();
                    }
                    ctx.restore();
                },
            };

            const render = () => {
                const model = buildChartModel();
                titleEl.textContent = model.title;
                const yMax = niceMax(model.values);
                // Category names would collide under the bars once there are many, so each bar gets
                // a colour and the names are listed below the chart with a matching square.
                const horizontal = false;
                if (legendEl) {
                    legendEl.hidden = model.type !== 'bar';
                    legendEl.replaceChildren(...(model.type === 'bar' ? model.labels.map((name, index) => {
                        const item = document.createElement('li');
                        item.className = 'flex min-w-0 items-center gap-1.5';
                        item.title = name;
                        const swatch = document.createElement('span');
                        swatch.className = 'h-2.5 w-2.5 shrink-0 rounded-sm';
                        swatch.style.backgroundColor = model.colors[index];
                        const label = document.createElement('span');
                        label.className = 'min-w-0 flex-1 truncate';
                        label.textContent = name;
                        const count = document.createElement('span');
                        count.className = 'shrink-0 font-semibold tabular-nums';
                        count.textContent = model.values[index];
                        item.append(swatch, label, count);
                        return item;
                    }) : []));
                }

                const dataset = model.type === 'bar'
                    ? {
                        data: model.values,
                        backgroundColor: model.colors,
                        borderRadius: horizontal
                            ? { topLeft: 0, topRight: 6, bottomLeft: 0, bottomRight: 6 }
                            : { topLeft: 6, topRight: 6, bottomLeft: 0, bottomRight: 0 },
                        borderSkipped: false,
                        maxBarThickness: 28,
                        categoryPercentage: 0.72,
                        barPercentage: 0.78,
                    }
                    : {
                        data: model.values,
                        borderColor: lineColor,
                        backgroundColor: lineColor,
                        borderWidth: 2,
                        tension: 0.35,
                        fill: false,
                        pointRadius: 4,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: lineColor,
                        pointBorderWidth: 2,
                    };

                const categoryAxis = {
                    grid: { display: false, drawBorder: false },
                    offset: true,
                    ticks: {
                        color: muted,
                        font: { family: fontSans, size: 10 },
                        maxRotation: 0,
                        minRotation: 0,
                        autoSkip: model.type !== 'bar',
                        callback: (value, index) => {
                            if (model.type !== 'bar') return model.labels[index];

                            const maxLineLength = model.labels.length <= 2
                                ? 28
                                : model.labels.length <= 4
                                    ? 22
                                    : 14;

                            return wrapChartLabel(model.labels[index], maxLineLength);
                        },
                    },
                    border: { display: false },
                };
                if (model.type === 'bar') categoryAxis.ticks.display = false;
                const valueAxis = {
                    beginAtZero: true,
                    suggestedMax: yMax,
                    ticks: {
                        color: muted,
                        font: { family: fontSans, size: 10 },
                        precision: 0,
                    },
                    grid: {
                        color: '#e5e7eb',
                        borderDash: [4, 4],
                        drawTicks: false,
                    },
                    border: { display: false },
                };

                const config = {
                    type: model.type,
                    data: {
                        labels: model.labels,
                        datasets: [dataset],
                    },
                    plugins: [columnHighlight],
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: horizontal ? 'y' : 'x',
                        interaction: { mode: 'index', intersect: false, axis: horizontal ? 'y' : 'x' },
                        layout: {
                            padding: { top: 12, bottom: 8, left: 8, right: 8 },
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#ffffff',
                                titleColor: muted,
                                bodyColor: lineColor,
                                titleFont: { family: fontSans, size: 11, weight: '500' },
                                bodyFont: { family: fontSans, size: 11, weight: '600' },
                                borderColor: '#e5e7eb',
                                borderWidth: 1,
                                cornerRadius: 8,
                                padding: 8,
                                displayColors: false,
                                callbacks: {
                                    title: (items) => model.tooltipLabels[items[0]?.dataIndex] ?? '',
                                    label: (item) => `value : ${model.values[item.dataIndex]}`,
                                },
                            },
                        },
                        scales: horizontal
                            ? { x: valueAxis, y: categoryAxis }
                            : { x: categoryAxis, y: valueAxis },
                    },
                };

                if (chart) {
                    chart.destroy();
                }
                chart = new Chart(canvas, config);
            };

            render();

        });
    </script>
</x-app-layout>
