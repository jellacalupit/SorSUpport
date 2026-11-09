<x-app-layout :role="'admin'" title="Working Calendar">
    <div x-data="{ selectedDate: null, selectedType: 'non_working', selectedDescription: '', selectedDeleteUrl: '' }" class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
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
                            \App\Models\CalendarOverride::TYPE_NON_WORKING => 'bg-rose-100 text-rose-950',
                            \App\Models\CalendarOverride::TYPE_WORKING => 'bg-emerald-100 text-emerald-950',
                            default => $day['is_weekend'] ? 'bg-slate-50 text-slate-500' : 'bg-white text-foreground',
                        };
                    @endphp
                    <button
                        type="button"
                        data-date="{{ $day['date'] }}"
                        data-type="{{ $day['type'] }}"
                        data-description="{{ $day['description'] }}"
                        data-delete-url="{{ $day['override_id'] ? route('admin.working-calendar.overrides.destroy', $day['override_id']) : '' }}"
                        x-on:click="selectedDate = $el.dataset.date; selectedType = $el.dataset.type || 'non_working'; selectedDescription = $el.dataset.description || ''; selectedDeleteUrl = $el.dataset.deleteUrl"
                        class="min-h-20 border-b border-r border-border p-1.5 text-left transition hover:bg-primary/5 sm:min-h-24 sm:p-2 {{ $dayStyle }} {{ $day['in_month'] ? '' : 'opacity-40' }} {{ $day['is_today'] ? 'ring-2 ring-inset ring-primary' : '' }}"
                        aria-label="{{ $day['date'] }}{{ $day['type'] ? ', ' . str_replace('_', ' ', $day['type']) . ' override' : ($day['is_weekend'] ? ', weekend' : '') }}"
                    >
                        <span class="flex items-center justify-between text-sm font-semibold">
                            {{ $day['day'] }}
                            @if ($day['is_weekend'])
                                <span class="text-[9px] font-medium uppercase">Weekend</span>
                            @endif
                        </span>
                        @if ($day['type'] === \App\Models\CalendarOverride::TYPE_NON_WORKING)
                            <span class="mt-2 block text-[10px] font-semibold sm:text-xs">Non-working</span>
                        @elseif ($day['type'] === \App\Models\CalendarOverride::TYPE_WORKING)
                            <span class="mt-2 block text-[10px] font-semibold sm:text-xs">Working</span>
                        @endif
                    </button>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-muted-foreground">Monday to Friday are working days by default. An override takes precedence over the weekday rule.</p>
        </section>

        <aside class="grid content-start gap-4">
            <section class="rounded-2xl border border-border bg-white p-4 shadow-sm sm:p-5">
                <h2 class="font-display text-base font-bold text-foreground">Mark a range</h2>
                <p class="mt-1 text-xs text-muted-foreground">Use for multi-day suspensions, breaks, or make-up working days. Maximum 60 calendar days, inclusive.</p>
                <form method="POST" action="{{ route('admin.working-calendar.ranges.store') }}" class="mt-4 grid gap-3">
                    @csrf
                    <label class="grid gap-1 text-xs font-medium text-foreground">Start date
                        <input type="date" name="start_date" required value="{{ old('start_date') }}" class="rounded-md border border-input px-3 py-2 text-sm">
                    </label>
                    <label class="grid gap-1 text-xs font-medium text-foreground">End date
                        <input type="date" name="end_date" required value="{{ old('end_date') }}" class="rounded-md border border-input px-3 py-2 text-sm">
                    </label>
                    <label class="grid gap-1 text-xs font-medium text-foreground">Type
                        <select name="type" required class="rounded-md border border-input px-3 py-2 text-sm">
                            <option value="non_working" @selected(old('type') === 'non_working')>Non-working</option>
                            <option value="working" @selected(old('type') === 'working')>Working</option>
                        </select>
                    </label>
                    <label class="grid gap-1 text-xs font-medium text-foreground">Description
                        <input type="text" name="description" maxlength="255" required value="{{ old('description') }}" placeholder="Short reason or event" class="rounded-md border border-input px-3 py-2 text-sm">
                    </label>
                    <button type="submit" class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground hover:bg-primary/90">Apply range</button>
                </form>
            </section>

            <section class="rounded-2xl border border-border bg-white p-4 shadow-sm sm:p-5">
                <h2 class="font-display text-base font-bold text-foreground">Overrides in {{ $month->format('F') }}</h2>
                @if ($overrides->isEmpty())
                    <p class="mt-3 text-sm text-muted-foreground">No overrides have been set for this month.</p>
                @else
                    <ul class="mt-3 divide-y divide-border">
                        @foreach ($overrides as $override)
                            <li class="flex items-start justify-between gap-3 py-3 first:pt-0">
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
                        <select name="type" x-model="selectedType" required class="rounded-md border border-input px-3 py-2 text-sm">
                            <option value="non_working">Non-working</option>
                            <option value="working">Working</option>
                        </select>
                    </label>
                    <label class="grid gap-1 text-xs font-medium text-foreground">Description
                        <input type="text" name="description" x-model="selectedDescription" maxlength="255" required class="rounded-md border border-input px-3 py-2 text-sm" placeholder="Short reason or event">
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
