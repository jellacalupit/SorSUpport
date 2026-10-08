<x-app-layout :role="'admin'" title="Analytics">
    @php
        $data = $dashboard;
        $total = (int) $data['total'];
        $percent = fn ($count, $of = null) => ($of ?? $total) > 0 ? round($count / ($of ?? $total) * 100, 1) : 0;
        $formatDate = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('M j, Y') : null;
        $hours = function ($value) {
            $value = (float) $value;

            return $value >= 48 ? round($value / 24, 1) . ' d' : round($value, 1) . ' h';
        };

        $periodLabel = ($filters['start_date'] ?? null) || ($filters['end_date'] ?? null)
            ? ($formatDate($filters['start_date'] ?? null) ?? 'Start') . ' – ' . ($formatDate($filters['end_date'] ?? null) ?? 'Today')
            : 'All time';
        $activeFilters = collect([
            ($filters['category_id'] ?? null) ? $categoryOptions->firstWhere('id', $filters['category_id'])?->name : null,
            ($filters['classification'] ?? null) ? ucwords(str_replace('_', ' ', $filters['classification'])) : null,
            ($filters['status'] ?? null) ? ucwords(str_replace('_', ' ', $filters['status'])) : null,
            $filters['unit'] ?? null,
        ])->filter()->values();

        $inProgress = (int) (($data['statusCounts']['Assigned'] ?? 0) + ($data['statusCounts']['In Progress'] ?? 0) + ($data['statusCounts']['Referred'] ?? 0));
        $awaitingReview = (int) (($data['statusCounts']['Submitted'] ?? 0) + ($data['statusCounts']['Needs Clarification'] ?? 0));
        $open = (int) ($awaitingReview + $inProgress + ($data['statusCounts']['Escalated'] ?? 0));
        $kpis = [
            ['label' => 'Total tickets', 'value' => number_format($total), 'hint' => $periodLabel, 'icon' => 'ticket', 'tone' => 'bg-primary-soft text-primary'],
            ['label' => 'Resolution rate', 'value' => $percent($data['resolved']) . '%', 'hint' => number_format($data['resolved']) . ' resolved or closed', 'icon' => 'check', 'tone' => 'bg-emerald-50 text-emerald-700'],
            ['label' => 'Avg. resolution time', 'value' => $hours($data['averageHours']), 'hint' => 'From submission to resolution', 'icon' => 'clock', 'tone' => 'bg-blue-50 text-blue-700'],
            ['label' => 'Escalation rate', 'value' => $data['escalations']['rate'] . '%', 'hint' => number_format($data['escalations']['tickets']) . ' ticket(s) escalated', 'icon' => 'alert-triangle', 'tone' => 'bg-red-50 text-red-700'],
        ];
        $pulse = [
            ['label' => 'Open now', 'value' => $open],
            ['label' => 'Awaiting review', 'value' => $awaitingReview],
            ['label' => 'In progress', 'value' => $inProgress],
            ['label' => 'Invalid', 'value' => $data['classification']['Invalid'] ?? 0],
        ];

        $statusSegments = [
            ['label' => 'Submitted', 'color' => '#facc15', 'count' => $awaitingReview],
            ['label' => 'In Progress', 'color' => '#2563eb', 'count' => $inProgress],
            ['label' => 'Escalated', 'color' => '#dc2626', 'count' => (int) ($data['statusCounts']['Escalated'] ?? 0)],
            ['label' => 'Resolved', 'color' => '#16a34a', 'count' => (int) ($data['statusCounts']['Resolved'] ?? 0)],
            ['label' => 'Closed', 'color' => '#9ca3af', 'count' => (int) ($data['statusCounts']['Closed'] ?? 0)],
        ];
        $escalationSegments = [
            ['label' => 'Never escalated', 'color' => '#10b981', 'count' => (int) $data['escalations']['never']],
            ['label' => 'Escalated once', 'color' => '#fbbf24', 'count' => (int) $data['escalations']['once']],
            ['label' => 'More than once', 'color' => '#ef4444', 'count' => (int) $data['escalations']['repeated']],
        ];
        $classificationBars = [
            ['label' => 'Needs Resolution', 'color' => 'bg-primary', 'count' => (int) ($data['classification']['Needs Resolution'] ?? 0)],
            ['label' => 'Informational', 'color' => 'bg-slate-800', 'count' => (int) ($data['classification']['Informational'] ?? 0)],
            ['label' => 'Invalid', 'color' => 'bg-red-500', 'count' => (int) ($data['classification']['Invalid'] ?? 0)],
        ];
        $categoryBars = collect($data['categoryCounts'])->sortDesc();
        $departmentBars = collect($data['units'])->sortByDesc('total')->values();
        $recipientRows = collect($data['recipients'])->sortByDesc('assigned')->values();

        $analyticsPayload = [
            'volume' => $data['volume'],
            'resolution' => ['labels' => array_keys($data['resolutionByCategory']), 'data' => array_values($data['resolutionByCategory'])],
        ];

        $filterDropdowns = [
            ['name' => 'category_id', 'label' => 'Category', 'all' => 'All categories', 'options' => $categoryOptions->mapWithKeys(fn ($category) => [(string) $category->id => $category->name])->all()],
            ['name' => 'classification', 'label' => 'Classification', 'all' => 'All classifications', 'options' => ['needs_resolution' => 'Needs Resolution', 'informational' => 'Informational', 'invalid' => 'Invalid', 'unclassified' => 'Unclassified']],
            ['name' => 'status', 'label' => 'Status', 'all' => 'All statuses', 'options' => \App\Models\Ticket::STATUS_LABELS],
            ['name' => 'unit', 'label' => 'College / Office', 'all' => 'All colleges and offices', 'options' => $unitOptions->mapWithKeys(fn ($unit) => [$unit => $unit])->all()],
        ];
        $cardClass = 'min-w-0 rounded-2xl border border-border bg-white p-4 shadow-sm';
        $titleClass = 'font-display text-sm font-bold text-foreground';
        $subtitleClass = 'mt-0.5 text-[11px] text-muted-foreground';
    @endphp

    <div class="grid min-w-0 gap-3 pb-6 sm:gap-4">
        <!-- What is being shown, and the report -->
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-foreground">{{ $periodLabel }}</p>
                <p class="flex flex-wrap gap-x-2 text-[11px] text-muted-foreground">
                    <span>{{ number_format($total) }} ticket(s)</span>
                    @forelse ($activeFilters as $label)
                        <span>· {{ $label }}</span>
                    @empty
                        <span>· No filters applied</span>
                    @endforelse
                </p>
            </div>
            {{-- Generate Report asks which parts to print; the report uses the filters applied on this page. --}}
            @php $reportSections = \App\Http\Controllers\Admin\AnalyticsController::REPORT_SECTIONS; @endphp
            <div x-data="{ open: @js($errors->has('sections')), chosen: @js(array_keys($reportSections)), all: @js(array_keys($reportSections)) }" class="shrink-0">
                <button type="button" x-on:click="open = true" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-md bg-primary px-3 text-xs font-semibold text-primary-foreground hover:bg-primary/90 sm:text-sm"><x-icons.download class="h-4 w-4" /> Generate Report</button>

                <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                    <form method="GET" action="{{ route('admin.analytics.export.pdf') }}" x-on:click.outside="open = false" x-on:submit="setTimeout(() => open = false, 300)" class="flex max-h-[min(85vh,40rem)] w-full max-w-md flex-col overflow-hidden rounded-2xl border border-border bg-white text-left shadow-2xl" role="dialog" aria-modal="true" aria-label="Generate report">
                        @foreach (request()->query() as $name => $value)
                            @if (is_scalar($value) && $name !== 'sections')
                                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <div class="flex items-start justify-between border-b border-border px-5 py-4">
                            <div>
                                <p class="text-[11px] font-semibold tracking-[0.2em] text-muted-foreground uppercase">Analytics</p>
                                <h2 class="mt-1 text-lg font-bold text-foreground">Generate Report</h2>
                            </div>
                            <button type="button" x-on:click="open = false" class="grid h-8 w-8 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground" aria-label="Close generate report"><x-icons.x class="h-4 w-4" /></button>
                        </div>
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
                                    <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-border px-3 py-2 text-sm text-foreground transition-colors hover:bg-muted/60" x-bind:class="chosen.includes(@js($key)) ? 'border-primary/30 bg-primary-soft/50' : ''">
                                        <input type="checkbox" name="sections[]" value="{{ $key }}" x-model="chosen" class="h-4 w-4 shrink-0 rounded-[4px] accent-red-800">
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
                            <button type="button" x-on:click="open = false" class="inline-flex h-9 items-center justify-center rounded-full border border-border bg-white px-4 text-xs font-semibold text-foreground hover:bg-muted">Cancel</button>
                            <button type="submit" x-bind:disabled="chosen.length === 0" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-full bg-primary px-5 text-xs font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-50"><x-icons.download class="h-4 w-4" /> Download PDF</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Filters: folded away on phones, always open from tablet width -->
        <form method="GET" x-data="{ open: window.innerWidth >= 768 }" class="{{ $cardClass }} relative z-20 p-3 sm:p-4">
            <button type="button" x-on:click="open = !open" class="flex w-full items-center justify-between gap-2 text-left md:pointer-events-none">
                <span class="flex items-center gap-2 text-sm font-bold text-primary">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h16l-6 7v5l-4 2v-7z" /></svg>
                    Filters
                    @if ($activeFilters->isNotEmpty())
                        <span class="rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold leading-none text-primary-foreground">{{ $activeFilters->count() }}</span>
                    @endif
                </span>
                <x-icons.arrow-down class="h-4 w-4 text-muted-foreground transition-transform md:hidden" ::class="open ? '' : 'rotate-180'" />
            </button>

            <div x-show="open" x-cloak class="mt-3 grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-4 xl:grid-cols-[1.6fr_1fr_1fr_1fr_1fr_auto]">
                <div class="col-span-2 lg:col-span-4 xl:col-span-1">
                    <label class="mb-1 block text-[11px] font-semibold text-muted-foreground">Date range</label>
                    <div class="flex items-center gap-1.5">
                        <input type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}" aria-label="Start date" class="h-9 min-w-0 flex-1 rounded-md border border-input bg-white px-2 text-xs">
                        <span class="shrink-0 text-xs text-muted-foreground">to</span>
                        <input type="date" name="end_date" value="{{ $filters['end_date'] ?? '' }}" aria-label="End date" class="h-9 min-w-0 flex-1 rounded-md border border-input bg-white px-2 text-xs">
                    </div>
                </div>
                @foreach ($filterDropdowns as $dropdown)
                    @php $current = (string) ($filters[$dropdown['name']] ?? ''); @endphp
                    <div class="min-w-0">
                        <label class="mb-1 block text-[11px] font-semibold text-muted-foreground">{{ $dropdown['label'] }}</label>
                        <details x-data="{}" class="group relative" x-on:click.outside="$el.removeAttribute('open')">
                            <summary class="flex h-9 cursor-pointer list-none items-center justify-between gap-1 rounded-md border border-input bg-white px-2.5 text-xs shadow-sm transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden">
                                <span class="truncate">{{ $dropdown['options'][$current] ?? $dropdown['all'] }}</span>
                                <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                            </summary>
                            <div class="absolute top-full z-50 mt-1 max-h-72 w-full min-w-44 overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md {{ $loop->even ? 'right-0 lg:right-auto' : '' }}">
                                @foreach (['' => $dropdown['all']] + $dropdown['options'] as $value => $label)
                                    <button type="button" data-analytics-filter="{{ $dropdown['name'] }}" data-value="{{ $value }}" data-label="{{ $label }}" class="relative flex w-full items-center rounded-sm py-1.5 px-2 text-left text-xs {{ $current === (string) $value ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">
                                        <span class="min-w-0 wrap-break-word">{{ $label }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </details>
                        <input type="hidden" name="{{ $dropdown['name'] }}" value="{{ $current }}" data-analytics-filter-input="{{ $dropdown['name'] }}">
                    </div>
                @endforeach
                <div class="col-span-2 flex items-end gap-2 lg:col-span-4 xl:col-span-1">
                    <button class="inline-flex h-9 flex-1 items-center justify-center rounded-md bg-primary px-4 text-xs font-semibold text-primary-foreground hover:bg-primary/90 xl:flex-none">Apply</button>
                    <a href="{{ route('admin.analytics.index') }}" class="inline-flex h-9 flex-1 items-center justify-center rounded-md border border-primary/40 px-4 text-xs font-semibold text-primary hover:bg-primary-soft xl:flex-none">Reset</a>
                </div>
            </div>
        </form>

        <!-- Headline numbers -->
        <div class="grid grid-cols-2 gap-2 sm:gap-3 xl:grid-cols-4">
            @foreach ($kpis as $kpi)
                <div class="{{ $cardClass }} p-3 sm:p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-muted-foreground sm:text-[11px]">{{ $kpi['label'] }}</p>
                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full sm:h-8 sm:w-8 {{ $kpi['tone'] }}"><x-dynamic-component :component="'icons.' . $kpi['icon']" class="h-4 w-4" /></span>
                    </div>
                    <p class="mt-1 font-display text-2xl font-bold leading-none text-primary sm:text-3xl">{{ $kpi['value'] }}</p>
                    <p class="mt-1.5 truncate text-[11px] text-muted-foreground">{{ $kpi['hint'] }}</p>
                </div>
            @endforeach
        </div>
        <dl class="grid grid-cols-4 divide-x divide-border rounded-2xl border border-border bg-white py-2.5 text-center shadow-sm">
            @foreach ($pulse as $item)
                <div class="min-w-0 px-1">
                    <dd class="font-display text-lg font-bold leading-none text-foreground">{{ number_format($item['value']) }}</dd>
                    <dt class="mt-1 truncate text-[10px] text-muted-foreground sm:text-[11px]">{{ $item['label'] }}</dt>
                </div>
            @endforeach
        </dl>

        <!-- Trend -->
        <section class="{{ $cardClass }}">
            <h2 class="{{ $titleClass }}">Ticket volume over time</h2>
            <p class="{{ $subtitleClass }}">Tickets submitted, resolved and closed in each period.</p>
            <div class="mt-3 h-56 sm:h-72"><canvas id="volumeChart"></canvas></div>
        </section>

        <!-- Where tickets stand -->
        <div class="grid gap-3 sm:gap-4 lg:grid-cols-3">
            <x-donut-chart title="Status" :segments="$statusSegments" class="rounded-2xl p-4" />
            <section class="{{ $cardClass }}">
                <h2 class="{{ $titleClass }}">Classification</h2>
                <p class="{{ $subtitleClass }}">How reviewed tickets were classified.</p>
                <div class="mt-3 grid gap-3">
                    @foreach ($classificationBars as $bar)
                        <div>
                            <div class="mb-1 flex justify-between gap-2 text-xs"><span class="text-muted-foreground">{{ $bar['label'] }}</span><strong class="tabular-nums">{{ $bar['count'] }} <span class="font-normal text-muted-foreground">({{ $percent($bar['count']) }}%)</span></strong></div>
                            <div class="h-2 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full {{ $bar['color'] }}" style="width: {{ $percent($bar['count']) }}%"></div></div>
                        </div>
                    @endforeach
                </div>
            </section>
            <x-donut-chart title="Escalations" :segments="$escalationSegments" class="rounded-2xl p-4" />
        </div>

        <!-- Where tickets come from -->
        <div class="grid gap-3 sm:gap-4 lg:grid-cols-2">
            <section class="{{ $cardClass }}">
                <h2 class="{{ $titleClass }}">Tickets by category</h2>
                <p class="{{ $subtitleClass }}">Which concerns students raise most.</p>
                <div class="mt-3 grid gap-2.5">
                    @forelse ($categoryBars as $label => $count)
                        <div>
                            <div class="mb-1 flex justify-between gap-2 text-xs"><span class="min-w-0 truncate text-foreground">{{ $label }}</span><strong class="shrink-0 tabular-nums">{{ $count }} <span class="font-normal text-muted-foreground">({{ $percent($count) }}%)</span></strong></div>
                            <div class="h-2 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full bg-primary" style="width: {{ $percent($count, max(1, $categoryBars->max())) }}%"></div></div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-xs text-muted-foreground">No tickets in this period.</p>
                    @endforelse
                </div>
            </section>
            <section class="{{ $cardClass }}">
                <h2 class="{{ $titleClass }}">Tickets by college / office</h2>
                <p class="{{ $subtitleClass }}">Offices handling the most tickets.</p>
                <div class="mt-3 grid gap-2.5">
                    @forelse ($departmentBars as $department)
                        <div>
                            <div class="mb-1 flex justify-between gap-2 text-xs"><span class="min-w-0 truncate text-foreground">{{ $department['name'] }}</span><strong class="shrink-0 tabular-nums">{{ $department['total'] }} <span class="font-normal text-muted-foreground">({{ $percent($department['total']) }}%)</span></strong></div>
                            <div class="h-2 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full bg-[#c4785a]" style="width: {{ $percent($department['total'], max(1, $departmentBars->max('total'))) }}%"></div></div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-xs text-muted-foreground">No college or office data in this period.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <!-- How fast tickets are resolved -->
        <section class="{{ $cardClass }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="{{ $titleClass }}">Resolution time by category</h2>
                    <p class="{{ $subtitleClass }}">Average days from submission to resolution.</p>
                </div>
                <dl class="grid grid-cols-3 divide-x divide-border text-center">
                    @foreach ([['Average', $data['averageHours']], ['Fastest', $data['fastestHours']], ['Longest', $data['longestHours']]] as [$label, $value])
                        <div class="px-3 first:pl-0 last:pr-0">
                            <dd class="font-display text-base font-bold leading-none text-primary">{{ $hours($value) }}</dd>
                            <dt class="mt-1 text-[10px] text-muted-foreground">{{ $label }}</dt>
                        </div>
                    @endforeach
                </dl>
            </div>
            <div data-resolution-box class="mt-3 h-64"><canvas id="resolutionChart"></canvas></div>
        </section>

        <!-- Who raises tickets, and how they end -->
        @php
            $breakdowns = $data['breakdowns'];
            $breakdownCards = [
                ['title' => 'Tickets by college', 'hint' => 'Where the students raising tickets belong. Students who hid their identity are not disclosed.', 'rows' => $breakdowns['colleges'], 'color' => 'bg-primary', 'empty' => 'No tickets in this period.'],
                ['title' => 'Tickets by program', 'hint' => 'The programs students raising tickets are enrolled in.', 'rows' => $breakdowns['programs'], 'color' => 'bg-[#c4785a]', 'empty' => 'No tickets in this period.'],
                ['title' => 'How tickets were resolved', 'hint' => 'The type of resolution the handler recorded.', 'rows' => $breakdowns['resolution_types'], 'color' => 'bg-emerald-600', 'empty' => 'No resolved tickets in this period.'],
                ['title' => 'Why tickets were closed', 'hint' => 'The reason recorded when each ticket was closed.', 'rows' => $breakdowns['closure_reasons'], 'color' => 'bg-slate-600', 'empty' => 'No closed tickets in this period.'],
                ['title' => 'Escalation level', 'hint' => 'How many times tickets needing resolution were escalated.', 'rows' => $breakdowns['escalation_levels'], 'color' => 'bg-red-600', 'empty' => 'No tickets needing resolution in this period.'],
            ];
            $satisfaction = $breakdowns['satisfaction'];
        @endphp
        <div class="grid gap-3 sm:gap-4 lg:grid-cols-2" data-analytics-breakdowns>
            @foreach ($breakdownCards as $card)
                @php $cardTotal = max(1, array_sum($card['rows'])); $cardMax = max(1, max($card['rows'] ?: [0])); @endphp
                <section class="{{ $cardClass }}">
                    <h2 class="{{ $titleClass }}">{{ $card['title'] }}</h2>
                    <p class="{{ $subtitleClass }}">{{ $card['hint'] }}</p>
                    <div class="mt-3 grid gap-2.5">
                        @forelse (array_filter($card['rows']) as $label => $count)
                            <div>
                                <div class="mb-1 flex justify-between gap-2 text-xs"><span class="min-w-0 truncate text-foreground">{{ $label }}</span><strong class="shrink-0 tabular-nums">{{ $count }} <span class="font-normal text-muted-foreground">({{ round($count / $cardTotal * 100) }}%)</span></strong></div>
                                <div class="h-2 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full {{ $card['color'] }}" style="width: {{ round($count / $cardMax * 100) }}%"></div></div>
                            </div>
                        @empty
                            <p class="py-6 text-center text-xs text-muted-foreground">{{ $card['empty'] }}</p>
                        @endforelse
                    </div>
                </section>
            @endforeach
            <section class="{{ $cardClass }}">
                <h2 class="{{ $titleClass }}">Student satisfaction</h2>
                <p class="{{ $subtitleClass }}">Ratings students gave after their tickets were closed.</p>
                @if ($satisfaction['count'] > 0)
                    <p class="mt-3 text-2xl font-bold text-foreground">{{ number_format($satisfaction['average'], 1) }} <span class="text-sm font-normal text-muted-foreground">out of 5 · {{ $satisfaction['count'] }} rating(s)</span></p>
                    <div class="mt-3 grid gap-2.5">
                        @foreach ($satisfaction['distribution'] as $rating => $count)
                            <div>
                                <div class="mb-1 flex justify-between gap-2 text-xs"><span class="text-foreground">{{ $rating }} out of 5</span><strong class="tabular-nums">{{ $count }}</strong></div>
                                <div class="h-2 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full bg-amber-500" style="width: {{ round($count / max(1, max($satisfaction['distribution'])) * 100) }}%"></div></div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="py-6 text-center text-xs text-muted-foreground">No ratings in this period.</p>
                @endif
            </section>
        </div>
        <!-- Who is handling them -->
        <section class="{{ $cardClass }} p-0">
            <div class="px-4 pt-4">
                <h2 class="{{ $titleClass }}">Recipient performance</h2>
                <p class="{{ $subtitleClass }}">Workload and results of everyone who handled tickets in this period.</p>
            </div>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[40rem] text-xs">
                    <thead class="border-y border-border bg-muted/50 text-[11px] uppercase tracking-wide text-muted-foreground">
                        <tr class="text-left"><th class="px-4 py-2 font-semibold">Recipient</th><th class="px-3 py-2 font-semibold">College / Office</th><th class="px-3 py-2 text-right font-semibold">Assigned</th><th class="px-3 py-2 font-semibold">Resolved</th><th class="px-3 py-2 text-right font-semibold">Avg. time</th><th class="px-4 py-2 text-right font-semibold">Escalated</th></tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($recipientRows as $recipient)
                            @php $rate = $percent($recipient['resolved'], max(1, $recipient['assigned'])); @endphp
                            <tr>
                                <td class="whitespace-nowrap px-4 py-2.5 font-semibold text-foreground">{{ $recipient['name'] }}</td>
                                <td class="px-3 py-2.5 text-muted-foreground">{{ $recipient['unit'] }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums">{{ $recipient['assigned'] }}</td>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="h-1.5 w-16 shrink-0 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full bg-emerald-500" style="width: {{ min(100, $rate) }}%"></div></div>
                                        <span class="tabular-nums">{{ $recipient['resolved'] }} <span class="text-muted-foreground">({{ $rate }}%)</span></span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums">{{ $recipient['average'] }} d</td>
                                <td class="px-4 py-2.5 text-right tabular-nums {{ $recipient['escalated'] > 0 ? 'font-semibold text-red-700' : 'text-muted-foreground' }}">{{ $recipient['escalated'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-muted-foreground">No recipient workload in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        (() => {
            document.querySelectorAll('[data-analytics-filter]').forEach((option) => {
                option.addEventListener('click', () => {
                    const dropdown = option.closest('details');
                    const input = document.querySelector(`[data-analytics-filter-input="${option.dataset.analyticsFilter}"]`);

                    if (input) input.value = option.dataset.value ?? '';
                    dropdown.querySelector('summary span').textContent = option.dataset.label ?? '';
                    dropdown.removeAttribute('open');
                });
            });

            if (typeof Chart === 'undefined') return;

            const payload = @json($analyticsPayload);
            const fontSans = "'Source Sans 3', ui-sans-serif, system-ui, sans-serif";
            const muted = '#6b7280';
            const narrow = window.innerWidth < 640;
            Chart.defaults.font.family = fontSans;
            Chart.defaults.font.size = 11;
            Chart.defaults.color = muted;

            const wrapLabel = (name, length) => {
                const lines = [];
                let line = '';
                String(name).split(' ').forEach((word) => {
                    if (line && `${line} ${word}`.length > length) {
                        lines.push(line);
                        line = word;
                    } else {
                        line = line ? `${line} ${word}` : word;
                    }
                });
                if (line) lines.push(line);
                return lines;
            };

            const volumeLabels = payload.volume.labels.map((label) => {
                if (payload.volume.period === 'monthly') return new Date(`${label}-01T00:00:00`).toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
                if (payload.volume.period === 'weekly') return `Week ${label.split('-')[1]}`;
                return new Date(`${label}T00:00:00`).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            });
            const line = (label, data, color, fill) => ({ label, data, borderColor: color, backgroundColor: `${color}1a`, borderWidth: 2, pointRadius: narrow ? 0 : 3, pointHoverRadius: 4, pointBackgroundColor: '#ffffff', pointBorderWidth: 2, fill, tension: 0.3 });

            const volumeCanvas = document.getElementById('volumeChart');
            if (volumeCanvas) {
                new Chart(volumeCanvas, {
                    type: 'line',
                    data: { labels: volumeLabels, datasets: [line('Submitted', payload.volume.submitted, '#7a1d2a', true), line('Resolved', payload.volume.resolved, '#16a34a', false), line('Closed', payload.volume.closed, '#f59e0b', false)] },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'line', boxWidth: 24, padding: 14 } } },
                        scales: {
                            x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: narrow ? 5 : 12 } },
                            y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#eef0f3' }, border: { display: false } },
                        },
                    },
                });
            }

            // Sideways bars on phones so the category names stay readable.
            const resolutionCanvas = document.getElementById('resolutionChart');
            if (resolutionCanvas) {
                const box = document.querySelector('[data-resolution-box]');
                const count = payload.resolution.labels.length;
                // Sideways bars on phones, and whenever there are too many categories for each to get
                // a readable column (under about 90px); the chart then grows one row per category.
                const sideways = narrow || (box && box.clientWidth / Math.max(1, count) < 90);
                if (sideways && box) box.style.height = `${Math.max(200, count * (narrow ? 40 : 30) + 30)}px`;
                const categoryAxis = { grid: { display: false }, ticks: { autoSkip: false, maxRotation: 0, callback: (value, index) => wrapLabel(payload.resolution.labels[index], sideways ? (narrow ? 16 : 30) : 18) }, border: { display: false } };
                const valueAxis = { beginAtZero: true, grid: { color: '#eef0f3' }, border: { display: false }, title: { display: !narrow, text: 'Days' } };

                new Chart(resolutionCanvas, {
                    type: 'bar',
                    data: { labels: payload.resolution.labels, datasets: [{ label: 'Average days', data: payload.resolution.data, backgroundColor: '#7a1d2a', borderRadius: 4, maxBarThickness: 28 }] },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: sideways ? 'y' : 'x',
                        plugins: { legend: { display: false } },
                        scales: sideways ? { x: valueAxis, y: categoryAxis } : { x: categoryAxis, y: valueAxis },
                    },
                });
            }
        })();
    </script>
</x-app-layout>
