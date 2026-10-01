@extends('layouts.auth')

@section('content')
@php($isReset = isset($request) && filled($request->route('token')))
<div class="flex min-h-screen flex-col bg-muted">
    <header class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 bg-primary px-4 py-4 text-primary-foreground">
        <span class="w-5"></span>
        <h1 class="text-center font-display text-sm font-bold sm:text-base">Set Up Password</h1>
        <span class="w-5"></span>
    </header>

    <main class="mx-auto w-full max-w-3xl flex-1 px-6 py-8 lg:px-10">
        <ol class="grid grid-cols-[auto_minmax(0,1fr)_auto_minmax(0,1fr)_auto] items-center gap-2">
            <li class="grid justify-items-center gap-1.5">
                <span class="grid h-9 w-9 place-items-center rounded-full bg-primary text-primary-foreground">✓</span>
                <span class="text-[10px] font-semibold text-primary sm:text-[11px]">Verify</span>
            </li>
            <span class="h-0.5 bg-primary"></span>
            <li class="grid justify-items-center gap-1.5">
                <span class="grid h-9 w-9 place-items-center rounded-full bg-primary text-sm font-bold text-primary-foreground">2</span>
                <span class="text-[10px] font-semibold text-primary sm:text-[11px]">Set Password</span>
            </li>
            <span class="h-0.5 bg-border"></span>
            <li class="grid justify-items-center gap-1.5">
                <span class="grid h-9 w-9 place-items-center rounded-full bg-muted text-sm font-bold text-muted-foreground ring-1 ring-border">3</span>
                <span class="text-[10px] text-muted-foreground sm:text-[11px]">Login</span>
            </li>
        </ol>

        <h2 class="mt-8 font-display text-xl font-bold sm:text-2xl">Create Your Password</h2>
        <p class="mt-2 text-xs text-muted-foreground sm:text-sm">Set a secure password to access your SorSUpport account.</p>

        <form id="set-password-form" method="POST" action="{{ $isReset ? route('password.store') : route('password.force.update') }}" class="mt-6 grid gap-5">
            @csrf
            @if ($isReset)
                <input type="hidden" name="token" value="{{ $request->route('token') }}">
                <input type="hidden" name="email" value="{{ old('email', $request->email) }}">
            @endif
            <div class="grid gap-5 rounded-2xl bg-card p-6 shadow-sm sm:p-8 lg:p-10">
                <div class="grid gap-2">
                    <label for="new-password" class="text-xs font-semibold sm:text-sm">New Password</label>
                    <div class="relative">
                        <input id="new-password" name="password" type="password" required minlength="8" placeholder="Enter new password" class="h-11 w-full rounded-xl border border-input bg-muted px-3 pr-11 text-xs text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring sm:text-sm" />
                        <button type="button" data-toggle-password="new-password" aria-label="Show password" class="absolute inset-y-0 right-3 grid place-items-center text-muted-foreground">
                            <svg data-eye-icon class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0Z"/><circle cx="12" cy="12" r="3"/></svg><svg data-eye-off-icon class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0Z"/><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" stroke-linejoin="round" d="m4 4 16 16"/></svg>
                        </button>
                    </div>
                    <p id="new-password-empty-error" class="hidden text-xs font-medium text-destructive">This field is required.</p>
                    @error('password') <p class="text-xs text-destructive">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-2">
                    <label for="confirm-password" class="text-xs font-semibold sm:text-sm">Confirm Password</label>
                    <div class="relative">
                        <input id="confirm-password" name="password_confirmation" type="password" required minlength="8" disabled placeholder="Re-enter new password" class="h-11 w-full rounded-xl border border-input bg-muted px-3 pr-11 text-xs text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-60 sm:text-sm" />
                        <button type="button" data-toggle-password="confirm-password" aria-label="Show password" class="absolute inset-y-0 right-3 grid place-items-center text-muted-foreground">
                            <svg data-eye-icon class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0Z"/><circle cx="12" cy="12" r="3"/></svg><svg data-eye-off-icon class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0Z"/><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" stroke-linejoin="round" d="m4 4 16 16"/></svg>
                        </button>
                    </div>
                    <p id="confirm-password-empty-error" class="hidden text-xs font-medium text-destructive">This field is required.</p>
                    <p id="password-mismatch-error" class="hidden text-xs font-medium text-destructive">New passwords don't match.</p>
                </div>
                <ul id="password-rules" class="grid gap-1.5 text-xs text-muted-foreground sm:text-sm">
                    <li data-rule="length" class="flex items-center gap-2"><span class="hidden shrink-0 text-base font-bold leading-none text-success">✓</span>At least 8 characters</li>
                    <li data-rule="uppercase" class="flex items-center gap-2"><span class="hidden shrink-0 text-base font-bold leading-none text-success">✓</span>One uppercase letter</li>
                    <li data-rule="number" class="flex items-center gap-2"><span class="hidden shrink-0 text-base font-bold leading-none text-success">✓</span>One number or special character</li>
                </ul>
            </div>

            <button type="submit" class="h-12 w-full rounded-full bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 sm:text-base">Save Password</button>
        </form>
    </main>
</div>

<script>
    document.querySelectorAll('[data-toggle-password]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.togglePassword);
            if (!input) return;
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            button.querySelector('[data-eye-icon]')?.classList.toggle('hidden', !isHidden);
            button.querySelector('[data-eye-off-icon]')?.classList.toggle('hidden', isHidden);
        });
    });

    const form = document.getElementById('set-password-form');
    const password = document.getElementById('new-password');
    const confirmation = document.getElementById('confirm-password');
    const checksFor = (value) => ({
        length: value.length >= 8,
        uppercase: /[A-Z]/.test(value),
        number: /[\d\W_]/.test(value),
    });
    const renderRules = (checks) => Object.entries(checks).forEach(([name, passed]) => {
        const rule = document.querySelector(`[data-rule="${name}"]`);
        rule?.classList.toggle('text-primary', passed);
        rule?.classList.toggle('text-muted-foreground', !passed);
        rule?.querySelector('span')?.classList.toggle('hidden', !passed);
    });
    const setMismatch = (value) => {
        confirmation.classList.toggle('border-destructive', value);
        confirmation.classList.toggle('!border-destructive', value);
        document.getElementById('password-mismatch-error')?.classList.toggle('hidden', !value);
    };

    password?.addEventListener('input', () => {
        const checks = checksFor(password.value);
        renderRules(checks);
        confirmation.disabled = password.value.length < 8;
        setMismatch(Boolean(confirmation.value && password.value !== confirmation.value));
    });
    confirmation?.addEventListener('input', () => setMismatch(Boolean(password.value !== confirmation.value)));
    form?.addEventListener('submit', (event) => {
        const invalid = !password.value || !confirmation.value || password.value !== confirmation.value;
        if (invalid) {
            event.preventDefault();
            confirmation.disabled = false;
            setMismatch(Boolean(confirmation.value && password.value !== confirmation.value));
        }
    });
</script>
@endsection
