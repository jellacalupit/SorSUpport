<x-guest-layout max-width="max-w-lg" :show-footer="false" :rounded-header="true">
    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-6">
        @csrf

        <div class="grid gap-1.5">
            <label for="username" class="mb-1.5 block text-xs font-medium text-foreground sm:text-sm">Student or Staff ID</label>
            <input
                id="username"
                class="flex h-11 w-full rounded-xl border {{ $errors->has('username') ? 'border-destructive focus-visible:ring-destructive' : 'border-input focus-visible:ring-ring' }} bg-muted px-3 text-xs text-foreground shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 sm:text-sm"
                type="text"
                name="username"
                inputmode="numeric"
                pattern="[0-9]*"
                data-numeric-only
                data-error-input
                :value="old('username')"
                autofocus
                autocomplete="off"
                oninput="this.value = this.value.replace(/\D/g, '')"
                placeholder="Enter your student or staff ID">
            <x-input-error data-login-error class="text-left text-sm" :messages="$errors->get('username')" />
        </div>

        <div class="grid gap-1.5">
            <label for="password" class="mb-1.5 block text-xs font-medium text-foreground sm:text-sm">Password</label>

            <div class="relative mt-1.5">
                <input
                    id="password"
                    class="flex h-11 w-full rounded-xl border border-input bg-muted px-3 pr-11 text-xs text-foreground shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring sm:text-sm"
                    type="password"
                    name="password"
                    autocomplete="new-password"
                    placeholder="Enter your password">

                <button type="button" id="toggle-password" class="absolute inset-y-0 right-3 grid place-items-center text-muted-foreground" aria-label="Show password">
                    <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="hidden h-4 w-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0Z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                    <svg id="eye-off-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0Z" />
                        <circle cx="12" cy="12" r="3" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4 4 16 16" />
                    </svg>
                </button>
            </div>

            <x-input-error data-login-error :messages="$errors->get('password')" />
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const input = document.getElementById('password');
                const usernameInput = document.getElementById('username');
                const toggleButton = document.getElementById('toggle-password');
                const eyeIcon = document.getElementById('eye-icon');
                const eyeOffIcon = document.getElementById('eye-off-icon');

                if (!input || !usernameInput || !toggleButton || !eyeIcon || !eyeOffIcon) {
                    return;
                }

                document.querySelectorAll('[data-numeric-only]').forEach(function (numericInput) {
                    numericInput.addEventListener('beforeinput', function (event) {
                        if (event.data && /\D/.test(event.data)) {
                            event.preventDefault();
                        }
                    });

                    numericInput.addEventListener('input', function () {
                        numericInput.value = numericInput.value.replace(/\D/g, '');
                    });
                });

                [usernameInput, input].forEach(function (field) {
                    field.addEventListener('input', function () {
                        const error = field.closest('.grid')?.querySelector('[data-login-error]');

                        if (error) {
                            error.classList.add('hidden');
                        }

                        if (field.matches('[data-error-input]')) {
                            field.classList.remove('border-destructive', 'focus-visible:ring-destructive');
                            field.classList.add('border-input', 'focus-visible:ring-ring');
                        }
                    });
                });

                toggleButton.addEventListener('click', function () {
                    const isPassword = input.type === 'password';
                    input.type = isPassword ? 'text' : 'password';
                    toggleButton.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
                    eyeIcon.classList.toggle('hidden', !isPassword);
                    eyeOffIcon.classList.toggle('hidden', isPassword);
                });
            });
        </script>

        <button type="submit" class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 sm:text-base">
            Log In
        </button>

        <p class="text-center text-xs text-muted-foreground">
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="font-semibold text-primary underline">
                    Forgot password?
                </a>
            @endif
        </p>
    </form>
</x-guest-layout>
