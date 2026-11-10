<x-app-layout :role="'admin'" title="Analytics">
    @php
        $data = $dashboard;
        $total = (int) $data['total'];
        $percent = fn ($count, $of = null) => ($of ?? $total) > 0 ? round($count / ($of ?? $total) * 100, 1) : 0;
        $days = fn ($value) => $value === null ? 'Not available' : number_format((float) $value, 1) . ' days';
        $formatDate = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('M j, Y') : null;
        $periodLabel = ($filters['start_date'] ?? null) || ($filters['end_date'] ?? null)
            ? ($formatDate($filters['start_date'] ?? null) ?? 'Start') . ' – ' . ($formatDate($filters['end_date'] ?? null) ?? 'Today')
            : 'All time';
        $statusFilterClassification = $filters['classification'] ?? '';
        $statusFilterEnabled = in_array($statusFilterClassification, [
            \App\Models\Ticket::CLASSIFICATION_NEEDS_RESOLUTION,
            'unclassified',
        ], true);
        $activeFilters = collect([
            ($filters['category_id'] ?? null) ? $categoryOptions->firstWhere('id', $filters['category_id'])?->name : null,
            ($filters['classification'] ?? null) ? ucwords(str_replace('_', ' ', $filters['classification'])) : null,
            ($filters['status'] ?? null) ? (\App\Models\Ticket::DISPLAY_STATUS_LABELS[$filters['status']] ?? ucfirst(str_replace('_', ' ', $filters['status']))) : null,
            $filters['unit'] ?? null,
        ])->filter()->values();

        $notCounted = collect($data['resolutionMetrics']['excluded'] ?? [])
            ->filter()
            ->map(fn ($count, $type) => str_replace('_', ' ', $type) . ' ' . $count)
            ->implode(', ');
        $needsResolution = $data['tickets']->where('classification', \App\Models\Ticket::CLASSIFICATION_NEEDS_RESOLUTION);
        $escalationCounts = $needsResolution->map(fn ($ticket) => $ticket->auditLogs->where('action', 'ticket_escalated')->count());
        $escalationTotal = $needsResolution->count();
        $escalatedTickets = $escalationCounts->filter(fn ($count) => $count > 0)->count();
        $escalationRate = $escalationTotal ? round($escalatedTickets / $escalationTotal * 100, 1) : 0;
        $escalationSegments = [
            ['label' => 'Never escalated', 'count' => $escalationCounts->filter(fn ($count) => $count === 0)->count(), 'color' => '#9ca3af'],
            ['label' => 'Escalated once', 'count' => $escalationCounts->filter(fn ($count) => $count === 1)->count(), 'color' => '#c4785a'],
            ['label' => 'Escalated more than once', 'count' => $escalationCounts->filter(fn ($count) => $count > 1)->count(), 'color' => '#7a1d2a'],
        ];
        $escalationMax = max(1, $escalationTotal);
        $statusCounts = $data['statusCounts'];
        $statusColors = [
            'submitted' => '#7a1d2a',
            'needs_clarification' => '#b45309',
            'assigned' => '#64748b',
            'in_progress' => '#2563eb',
            'escalated' => '#b91c1c',
            'referred' => '#7c3aed',
            'resolved' => '#15803d',
            'closed' => '#9ca3af',
        ];
        $statusSegments = collect(\App\Models\Ticket::DISPLAY_STATUS_LABELS)
            ->map(fn ($label, $status) => [
                'label' => $label,
                'count' => (int) ($statusCounts[$label] ?? 0),
                'color' => $statusColors[$status],
            ])
            ->values()
            ->all();
        $statusMax = max(1, array_sum(array_column($statusSegments, 'count')));

        $categoryCounts = collect($data['categoryCounts'])->filter(fn ($count) => $count > 0)->sortDesc();
        $topCategories = $categoryCounts->take(5);
        $otherCategoryCount = max(0, $categoryCounts->count() - $topCategories->count());
        $otherTicketCount = $categoryCounts->slice(5)->sum();
        $officeRows = collect($data['units'])->sortByDesc('total')->take(5);

        $volumeByMonth = [];
        foreach ($data['tickets'] as $ticket) {
            $submittedAt = $ticket->complaint?->created_at;
            if ($submittedAt) {
                $month = $submittedAt->format('Y-m');
                $volumeByMonth[$month] ??= ['submitted' => 0, 'resolved' => 0];
                $volumeByMonth[$month]['submitted']++;
            }

            if (
                $ticket->classification === \App\Models\Ticket::CLASSIFICATION_NEEDS_RESOLUTION
                && in_array($ticket->status, [\App\Models\Ticket::STATUS_RESOLVED, \App\Models\Ticket::STATUS_CLOSED], true)
                && $ticket->resolved_at
            ) {
                $month = $ticket->resolved_at->format('Y-m');
                $volumeByMonth[$month] ??= ['submitted' => 0, 'resolved' => 0];
                $volumeByMonth[$month]['resolved']++;
            }
        }
        ksort($volumeByMonth);
        $volumeMonths = collect($volumeByMonth)->take(-12);
        $volumeMax = max(1, $volumeMonths->max(fn ($counts) => max($counts['submitted'], $counts['resolved'])) ?? 0);

        $resolutionTime = $data['resolutionTime'];
        $performance = $data['performanceMetrics'];
        $breakdowns = $data['breakdowns'];
        $satisfaction = $breakdowns['satisfaction'];
        $studentDimensions = [
            'colleges' => ['label' => 'College', 'rows' => $breakdowns['colleges']],
            'programs' => ['label' => 'Program', 'rows' => $breakdowns['programs']],
            'year_levels' => ['label' => 'Year level', 'rows' => $breakdowns['year_levels']],
            'blocks' => ['label' => 'Block', 'rows' => $breakdowns['blocks']],
        ];
        $originRows = $breakdowns['origin'];
        $ratingDistribution = $satisfaction['distribution'];

        $staffRows = collect($performance['handler_rows'])
            ->sort(fn ($left, $right) => $right['open_tickets'] <=> $left['open_tickets']
                ?: ($right['oldest_open_ticket_age_days'] ?? -1) <=> ($left['oldest_open_ticket_age_days'] ?? -1))
            ->values();
        $officePerformanceRows = collect($performance['office_rows'])
            ->sort(fn ($left, $right) => $right['open_tickets'] <=> $left['open_tickets']
                ?: ($right['oldest_open_ticket_age_days'] ?? -1) <=> ($left['oldest_open_ticket_age_days'] ?? -1))
            ->values();

        $filterDropdowns = [
            ['name' => 'category_id', 'label' => 'Category', 'all' => 'All categories', 'options' => $categoryOptions->mapWithKeys(fn ($category) => [(string) $category->id => $category->name])->all()],
            ['name' => 'classification', 'label' => 'Classification', 'all' => 'All classifications', 'options' => ['needs_resolution' => 'Needs Resolution', 'informational' => 'Informational', 'invalid' => 'Invalid', 'unclassified' => 'Unclassified']],
            ['name' => 'status', 'label' => 'Status', 'all' => 'All statuses', 'options' => \App\Models\Ticket::DISPLAY_STATUS_LABELS],
            ['name' => 'unit', 'label' => 'College / Office', 'all' => 'All colleges and offices', 'options' => $unitOptions->mapWithKeys(fn ($unit) => [$unit => $unit])->all()],
        ];
        $reportSections = \App\Http\Controllers\Admin\AnalyticsController::REPORT_SECTIONS;
        $cardClass = 'min-w-0 rounded-xl border border-border bg-white p-4 shadow-sm';
        $titleClass = 'font-display text-base font-bold text-foreground';
        $mutedClass = 'text-xs text-muted-foreground';
    @endphp

    <div class="mx-auto grid min-w-0 max-w-7xl gap-4 pb-6">
        <header class="flex flex-wrap items-center justify-between gap-2">
            <div class="min-w-0">
                <h1 class="font-display text-xl font-bold text-foreground">Analytics</h1>
                <p class="text-sm font-semibold text-foreground">{{ $periodLabel }}</p>
                <p class="flex flex-wrap gap-x-2 text-xs text-muted-foreground">
                    <span>{{ number_format($total) }} ticket(s)</span>
                    @forelse ($activeFilters as $label)
                        <span>· {{ $label }}</span>
                    @empty
                        <span>· No filters applied</span>
                    @endforelse
                </p>
            </div>
            <div x-data="{ open: @js($errors->has('sections')), chosen: @js(array_keys($reportSections)), all: @js(array_keys($reportSections)) }" class="shrink-0">
                <button type="button" x-on:click="open = true" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-md bg-primary px-3 text-xs font-semibold text-primary-foreground hover:bg-primary/90">
                    <x-icons.download class="h-4 w-4" /> Generate Report
                </button>
                <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                    <form method="GET" action="{{ route('admin.analytics.export.pdf') }}" x-on:click.outside="open = false" x-on:submit="setTimeout(() => open = false, 300)" class="flex max-h-[min(85vh,40rem)] w-full max-w-md flex-col overflow-hidden rounded-xl border border-border bg-white text-left shadow-2xl" role="dialog" aria-modal="true" aria-label="Generate report">
                        @foreach (request()->query() as $name => $value)
                            @if (is_scalar($value) && $name !== 'sections')
                                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                            <p class="text-xs leading-relaxed text-muted-foreground">Choose the parts to include. The report covers the filters applied on this page and is printed on the university letterhead.</p>
                            <div class="mt-3 flex items-center justify-between">
                                <p class="text-xs font-semibold text-foreground"><span x-text="chosen.length"></span> of {{ count($reportSections) }} selected</p>
                                <div class="flex gap-3 text-xs font-semibold text-primary">
                                    <button type="button" x-on:click="chosen = [...all]" class="hover:underline">Select all</button>
                                    <button type="button" x-on:click="chosen = []" class="hover:underline">Clear</button>
                                </div>
                            </div>
                            <div class="mt-2 grid gap-1">
                                @foreach ($reportSections as $key => $label)
                                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm text-foreground hover:bg-muted/60" x-bind:class="chosen.includes(@js($key)) ? 'border-primary/30 bg-primary-soft/50' : ''">
                                        <input type="checkbox" name="sections[]" value="{{ $key }}" x-model="chosen" class="h-4 w-4 accent-red-800">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('sections')
                                <p class="mt-2 text-xs font-medium text-destructive">{{ $message }}</p>
                            @enderror
                            <p x-show="chosen.length === 0" x-cloak class="mt-2 text-xs font-medium text-destructive">Choose at least one part.</p>
                        </div>
                        <div class="flex justify-end gap-2 border-t border-border px-5 py-3">
                            <button type="button" x-on:click="open = false" class="inline-flex h-9 items-center rounded-full border border-border bg-white px-4 text-xs font-semibold text-foreground hover:bg-muted">Cancel</button>
                            <button type="submit" x-bind:disabled="chosen.length === 0" class="inline-flex h-9 items-center gap-1.5 rounded-full bg-primary px-5 text-xs font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-50"><x-icons.download class="h-4 w-4" /> Download PDF</button>
                        </div>
                    </form>
                </div>
            </div>
        </header>

        <form method="GET" data-analytics-filter-form x-data="{ open: window.innerWidth >= 768 }" class="{{ $cardClass }} relative z-20 p-3">
            <button type="button" x-on:click="open = !open" class="flex w-full items-center justify-between gap-2 text-left md:pointer-events-none">
                <span class="flex items-center gap-2 text-sm font-bold text-primary">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 5h16l-6 7v5l-4 2v-7z" /></svg>
                    Filters
                    @if ($activeFilters->isNotEmpty())
                        <span class="rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold text-primary-foreground">{{ $activeFilters->count() }}</span>
                    @endif
                </span>
                <x-icons.arrow-down class="h-4 w-4 text-muted-foreground transition-transform md:hidden" ::class="open ? '' : 'rotate-180'" />
            </button>
            <div x-show="open" x-cloak class="mt-3 grid grid-cols-2 gap-2 lg:grid-cols-4 xl:grid-cols-[1.4fr_1fr_1fr_1fr_1fr_auto]">
                <div class="col-span-2 lg:col-span-4 xl:col-span-1">
                    <label class="mb-1 block text-[11px] font-semibold text-muted-foreground">Date range</label>
                    <div class="flex items-center gap-1.5">
                        <input type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}" aria-label="Start date" class="h-9 min-w-0 flex-1 rounded-md border border-input bg-white px-2 text-xs">
                        <span class="shrink-0 text-xs text-muted-foreground">to</span>
                        <input type="date" name="end_date" value="{{ $filters['end_date'] ?? '' }}" aria-label="End date" class="h-9 min-w-0 flex-1 rounded-md border border-input bg-white px-2 text-xs">
                    </div>
                </div>
                @foreach ($filterDropdowns as $dropdown)
                    @php
                        $isStatusFilter = $dropdown['name'] === 'status';
                        $current = $isStatusFilter && ! $statusFilterEnabled
                            ? ''
                            : (string) ($filters[$dropdown['name']] ?? '');
                    @endphp
                    <div class="min-w-0">
                        <label class="mb-1 block text-[11px] font-semibold text-muted-foreground">{{ $dropdown['label'] }}</label>
                        <details
                            class="group relative {{ $isStatusFilter && ! $statusFilterEnabled ? 'pointer-events-none opacity-70' : '' }}"
                            @if ($isStatusFilter)
                                data-analytics-status-dropdown
                                data-disabled="{{ $statusFilterEnabled ? 'false' : 'true' }}"
                            @endif
                            x-on:click.outside="$el.removeAttribute('open')"
                        >
                            <summary
                                class="flex h-9 cursor-pointer list-none items-center justify-between gap-1 rounded-md border border-input px-2.5 text-xs shadow-sm [&::-webkit-details-marker]:hidden {{ $isStatusFilter && ! $statusFilterEnabled ? 'bg-muted text-muted-foreground' : 'bg-white hover:bg-muted' }}"
                                @if ($isStatusFilter)
                                    data-analytics-status-summary
                                    aria-disabled="{{ $statusFilterEnabled ? 'false' : 'true' }}"
                                    tabindex="{{ $statusFilterEnabled ? '0' : '-1' }}"
                                @endif
                            >
                                <span class="truncate">{{ $dropdown['options'][$current] ?? $dropdown['all'] }}</span>
                                <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                            </summary>
                            <div class="absolute top-full z-50 mt-1 max-h-72 w-full min-w-44 overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                @foreach (['' => $dropdown['all']] + $dropdown['options'] as $value => $label)
                                    @php
                                        $statusOptionVisible = ! $isStatusFilter
                                            || ($statusFilterEnabled
                                                && ($value === ''
                                                    || $statusFilterClassification === \App\Models\Ticket::CLASSIFICATION_NEEDS_RESOLUTION
                                                    || in_array($value, \App\Models\Ticket::UNCLASSIFIED_DISPLAY_STATUSES, true)));
                                    @endphp
                                    <button
                                        type="button"
                                        data-analytics-filter="{{ $dropdown['name'] }}"
                                        data-value="{{ $value }}"
                                        data-label="{{ $label }}"
                                        @if ($isStatusFilter)
                                            data-analytics-status-option
                                            @if (! $statusOptionVisible) hidden @endif
                                        @endif
                                        class="flex w-full rounded-sm px-2 py-1.5 text-left text-xs {{ $current === (string) $value ? 'bg-primary-soft text-primary' : 'hover:bg-accent' }}"
                                    >
                                        <span class="wrap-break-word">{{ $label }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </details>
                        @if ($isStatusFilter)
                            <p data-analytics-status-help @if ($statusFilterEnabled) hidden @endif class="mt-1 text-[10px] text-muted-foreground">Select Needs Resolution to filter by status</p>
                        @endif
                        <input type="hidden" name="{{ $dropdown['name'] }}" value="{{ $current }}" data-analytics-filter-input="{{ $dropdown['name'] }}">
                    </div>
                @endforeach
                <div class="col-span-2 flex items-end gap-2 lg:col-span-4 xl:col-span-1">
                    <button class="inline-flex h-9 flex-1 items-center justify-center rounded-md bg-primary px-4 text-xs font-semibold text-primary-foreground hover:bg-primary/90 xl:flex-none">Apply</button>
                    <a href="{{ route('admin.analytics.index') }}" class="inline-flex h-9 flex-1 items-center justify-center rounded-md border border-primary/40 px-4 text-xs font-semibold text-primary hover:bg-primary-soft xl:flex-none">Reset</a>
                </div>
            </div>
        </form>

        <section class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-6" aria-label="Analytics summary">
            <article class="{{ $cardClass }} p-3">
                <h2 class="text-[11px] font-semibold text-muted-foreground">Total tickets</h2>
                <p class="mt-1 font-display text-2xl font-bold leading-none text-primary">{{ number_format($total) }}</p>
                <p class="mt-1 text-xs text-muted-foreground">{{ number_format($data['openCount']) }} open now</p>
            </article>
            <article class="{{ $cardClass }} p-3">
                <h2 class="text-[11px] font-semibold text-muted-foreground">Resolution rate</h2>
                <p class="mt-1 font-display text-2xl font-bold leading-none text-primary">{{ number_format($data['resolutionRate'], 1) }}%</p>
                <p class="mt-1 text-xs text-muted-foreground">{{ number_format($data['resolved']) }} of {{ number_format($data['resolutionTickets']) }} counted</p>
                @if ($notCounted)
                    <p class="mt-1 text-[10px] leading-tight text-muted-foreground">Not counted: {{ $notCounted }}</p>
                @endif
            </article>
            <article class="{{ $cardClass }} p-3">
                <h2 class="text-[11px] font-semibold text-muted-foreground">Active handling time</h2>
                <p class="mt-1 font-display text-2xl font-bold leading-none text-primary">{{ $days($resolutionTime['median_days']) }}</p>
                <p class="mt-1 text-xs text-muted-foreground">average {{ $days($resolutionTime['average_days']) }}</p>
            </article>
            <article class="{{ $cardClass }} p-3">
                <h2 class="text-[11px] font-semibold text-muted-foreground">First staff reply (SDS or recipient)</h2>
                <p class="mt-1 font-display text-2xl font-bold leading-none text-primary">{{ $days($performance['first_response_time']['median_days']) }}</p>
                <p class="mt-1 text-xs text-muted-foreground">Median calendar time</p>
            </article>
            <article class="{{ $cardClass }} p-3">
                <h2 class="text-[11px] font-semibold text-muted-foreground">Escalation rate</h2>
                <p class="mt-1 font-display text-2xl font-bold leading-none text-primary">{{ number_format($escalationRate, 1) }}%</p>
                <p class="mt-1 text-xs text-muted-foreground">{{ number_format($escalatedTickets) }} of {{ number_format($escalationTotal) }} needs-resolution</p>
            </article>
            <article class="{{ $cardClass }} p-3">
                <h2 class="text-[11px] font-semibold text-muted-foreground">Satisfaction</h2>
                @if ($satisfaction['count'])
                    <p class="mt-1 font-display text-2xl font-bold leading-none text-primary">{{ number_format($satisfaction['average'], 1) }} / 5</p>
                    <p class="mt-1 text-xs text-muted-foreground">{{ number_format($satisfaction['count']) }} rating(s)</p>
                @else
                    <p class="mt-1 font-display text-base font-bold leading-none text-primary">No ratings yet</p>
                @endif
            </article>
        </section>

        <div x-data="{
            tab: 'overview',
            staffMode: 'offices',
            categoryRow: null,
            studentDimension: 'colleges',
            init() {
                const valid = ['overview', 'response', 'staff', 'students'];
                const fromHash = window.location.hash.slice(1);
                this.tab = valid.includes(fromHash) ? fromHash : 'overview';
                window.addEventListener('hashchange', () => {
                    const next = window.location.hash.slice(1);
                    if (valid.includes(next)) this.tab = next;
                });
            },
            selectTab(name) {
                this.tab = name;
                history.replaceState(null, '', `#${name}`);
            }
        }" class="grid min-w-0 gap-4">
            <nav aria-label="Analytics sections" class="-mx-1 flex gap-1 overflow-x-auto border-b border-border px-1">
                @foreach (['overview' => 'Overview', 'response' => 'Response and resolution', 'staff' => 'Staff and offices', 'students' => 'Students and feedback'] as $tabKey => $tabLabel)
                    <a href="#{{ $tabKey }}" x-on:click.prevent="selectTab('{{ $tabKey }}')" x-bind:aria-current="tab === '{{ $tabKey }}' ? 'page' : null" x-bind:class="tab === '{{ $tabKey }}' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'" class="shrink-0 border-b-2 px-3 py-2.5 text-sm font-semibold">{{ $tabLabel }}</a>
                @endforeach
            </nav>

            <section id="overview" x-show="tab === 'overview'" x-cloak data-tab-panel="overview" class="grid min-w-0 gap-4">
                <h2 class="sr-only">Overview</h2>
                <div class="grid min-w-0 gap-4 lg:grid-cols-2">
                    <article class="{{ $cardClass }}">
                        <h3 class="{{ $titleClass }}">Ticket volume</h3>
                        <p class="mt-0.5 {{ $mutedClass }}">Submitted and resolved by month; only months with activity, up to 12.</p>
                        @if ($volumeMonths->isEmpty())
                            <p class="mt-4 text-xs text-muted-foreground">No ticket volume data in this period.</p>
                        @else
                            <div class="mt-3 flex flex-wrap gap-4 text-xs text-muted-foreground" aria-label="Chart legend">
                                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-primary"></span>Submitted</span>
                                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-700"></span>Resolved</span>
                            </div>
                            <div class="mt-2 grid h-[220px] grid-flow-col auto-cols-fr items-end gap-2 border-b border-border px-1" role="img" aria-label="Monthly submitted and resolved ticket counts">
                                @foreach ($volumeMonths as $month => $counts)
                                    @php
                                        $submittedHeight = $counts['submitted'] ? max(4, round($counts['submitted'] / $volumeMax * 174)) : 0;
                                        $resolvedHeight = $counts['resolved'] ? max(4, round($counts['resolved'] / $volumeMax * 174)) : 0;
                                    @endphp
                                    <div class="flex h-full min-w-0 flex-col items-center justify-end">
                                        <div class="flex h-[184px] w-full items-end justify-center gap-1">
                                            <div class="w-1/3 rounded-t bg-primary" style="height: {{ $submittedHeight }}px" aria-label="{{ $counts['submitted'] }} submitted"></div>
                                            <div class="w-1/3 rounded-t bg-emerald-700" style="height: {{ $resolvedHeight }}px" aria-label="{{ $counts['resolved'] }} resolved"></div>
                                        </div>
                                        <span class="mt-1 truncate text-[10px] text-muted-foreground">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('M y') }}</span>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-1 flex flex-wrap justify-end gap-x-3 text-[10px] text-muted-foreground">
                                @foreach ($volumeMonths as $month => $counts)
                                    <span>{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('M y') }}: {{ $counts['submitted'] }} submitted, {{ $counts['resolved'] }} resolved</span>
                                @endforeach
                            </div>
                        @endif
                    </article>

                    <article class="{{ $cardClass }}">
                        <h3 class="{{ $titleClass }}">Status</h3>
                        <p class="mt-0.5 {{ $mutedClass }}">Ticket counts by current status.</p>
                        @if ($total)
                            <div class="mt-4 flex h-1.5 overflow-hidden rounded-full bg-muted" role="img" aria-label="Stacked ticket status totals">
                                @foreach ($statusSegments as $segment)
                                    @if ($segment['count'] > 0)
                                        <span style="width: {{ $segment['count'] / $statusMax * 100 }}%; background-color: {{ $segment['color'] }}" aria-label="{{ $segment['label'] }}: {{ $segment['count'] }}"></span>
                                    @endif
                                @endforeach
                            </div>
                            <ul class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1.5 text-xs sm:grid-cols-3">
                                @foreach ($statusSegments as $segment)
                                    <li class="flex min-w-0 items-center gap-1.5">
                                        <span class="h-2 w-2 shrink-0 rounded-sm" style="background-color: {{ $segment['color'] }}"></span>
                                        <span class="min-w-0 flex-1 truncate">{{ $segment['label'] }}</span>
                                        <strong class="tabular-nums">{{ number_format($segment['count']) }}</strong>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-4 text-xs text-muted-foreground">No tickets in this period.</p>
                        @endif
                    </article>
                </div>

                <div class="grid min-w-0 gap-4 lg:grid-cols-2">
                    <article class="{{ $cardClass }}">
                        <h3 class="{{ $titleClass }}">Top 5 categories</h3>
                        <p class="mt-0.5 {{ $mutedClass }}">Counts and share of selected tickets.</p>
                        @forelse ($topCategories as $category => $count)
                            <div class="mt-3">
                                <div class="flex justify-between gap-2 text-xs"><span class="truncate">{{ $category }}</span><strong class="shrink-0">{{ number_format($count) }} ({{ number_format($percent($count), 1) }}%)</strong></div>
                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full bg-primary" style="width: {{ min(100, $percent($count)) }}%"></div></div>
                            </div>
                        @empty
                            <p class="mt-4 text-xs text-muted-foreground">No category totals in this period.</p>
                        @endforelse
                        @if ($otherCategoryCount)
                            <p class="mt-3 border-t border-border pt-2 text-xs text-muted-foreground">{{ $otherCategoryCount }} other categories · {{ number_format($otherTicketCount) }} tickets</p>
                        @endif
                    </article>
                    <article class="{{ $cardClass }}">
                        <h3 class="{{ $titleClass }}">Handled by office</h3>
                        <p class="mt-0.5 {{ $mutedClass }}">Top offices by ticket count.</p>
                        @forelse ($officeRows as $office)
                            <div class="mt-3">
                                <div class="flex justify-between gap-2 text-xs"><span class="truncate">{{ $office['name'] }}</span><strong class="shrink-0">{{ number_format($office['total']) }}</strong></div>
                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full bg-[#c4785a]" style="width: {{ min(100, $percent($office['total'])) }}%"></div></div>
                            </div>
                        @empty
                            <p class="mt-4 text-xs text-muted-foreground">No office assignments in this period.</p>
                        @endforelse
                    </article>
                </div>
            </section>

            <section id="response" x-show="tab === 'response'" x-cloak data-tab-panel="response" class="grid min-w-0 gap-4">
                <h2 class="sr-only">Response and resolution</h2>
                <article class="{{ $cardClass }} overflow-x-auto">
                    <h3 class="{{ $titleClass }}">Response and handling times</h3>
                    <p class="mt-0.5 {{ $mutedClass }}">Calendar days from the selected start point.</p>
                    <table class="mt-3 w-full min-w-[560px] text-left text-xs">
                        <thead class="border-b border-border text-muted-foreground"><tr><th class="py-2 pr-3 font-semibold">Measure</th><th class="px-2 py-2 font-semibold">Median</th><th class="px-2 py-2 font-semibold">Average</th><th class="px-2 py-2 text-right font-semibold">Tickets counted</th></tr></thead>
                        <tbody>
                            @foreach ([
                                ['Review time', $performance['review_time']],
                                ['Time to acknowledge', $performance['acknowledgment_time']],
                                ['First staff reply (SDS or recipient)', $performance['first_response_time']],
                            ] as [$label, $timing])
                                <tr class="border-b border-border last:border-0"><th class="py-2 pr-3 font-medium">{{ $label }}</th><td class="px-2 py-2">{{ $days($timing['median_days']) }}</td><td class="px-2 py-2">{{ $days($timing['average_days']) }}</td><td class="px-2 py-2 text-right tabular-nums">{{ number_format($timing['sample_count']) }}</td></tr>
                            @endforeach
                            <tr class="border-b border-border last:border-0"><th class="py-2 pr-3 font-medium">Active handling time</th><td class="px-2 py-2">{{ $days($resolutionTime['median_days']) }}</td><td class="px-2 py-2">{{ $days($resolutionTime['average_days']) }}</td><td class="px-2 py-2 text-right tabular-nums">{{ number_format($resolutionTime['ticket_count']) }}</td></tr>
                        </tbody>
                    </table>
                    @if ($resolutionTime['fallback_count'] > 0)
                        <p class="mt-2 text-[11px] text-muted-foreground">{{ number_format($resolutionTime['fallback_count']) }} ticket(s) used full time because a wait interval could not be determined.</p>
                    @endif
                </article>

                <article class="{{ $cardClass }} overflow-x-auto">
                    <h3 class="{{ $titleClass }}">Active handling time by category</h3>
                    <p class="mt-0.5 {{ $mutedClass }}">Calendar days, less clarification and referral waits where recorded.</p>
                    <table class="mt-3 w-full min-w-[460px] text-left text-xs">
                        <thead class="border-b border-border text-muted-foreground"><tr><th class="py-2 pr-3 font-semibold">Category / outcome</th><th class="px-2 py-2 font-semibold">Median</th><th class="px-2 py-2 font-semibold">Average</th><th class="px-2 py-2 text-right font-semibold">Tickets</th></tr></thead>
                        <tbody>
                            @forelse ($resolutionTime['categories'] as $category => $timing)
                                <tr class="border-b border-border last:border-0"><th class="py-2 pr-3 font-medium">{{ $category }}</th><td class="px-2 py-2">{{ $days($timing['median_days']) }}</td><td class="px-2 py-2">{{ $days($timing['average_days']) }}</td><td class="px-2 py-2 text-right tabular-nums">{{ number_format($timing['ticket_count']) }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="py-3 text-xs text-muted-foreground">No handling-time data in this period.</td></tr>
                            @endforelse
                            @if (($resolutionTime['committee_outcomes']['ticket_count'] ?? 0) > 0)
                                <tr class="border-t border-border"><th class="py-2 pr-3 font-medium">Committee outcomes</th><td class="px-2 py-2">{{ $days($resolutionTime['committee_outcomes']['median_days']) }}</td><td class="px-2 py-2">{{ $days($resolutionTime['committee_outcomes']['average_days']) }}</td><td class="px-2 py-2 text-right tabular-nums">{{ number_format($resolutionTime['committee_outcomes']['ticket_count']) }}</td></tr>
                            @endif
                        </tbody>
                    </table>
                </article>

                <div class="grid min-w-0 gap-4 lg:grid-cols-2">
                    @foreach ([['title' => 'How tickets were resolved', 'rows' => $breakdowns['resolution_types']], ['title' => 'Why tickets were closed', 'rows' => $breakdowns['closure_reasons']]] as $outcome)
                        <article class="{{ $cardClass }}">
                            <h3 class="{{ $titleClass }}">{{ $outcome['title'] }}</h3>
                            <ul class="mt-2 divide-y divide-border text-xs">
                                @forelse ($outcome['rows'] as $label => $count)
                                    <li class="flex justify-between gap-2 py-2"><span>{{ $label }}</span><strong class="tabular-nums">{{ number_format($count) }}</strong></li>
                                @empty
                                    <li class="py-3 text-muted-foreground">No outcome data in this period.</li>
                                @endforelse
                            </ul>
                        </article>
                    @endforeach
                </div>

                <article class="{{ $cardClass }}">
                    <h3 class="{{ $titleClass }}">Escalation and follow-up</h3>
                    <p class="mt-0.5 {{ $mutedClass }}">Escalation rate: {{ number_format($escalatedTickets) }} of {{ number_format($escalationTotal) }} needs-resolution tickets ({{ number_format($escalationRate, 1) }}%).</p>
                    <div class="mt-3 flex h-1.5 overflow-hidden rounded-full bg-muted" role="img" aria-label="Needs-resolution tickets by escalation count">
                        @foreach ($escalationSegments as $segment)
                            @if ($segment['count'] > 0)
                                <span style="width: {{ $segment['count'] / $escalationMax * 100 }}%; background-color: {{ $segment['color'] }}" aria-label="{{ $segment['label'] }}: {{ $segment['count'] }}"></span>
                            @endif
                        @endforeach
                    </div>
                    <ul class="mt-2 grid gap-x-4 gap-y-1 text-xs sm:grid-cols-3">
                        @foreach ($escalationSegments as $segment)
                            <li class="flex items-center gap-1.5"><span class="h-2 w-2 shrink-0 rounded-sm" style="background-color: {{ $segment['color'] }}"></span>{{ $segment['label'] }} <strong class="ml-auto tabular-nums">{{ number_format($segment['count']) }}</strong></li>
                        @endforeach
                    </ul>
                    <div class="mt-3 grid gap-x-4 gap-y-2 border-t border-border pt-3 text-xs sm:grid-cols-2 lg:grid-cols-4">
                        <p>Student escalation requests: <strong>{{ number_format($performance['student_requests']['escalation']['count']) }}</strong> · {{ number_format($performance['student_requests']['escalation']['followed_by_sds_action']) }} followed by SDS</p>
                        <p>Student update requests: <strong>{{ number_format($performance['student_requests']['update']['count']) }}</strong> · {{ number_format($performance['student_requests']['update']['followed_by_sds_action']) }} followed by SDS</p>
                        <p>Further-action rate: <strong>{{ number_format($performance['further_action_rate'], 1) }}%</strong></p>
                        <p>Reopened: <strong>{{ number_format($performance['reopen_count']) }}</strong></p>
                    </div>
                </article>
            </section>

            <section id="staff" x-show="tab === 'staff'" x-cloak data-tab-panel="staff" class="grid min-w-0 gap-3">
                <h2 class="sr-only">Staff and offices</h2>
                <div class="flex items-center gap-1" role="group" aria-label="Performance table">
                    <button type="button" x-on:click="staffMode = 'offices'" x-bind:aria-pressed="staffMode === 'offices'" x-bind:class="staffMode === 'offices' ? 'bg-primary text-white' : 'bg-white text-foreground'" class="rounded-md border border-border px-3 py-1.5 text-xs font-semibold">Offices</button>
                    <button type="button" x-on:click="staffMode = 'staff'" x-bind:aria-pressed="staffMode === 'staff'" x-bind:class="staffMode === 'staff' ? 'bg-primary text-white' : 'bg-white text-foreground'" class="rounded-md border border-border px-3 py-1.5 text-xs font-semibold">Staff</button>
                </div>
                <article x-show="staffMode === 'offices'" class="{{ $cardClass }} overflow-x-auto">
                    <h3 class="{{ $titleClass }}">Performance by office</h3>
                    <table class="mt-2 w-full min-w-[760px] text-left text-xs">
                        <thead class="border-b border-border text-muted-foreground"><tr><th class="py-2 pr-3">Office</th><th class="px-2 text-right">Open</th><th class="px-2">Oldest open</th><th class="px-2">Median to acknowledge</th><th class="px-2 text-right">Resolved</th><th class="px-2">Handling time</th></tr></thead>
                        <tbody>
                            @forelse ($officePerformanceRows as $row)
                                <tr class="border-b border-border last:border-0"><th class="py-2 pr-3 font-medium">{{ $row['name'] }}</th><td class="px-2 text-right tabular-nums">{{ number_format($row['open_tickets']) }}</td><td class="px-2">{{ $days($row['oldest_open_ticket_age_days']) }}</td><td class="px-2">{{ $row['median_acknowledgment_days'] === null ? 'n/a' : $days($row['median_acknowledgment_days']) }}</td><td class="px-2 text-right tabular-nums">{{ number_format($row['resolved_tickets']) }}</td><td class="px-2">
                                    @if ($row['handling_by_category'])
                                        <button type="button" class="text-primary underline-offset-2 hover:underline" x-on:click="categoryRow = categoryRow === 'office-{{ $loop->index }}' ? null : 'office-{{ $loop->index }}'">Show categories</button>
                                    @else
                                        n/a
                                    @endif
                                </td></tr>
                                @if ($row['handling_by_category'])
                                    <tr x-show="categoryRow === 'office-{{ $loop->index }}'" x-cloak class="border-b border-border bg-muted/30">
                                        <td colspan="6" class="px-3 py-2 text-xs">
                                            <span class="font-semibold">Median handling time by category:</span>
                                            <ul class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                                                @foreach ($row['handling_by_category'] as $category => $timing)
                                                    <li>{{ $category }}: {{ $timing['median_days'] === null ? 'n/a' : $days($timing['median_days']) }}</li>
                                                @endforeach
                                            </ul>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr><td colspan="6" class="py-4 text-center text-muted-foreground">No office performance data in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </article>
                <article x-show="staffMode === 'staff'" class="{{ $cardClass }} overflow-x-auto">
                    <h3 class="{{ $titleClass }}">Performance by staff</h3>
                    <table class="mt-2 w-full min-w-[760px] text-left text-xs">
                        <thead class="border-b border-border text-muted-foreground"><tr><th class="py-2 pr-3">Name and office</th><th class="px-2 text-right">Open</th><th class="px-2">Oldest open</th><th class="px-2">Median to acknowledge</th><th class="px-2 text-right">Resolved</th><th class="px-2">Handling time</th></tr></thead>
                        <tbody>
                            @forelse ($staffRows as $row)
                                <tr class="border-b border-border last:border-0"><th class="py-2 pr-3 font-medium">{{ $row['name'] }}<span class="block text-[10px] font-normal text-muted-foreground">{{ $row['unit'] }}</span></th><td class="px-2 text-right tabular-nums">{{ number_format($row['open_tickets']) }}</td><td class="px-2">{{ $days($row['oldest_open_ticket_age_days']) }}</td><td class="px-2">{{ $row['median_acknowledgment_days'] === null ? 'n/a' : $days($row['median_acknowledgment_days']) }}</td><td class="px-2 text-right tabular-nums">{{ number_format($row['resolved_tickets']) }}</td><td class="px-2">
                                    @if ($row['handling_by_category'])
                                        <button type="button" class="text-primary underline-offset-2 hover:underline" x-on:click="categoryRow = categoryRow === 'staff-{{ $loop->index }}' ? null : 'staff-{{ $loop->index }}'">Show categories</button>
                                    @else
                                        n/a
                                    @endif
                                </td></tr>
                                @if ($row['handling_by_category'])
                                    <tr x-show="categoryRow === 'staff-{{ $loop->index }}'" x-cloak class="border-b border-border bg-muted/30">
                                        <td colspan="6" class="px-3 py-2 text-xs">
                                            <span class="font-semibold">Median handling time by category:</span>
                                            <ul class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                                                @foreach ($row['handling_by_category'] as $category => $timing)
                                                    <li>{{ $category }}: {{ $timing['median_days'] === null ? 'n/a' : $days($timing['median_days']) }}</li>
                                                @endforeach
                                            </ul>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr><td colspan="6" class="py-4 text-center text-muted-foreground">No staff performance data in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </article>
                <p class="text-[11px] text-muted-foreground">n/a means fewer than 3 tickets. Escalations are counted by category and level, not per handler.</p>
            </section>

            <section id="students" x-show="tab === 'students'" x-cloak data-tab-panel="students" class="grid min-w-0 gap-4 lg:grid-cols-2">
                <h2 class="sr-only">Students and feedback</h2>
                <article class="{{ $cardClass }}">
                    <h3 class="{{ $titleClass }}">Who raises tickets</h3>
                    <label class="mt-2 block text-[11px] font-semibold text-muted-foreground" for="student-dimension">Student attribute</label>
                    <select id="student-dimension" x-model="studentDimension" class="mt-1 h-9 w-full rounded-md border border-input bg-white px-2 text-xs sm:max-w-xs">
                        @foreach ($studentDimensions as $key => $dimension)
                            <option value="{{ $key }}">{{ $dimension['label'] }}</option>
                        @endforeach
                    </select>
                    @foreach ($studentDimensions as $key => $dimension)
                        <ul x-show="studentDimension === '{{ $key }}'" x-cloak class="mt-2 divide-y divide-border text-xs">
                            @forelse ($dimension['rows'] as $label => $count)
                                <li class="flex justify-between gap-2 py-2"><span>{{ $label }}</span><strong>{{ number_format($count) }} ({{ number_format($percent($count, max(1, array_sum($dimension['rows']))), 1) }}%)</strong></li>
                            @empty
                                <li class="py-3 text-muted-foreground">No {{ strtolower($dimension['label']) }} breakdown data in this period.</li>
                            @endforelse
                        </ul>
                    @endforeach
                    @if (($breakdowns['sensitive_not_broken_down'] ?? 0) > 0)
                        <p class="mt-3 border-t border-border pt-2 text-[11px] text-muted-foreground">Sensitive categories (not broken down): {{ number_format($breakdowns['sensitive_not_broken_down']) }}</p>
                    @endif
                </article>
                <article class="{{ $cardClass }}">
                    <h3 class="{{ $titleClass }}">Ticket origin</h3>
                    <ul class="mt-2 divide-y divide-border text-xs">
                        @forelse ($originRows as $label => $count)
                            <li class="flex justify-between gap-2 py-2"><span>{{ $label }}</span><strong>{{ number_format($count) }} ({{ number_format($percent($count, max(1, array_sum($originRows))), 1) }}%)</strong></li>
                        @empty
                            <li class="py-3 text-muted-foreground">No ticket origin data in this period.</li>
                        @endforelse
                    </ul>
                </article>
                <article class="{{ $cardClass }} lg:col-span-2">
                    <h3 class="{{ $titleClass }}">Satisfaction</h3>
                    @if ($satisfaction['count'])
                        <p class="mt-1 font-display text-xl font-bold text-primary">{{ number_format($satisfaction['average'], 1) }} / 5 <span class="text-xs font-normal text-muted-foreground">{{ number_format($satisfaction['count']) }} rating(s)</span></p>
                        <ul class="mt-2 grid gap-x-6 sm:grid-cols-2">
                            @foreach ($ratingDistribution as $rating => $count)
                                <li class="flex items-center gap-2 py-1 text-xs">
                                    <span class="w-12 shrink-0">{{ $rating }} out of 5</span>
                                    <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted"><span class="block h-full rounded-full bg-[#c4785a]" style="width: {{ $count ? $count / $satisfaction['count'] * 100 : 0 }}%"></span></span>
                                    <strong class="w-8 text-right tabular-nums">{{ number_format($count) }}</strong>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-2 text-xs text-muted-foreground">No satisfaction ratings in this period.</p>
                    @endif
                </article>
            </section>
        </div>
    </div>

    <script>
        const analyticsFilterForm = document.querySelector('[data-analytics-filter-form]');
        const statusDropdown = analyticsFilterForm?.querySelector('[data-analytics-status-dropdown]');
        const statusSummary = statusDropdown?.querySelector('[data-analytics-status-summary]');
        const statusInput = analyticsFilterForm?.querySelector('[data-analytics-filter-input="status"]');
        const statusHelp = analyticsFilterForm?.querySelector('[data-analytics-status-help]');
        const statusOptions = analyticsFilterForm?.querySelectorAll('[data-analytics-status-option]') ?? [];

        const syncStatusFilter = () => {
            if (!statusDropdown || !statusSummary || !statusInput) return;

            const classification = analyticsFilterForm.querySelector('[data-analytics-filter-input="classification"]')?.value ?? '';
            const enabled = classification === 'needs_resolution' || classification === 'unclassified';
            statusDropdown.dataset.disabled = String(!enabled);
            statusDropdown.classList.toggle('pointer-events-none', !enabled);
            statusDropdown.classList.toggle('opacity-70', !enabled);
            statusSummary.setAttribute('aria-disabled', String(!enabled));
            statusSummary.tabIndex = enabled ? 0 : -1;
            statusSummary.classList.toggle('bg-muted', !enabled);
            statusSummary.classList.toggle('text-muted-foreground', !enabled);
            statusSummary.classList.toggle('bg-white', enabled);
            statusSummary.classList.toggle('hover:bg-muted', enabled);
            if (statusHelp) statusHelp.hidden = enabled;

            statusOptions.forEach((option) => {
                const value = option.dataset.value ?? '';
                const visible = enabled
                    && (value === '' || classification === 'needs_resolution' || ['submitted', 'needs_clarification'].includes(value));
                option.hidden = !visible;
            });
        };

        document.querySelectorAll('[data-analytics-filter]').forEach((option) => {
            option.addEventListener('click', () => {
                const dropdown = option.closest('details');
                if (option.dataset.analyticsFilter === 'status' && statusDropdown?.dataset.disabled === 'true') return;
                const input = document.querySelector(`[data-analytics-filter-input="${option.dataset.analyticsFilter}"]`);
                if (input) input.value = option.dataset.value ?? '';
                dropdown.querySelector('summary span').textContent = option.dataset.label ?? '';
                dropdown.removeAttribute('open');

                if (option.dataset.analyticsFilter === 'classification' && statusInput && statusSummary) {
                    statusInput.value = '';
                    statusSummary.querySelector('span').textContent = 'All statuses';
                    statusOptions.forEach((statusOption) => {
                        statusOption.classList.remove('bg-primary-soft', 'text-primary');
                    });
                    syncStatusFilter();
                }
            });
        });
        syncStatusFilter();
    </script>
</x-app-layout>
