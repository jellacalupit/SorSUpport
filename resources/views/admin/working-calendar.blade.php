<x-app-layout :role="'admin'" title="Working Calendar">
    <div x-data="{ selectedDate: null, selectedType: 'non_working', selectedDescription: '', selectedDeleteUrl: '', rangeExpanded: true, showAllOverrides: false }" class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
        <section class="rounded-2xl border border-border bg-white p-4 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="font-display text-xl font-bold text-foreground">{{ $month->format('F Y') }}</h1>
                <nav aria-label="Calendar month navigation" class="flex items-center gap-2">
                    <a href="{{ route('admin.working-calendar', ['month' => $previousMonth]) }}" class="rounded-md border border-border px-3 py-2 text-sm font-medium hover:bg-muted" aria-label="Previous month">&larr;</a>
                    <a href="{{ route('admin.working-calendar', ['month' => $todayMonth]) }}" class="rounded-md border border-border px-3 py-2 text-sm font-medium hover:bg-muted">Today</a>
                    <a href="{{ route('admin.working-calendar', ['month' => $nextMonth]) }}" class="rounded-md border border-border px-3 py-2 text-sm font-medium hover:bg-muted" aria-label="Next month">&rarr;</a>
                </nav>
            </div>

            @if (session('success'))
                <p role="status" class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('success') }}</p>
            @endif
            @if ($errors->any())
                <div role="alert" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="mt-5 flex flex-wrap gap-3 text-xs text-muted-foreground" aria-label="Calendar legend">
                <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm border border-border bg-slate-100"></span> Weekend (default non-working)</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm bg-rose-100"></span> Non-working override</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm bg-emerald-100"></span> Working override</span>
            </div>

            <div class="mt-4 grid grid-cols-7 border-l border-t border-border text-center">
                @foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $weekday)
                    <div class="border-b border-r border-border bg-muted/50 px-1 py-2 text-[10px] font-semibold uppercase text-muted-foreground sm:text-xs">{{ $weekday }}</div>
                @endforeach
                @foreach ($calendarDays as $day)
                    @php
                        $dayStyle = match ($day['type']) {
                            \App\Models\CalendarOverride::TYPE_NON_WORKING => 'bg-rose-50 text-rose-950 hover:bg-rose-100',
                            \App\Models\CalendarOverride::TYPE_WORKING => 'bg-emerald-50 text-emerald-950 hover:bg-emerald-100',
                            default => $day['is_weekend'] ? 'bg-white text-slate-500 hover:bg-slate-50' : 'bg-white text-foreground hover:bg-primary/5',
                        };
                    @endphp
                    <button
                        type="button"
                        data-date="{{ $day['date'] }}"
                        data-type="{{ $day['type'] }}"
                        data-description="{{ $day['description'] }}"
                        data-delete-url="{{ $day['override_id'] ? route('admin.working-calendar.overrides.destroy', $day['override_id']) : '' }}"
                        x-on:click="selectedDate = $el.dataset.date; selectedType = $el.dataset.type || 'non_working'; selectedDescription = $el.dataset.description || ''; selectedDeleteUrl = $el.dataset.deleteUrl"
                        class="flex min-h-20 flex-col items-center border-b border-r border-border p-1.5 text-center transition sm:min-h-24 sm:p-2 {{ $dayStyle }} {{ $day['in_month'] ? '' : 'opacity-40' }} {{ $day['is_today'] ? 'ring-2 ring-inset ring-primary' : '' }}"
                        aria-label="{{ $day['date'] }}{{ $day['type'] ? ', ' . str_replace('_', ' ', $day['type']) . ' override' : ($day['is_weekend'] ? ', weekend' : '') }}"
                    >
                        <span class="block text-sm font-semibold leading-none">{{ $day['day'] }}</span>
                        @if ($day['type'] === \App\Models\CalendarOverride::TYPE_NON_WORKING)
                            <span class="mt-3 inline-flex max-w-full items-center justify-center rounded-md bg-rose-200/80 px-1.5 py-1 text-[9px] font-semibold leading-tight text-rose-950 sm:px-2 sm:text-xs">Non-working</span>
                        @elseif ($day['type'] === \App\Models\CalendarOverride::TYPE_WORKING)
                            <span class="mt-3 inline-flex max-w-full items-center justify-center rounded-md bg-emerald-200/80 px-1.5 py-1 text-[9px] font-semibold leading-tight text-emerald-950 sm:px-2 sm:text-xs">Working</span>
                        @elseif ($day['is_weekend'])
                            <span class="mt-3 block text-[9px] font-medium uppercase leading-tight text-slate-500 sm:text-[10px]">Weekend</span>
                        @endif
                    </button>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-muted-foreground">Monday to Friday are working days by default. An override takes precedence over the weekday rule.</p>
        </section>

        <aside class="grid content-start gap-4">
            <section class="rounded-2xl border border-border bg-white p-4 shadow-sm sm:p-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-display text-base font-bold text-foreground">Mark a range</h2>
                    <button
                        type="button"
                        x-on:click="rangeExpanded = !rangeExpanded"
                        x-bind:aria-expanded="rangeExpanded"
                        x-bind:aria-label="rangeExpanded ? 'Collapse Mark a range' : 'Expand Mark a range'"
                        class="grid h-8 w-8 shrink-0 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-primary focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary"
                    >
                        <svg class="h-4 w-4 transition-transform duration-200" x-bind:class="rangeExpanded ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                    </button>
                </div>
                <div x-show="rangeExpanded" x-collapse>
                    <p class="mt-1 text-xs text-muted-foreground">Use for multi-day suspensions, breaks, or make-up working days. Maximum 60 calendar days, inclusive.</p>
                    <form method="POST" action="{{ route('admin.working-calendar.ranges.store') }}" class="mt-4 grid gap-3">
                        @csrf
                        <label class="grid gap-1 text-xs font-medium text-foreground">Start date
                            <input type="date" name="start_date" required value="{{ old('start_date') }}" class="rounded-md border border-input bg-white px-3 py-2 text-sm outline-none transition focus:border-primary focus:ring-1 focus:ring-primary">
                        </label>
                        <label class="grid gap-1 text-xs font-medium text-foreground">End date
                            <input type="date" name="end_date" required value="{{ old('end_date') }}" class="rounded-md border border-input bg-white px-3 py-2 text-sm outline-none transition focus:border-primary focus:ring-1 focus:ring-primary">
                        </label>
                        <div class="grid gap-1">
                            <p class="text-xs font-medium text-foreground">Type</p>
                            <x-picker
                                name="type"
                                required
                                label="Override type"
                                :selected="old('type', 'non_working')"
                                :options="[
                                    ['value' => 'non_working', 'label' => 'Non-working'],
                                    ['value' => 'working', 'label' => 'Working'],
                                ]"
                            />
                        </div>
                        <label class="grid gap-1 text-xs font-medium text-foreground">Description
                            <input type="text" name="description" maxlength="255" required value="{{ old('description') }}" placeholder="Short reason or event" class="rounded-md border border-input bg-white px-3 py-2 text-sm outline-none transition focus:border-primary focus:ring-1 focus:ring-primary">
                        </label>
                        <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground hover:bg-primary/90">Apply range</button>
                    </form>
                </div>
            </section>

            <section class="rounded-2xl border border-border bg-white p-4 shadow-sm sm:p-5">
                <h2 class="font-display text-base font-bold text-foreground">Overrides in {{ $month->format('F') }}</h2>
                @if ($overrides->isEmpty())
                    <p class="mt-3 text-sm text-muted-foreground">No overrides have been set for this month.</p>
                @else
                    <ul class="mt-3 divide-y divide-border">
                        @foreach ($overrides as $override)
                            <li
                                @if ($loop->index >= 3) x-cloak @endif
                                x-show="showAllOverrides || {{ $loop->index < 3 ? 'true' : 'false' }}"
                                x-transition.opacity
                                class="flex items-start justify-between gap-3 py-3 first:pt-0"
                            >
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-foreground">
                                        {{ $override->date->format('M j') }} · {{ $override->type === \App\Models\CalendarOverride::TYPE_WORKING ? 'Working' : 'Non-working' }}
                                    </p>
                                    <p class="mt-0.5 break-words text-xs text-muted-foreground">{{ $override->description }}</p>
                                </div>
                                <form method="POST" action="{{ route('admin.working-calendar.overrides.destroy', $override) }}" onsubmit="return confirm('Remove this calendar override?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="shrink-0 rounded-md border border-border px-2.5 py-1.5 text-xs font-medium hover:bg-muted">Remove</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                    @if ($overrides->count() > 3)
                        <button
                            type="button"
                            x-on:click="showAllOverrides = !showAllOverrides; rangeExpanded = !showAllOverrides"
                            x-bind:aria-expanded="showAllOverrides"
                            class="mt-3 w-full rounded-md border border-border px-3 py-2 text-xs font-semibold text-primary transition-colors hover:border-primary hover:bg-primary-soft focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary"
                        >
                            <span x-show="!showAllOverrides">See all overrides ({{ $overrides->count() }})</span>
                            <span x-cloak x-show="showAllOverrides">Show less</span>
                        </button>
                    @endif
                @endif
            </section>
        </aside>

        <div x-cloak x-show="selectedDate" x-transition.opacity class="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" role="dialog" aria-modal="true" aria-labelledby="calendar-override-title" x-on:keydown.escape.window="selectedDate = null">
            <div x-on:click.outside="selectedDate = null" class="w-full max-w-sm rounded-xl border border-border bg-white p-5 shadow-xl">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 id="calendar-override-title" class="font-display text-lg font-bold text-foreground">Set date override</h2>
                        <p class="mt-0.5 text-sm text-muted-foreground" x-text="selectedDate"></p>
                    </div>
                    <button type="button" x-on:click="selectedDate = null" class="rounded-md px-2 py-1 text-muted-foreground hover:bg-muted" aria-label="Close">&times;</button>
                </div>
                <form method="POST" action="{{ route('admin.working-calendar.overrides.store') }}" class="mt-4 grid gap-3">
                    @csrf
                    <input type="hidden" name="date" x-bind:value="selectedDate">
                    <label class="grid gap-1 text-xs font-medium text-foreground">Type
                        <span class="relative block">
                            <select name="type" x-model="selectedType" required class="w-full appearance-none rounded-md border border-input bg-white px-3 py-2 pr-10 text-sm outline-none transition focus:border-primary focus:ring-1 focus:ring-primary">
                                <option value="non_working">Non-working</option>
                                <option value="working">Working</option>
                            </select>
                            <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                        </span>
                    </label>
                    <label class="grid gap-1 text-xs font-medium text-foreground">Description
                        <input type="text" name="description" x-model="selectedDescription" maxlength="255" required class="rounded-md border border-input bg-white px-3 py-2 text-sm outline-none transition focus:border-primary focus:ring-1 focus:ring-primary" placeholder="Short reason or event">
                    </label>
                    <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground hover:bg-primary/90">Save override</button>
                </form>
                <form x-show="selectedDeleteUrl" x-bind:action="selectedDeleteUrl" method="POST" class="mt-2" onsubmit="return confirm('Remove this calendar override?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="w-full rounded-md border border-border px-4 py-2.5 text-sm font-semibold text-foreground hover:bg-muted">Remove override</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
