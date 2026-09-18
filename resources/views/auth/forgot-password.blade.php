@extends('layouts.auth')

@section('content')
<div class="grid min-h-screen place-items-center bg-sidebar px-5 py-12">
    <div class="relative w-full max-w-md rounded-2xl bg-card p-6 text-center shadow-lg sm:p-8">
        <button
            type="button"
            onclick="window.location.href='{{ route('login') }}'"
            aria-label="Close"
            class="absolute top-4 right-4 text-muted-foreground"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
        </button>

        <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-primary text-primary-foreground">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-12 w-12">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9a2.25 2.25 0 1 1 3.75 1.67c-.84.7-1.5 1.08-1.5 2.33"/>
                <path stroke-linecap="round" d="M12 16.5h.01"/>
            </svg>
        </span>

    <x-auth-session-status class="mb-6" :status="session('status')" />

    <div class="mt-4 mb-4 text-center">
        <h1 class="text-2xl font-bold text-foreground">Forgot password?</h1>
            <p class="mt-2 text-sm text-muted-foreground">Enter your student or staff ID and we will send a password reset link to your registered email.</p>
    </div>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-6">
        @csrf

        <div class="grid gap-1.5">
            <input
                    id="username"
                class="flex h-10 w-full rounded-xl border {{ $errors->has('username') ? 'border-destructive focus-visible:ring-destructive' : 'border-input focus-visible:ring-ring' }} bg-muted px-3 text-xs text-foreground shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 sm:text-sm"
                    type="text"
                    name="username"
                aria-label="Student or Staff ID"
                    value="{{ old('username') }}"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    data-numeric-only
                    data-error-input
                autofocus
                    autocomplete="username"
                    oninput="this.value = this.value.replace(/\D/g, '')"
                    placeholder="Enter your student or staff ID">
                <x-input-error data-input-error class="text-left text-sm" :messages="$errors->get('username')" />
        </div>

            <button type="submit" class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 sm:text-base">
                Send Password Reset Link
        </button>

        <p class="text-center text-xs text-muted-foreground">
            <a href="{{ route('login') }}" class="font-semibold text-primary underline">Back to login</a>
        </p>
    </form>

    <script>
        document.querySelectorAll('[data-numeric-only]').forEach(function (numericInput) {
            numericInput.addEventListener('beforeinput', function (event) {
                if (event.data && /\D/.test(event.data)) {
                    event.preventDefault();
                }
            });

            numericInput.addEventListener('input', function () {
                numericInput.value = numericInput.value.replace(/\D/g, '');
            });

            numericInput.addEventListener('input', function () {
                const error = numericInput.closest('.grid')?.querySelector('[data-input-error]');

                if (error) {
                    error.classList.add('hidden');
                }

                numericInput.classList.remove('border-destructive', 'focus-visible:ring-destructive');
                numericInput.classList.add('border-input', 'focus-visible:ring-ring');
            });
        });
    </script>
    </div>
</div>
@endsection
