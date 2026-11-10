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
        $classificationColors = [
            'needs_resolution' => '#7a1d2a',
            'informational' => '#14283f',
            'invalid' => '#dc2626',
            'unclassified' => '#9ca3af',
            'resolved' => '#15803d',
        ];
        $statusClassificationCounts = $data['statusClassificationCounts'];
        $statusSegments = [];
        foreach (\App\Models\Ticket::DISPLAY_STATUS_LABELS as $status => $label) {
            $statusSegments[] = [
                'label' => $label . ' (Needs Resolution)',
                'count' => (int) ($statusClassificationCounts[$status . ':needs_resolution'] ?? 0),
                'color' => $status === \App\Models\Ticket::STATUS_RESOLVED
                    ? $classificationColors['resolved']
                    : $classificationColors['needs_resolution'],
            ];
            if (in_array($status, \App\Models\Ticket::UNCLASSIFIED_DISPLAY_STATUSES, true)) {
                $statusSegments[] = [
                    'label' => $label . ' (Unclassified)',
                    'count' => (int) ($statusClassificationCounts[$status . ':unclassified'] ?? 0),
                    'color' => $classificationColors['unclassified'],
                ];
            }
        }
        $statusDonutTotal = array_sum(array_column($statusSegments, 'count'));
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
        $categoryEmptyCount = $data['categoryVolumes']['empty_category_count'];
        $categoryVolumeMax = max(1, max(array_column($categoryRows ?: [['total' => 1]], 'total')));

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
                @foreach (['overview' => 'Overview', 'staff' => 'Staff performance', 'students' => 'Students and feedback'] as $tabKey => $tabLabel)
                    <a href="#{{ $tabKey }}" x-on:click.prevent="selectTab('{{ $tabKey }}')" x-bind:aria-current="tab === '{{ $tabKey }}' ? 'page' : null" x-bind:class="tab === '{{ $tabKey }}' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'" class="shrink-0 border-b-2 px-3 py-2.5 text-sm font-semibold">{{ $tabLabel }}</a>
                @endforeach
            </nav>

            <section id="overview" x-show="tab === 'overview'" x-cloak data-tab-panel="overview" class="grid min-w-0 gap-4">
                <h2 class="sr-only">Overview</h2>
                <div class="grid min-w-0 gap-4 lg:grid-cols-2">
                    @foreach ([
                        ['title' => 'Needs-resolution tickets', 'key' => 'needs-resolution', 'color1' => $classificationColors['needs_resolution'], 'color2' => $classificationColors['resolved'], 'label1' => 'Submitted', 'label2' => 'Resolved', 'key1' => 'submitted', 'key2' => 'resolved', 'totals' => $monthlyGraphOneTotals],
                        ['title' => 'Informational and invalid tickets', 'key' => 'informational-invalid', 'color1' => $classificationColors['informational'], 'color2' => $classificationColors['invalid'], 'label1' => 'Informational', 'label2' => 'Invalid', 'key1' => 'informational', 'key2' => 'invalid', 'totals' => $monthlyGraphTwoTotals],
                    ] as $graph)
                        <article class="{{ $cardClass }}">
                            <h3 class="{{ $titleClass }}">{{ $graph['title'] }}</h3>
                            <p class="mt-0.5 {{ $mutedClass }}">Monthly ticket volume over the latest 12 months in the selected range.</p>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-muted-foreground" aria-label="{{ $graph['title'] }} chart labels">
                                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $graph['color1'] }}"></span>{{ $graph['label1'] }}</span>
                                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background-color: {{ $graph['color2'] }}"></span>{{ $graph['label2'] }}</span>
                            </div>
                            <div class="mt-2 h-[220px] min-w-0">
                                <canvas data-analytics-volume-chart="{{ $graph['key'] }}" role="img" aria-label="{{ $graph['title'] }} by month"></canvas>
                            </div>
                            @if (array_sum($graph['totals']) === 0)
                                <p class="mt-1 text-[11px] text-muted-foreground">No ticket volume data in this period.</p>
                            @endif
                            <p class="mt-2 text-[10px] text-muted-foreground">
                                12-month totals: {{ $graph['label1'] }} {{ number_format($graph['totals'][$graph['key1']]) }} · {{ $graph['label2'] }} {{ number_format($graph['totals'][$graph['key2']]) }}
                            </p>
                        </article>
                    @endforeach
                </div>

                <div class="grid min-w-0 items-start gap-4 xl:grid-cols-[minmax(19rem,0.9fr)_minmax(0,1.4fr)]">
                    <div class="grid min-w-0 gap-4">
                        <x-donut-chart title="Status" description="Needs-resolution and unclassified tickets" :segments="$statusSegments" total-label="Tickets" large />
                        @if ($statusDonutTotal === 0)
                            <p class="-mt-2 text-xs text-muted-foreground">No tickets in this period.</p>
                        @endif
                        <x-donut-chart title="Classification" :segments="$classificationSegments" total-label="Tickets" large />
                    </div>

                    <article class="{{ $cardClass }} min-w-0">
                        <h3 class="{{ $titleClass }}">Volume by category</h3>
                        <p class="mt-0.5 {{ $mutedClass }}">Each ticket appears in exactly one classification segment.</p>
                        @forelse ($categoryRows as $row)
                            <div class="mt-4 grid grid-cols-[minmax(5rem,8rem)_minmax(0,1fr)_2rem] items-center gap-2" data-category-total="{{ $row['total'] }}">
                                <span class="truncate text-xs" title="{{ $row['name'] }}">{{ $row['name'] }}</span>
                                <div class="flex h-6 min-w-0 overflow-hidden rounded bg-muted" role="img" aria-label="{{ $row['name'] }}: {{ $row['total'] }} tickets">
                                    @foreach ([
                                        ['key' => 'submitted', 'label' => 'Submitted (not resolved)', 'color' => $classificationColors['needs_resolution']],
                                        ['key' => 'resolved', 'label' => 'Resolved', 'color' => $classificationColors['resolved']],
                                        ['key' => 'informational', 'label' => 'Informational', 'color' => $classificationColors['informational']],
                                        ['key' => 'invalid', 'label' => 'Invalid', 'color' => $classificationColors['invalid']],
                                        ['key' => 'unclassified', 'label' => 'Unclassified', 'color' => $classificationColors['unclassified']],
                                    ] as $segment)
                                        @php
                                            $count = $row['segments'][$segment['key']];
                                            $width = $row['total'] ? $count / $row['total'] * 100 : 0;
                                        @endphp
                                        @if ($count > 0)
                                            <span class="flex h-full min-w-0 items-center justify-center overflow-hidden text-[10px] font-semibold text-white" style="width: {{ $width }}%; background-color: {{ $segment['color'] }}" title="{{ $segment['label'] }}: {{ $count }}" aria-label="{{ $segment['label'] }}: {{ $count }}">
                                                @if ($width >= 12){{ number_format($count) }}@endif
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                                <strong class="text-right text-xs tabular-nums">{{ number_format($row['total']) }}</strong>
                            </div>
                        @empty
                            <p class="mt-4 text-xs text-muted-foreground">No category totals in this period.</p>
                        @endforelse
                        <ul class="mt-4 flex flex-wrap gap-x-4 gap-y-2 border-t border-border pt-3 text-[11px]">
                            @foreach ([
                                ['label' => 'Submitted (not resolved)', 'color' => $classificationColors['needs_resolution']],
                                ['label' => 'Resolved', 'color' => $classificationColors['resolved']],
                                ['label' => 'Informational', 'color' => $classificationColors['informational']],
                                ['label' => 'Invalid', 'color' => $classificationColors['invalid']],
                                ['label' => 'Unclassified', 'color' => $classificationColors['unclassified']],
                            ] as $segment)
                                <li class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background-color: {{ $segment['color'] }}"></span>{{ $segment['label'] }}</li>
                            @endforeach
                        </ul>
                        <p class="mt-2 text-[10px] text-muted-foreground">{{ number_format($categoryEmptyCount) }} categories have no tickets.</p>
                    </article>
                </div>
            </section>

            <section id="staff" x-show="tab === 'staff'" x-cloak data-tab-panel="staff" class="grid min-w-0 gap-3">
                <h2 class="sr-only">Staff performance</h2>
                <p class="text-[11px] text-muted-foreground">
                    Review time {{ $days($performance['review_time']['average_days']) }},
                    time to acknowledge {{ $days($performance['acknowledgment_time']['average_days']) }},
                    first staff reply {{ $days($performance['first_response_time']['average_days']) }},
                    active handling time {{ $days($resolutionTime['average_days']) }}.
                </p>
                <article class="{{ $cardClass }} min-w-0 overflow-x-auto">
                    <h3 class="{{ $titleClass }}">Staff performance</h3>
                    <p class="mt-0.5 {{ $mutedClass }}">Assigned counts use the current assignee, or current handler when no assignee is set.</p>
                    <table data-staff-sort-table class="mt-2 w-full min-w-[1080px] text-left text-xs">
                        <thead class="border-b border-border text-muted-foreground">
                            <tr>
                                @foreach ([
                                    'name' => 'Name',
                                    'assigned_tickets' => 'Tickets',
                                    'open_tickets' => 'Open',
                                    'oldest_open_ticket_age_days' => 'Oldest open',
                                    'resolved_tickets' => 'Resolved',
                                    'acknowledgment_average_days' => 'Avg time to acknowledge',
                                    'handling_average_days' => 'Avg handling time (from assignment)',
                                    'rating_average' => 'Avg rating',
                                ] as $sortKey => $label)
                                    <th class="px-2 py-2 first:pl-0">
                                        <button type="button" data-staff-sort="{{ $sortKey }}" class="font-semibold hover:text-foreground">{{ $label }}</button>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($staffRows as $row)
                                <tr data-staff-row
                                    data-sort-name="{{ $row['name'] }}"
                                    data-sort-assigned_tickets="{{ $row['assigned_tickets'] }}"
                                    data-sort-open_tickets="{{ $row['open_tickets'] }}"
                                    data-sort-oldest_open_ticket_age_days="{{ $row['oldest_open_ticket_age_days'] ?? -1 }}"
                                    data-sort-resolved_tickets="{{ $row['resolved_tickets'] }}"
                                    data-sort-acknowledgment_average_days="{{ $row['acknowledgment_average_days'] ?? -1 }}"
                                    data-sort-handling_average_days="{{ $row['handling_average_days'] ?? -1 }}"
                                    data-sort-rating_average="{{ $row['rating_average'] ?? -1 }}"
                                    class="border-b border-border last:border-0">
                                    <th class="py-2 pr-3 font-medium">{{ $row['name'] }}<span class="block text-[10px] font-normal text-muted-foreground">{{ $row['unit'] }}</span></th>
                                    <td class="px-2 py-2 text-right tabular-nums">{{ number_format($row['assigned_tickets']) }}</td>
                                    <td class="px-2 py-2 text-right tabular-nums">{{ number_format($row['open_tickets']) }}</td>
                                    <td class="px-2 py-2">{{ $row['oldest_open_ticket_age_days'] === null ? 'No data' : $days($row['oldest_open_ticket_age_days']) }}</td>
                                    <td class="px-2 py-2">{{ number_format($row['resolved_tickets']) }} ({{ number_format($row['resolved_percentage'], 1) }}%)</td>
                                    <td class="px-2 py-2">
                                        {{ $row['acknowledgment_average_days'] === null ? 'No data' : $days($row['acknowledgment_average_days']) . ' (' . number_format($row['acknowledgment_count']) . ')' }}
                                    </td>
                                    <td class="px-2 py-2">
                                        @if ($row['handling_average_days'] === null)
                                            No data
                                        @else
                                            {{ $days($row['handling_average_days']) }} ({{ number_format($row['handling_count']) }})
                                        @endif
                                        <span class="block text-[10px] text-muted-foreground">{{ number_format($row['handling_fallback_count']) }} fallback(s)</span>
                                    </td>
                                    <td class="px-2 py-2">{{ $row['rating_average'] === null ? 'No ratings' : number_format($row['rating_average'], 1) . ' (' . number_format($row['rating_count']) . ')' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="py-4 text-center text-muted-foreground">No staff performance data in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </article>
                <div class="grid min-w-0 gap-3 text-[11px] text-muted-foreground">
                    <p>Averages from very few tickets are not reliable; see the count in brackets.</p>
                    <p>Escalations are counted by category and level, not per handler.</p>
                    <p>Committee outcomes: average {{ $days($resolutionTime['committee_outcomes']['average_days'] ?? null) }} ({{ number_format($resolutionTime['committee_outcomes']['ticket_count'] ?? 0) }} tickets).</p>
                </div>

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

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const monthlyTicketVolumes = @js($monthlyTicketVolumes);
        const volumeChartInstances = new Map();
        const volumeChartDefinitions = {
            'needs-resolution': [
                { label: 'Submitted', key: 'submitted', color: '#7a1d2a', pointStyle: 'circle', borderDash: [] },
                { label: 'Resolved', key: 'resolved', color: '#15803d', pointStyle: 'rectRounded', borderDash: [] },
            ],
            'informational-invalid': [
                { label: 'Informational', key: 'informational', color: '#14283f', pointStyle: 'circle', borderDash: [] },
                { label: 'Invalid', key: 'invalid', color: '#dc2626', pointStyle: 'triangle', borderDash: [6, 4] },
            ],
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
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        pointStyle: series.pointStyle,
                        borderDash: series.borderDash,
                        borderWidth: 2,
                        tension: 0,
                        fill: false,
                    }));

                    volumeChartInstances.set(canvas, new Chart(canvas, {
                        type: 'line',
                        data: { labels: monthlyTicketVolumes.labels, datasets },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: '#ffffff',
                                    titleColor: '#6b7280',
                                    bodyColor: '#111827',
                                    borderColor: '#e5e7eb',
                                    borderWidth: 1,
                                    cornerRadius: 8,
                                    padding: 8,
                                    callbacks: {
                                        title: (items) => items[0]?.label ?? '',
                                        label: (item) => `${item.dataset.label}: ${item.parsed.y}`,
                                    },
                                },
                            },
                            scales: {
                                x: {
                                    grid: { display: false },
                                    ticks: { color: '#6b7280', maxRotation: 0, autoSkip: false, font: { size: 9 } },
                                    border: { display: false },
                                },
                                y: {
                                    beginAtZero: true,
                                    ticks: { color: '#6b7280', precision: 0, font: { size: 10 } },
                                    grid: { color: '#e5e7eb', borderDash: [4, 4], drawTicks: false },
                                    border: { display: false },
                                },
                            },
                        },
                    }));
                });
            });
        };

        window.addEventListener('load', () => window.renderAnalyticsVolumeCharts());

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
                    const comparison = key === 'name'
                        ? leftValue.localeCompare(rightValue)
                        : Number(leftValue) - Number(rightValue);

                    return direction === 'asc' ? comparison : -comparison;
                });
                rows.forEach((row) => body.append(row));
            });
        });
    </script>
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
