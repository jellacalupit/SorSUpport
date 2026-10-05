@props(['role' => 'student'])

@php
    $studentTabs = [
        ['route' => 'student.dashboard', 'label' => 'Home', 'icon' => 'home'],
        ['route' => 'student.complaints.index', 'label' => 'My Tickets', 'icon' => 'ticket'],
        ['route' => 'student.notifications', 'label' => 'Notification', 'icon' => 'bell'],
        ['route' => 'student.profile', 'label' => 'Profile', 'icon' => 'user-round'],
    ];

    $recipientTabs = [
        ['route' => 'recipient.dashboard', 'label' => 'Home', 'icon' => 'home'],
        ['route' => 'recipient.tickets.index', 'label' => 'My Tickets', 'icon' => 'ticket'],
        ['route' => 'recipient.notifications', 'label' => 'Notification', 'icon' => 'bell'],
        ['route' => 'recipient.profile', 'label' => 'Profile', 'icon' => 'user-round'],
    ];

    $tabs = $role === 'recipient' ? $recipientTabs : $studentTabs;
    $fabRoute = $role === 'student' ? 'student.complaints.create' : null;
    $showFab = $fabRoute && Route::currentRouteName() !== $fabRoute;

    $unreadChangeCount = 0;
    $currentUser = Auth::user();
    $unreadService = app(\App\Services\TicketUnreadService::class);

    if ($currentUser) {
        if ($currentUser->isStudent() && $currentUser->student) {
            $unreadChangeCount = \App\Models\Complaint::query()
                ->where('student_id', $currentUser->student->id)
                ->with('ticket.auditLogs')
                ->get()
                ->sum(fn (\App\Models\Complaint $complaint) => $unreadService->unreadCountForTicket($currentUser, $complaint->ticket));
        } elseif ($currentUser->isRecipient()) {
            $unreadChangeCount = \App\Models\Ticket::query()
                ->where('assigned_to', $currentUser->id)
                ->with('auditLogs')
                ->get()
                ->sum(fn (\App\Models\Ticket $ticket) => $unreadService->unreadCountForTicket($currentUser, $ticket));
        }
    }
@endphp

{{-- Sits in the page column below the scrolling area (not floating over it), so content and its scrollbar always end above it. --}}
<nav class="relative z-30 w-full shrink-0 border-t border-border bg-card">
    <div class="relative mx-auto grid w-full items-end gap-1 px-3 pt-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] sm:gap-2 sm:px-6 md:gap-4 md:px-10 lg:gap-8 lg:px-16 {{ $showFab ? 'grid-cols-5 sm:grid-cols-4' : 'grid-cols-4' }}">
        @foreach($tabs as $tabIndex => $tab)
            @php
                $isActive = Route::currentRouteName() === $tab['route'];
                $iconComponent = 'icons.' . $tab['icon'];
                $badgedRoutes = [
                    'student.dashboard',
                    'student.complaints.index',
                    'student.notifications',
                    'recipient.dashboard',
                    'recipient.tickets.index',
                    'recipient.notifications',
                ];
                $showBadge = in_array($tab['route'], $badgedRoutes, true) && $unreadChangeCount > 0;
                $badgeLabel = $unreadChangeCount > 99 ? '99+' : $unreadChangeCount;
            @endphp
            <a
                href="{{ route($tab['route']) }}"
                class="relative flex flex-col items-center gap-1 rounded-lg py-1 text-[10px] font-medium {{ $isActive ? 'text-primary' : 'text-muted-foreground' }}"
            >
                <div class="relative">
                    <x-dynamic-component :component="$iconComponent" class="h-5 w-5 shrink-0" />
                    @if ($showBadge)
                        <span class="absolute -right-1.5 -top-1.5 grid min-h-4 min-w-4 place-items-center rounded-full bg-[#7d1f2a] px-1 text-[9px] font-bold leading-none text-white">{{ $badgeLabel }}</span>
                    @endif
                </div>
                <span class="max-w-full truncate">{{ $tab['label'] }}</span>
            </a>
            @if ($showFab && $tabIndex === 1)
                <span class="sm:hidden" aria-hidden="true"></span>
            @endif
        @endforeach

        @if($showFab)
            <a
                href="{{ route($fabRoute) }}"
                aria-label="Submit Complaint"
                class="absolute -top-6 left-1/2 grid h-14 w-14 -translate-x-1/2 place-items-center rounded-full bg-primary text-primary-foreground shadow-lg ring-4 ring-card transition-colors hover:bg-primary/90 sm:hidden"
            >
                <x-icons.plus class="h-6 w-6" />
            </a>
        @endif
    </div>
</nav>
