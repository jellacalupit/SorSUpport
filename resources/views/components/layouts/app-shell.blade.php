@props(['role' => 'student', 'title' => '', 'description' => ''])

@php
    $isAdmin = $role === 'admin' || (Auth::check() && Auth::user()->role === 'sds_admin');
    $isStudent = $role === 'student' || (Auth::check() && Auth::user()->role === 'student');
    $isRecipient = $role === 'recipient' || (Auth::check() && Auth::user()->role === 'recipient');
    $isProfile = $title === 'My Profile';
    $adminNavigationLocked = $isAdmin && Auth::user()?->must_change_password;

    $sessionProfileNameParts = $isAdmin && is_array(session('profile_name_parts')) ? session('profile_name_parts') : [];
    $userNameParts = array_values(array_filter(preg_split('/\s+/', trim((string) (Auth::user()?->name ?? ''))) ?: [], static fn ($part) => $part !== ''));
    $profileFirstName = trim((string) ($sessionProfileNameParts['first_name'] ?? ''));
    $profileMiddleName = trim((string) ($sessionProfileNameParts['middle_name'] ?? ''));
    $profileLastName = trim((string) ($sessionProfileNameParts['last_name'] ?? ''));

    if (! $isAdmin) {
        $profileFirstName = trim((string) (Auth::user()?->first_name ?? ($userNameParts[0] ?? '')));
        $profileMiddleName = trim((string) (Auth::user()?->middle_name ?? ''));
        $profileLastName = trim((string) (Auth::user()?->last_name ?? ''));
    }

    if ($isAdmin && $profileFirstName === '' && blank(Auth::user()?->first_name) && blank(Auth::user()?->name)) {
        $profileFirstName = 'Administrator';
    }

    if ($isAdmin && $profileFirstName === '' && $userNameParts !== []) {
        if (count($userNameParts) >= 3) {
            $profileFirstName = implode(' ', array_slice($userNameParts, 0, -2));
        } elseif (count($userNameParts) === 2) {
            $profileFirstName = $userNameParts[0];
        } else {
            $profileFirstName = $userNameParts[0];
        }
    }

    if ($isAdmin && $profileMiddleName === '' && count($userNameParts) >= 3) {
        $profileMiddleName = $userNameParts[count($userNameParts) - 2];
    }

    if ($isAdmin && $profileLastName === '' && $userNameParts !== []) {
        $profileLastName = $userNameParts[count($userNameParts) - 1];
    }

    $adminFirstName = $profileFirstName !== ''
        ? $profileFirstName
        : (count($userNameParts) >= 3
            ? implode(' ', array_slice($userNameParts, 0, -2))
            : ($userNameParts[0] ?? 'Administrator'));

    $adminMiddleInitial = $profileMiddleName !== ''
        ? strtoupper(substr($profileMiddleName, 0, 1)) . '.'
        : ($isAdmin && count($userNameParts) >= 3
            ? strtoupper(substr($userNameParts[count($userNameParts) - 2], 0, 1)) . '.'
            : '');

    $adminLastName = $profileLastName !== ''
        ? $profileLastName
        : ($userNameParts[count($userNameParts) - 1] ?? '');

    $adminDisplayIsPlaceholder = $isAdmin
        && blank($profileFirstName)
        && blank($profileMiddleName)
        && blank($profileLastName)
        && blank(Auth::user()?->name ?? '');

    $headerDisplayName = $adminDisplayIsPlaceholder
        ? '—'
        : trim(
            implode(
                ' ',
                array_filter(
                    [$adminFirstName, $adminMiddleInitial, $adminLastName],
                    static fn ($part) => $part !== null && $part !== ''
                )
            )
        );

    $adminUserNameForAvatar = trim((string) (Auth::user()?->name ?? ''));
    $adminAvatarInitials = Auth::user()->must_change_password
        ? 'A'
        : (Auth::user()?->name_initials ?: 'A');
    $adminInSetupMode = Auth::check() && Auth::user()->role === 'sds_admin' && Auth::user()->must_change_password;
    $adminIdentityDepartment = $adminInSetupMode ? '—' : (blank(Auth::user()->recipient?->department) ? '—' : Auth::user()->recipient?->department);
    $adminIdentityDesignation = $adminInSetupMode ? '—' : (blank(Auth::user()->recipient?->designation) ? '—' : Auth::user()->recipient?->designation);
    $adminIdentityId = $adminInSetupMode ? '—' : (Auth::user()->username ?? Auth::user()->id);
    $adminHeaderGreeting = Auth::user()->must_change_password
        ? 'Welcome, Admin!'
        : ((blank(Auth::user()?->name) && blank(Auth::user()?->first_name)) ? 'Welcome back, Administrator!' : 'Welcome back, ' . $adminFirstName . '!');
    $sidebarAccountType = $isAdmin ? 'Administrator' : ($isStudent ? 'Student' : 'Recipient');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" href="{{ asset('branding/sorsu logo.png') }}" type="image/png">

        <title>SorSUpport</title>

        <style>
            [x-cloak] {
                display: none !important;
            }
        </style>

        @if ($isAdmin)
            <script>
                (() => {
                    const navigation = performance.getEntriesByType('navigation')[0];

                    if (navigation?.type === 'reload' && window.location.search) {
                        window.location.replace(window.location.pathname);
                    }
                })();
            </script>
        @endif

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link
            href="https://fonts.googleapis.com/css2?family=Bitter:wght@600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap"
            rel="stylesheet"
        />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="overflow-hidden font-sans antialiased bg-background text-foreground">

        <div>

            @if($isAdmin)

                <!-- =========================================================
                     ADMIN LAYOUT
                     ========================================================= -->

                <div
                    x-data="{ sidebarOpen: window.innerWidth >= 1024 }"
                    x-init="
                        sidebarOpen =
                            localStorage.getItem('adminSidebarOpen') !== null
                                ? localStorage.getItem('adminSidebarOpen') === 'true'
                                : window.innerWidth >= 1024;

                        $watch(
                            'sidebarOpen',
                            value => localStorage.setItem(
                                'adminSidebarOpen',
                                JSON.stringify(value)
                            )
                        )
                    "
                    data-admin-shell
                    class="app-viewport flex overflow-hidden"
                >

                    <!-- Mobile Overlay -->
                    <div
                        x-show="sidebarOpen"
                        x-transition.opacity
                        @click="sidebarOpen = false"
                        class="fixed inset-0 z-30 bg-black/40 lg:hidden"
                        aria-hidden="true"
                    ></div>

                    <!-- =====================================================
                         SIDEBAR
                         ===================================================== -->

                    <aside
                        data-admin-sidebar-nav
                        :class="
                            sidebarOpen
                                ? 'translate-x-0 lg:w-60'
                                : '-translate-x-full lg:translate-x-0 lg:w-16'
                        "
                        class="fixed left-0 top-0 z-40 flex h-screen w-72 -translate-x-full flex-col overflow-hidden border-r border-sidebar-border bg-sidebar p-3 text-sidebar-foreground transition-[transform,width] duration-200"
                    >

                        <!-- Brand -->
                        <div class="mb-4 flex items-start justify-between gap-2">

                            <a
                                href="{{ route('admin.dashboard') }}"
                                class="block min-w-0"
                                x-show="sidebarOpen"
                            >
                                <x-sidebar-brand />
                            </a>

                            <a
                                href="{{ route('admin.dashboard') }}"
                                class="mx-auto block"
                                x-show="!sidebarOpen"
                            >
                                <x-sidebar-brand compact />
                            </a>

                            <button
                                type="button"
                                x-show="sidebarOpen"
                                @click="sidebarOpen = false"
                                class="grid h-8 w-8 shrink-0 place-items-center rounded-md text-sidebar-foreground/75 transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                                aria-label="Hide navigation"
                            >
                                <x-icons.menu class="h-5 w-5" />
                            </button>

                        </div>

                            <!-- Navigation -->

                        <div
                            class="flex-1 min-w-0 overflow-y-auto overflow-x-hidden"
                        >
                            <x-sidebar-nav :locked="$adminNavigationLocked" />
                        </div>

                        <!-- =================================================
                             USER MENU
                             ================================================= -->

                        <div class="border-t border-sidebar-border pt-3">

                            <!-- Expanded User -->
                            <div
                                class="mb-2 flex items-center gap-2 px-2"
                                x-show="sidebarOpen"
                            >

                                <span class="grid h-8 w-8 shrink-0 place-items-center overflow-hidden rounded-full bg-sidebar-primary text-xs font-bold text-sidebar-primary-foreground">

                                    @if (Auth::user()->avatar_path)

                                        <img
                                            src="{{ asset('storage/' . Auth::user()->avatar_path) }}"
                                            alt="{{ Auth::user()->name }}"
                                            class="h-full w-full object-cover"
                                        >

                                    @else

                                        {{ $adminAvatarInitials }}

                                    @endif

                                </span>

                                <span class="min-w-0">

                                    <span class="block truncate text-xs font-semibold">
                                        {{ $headerDisplayName }}
                                    </span>

                                    <span class="mt-0.5 block truncate text-[10px] font-medium uppercase tracking-wide text-sidebar-foreground/70">
                                        {{ $sidebarAccountType }}
                                    </span>

                                </span>

                            </div>

                            <!-- Collapsed User -->
                            <div
                                x-show="!sidebarOpen"
                                class="mb-2 flex justify-center"
                            >

                                <span class="grid h-8 w-8 place-items-center overflow-hidden rounded-full bg-sidebar-primary text-xs font-bold text-sidebar-primary-foreground">

                                    @if (Auth::user()->avatar_path)

                                        <img
                                            src="{{ asset('storage/' . Auth::user()->avatar_path) }}"
                                            alt="{{ Auth::user()->name }}"
                                            class="h-full w-full object-cover"
                                        >

                                    @else

                                        {{ $adminAvatarInitials }}

                                    @endif

                                </span>

                            </div>

                            <!-- My Profile -->
                            <a
                                data-admin-profile-nav
                                href="{{ route('profile.edit') }}"
                                :class="sidebarOpen ? 'px-2' : 'justify-center px-0'"
                                class="group flex items-center gap-2 rounded-lg py-1.5 text-sm transition-colors {{ request()->routeIs('profile.edit') ? 'bg-sidebar-accent text-sidebar-accent-foreground' : 'text-sidebar-foreground/85 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground' }}"
                            >

                                <x-icons.user-round class="h-4 w-4 transition-transform group-hover:scale-110" />

                                <span x-show="sidebarOpen" class="transition-transform group-hover:scale-[1.02]">
                                    My Profile
                                </span>

                            </a>

                            <!-- Logout -->
                            <a
                                href="{{ route('logout.get') }}"
                                :class="sidebarOpen ? 'px-2' : 'justify-center px-0'"
                                class="group w-full flex items-center gap-2 rounded-lg py-1.5 text-left text-sm text-sidebar-foreground/85 transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                            >

                                <x-icons.log-out class="h-4 w-4 transition-transform group-hover:scale-110" />

                                <span x-show="sidebarOpen" class="transition-transform group-hover:scale-[1.02]">
                                    Logout
                                </span>

                            </a>

                        </div>

                    </aside>

                    <!-- =====================================================
                         MAIN AREA
                         ===================================================== -->

                    <div
                        :class="sidebarOpen ? 'lg:ml-60' : 'lg:ml-16'"
                        class="flex min-w-0 flex-1 flex-col transition-[margin] duration-200"
                    >

                        <!-- =================================================
                             TOP HEADER
                             ================================================= -->

                        <header class="sticky top-0 z-50 border-b border-border bg-card shadow-panel">

                            <div class="flex items-center justify-between px-4 py-3 sm:px-6">

                                <div class="flex min-w-0 items-center gap-3">

                                    <!-- Mobile Menu -->
                                    <button
                                        type="button"
                                        x-show="!sidebarOpen"
                                        @click="sidebarOpen = true"
                                        class="grid h-9 w-9 shrink-0 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-primary"
                                        aria-label="Show navigation"
                                        :aria-expanded="sidebarOpen"
                                    >
                                        <x-icons.menu class="h-6 w-6" />
                                    </button>

                                    <!-- Page Title -->
                                    <div class="min-w-0">

                                        <h1
                                            data-admin-page-title
                                            class="flex items-center gap-2 font-display text-lg font-bold text-primary sm:text-xl"
                                        >
                                            {{ $title ?: 'Dashboard' }}
                                        </h1>

                                        @if($description)

                                            <p class="mt-0.5 text-sm text-muted-foreground">
                                                {{ $description }}
                                            </p>

                                        @endif

                                    </div>

                                </div>

                                <!-- Header User Menu -->
                                <div class="flex shrink-0 items-center gap-3">

                                    <details class="relative" data-account-menu>

                                        <summary
                                            class="flex cursor-pointer list-none items-center justify-end gap-2.5 rounded-full outline-none [&::-webkit-details-marker]:hidden"
                                        >

                                            <span class="hidden sm:flex sm:flex-col sm:items-end">

                                                <span class="block max-w-56 truncate text-sm font-semibold text-primary">
                                                    {{ $adminHeaderGreeting }}
                                                </span>

                                                @unless (Auth::user()->must_change_password)
                                                    <span class="block whitespace-nowrap text-right text-[11px] leading-tight text-muted-foreground">
                                                        ID {{ $adminIdentityId }} · {{ $adminIdentityDepartment }} · {{ $adminIdentityDesignation }}
                                                    </span>
                                                @endunless

                                            </span>

                                            <span class="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-full bg-primary text-xs font-bold text-primary-foreground ring-2 ring-primary/10">

                                                @if (Auth::user()->avatar_path)

                                                    <img
                                                        src="{{ asset('storage/' . Auth::user()->avatar_path) }}"
                                                        alt="{{ Auth::user()->name }}"
                                                        class="h-full w-full object-cover"
                                                    >

                                                @else

                                                    {{ $adminAvatarInitials }}

                                                @endif

                                            </span>

                                        </summary>

                                        <div class="absolute right-0 top-[calc(100%+0.5rem)] z-50 w-48 origin-top-right overflow-hidden rounded-md border border-border bg-card p-1 text-card-foreground shadow-lg">

                                            <a
                                                href="{{ route('profile.edit') }}"
                                                class="flex items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none transition-colors hover:bg-accent hover:text-accent-foreground"
                                            >
                                                <x-icons.user-round class="h-4 w-4" />
                                                My Profile
                                            </a>

                                            <div class="my-1 border-t border-border"></div>

                                            <a
                                                href="{{ route('logout.get') }}"
                                                class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm text-destructive outline-none transition-colors hover:bg-destructive/10"
                                            >
                                                <x-icons.log-out class="h-4 w-4" />
                                                Logout
                                            </a>

                                        </div>

                                    </details>

                                </div>

                            </div>

                        </header>

                        <!-- =================================================
                             MAIN CONTENT
                             ================================================= -->

                        <main
                            data-admin-main
                            class="min-w-0 flex-1 overflow-y-auto overflow-x-visible"
                        >

                            <div class="admin-content w-full min-w-0 max-w-full break-words px-3 pb-2 pt-3 sm:px-4 sm:pb-2 sm:pt-4 lg:px-5 lg:pb-2 lg:pt-5">
                                {{ $slot }}
                            </div>

                        </main>

                    </div>

                </div>

                <!-- =========================================================
                     ADMIN NAVIGATION SCRIPT
                     ========================================================= -->

                <script>
                    (() => {

                        const shell =
                            document.querySelector('[data-admin-shell]');

                        if (
                            !shell ||
                            shell.dataset.navigationReady
                        ) {
                            return;
                        }

                        shell.dataset.navigationReady = 'true';

                        let navigationRequest = 0;

                        const updateActiveNavigation = (url) => {
                            const navigation = shell.querySelector('[data-admin-navigation]');
                            if (!navigation) return;

                            const currentPath = url.pathname.replace(/\/$/, '');
                            shell.querySelectorAll('[data-admin-nav], [data-admin-profile-nav]').forEach((link) => {
                                const linkPath = new URL(link.href).pathname.replace(/\/$/, '');
                                const isActive = currentPath === linkPath || currentPath.startsWith(`${linkPath}/`);
                                link.classList.toggle('bg-sidebar-accent', isActive);
                                link.classList.toggle('text-sidebar-accent-foreground', isActive);
                                link.classList.toggle('text-sidebar-foreground/85', !isActive);
                            });
                        };

                        updateActiveNavigation(new URL(window.location.href));

                        /*
                         * -----------------------------------------------------
                         * EXECUTE SCRIPTS FROM AJAX CONTENT
                         * -----------------------------------------------------
                         */

                        const executeScripts = async (main) => {

                            for (
                                const script
                                of main.querySelectorAll('script')
                            ) {

                                const replacement =
                                    document.createElement('script');

                                Array.from(
                                    script.attributes
                                ).forEach((attribute) => {

                                    replacement.setAttribute(
                                        attribute.name,
                                        attribute.value
                                    );

                                });

                                if (script.src) {

                                    await new Promise((resolve) => {

                                        replacement.onload = resolve;
                                        replacement.onerror = resolve;

                                        document.body.appendChild(
                                            replacement
                                        );

                                    });

                                } else {

                                    replacement.textContent =
                                        script.textContent;

                                    document.body.appendChild(
                                        replacement
                                    );

                                }

                                replacement.remove();

                            }

                        };

                        /*
                         * -----------------------------------------------------
                         * AJAX NAVIGATION
                         * -----------------------------------------------------
                         */

                        const navigate = async (
                            url,
                            addHistory = true
                        ) => {

                            const currentNavigationRequest =
                                ++navigationRequest;

                            const response =
                                await fetch(url, {
                                    headers: {
                                        'X-Requested-With':
                                            'XMLHttpRequest',
                                    },
                                });

                            if (!response.ok) {

                                throw new Error(
                                    `Navigation failed: ${response.status}`
                                );

                            }

                            if (
                                currentNavigationRequest !==
                                navigationRequest
                            ) {
                                return;
                            }

                            const documentText =
                                await response.text();

                            const nextDocument =
                                new DOMParser()
                                    .parseFromString(
                                        documentText,
                                        'text/html'
                                    );

                            const nextMain =
                                nextDocument.querySelector(
                                    '[data-admin-main]'
                                );

                            const currentMain =
                                shell.querySelector(
                                    '[data-admin-main]'
                                );

                            if (
                                !nextMain ||
                                !currentMain
                            ) {

                                window.location.href =
                                    url;

                                return;

                            }

                            /*
                             * Replace only the main content.
                             *
                             * Sidebar remains mounted so the
                             * indicator can physically slide.
                             */

                            currentMain.replaceWith(
                                nextMain
                            );

                            /*
                             * Update page title.
                             */

                            const nextTitle =
                                nextDocument.querySelector(
                                    '[data-admin-page-title]'
                                );

                            const currentTitle =
                                shell.querySelector(
                                    '[data-admin-page-title]'
                                );

                            if (
                                nextTitle &&
                                currentTitle
                            ) {

                                currentTitle.textContent =
                                    nextTitle.textContent;

                            }

                            /*
                             * Update browser history.
                             */

                            if (addHistory) {

                                window.history.pushState(
                                    {},
                                    '',
                                    url
                                );

                            }

                            /*
                             * Reinitialize Alpine.
                             */

                            if (window.Alpine) {

                                window.Alpine.initTree(
                                    nextMain
                                );

                            }

                            /*
                             * Execute scripts contained
                             * in the new page.
                             */

                            await executeScripts(
                                nextMain
                            );

                            if (
                                currentNavigationRequest ===
                                navigationRequest
                            ) {

                                updateActiveNavigation(
                                    new URL(
                                        url,
                                        window.location.origin
                                    ),
                                    false
                                );

                            }

                        };

                        /*
                         * -----------------------------------------------------
                         * SIDEBAR CLICK
                         * -----------------------------------------------------
                         */

                        shell.addEventListener(
                            'click',
                            async (event) => {

                                const link = event.target.closest(
                                    '[data-admin-nav], [data-admin-profile-nav], [data-admin-page-nav]'
                                );

                                if (
                                    !link ||
                                    event.defaultPrevented ||
                                    event.button !== 0 ||
                                    event.metaKey ||
                                    event.ctrlKey ||
                                    event.shiftKey ||
                                    event.altKey
                                ) {
                                    return;
                                }

                                event.preventDefault();

                                try {

                                    await navigate(
                                        link.href
                                    );

                                } catch {

                                    window.location.href =
                                        link.href;

                                }

                            }
                        );

                        /*
                         * -----------------------------------------------------
                         * BROWSER BACK / FORWARD
                         * -----------------------------------------------------
                         */

                        window.addEventListener(
                            'popstate',
                            async () => {

                                try {

                                    await navigate(
                                        window.location.href,
                                        false
                                    );

                                } catch {

                                    window.location.reload();

                                }

                            }
                        );

                    })();
                </script>

            @else

                <!-- =========================================================
                     STUDENT / RECIPIENT LAYOUT
                     ========================================================= -->

                <div class="app-viewport flex min-w-0 flex-col overflow-hidden">

                    <!-- Header -->
                    @unless($isProfile)

                        <header class="sticky top-0 z-20 grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 bg-primary px-4 py-3 text-primary-foreground sm:px-6">

                            <div class="flex min-w-0 items-center gap-2.5">

                                <img
                                    src="{{ asset('branding/sorsu logo.png') }}"
                                    alt="SorSUpport logo"
                                    class="h-9 w-9 shrink-0 rounded-lg object-contain"
                                >

                                <span class="min-w-0">

                                    <span class="block font-display text-base font-bold leading-tight">
                                        SorSUpport
                                    </span>

                                    <span class="block text-[11px] leading-tight opacity-75">
                                        Sorsogon State University – Bulan Campus
                                    </span>

                                </span>

                            </div>

                            <div class="flex items-center gap-2.5">

                                @if($title)

                                    <h1 class="sr-only">
                                        {{ $title }}
                                    </h1>

                                @endif

                                <span class="hidden max-w-64 whitespace-nowrap text-sm font-semibold sm:block">
                                    {{ $isStudent || $isRecipient ? $headerDisplayName : ($isAdmin ? $headerDisplayName : Auth::user()->name) }}
                                </span>

                                <details
                                    class="relative shrink-0"
                                    data-account-menu
                                >

                                    <summary
                                        class="block h-9 w-9 shrink-0 cursor-pointer list-none overflow-hidden rounded-full bg-primary-foreground/15 text-sm font-bold ring-1 ring-primary-foreground/30 [&::-webkit-details-marker]:hidden"
                                        aria-label="Account menu"
                                    >

                                        @if (Auth::user()->avatar_path)

                                            <img
                                                src="{{ asset('storage/' . Auth::user()->avatar_path) }}"
                                                alt="{{ Auth::user()->name }}"
                                                class="block h-9 w-9 max-w-none object-cover"
                                            >

                                        @else

                                            <span class="grid h-9 w-9 place-items-center">
                                                {{ Auth::user()->must_change_password ? 'A' : (Auth::user()->name_initials ?: 'A') }}
                                            </span>

                                        @endif

                                    </summary>

                                    <div class="absolute right-0 top-[calc(100%+0.5rem)] z-50 w-44 origin-top-right overflow-hidden rounded-lg border border-border bg-card p-1 text-foreground shadow-lg">

                                        <a
                                            href="{{ $isRecipient ? route('recipient.profile') : route('student.profile') }}"
                                            class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                                        >
                                            <x-icons.user-round class="h-4 w-4" />
                                            My Profile
                                        </a>

                                        <div class="my-1 border-t border-border"></div>

                                        <a
                                            href="{{ $isStudent ? route('logout.get') : route('logout') }}"
                                            class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm font-medium text-destructive transition-colors hover:bg-destructive/10"
                                        >
                                            <x-icons.log-out class="h-4 w-4" />
                                            Logout
                                        </a>

                                    </div>

                                </details>

                            </div>

                        </header>

                    @endunless

                    <!-- Student/Recipient Scripts -->
                    <script>

                        window.addEventListener(
                            'pageshow',
                            (event) => {

                                const navType =
                                    performance
                                        .getEntriesByType('navigation')[0]
                                        ?.type;

                                if (
                                    event.persisted ||
                                    navType === 'back_forward'
                                ) {

                                    window.location.reload();

                                }

                            }
                        );

                        document
                            .querySelectorAll(
                                '.auto-hide-scrollbar'
                            )
                            .forEach((scrollArea) => {

                                let scrollTimeout;

                                scrollArea.addEventListener(
                                    'scroll',
                                    () => {

                                        scrollArea.classList.add(
                                            'is-scrolling'
                                        );

                                        clearTimeout(
                                            scrollTimeout
                                        );

                                        scrollTimeout =
                                            setTimeout(
                                                () => {

                                                    scrollArea.classList.remove(
                                                        'is-scrolling'
                                                    );

                                                },
                                                700
                                            );

                                    }
                                );

                            });

                        document.addEventListener(
                            'click',
                            (event) => {

                                document
                                    .querySelectorAll(
                                        '[data-account-menu][open]'
                                    )
                                    .forEach((menu) => {

                                        if (
                                            !menu.contains(
                                                event.target
                                            )
                                        ) {

                                            menu.removeAttribute(
                                                'open'
                                            );

                                        }

                                    });

                            }
                        );

                    </script>

                    <!-- Main Content -->
                    <main class="mx-auto mb-16 min-h-0 w-full min-w-0 max-w-6xl flex-1 overflow-y-auto px-4 py-5 sm:px-6 lg:mb-0 lg:px-8">

                        <div class="min-w-0">
                            {{ $slot }}
                        </div>

                    </main>

                    <!-- Tab Bar -->
                    <x-tab-bar-nav
                        :role="$isRecipient ? 'recipient' : 'student'"
                    />

                </div>

            @endif

        </div>

        <script>
            (() => {
                let timer;
                let searchRequest = 0;
                let abortController;

                const applyFilterDocument = (form, nextDocument, { preserveLiveInputs = false } = {}) => {
                    const results = document.querySelector('[data-ticket-results]');
                    const nextForm = nextDocument.querySelector('[data-ticket-filter-form]');
                    const nextResults = nextDocument.querySelector('[data-ticket-results]');

                    if (!nextResults) {
                        throw new Error('Ticket results not found');
                    }

                    if (form && nextForm && !preserveLiveInputs) {
                        const activeElement = document.activeElement;
                        const activeInputId = activeElement?.matches('[data-ticket-filter-form] input[name="search"]') ? activeElement.id : null;
                        const activeInputValue = activeInputId ? activeElement.value : null;
                        const selectionStart = activeInputId ? activeElement.selectionStart : null;
                        const selectionEnd = activeInputId ? activeElement.selectionEnd : null;

                        form.replaceWith(nextForm);
                        window.Alpine?.initTree(nextForm);

                        if (activeInputId) {
                            const nextInput = document.getElementById(activeInputId);
                            if (nextInput && activeInputValue !== null) nextInput.value = activeInputValue;
                            nextInput?.focus({ preventScroll: true });
                            if (selectionStart !== null && selectionEnd !== null) {
                                nextInput?.setSelectionRange(selectionStart, selectionEnd);
                            }
                        }
                    }

                    results?.replaceWith(nextResults);
                };

                const loadResults = async (form, url, { pushState = true, preserveLiveInputs = false } = {}) => {
                    const requestId = ++searchRequest;
                    abortController?.abort();
                    abortController = new AbortController();
                    const results = document.querySelector('[data-ticket-results]');
                    results?.classList.add('pointer-events-none', 'opacity-60');

                    try {
                        const response = await fetch(url, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            signal: abortController.signal,
                        });
                        if (!response.ok) throw new Error(`Ticket search request failed: ${response.status}`);
                        const nextDocument = new DOMParser().parseFromString(await response.text(), 'text/html');

                        if (requestId === searchRequest) {
                            applyFilterDocument(form, nextDocument, { preserveLiveInputs });
                        }

                        if (requestId === searchRequest && pushState) window.history.pushState({}, '', url);
                    } catch (error) {
                        if (error.name !== 'AbortError') window.location.assign(url);
                    } finally {
                        if (requestId === searchRequest) {
                            document.querySelector('[data-ticket-results]')?.classList.remove('pointer-events-none', 'opacity-60');
                        }
                    }
                };

                const mergeFormParams = (url, form) => {
                    if (!form) return url;

                    const formData = new FormData(form);
                    formData.forEach((value, key) => {
                        if (String(value).trim() !== '') {
                            url.searchParams.set(key, value);
                        } else {
                            url.searchParams.delete(key);
                        }
                    });
                    url.searchParams.delete('page');

                    return url;
                };

                const submitSearch = (form, preserveLiveInputs = true) => {
                    const url = mergeFormParams(new URL(window.location.href), form);
                    loadResults(form, url.toString(), { preserveLiveInputs });
                };

                document.addEventListener('input', (event) => {
                    const searchInput = event.target.closest('[data-ticket-filter-form] input[name="search"]');
                    if (!searchInput) return;

                    clearTimeout(timer);
                    timer = setTimeout(() => submitSearch(searchInput.form), 350);
                });

                document.addEventListener('change', (event) => {
                    const dateInput = event.target.closest('[data-ticket-filter-form] input[type="date"]');
                    if (!dateInput) return;

                    clearTimeout(timer);
                    submitSearch(dateInput.form);
                });

                document.addEventListener('submit', (event) => {
                    const form = event.target.closest('[data-ticket-filter-form]');
                    if (!form) return;

                    event.preventDefault();
                    submitSearch(form);
                });

                document.addEventListener('click', (event) => {
                    if (event.target.closest('[data-ticket-filter-link]')) {
                        return;
                    }

                    const resetLink = event.target.closest('[data-reset-filters]');
                    if (resetLink) {
                        const form = resetLink.closest('[data-ticket-filter-form]');
                        const results = document.querySelector('[data-ticket-results]');
                        const url = new URL(resetLink.href, window.location.href);
                        if (!form || !results || url.origin !== window.location.origin) return;

                        event.preventDefault();
                        loadResults(form, url.toString());
                        return;
                    }

                    const link = event.target.closest('[data-ticket-filter-form] a[href]:not([data-full-navigation]), [data-ticket-results] nav a[href]');
                    if (!link) return;
                    if (link.closest('[data-account-filter-form]')) return;

                    const form = link.closest('[data-ticket-filter-form]') || document.querySelector('[data-ticket-filter-form]');
                    const isFilterFormLink = Boolean(link.closest('[data-ticket-filter-form]'));
                    const url = isFilterFormLink
                        ? mergeFormParams(new URL(link.href, window.location.href), form)
                        : new URL(link.href, window.location.href);
                    if (url.origin !== window.location.origin) return;

                    event.preventDefault();
                    loadResults(form, url.toString(), { preserveLiveInputs: !isFilterFormLink });
                });

                window.addEventListener('popstate', () => {
                    const form = document.querySelector('[data-ticket-filter-form]');
                    if (form) loadResults(form, window.location.href, { pushState: false });
                });
            })();
        </script>

        @if (session('success'))
            <div
                x-data="{ visible: true }"
                x-show="visible"
                x-init="setTimeout(() => visible = false, 5000)"
                x-transition:enter="transform transition ease-out duration-300"
                x-transition:enter-start="translate-x-full opacity-0"
                x-transition:enter-end="translate-x-0 opacity-100"
                x-transition:leave="transform transition ease-in duration-300"
                x-transition:leave-start="translate-x-0 opacity-100"
                x-transition:leave-end="translate-x-full opacity-0"
                class="fixed right-5 bottom-5 z-[100] w-[min(22rem,calc(100vw-2.5rem))] rounded-xl border border-green-700 bg-green-700 p-4 text-white shadow-xl"
                role="status"
                aria-live="polite"
            >
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-white/20 text-white" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
                            <path d="m5 10 3 3 7-7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-white">{{ session('success') }}</p>
                        @if (session('upload_summary.errors'))
                            <ul class="mt-2 space-y-0.5 text-xs font-normal text-green-50">
                                @foreach (session('upload_summary.errors') as $error)
                                    <li>Row {{ $error['row'] }}: {{ implode(' ', $error['messages']) }}</li>
                                @endforeach
                            </ul>
                            <a href="{{ route('admin.accounts.upload.errors') }}" class="mt-1 inline-block text-xs font-semibold text-white underline">Download error report</a>
                        @endif
                    </div>
                    <button type="button" @click="visible = false" class="shrink-0 text-white/80 transition-colors hover:text-white" aria-label="Dismiss notification">&times;</button>
                </div>
            </div>
        @endif

    </body>
</html>