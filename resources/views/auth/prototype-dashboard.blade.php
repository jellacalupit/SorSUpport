<div class="flex min-h-screen flex-col bg-muted">
    <header class="brand-gradient px-6 py-10 text-center text-primary-foreground">
        <img src="{{ asset('branding/sorsu logo.png') }}" alt="SorSUpport logo" class="mx-auto h-20 w-20 rounded-full object-contain">
        <p class="text-sm opacity-85">SorSUpport</p>
        <h1 class="mt-2 font-display text-2xl font-bold">{{ $role }} Dashboard</h1>
        <p class="mt-2 text-sm opacity-85">{{ $description }}</p>
    </header>

    <main class="mx-auto w-full max-w-2xl flex-1 px-6 py-10">
        <section class="surface p-6 sm:p-8">
            <p class="text-sm text-muted-foreground">Prototype interface</p>
            <h2 class="mt-2 font-display text-2xl font-bold">Welcome to the {{ $role }} view</h2>
            <p class="mt-2 text-sm text-muted-foreground">This UI-only preview is ready for customization.</p>
            <a href="{{ route('choose.account') }}" class="mt-6 inline-flex h-12 w-full items-center justify-center rounded-full bg-primary px-4 py-2 text-base font-medium text-primary-foreground transition-colors hover:bg-primary/90">
                Back to Account Selection
            </a>
        </section>
    </main>

    <footer class="px-6 py-8 text-center text-xs text-muted-foreground">
        © 2026 SorSU - Bulan Campus. All rights reserved.
    </footer>
</div>