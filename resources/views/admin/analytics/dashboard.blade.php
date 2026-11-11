<x-app-layout :role="'admin'" title="Analytics">
    @php
        $data = $dashboard;
        $total = (int) $data['total'];
        $percent = fn ($count, $of = null) => ($of ?? $total) > 0 ? round($count / ($of ?? $total) * 100, 1) : 0;
        $days = fn ($value) => $value === null ? 'Not available' : number_format((float) $value, 1) . ' days';
        $formatDate = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('M j, Y') : null;
        $periodLabel = ($filters['start_date'] ?? null) || ($filters['end_date'] ?? null)
            ? ($formatDate($filters['start_date'] ?? null) ?? 'Start') . ' – ' . ($formatDate($filters['end_date'] ?? null) ?? 'Today')
            : null;
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
        $classificationColors = [
            'needs_resolution' => '#7a1d2a',
            'informational' => '#14283f',
            'invalid' => '#dc2626',
            'unclassified' => '#9ca3af',
            'resolved' => '#15803d',
        ];
        $statusColors = [
            'submitted' => '#facc15',
            'needs_clarification' => '#f59e0b',
            'assigned' => '#2563eb',
            'in_progress' => '#0e7490',
            'escalated' => '#dc2626',
            'referred' => '#7c3aed',
            'resolved' => '#16a34a',
            'closed' => '#9ca3af',
        ];
        $statusSegments = collect(\App\Models\Ticket::DISPLAY_STATUS_LABELS)
            ->map(fn ($label, $status) => [
                'label' => $label,
                'count' => $data['tickets']->where('status', $status)->count(),
                'color' => $statusColors[$status],
            ])
            ->values()
            ->all();
        $classificationSegments = [
            ['label' => 'Needs resolution', 'color' => $classificationColors['needs_resolution'], 'count' => (int) ($data['classification']['Needs Resolution'] ?? 0)],
            ['label' => 'Informational', 'color' => $classificationColors['informational'], 'count' => (int) ($data['classification']['Informational'] ?? 0)],
            ['label' => 'Invalid', 'color' => $classificationColors['invalid'], 'count' => (int) ($data['classification']['Invalid'] ?? 0)],
            ['label' => 'Unclassified', 'color' => $classificationColors['unclassified'], 'count' => (int) ($data['classification']['Unclassified'] ?? 0)],
        ];
        $monthlyTicketVolumes = $data['monthlyTicketVolumes'];
        $monthlyGraphOneTotals = [
            'submitted' => array_sum(array_column($monthlyTicketVolumes['values'], 'submitted')),
            'resolved' => array_sum(array_column($monthlyTicketVolumes['values'], 'resolved')),
        ];
        $monthlyGraphTwoTotals = [
            'informational' => array_sum(array_column($monthlyTicketVolumes['values'], 'informational')),
            'invalid' => array_sum(array_column($monthlyTicketVolumes['values'], 'invalid')),
        ];
        $categoryRows = $data['categoryVolumes']['rows'];
        $categoryChartSegments = [
            ['key' => 'submitted', 'label' => 'Submitted (not resolved)', 'color' => $classificationColors['needs_resolution']],
            ['key' => 'resolved', 'label' => 'Resolved', 'color' => $classificationColors['resolved']],
            ['key' => 'informational', 'label' => 'Informational', 'color' => $classificationColors['informational']],
            ['key' => 'invalid', 'label' => 'Invalid', 'color' => $classificationColors['invalid']],
            ['key' => 'unclassified', 'label' => 'Unclassified', 'color' => $classificationColors['unclassified']],
        ];

        $resolutionTime = $data['resolutionTime'];
        $performance = $data['performanceMetrics'];
        $breakdowns = $data['breakdowns'];
        $satisfaction = $breakdowns['satisfaction'];
        $studentDimensions = [
            'colleges' => ['label' => 'College', 'rows' => $breakdowns['colleges']],
            'programs' => ['label' => 'Program', 'rows' => $breakdowns['programs']],
            'year_levels' => ['label' => 'Year level', 'rows' => $breakdowns['year_levels']],
        ];
        $originRows = $breakdowns['origin'];
        $ratingDistribution = $satisfaction['distribution'];

        $staffRows = collect($performance['handler_rows'])
            ->sort(fn ($left, $right) => $right['assigned_tickets'] <=> $left['assigned_tickets']
                ?: $right['open_tickets'] <=> $left['open_tickets']
                ?: strcmp($left['name'], $right['name']))
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
                @if ($periodLabel)
                    <p class="text-sm font-semibold text-foreground">{{ $periodLabel }}</p>
                @endif
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
                <div class="col-span-2 lg:col-span-4 xl:col-span-1">
                    <span aria-hidden="true" class="invisible mb-1 hidden text-[11px] font-semibold xl:block">Actions</span>
                    <div class="flex gap-2">
                        <button class="inline-flex h-9 flex-1 items-center justify-center rounded-md bg-primary px-4 text-xs font-semibold text-primary-foreground hover:bg-primary/90 xl:flex-none">Apply</button>
                        <a href="{{ route('admin.analytics.index') }}" class="inline-flex h-9 flex-1 items-center justify-center rounded-md border border-primary/40 px-4 text-xs font-semibold text-primary hover:bg-primary-soft xl:flex-none">Reset</a>
                    </div>
                </div>
            </div>
        </form>

        <section class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-5" aria-label="Analytics summary">
            <article class="{{ $cardClass }} p-3">
                <h2 class="text-[11px] font-semibold text-black">Total tickets</h2>
                <p class="mt-1 font-display text-2xl font-bold leading-none text-primary">{{ number_format($total) }}</p>
                <p class="mt-1 text-xs text-green-700">{{ number_format($data['openCount']) }} active tickets</p>
            </article>
            <article class="{{ $cardClass }} p-3">
                <h2 class="text-[11px] font-semibold text-black">Resolution rate</h2>
                <p class="mt-1 font-display text-2xl font-bold leading-none text-primary">{{ number_format($data['resolutionRate'], 1) }}%</p>
                <p class="mt-1 text-xs text-muted-foreground">{{ number_format($data['resolved']) }} of {{ number_format($data['resolutionTickets']) }} counted</p>
                @if ($notCounted)
                    <p class="mt-1 text-[10px] leading-tight text-muted-foreground">Not counted: {{ $notCounted }}</p>
                @endif
            </article>
            <article class="{{ $cardClass }} p-3">
                <h2 class="text-[11px] font-semibold text-black">Active handling time</h2>
                <p class="mt-1 font-display text-2xl font-bold leading-none text-primary">{{ $days($resolutionTime['median_days']) }}</p>
                <p class="mt-1 text-xs text-muted-foreground">average {{ $days($resolutionTime['average_days']) }}</p>
            </article>
            <article class="{{ $cardClass }} p-3">
                <h2 class="text-[11px] font-semibold text-black">Escalation rate</h2>
                <p class="mt-1 font-display text-2xl font-bold leading-none text-primary">{{ number_format($escalationRate, 1) }}%</p>
                <p class="mt-1 text-xs text-muted-foreground">{{ number_format($escalatedTickets) }} of {{ number_format($escalationTotal) }} needs-resolution</p>
            </article>
            <article class="{{ $cardClass }} p-3">
                <h2 class="text-[11px] font-semibold text-black">Satisfaction</h2>
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
            studentDimension: 'colleges',
            init() {
                const valid = ['overview', 'staff', 'students'];
                const fromHash = window.location.hash.slice(1);
                this.tab = valid.includes(fromHash) ? fromHash : 'overview';
                window.addEventListener('hashchange', () => {
                    const next = window.location.hash.slice(1);
                    if (valid.includes(next)) this.tab = next;
                });
                this.$watch('tab', () => this.$nextTick(() => window.renderAnalyticsVolumeCharts?.()));
            },
            selectTab(name) {
                this.tab = name;
                history.replaceState(null, '', `#${name}`);
            }
        }" class="grid min-w-0 gap-4">
            <nav aria-label="Analytics sections" class="-mx-1 flex gap-1 overflow-x-auto border-b border-border px-1">
                @foreach (['overview' => 'Overview', 'staff' => 'Recipient performance', 'students' => 'Students and feedback'] as $tabKey => $tabLabel)
                    <a href="#{{ $tabKey }}" x-on:click.prevent="selectTab('{{ $tabKey }}')" x-bind:aria-current="tab === '{{ $tabKey }}' ? 'page' : null" x-bind:class="tab === '{{ $tabKey }}' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'" class="shrink-0 border-b-2 px-3 py-2.5 text-sm font-semibold">{{ $tabLabel }}</a>
                @endforeach
            </nav>

            <section id="overview" x-show="tab === 'overview'" x-cloak data-tab-panel="overview" class="grid min-w-0 gap-4">
                <h2 class="sr-only">Overview</h2>
                <div class="grid min-w-0 gap-4 lg:grid-cols-2">
                    @foreach ([
                        ['title' => 'Volume of Needs Resolution Tickets', 'key' => 'needs-resolution', 'color1' => $classificationColors['needs_resolution'], 'color2' => $classificationColors['resolved'], 'label1' => 'Submitted', 'label2' => 'Resolved', 'key1' => 'submitted', 'key2' => 'resolved', 'totals' => $monthlyGraphOneTotals],
                        ['title' => 'Volume of Informational and Invalid Tickets', 'key' => 'informational-invalid', 'color1' => $classificationColors['informational'], 'color2' => $classificationColors['invalid'], 'label1' => 'Informational', 'label2' => 'Invalid', 'key1' => 'informational', 'key2' => 'invalid', 'totals' => $monthlyGraphTwoTotals],
                    ] as $graph)
                        <article class="{{ $cardClass }}">
                            <h3 class="{{ $titleClass }}">{{ $graph['title'] }}</h3>
                            <p class="mt-0.5 {{ $mutedClass }}">Monthly ticket volume over the latest 12 months in the selected range.</p>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-muted-foreground" aria-label="{{ $graph['title'] }} chart labels">
                                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $graph['color1'] }}"></span>{{ $graph['label1'] }} = {{ number_format($graph['totals'][$graph['key1']]) }}</span>
                                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background-color: {{ $graph['color2'] }}"></span>{{ $graph['label2'] }} = {{ number_format($graph['totals'][$graph['key2']]) }}</span>
                            </div>
                            <div class="mt-2 h-[220px] min-w-0">
                                <canvas data-analytics-volume-chart="{{ $graph['key'] }}" role="img" aria-label="{{ $graph['title'] }} by month"></canvas>
                            </div>
                            @if (array_sum($graph['totals']) === 0)
                                <p class="mt-1 text-[11px] text-muted-foreground">No ticket volume data in this period.</p>
                            @endif
                        </article>
                    @endforeach
                </div>

                <div class="grid min-w-0 items-stretch gap-4 lg:grid-cols-[minmax(20rem,0.8fr)_minmax(0,1.5fr)]" data-analytics-distribution-row>
                    <div class="grid min-w-0 gap-4 lg:grid-rows-2" data-analytics-distribution-stack>
                        <x-donut-chart class="h-full" title="Status" empty-message="No tickets in this period." :segments="$statusSegments" total-label="Tickets" :legend-columns="1" ring-class="h-32 w-32 sm:h-36 sm:w-36" :stroke-width="15" />
                        <x-donut-chart class="h-full" title="Classification" :segments="$classificationSegments" total-label="Tickets" :legend-columns="1" ring-class="h-32 w-32 sm:h-36 sm:w-36" :stroke-width="15" />
                    </div>

                    <article class="{{ $cardClass }} flex min-w-0 flex-col">
                        <h3 class="{{ $titleClass }}">Volume by category</h3>
                        @if (count($categoryRows))
                            <div class="relative mt-3 min-h-80 w-full flex-1" style="height: {{ max(320, min(460, count($categoryRows) * 30 + 70)) }}px">
                                <canvas class="absolute inset-0" data-analytics-category-volume-chart role="img" aria-label="Ticket volume by category and classification"></canvas>
                            </div>
                        @else
                            <p class="mt-4 text-xs text-muted-foreground">No category totals in this period.</p>
                        @endif
                        <ul class="mt-3 grid grid-cols-1 gap-x-4 gap-y-1.5 border-t border-border pt-3 text-[11px] sm:grid-cols-2">
                            @foreach ($categoryChartSegments as $segment)
                                @php $segmentTotal = array_sum(array_map(fn ($row) => $row['segments'][$segment['key']], $categoryRows)); @endphp
                                <li class="flex min-w-0 items-center gap-1.5">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-sm" style="background-color: {{ $segment['color'] }}"></span>
                                    <span class="min-w-0 flex-1 truncate">{{ $segment['label'] }}</span>
                                    <strong class="shrink-0 tabular-nums">{{ number_format($segmentTotal) }}</strong>
                                </li>
                            @endforeach
                        </ul>
                    </article>
                </div>
            </section>

            <section id="staff" x-show="tab === 'staff'" x-cloak data-tab-panel="staff" class="grid min-w-0 gap-3">
                <div>
                    <h2 class="{{ $titleClass }}">Recipient performance</h2>
                    <p class="mt-0.5 {{ $mutedClass }}">Assigned counts use the current assignee, or current handler when no assignee is set.</p>
                </div>
                <article class="min-w-0 overflow-x-auto rounded-xl border border-border bg-white shadow-sm">
                    <table data-staff-sort-table class="w-full min-w-[1200px] text-left text-xs">
                        <thead class="border-b bg-primary text-white">
                            <tr class="text-left">
                                @foreach ([
                                    'name' => ['Name'],
                                    'designation' => ['Designation'],
                                    'assigned_tickets' => ['Tickets'],
                                    'open_tickets' => ['Open'],
                                    'oldest_open_ticket_age_days' => ['Oldest open'],
                                    'resolved_tickets' => ['Resolved'],
                                    'acknowledgment_average_days' => ['Avg time to', 'acknowledge (tickets)'],
                                    'handling_average_days' => ['Avg handling time', 'from assignment (tickets)'],
                                    'rating_average' => ['Avg rating'],
                                ] as $sortKey => $label)
                                    <th class="px-3 py-2.5 text-left font-semibold first:rounded-tl-xl last:rounded-tr-xl">
                                        <button type="button" data-staff-sort="{{ $sortKey }}" class="w-full text-left font-semibold leading-tight text-white hover:text-white/80">
                                            @foreach ($label as $labelLine)
                                                <span class="block">{{ $labelLine }}</span>
                                            @endforeach
                                        </button>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($staffRows as $row)
                                <tr data-staff-row
                                    data-sort-name="{{ $row['name'] }}"
                                    data-sort-designation="{{ $row['designation'] }}"
                                    data-sort-assigned_tickets="{{ $row['assigned_tickets'] }}"
                                    data-sort-open_tickets="{{ $row['open_tickets'] }}"
                                    data-sort-oldest_open_ticket_age_days="{{ $row['oldest_open_ticket_age_days'] ?? -1 }}"
                                    data-sort-resolved_tickets="{{ $row['resolved_tickets'] }}"
                                    data-sort-acknowledgment_average_days="{{ $row['acknowledgment_average_days'] ?? -1 }}"
                                    data-sort-handling_average_days="{{ $row['handling_average_days'] ?? -1 }}"
                                    data-sort-rating_average="{{ $row['rating_average'] ?? -1 }}"
                                    class="border-b border-border last:border-0">
                                    <th class="px-3 py-2.5 text-left font-medium">{{ $row['name'] }}<span class="block text-[10px] font-normal text-muted-foreground">{{ $row['unit'] }}</span></th>
                                    <td class="px-3 py-2.5 text-left">{{ $row['designation'] }}</td>
                                    <td class="px-3 py-2.5 text-left tabular-nums">{{ number_format($row['assigned_tickets']) }}</td>
                                    <td class="px-3 py-2.5 text-left tabular-nums">{{ number_format($row['open_tickets']) }}</td>
                                    <td class="px-3 py-2.5 text-left">{{ $row['oldest_open_ticket_age_days'] === null ? '—' : $days($row['oldest_open_ticket_age_days']) }}</td>
                                    <td class="px-3 py-2.5 text-left">{{ number_format($row['resolved_tickets']) }} ({{ number_format($row['resolved_percentage'], 1) }}%)</td>
                                    <td class="px-3 py-2.5 text-left">
                                        {{ $row['acknowledgment_average_days'] === null ? '—' : $days($row['acknowledgment_average_days']) }}
                                    </td>
                                    <td class="px-3 py-2.5 text-left">
                                        @if ($row['handling_average_days'] === null)
                                            —
                                        @else
                                            {{ $days($row['handling_average_days']) }}
                                            <span class="block text-[10px] text-muted-foreground">{{ number_format($row['handling_fallback_count']) }} fallback(s)</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-left">{{ $row['rating_average'] === null ? '—' : number_format($row['rating_average'], 1) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="px-3 py-4 text-left text-muted-foreground">—</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </article>

                <div class="grid min-w-0 gap-3 lg:grid-cols-3">
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
                    <article class="{{ $cardClass }}">
                        <h3 class="{{ $titleClass }}">Escalation and follow-up</h3>
                        <ul class="mt-2 divide-y divide-border text-xs">
                            <li class="flex justify-between gap-2 py-2"><span>Escalation rate</span><strong>{{ number_format($escalatedTickets) }} of {{ number_format($escalationTotal) }} ({{ number_format($escalationRate, 1) }}%)</strong></li>
                            @foreach ($escalationSegments as $segment)
                                <li class="flex justify-between gap-2 py-2"><span>{{ $segment['label'] }}</span><strong class="tabular-nums">{{ number_format($segment['count']) }}</strong></li>
                            @endforeach
                            <li class="flex justify-between gap-2 py-2"><span>Student escalation requests (followed by SDS)</span><strong>{{ number_format($performance['student_requests']['escalation']['count']) }} ({{ number_format($performance['student_requests']['escalation']['followed_by_sds_action']) }})</strong></li>
                            <li class="flex justify-between gap-2 py-2"><span>Student update requests (followed by SDS)</span><strong>{{ number_format($performance['student_requests']['update']['count']) }} ({{ number_format($performance['student_requests']['update']['followed_by_sds_action']) }})</strong></li>
                            <li class="flex justify-between gap-2 py-2"><span>Further-action rate</span><strong>{{ number_format($performance['further_action_rate'], 1) }}%</strong></li>
                            <li class="flex justify-between gap-2 py-2"><span>Reopened</span><strong class="tabular-nums">{{ number_format($performance['reopen_count']) }}</strong></li>
                        </ul>
                    </article>
                </div>
            </section>

            <section id="students" x-show="tab === 'students'" x-cloak data-tab-panel="students" class="grid min-w-0 gap-4 lg:grid-cols-2">
                <h2 class="sr-only">Students and feedback</h2>
                <article class="{{ $cardClass }}">
                    <h3 class="{{ $titleClass }}">Who raises tickets</h3>
                    <div class="mt-2 flex w-full max-w-md items-center gap-2 text-left" data-student-attribute-dropdown>
                        <label class="shrink-0 text-[11px] font-semibold text-foreground">Student attribute</label>
                        <details x-data="{}" class="group relative min-w-0 flex-1" x-on:click.outside="$el.removeAttribute('open')">
                            <summary aria-label="Student attribute" class="flex h-9 w-full cursor-pointer list-none items-center justify-between gap-1 rounded-md border border-input bg-white px-2.5 text-xs shadow-sm outline-none transition-colors hover:bg-muted focus-visible:border-primary focus-visible:ring-1 focus-visible:ring-primary [&::-webkit-details-marker]:hidden">
                                <span class="min-w-0 truncate">
                                    @foreach ($studentDimensions as $key => $dimension)
                                        <span x-show="studentDimension === '{{ $key }}'" @if ($key !== 'colleges') x-cloak @endif>{{ $dimension['label'] }}</span>
                                    @endforeach
                                </span>
                                <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                            </summary>
                            <div class="absolute top-full left-0 z-50 mt-1 max-h-72 w-full min-w-44 overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                @foreach ($studentDimensions as $key => $dimension)
                                    <button
                                        type="button"
                                        x-on:click="studentDimension = '{{ $key }}'; $el.closest('details').removeAttribute('open')"
                                        x-bind:class="studentDimension === '{{ $key }}' ? 'bg-primary-soft text-primary' : 'hover:bg-accent'"
                                        class="flex w-full rounded-sm px-2 py-1.5 text-left text-xs"
                                    >
                                        {{ $dimension['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        </details>
                    </div>
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

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        (() => {
        window.destroyAnalyticsVolumeCharts?.();
        const monthlyTicketVolumes = @js($monthlyTicketVolumes);
        const analyticsCategoryRows = @js($categoryRows);
        const analyticsCategorySegments = @js($categoryChartSegments);
        const volumeChartInstances = new Map();
        const volumeChartDefinitions = {
            'needs-resolution': [
                { label: 'Submitted', key: 'submitted', color: '#7a1d2a', pointStyle: 'circle' },
                { label: 'Resolved', key: 'resolved', color: '#15803d', pointStyle: 'rectRounded' },
            ],
            'informational-invalid': [
                { label: 'Informational', key: 'informational', color: '#14283f', pointStyle: 'circle' },
                { label: 'Invalid', key: 'invalid', color: '#dc2626', pointStyle: 'rectRounded' },
            ],
        };
        const analyticsVolumeFont = "'Source Sans 3', ui-sans-serif, system-ui, sans-serif";
        const analyticsVolumeHoverLine = {
            id: 'analyticsVolumeHoverLine',
            beforeDatasetsDraw(chart) {
                const active = chart.getActiveElements()[0];
                if (!active) return;

                const point = chart.getDatasetMeta(active.datasetIndex).data[active.index];
                if (!point) return;

                const { ctx, chartArea } = chart;
                ctx.save();
                ctx.strokeStyle = 'rgba(156, 163, 175, 0.7)';
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(point.x, chartArea.top);
                ctx.lineTo(point.x, chartArea.bottom);
                ctx.stroke();
                ctx.restore();
            },
        };
        const analyticsCategoryTotalLabels = {
            id: 'analyticsCategoryTotalLabels',
            afterDatasetsDraw(chart) {
                const { ctx, chartArea, scales } = chart;
                ctx.save();
                ctx.font = `600 11px ${analyticsVolumeFont}`;
                ctx.textBaseline = 'middle';

                analyticsCategoryRows.forEach((row, index) => {
                    const total = Number(row.total || 0);
                    const y = scales.y.getPixelForValue(index);
                    const barEnd = scales.x.getPixelForValue(total);
                    const label = String(total);
                    const labelWidth = ctx.measureText(label).width;
                    const fitsAfterBar = barEnd + 7 + labelWidth <= chartArea.right;

                    ctx.fillStyle = fitsAfterBar ? '#7a1d2a' : '#ffffff';
                    ctx.textAlign = fitsAfterBar ? 'left' : 'right';
                    ctx.fillText(label, fitsAfterBar ? barEnd + 7 : Math.max(chartArea.left + labelWidth, barEnd - 6), y);
                });

                ctx.restore();
            },
        };

        window.renderAnalyticsVolumeCharts = () => {
            requestAnimationFrame(() => {
                document.querySelectorAll('[data-analytics-volume-chart]').forEach((canvas) => {
                    if (canvas.offsetParent === null) return;

                    const existing = volumeChartInstances.get(canvas);
                    if (existing) {
                        existing.resize();
                        return;
                    }

                    const definition = volumeChartDefinitions[canvas.dataset.analyticsVolumeChart];
                    if (!definition) return;

                    const datasets = definition.map((series) => ({
                        label: series.label,
                        data: monthlyTicketVolumes.values.map((month) => month[series.key]),
                        borderColor: series.color,
                        backgroundColor: series.color,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: series.color,
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 5,
                        pointStyle: series.pointStyle,
                        borderWidth: 2,
                        tension: 0.35,
                        fill: false,
                    }));
                    const maximumValue = Math.max(0, ...datasets.flatMap((dataset) => dataset.data));
                    const suggestedMaximum = maximumValue <= 4 ? 4 : undefined;

                    volumeChartInstances.set(canvas, new Chart(canvas, {
                        type: 'line',
                        data: { labels: monthlyTicketVolumes.labels, datasets },
                        plugins: [analyticsVolumeHoverLine],
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            devicePixelRatio: Math.max(2, window.devicePixelRatio || 1),
                            animation: { duration: 500, easing: 'easeOutQuart' },
                            interaction: { mode: 'index', intersect: false },
                            layout: { padding: { top: 8, right: 8, bottom: 8, left: 4 } },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: '#ffffff',
                                    titleColor: '#111111',
                                    bodyColor: '#111827',
                                    titleFont: { family: analyticsVolumeFont, size: 12, weight: '600' },
                                    bodyFont: { family: analyticsVolumeFont, size: 11, weight: '600' },
                                    borderColor: '#e5e7eb',
                                    borderWidth: 1,
                                    cornerRadius: 8,
                                    padding: 8,
                                    usePointStyle: true,
                                    callbacks: {
                                        title: (items) => items[0]?.label ?? '',
                                        label: (item) => `${item.dataset.label}: ${item.parsed.y} ticket${item.parsed.y === 1 ? '' : 's'}`,
                                    },
                                },
                            },
                            scales: {
                                x: {
                                    grid: { display: false },
                                    ticks: { color: '#6b7280', maxRotation: 0, autoSkip: false, font: { family: analyticsVolumeFont, size: 10, weight: '400' } },
                                    border: { display: false },
                                },
                                y: {
                                    beginAtZero: true,
                                    suggestedMax: suggestedMaximum,
                                    ticks: { color: '#6b7280', precision: 0, font: { family: analyticsVolumeFont, size: 10 } },
                                    grid: { color: '#e5e7eb', borderDash: [4, 4], drawTicks: false },
                                    border: { display: false },
                                },
                            },
                        },
                    }));
                });

                const categoryCanvas = document.querySelector('[data-analytics-category-volume-chart]');
                if (!categoryCanvas || categoryCanvas.offsetParent === null) return;

                const existingCategoryChart = volumeChartInstances.get(categoryCanvas);
                if (existingCategoryChart) {
                    existingCategoryChart.resize();
                    return;
                }

                const categoryDatasets = analyticsCategorySegments.map((segment, segmentIndex) => ({
                    label: segment.label,
                    data: analyticsCategoryRows.map((row) => Number(row.segments?.[segment.key] || 0)),
                    backgroundColor: segment.color,
                    borderColor: segment.color,
                    borderWidth: 0,
                    borderSkipped: false,
                    borderRadius: (context) => {
                        const row = analyticsCategoryRows[context.dataIndex];
                        const lastVisibleIndex = analyticsCategorySegments.reduce(
                            (last, candidate, index) => Number(row?.segments?.[candidate.key] || 0) > 0 ? index : last,
                            -1,
                        );

                        return segmentIndex === lastVisibleIndex
                            ? { topLeft: 0, bottomLeft: 0, topRight: 5, bottomRight: 5 }
                            : 0;
                    },
                    maxBarThickness: 24,
                    categoryPercentage: 0.72,
                    barPercentage: 0.8,
                }));
                const categoryMaximum = Math.max(0, ...analyticsCategoryRows.map((row) => Number(row.total || 0)));

                volumeChartInstances.set(categoryCanvas, new Chart(categoryCanvas, {
                    type: 'bar',
                    data: {
                        labels: analyticsCategoryRows.map((row) => row.name),
                        datasets: categoryDatasets,
                    },
                    plugins: [analyticsCategoryTotalLabels],
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        devicePixelRatio: Math.max(2, window.devicePixelRatio || 1),
                        indexAxis: 'y',
                        animation: { duration: 500, easing: 'easeOutQuart' },
                        interaction: { mode: 'index', intersect: false, axis: 'y' },
                        layout: { padding: { top: 4, right: 28, bottom: 4, left: 4 } },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#ffffff',
                                titleColor: '#111111',
                                bodyColor: '#111827',
                                titleFont: { family: analyticsVolumeFont, size: 12, weight: '600' },
                                bodyFont: { family: analyticsVolumeFont, size: 11, weight: '600' },
                                borderColor: '#e5e7eb',
                                borderWidth: 1,
                                cornerRadius: 8,
                                padding: 8,
                                usePointStyle: true,
                                filter: (item) => Number(item.raw) > 0,
                                callbacks: {
                                    label: (item) => `${item.dataset.label}: ${item.raw}`,
                                    footer: (items) => `Total: ${items.reduce((sum, item) => sum + Number(item.raw || 0), 0)}`,
                                },
                            },
                        },
                        scales: {
                            x: {
                                stacked: true,
                                beginAtZero: true,
                                suggestedMax: categoryMaximum <= 4 ? 4 : undefined,
                                ticks: { color: '#6b7280', precision: 0, font: { family: analyticsVolumeFont, size: 10 } },
                                grid: { color: '#e5e7eb', borderDash: [4, 4], drawTicks: false },
                                border: { display: false },
                            },
                            y: {
                                stacked: true,
                                grid: { display: false },
                                ticks: { color: '#111111', autoSkip: false, font: { family: analyticsVolumeFont, size: 11, weight: '400' } },
                                border: { display: false },
                            },
                        },
                    },
                }));
            });
        };

        const renderAnalyticsChartsWhenVisible = () => {
            window.renderAnalyticsVolumeCharts();
            requestAnimationFrame(() => window.renderAnalyticsVolumeCharts());
            window.setTimeout(() => window.renderAnalyticsVolumeCharts(), 100);
        };

        // Alpine may still be revealing Overview after an in-app navigation, so retry after its
        // DOM update instead of relying on the window load event, which has already fired.
        renderAnalyticsChartsWhenVisible();
        if (document.readyState === 'loading') {
            window.addEventListener('load', renderAnalyticsChartsWhenVisible, { once: true });
        }

        window.destroyAnalyticsVolumeCharts = () => {
            volumeChartInstances.forEach((chart) => chart.destroy());
            volumeChartInstances.clear();
        };

        document.querySelectorAll('[data-staff-sort]').forEach((button) => {
            button.addEventListener('click', () => {
                const table = button.closest('[data-staff-sort-table]');
                const body = table?.querySelector('tbody');
                if (!table || !body) return;

                const key = button.dataset.staffSort;
                const previousKey = table.dataset.sortKey;
                const direction = previousKey === key && table.dataset.sortDirection === 'asc'
                    ? 'desc'
                    : key === 'name' ? 'asc' : 'desc';
                table.dataset.sortKey = key;
                table.dataset.sortDirection = direction;

                const rows = [...body.querySelectorAll('[data-staff-row]')];
                rows.sort((left, right) => {
                    const leftValue = left.getAttribute(`data-sort-${key}`) ?? '';
                    const rightValue = right.getAttribute(`data-sort-${key}`) ?? '';
                    const comparison = ['name', 'designation'].includes(key)
                        ? leftValue.localeCompare(rightValue)
                        : Number(leftValue) - Number(rightValue);

                    return direction === 'asc' ? comparison : -comparison;
                });
                rows.forEach((row) => body.append(row));
            });
        });
        })();
    </script>
    <script>
        (() => {
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
        })();
    </script>
</x-app-layout>
