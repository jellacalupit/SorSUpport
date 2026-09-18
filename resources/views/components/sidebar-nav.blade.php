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
                ['route' => 'admin.tickets.my', 'label' => 'My Tickets', 'icon' => 'briefcase'],
                ['route' => 'admin.complaints.index', 'label' => 'All Tickets', 'icon' => 'files-2'],
            ],
        ],
        [
            'label' => 'Administration',
            'items' => [
                ['route' => 'admin.accounts.index', 'label' => 'Manage Accounts', 'icon' => 'users'],
                ['route' => 'admin.settings', 'label' => 'System Settings', 'icon' => 'settings'],
            ],
        ],
        [
            'label' => 'Insight',
            'items' => [
                ['route' => 'admin.analytics.index', 'label' => 'Analytics', 'icon' => 'bar-chart-3'],
                ['route' => 'admin.reports', 'label' => 'Reports', 'icon' => 'file-text'],
                ['route' => 'admin.audit', 'label' => 'Audit Trail', 'icon' => 'history'],
            ],
        ],
    ];
@endphp

<nav
    data-admin-navigation
    class="relative flex flex-col gap-3"
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
</nav>