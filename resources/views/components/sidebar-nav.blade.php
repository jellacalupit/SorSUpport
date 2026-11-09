@props(['currentRouteName' => '', 'locked' => false])

@php
    $adminNav = [
        [
            'label' => 'Overview',
            'items' => [
                ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard-grid'],
            ],
        ],
        [
            'label' => 'Ticket Management',
            'items' => [
                ['route' => 'admin.tickets.review.index', 'label' => 'Ticket Review', 'icon' => 'inbox', 'badge' => $pendingReview ?? null],
                ['route' => 'admin.tickets.needs-attention', 'label' => 'Needs Attention', 'icon' => 'alert-triangle'],
                ['route' => 'admin.tickets.my', 'label' => 'My Tickets', 'icon' => 'briefcase'],
                ['route' => 'admin.complaints.index', 'label' => 'All Tickets', 'icon' => 'files-2'],
            ],
        ],
        [
            'label' => 'Administration',
            'items' => [
                ['route' => 'admin.accounts.index', 'label' => 'Manage Accounts', 'icon' => 'users'],
                ['route' => 'admin.settings', 'label' => 'System Settings', 'icon' => 'settings'],
                ['route' => 'admin.working-calendar', 'label' => 'Working Calendar', 'icon' => 'calendar-clock'],
            ],
        ],
        [
            'label' => 'Insight',
            'items' => [
                ['route' => 'admin.analytics.index', 'label' => 'Analytics', 'icon' => 'bar-chart-3'],
                ['route' => 'admin.audit', 'label' => 'Audit Trail', 'icon' => 'history'],
            ],
        ],
    ];
@endphp

<nav
    data-admin-navigation
    class="relative flex flex-col gap-3"
    x-data="{ tooltipVisible: false, tooltipLabel: '', tooltipTop: 0, tooltipLeft: 0 }"
>
    @foreach($adminNav as $section)
        <div class="relative z-10 border-b border-sidebar-border pb-3 last:border-b-0 last:pb-0">

            <p
                x-show="sidebarOpen"
                class="mb-1 px-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-sidebar-foreground/60"
            >
                {{ $section['label'] }}
            </p>

            <div class="flex flex-col">

                @foreach($section['items'] as $item)
                    @php
                        $isActive = ! $locked && (
                            Route::currentRouteName() === $item['route'] ||
                            (Route::currentRouteName() && str_starts_with(Route::currentRouteName(), str_replace('.index', '', $item['route'])))
                        );

                        $iconComponent = 'icons.' . $item['icon'];
                    @endphp

                    <a
                        data-admin-nav
                        href="{{ $locked ? '#' : route($item['route']) }}"
                        @if ($locked) aria-disabled="true" tabindex="-1" onclick="return false" @endif
                        :class="sidebarOpen ? 'px-2' : 'justify-center px-0'"
                        :aria-label="sidebarOpen ? null : @js($item['label'])"
                        @if(!$locked)
                            x-on:mouseenter="if (!sidebarOpen) { const rect = $el.getBoundingClientRect(); tooltipLabel = @js($item['label']); tooltipTop = rect.top + rect.height / 2; tooltipLeft = rect.right + 8; tooltipVisible = true }"
                            x-on:mouseleave="tooltipVisible = false"
                        @endif
                        class="group relative z-10 flex min-h-9 items-center gap-3 rounded-md text-sm font-medium {{ $locked ? 'pointer-events-none !bg-transparent text-sidebar-foreground/55' : 'transition-colors ' . ($isActive ? 'bg-sidebar-accent text-sidebar-accent-foreground' : 'text-sidebar-foreground/85') }}"
                    >

                        <x-dynamic-component
                            :component="$iconComponent"
                            x-bind:class="sidebarOpen ? 'h-4 w-4 shrink-0' : 'h-5 w-5 shrink-0'"
                            class="{{ $locked ? '' : 'transition-transform ' . ($isActive ? '' : 'group-hover:scale-110') }}"
                        />

                        <span
                            x-show="sidebarOpen"
                            class="min-w-0 flex-1 truncate {{ $locked ? '' : 'transition-transform ' . ($isActive ? '' : 'group-hover:scale-[1.02]') }}"
                        >
                            {{ $item['label'] }}
                        </span>

                        @if(isset($item['badge']) && $item['badge'] > 0)
                            <span
                                x-show="sidebarOpen"
                                class="grid h-5 min-w-5 place-items-center rounded-full bg-sidebar-foreground px-1 text-[10px] font-bold text-sidebar-primary-foreground"
                            >
                                {{ $item['badge'] }}
                            </span>
                        @endif

                    </a>
                @endforeach

            </div>
        </div>
    @endforeach

    <template x-teleport="body">
        <div
            x-show="!sidebarOpen && tooltipVisible"
            x-transition:enter="transition ease-out duration-75"
            x-transition:enter-start="translate-x-1 opacity-0"
            x-transition:enter-end="translate-x-0 opacity-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="translate-x-0 opacity-100"
            x-transition:leave-end="translate-x-1 opacity-0"
            :style="'top: ' + tooltipTop + 'px; left: ' + tooltipLeft + 'px'"
            class="pointer-events-none fixed z-[100] -translate-y-1/2 whitespace-nowrap rounded-md border border-primary/20 bg-white px-3 py-1.5 text-xs font-semibold text-primary shadow-lg"
            aria-hidden="true"
        >
            <span x-text="tooltipLabel"></span>
        </div>
    </template>
</nav>