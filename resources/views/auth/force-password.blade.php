<x-guest-layout>

    <!-- Page Title -->
    <div class="text-center mb-8">
        <h1 class="text-2xl font-semibold text-foreground mb-2">{{ __('Change Your Password') }}</h1>
        <p class="text-sm text-muted-foreground">
            {{ __('This is your first login. For security, please create a new password before continuing.') }}
        </p>
    </div>

    <form method="POST" action="{{ route('password.force.update') }}" class="space-y-6">

        @csrf

        <!-- New Password -->
        <div>

            <x-input-label
                for="password"
                :value="__('New Password')" />

            <x-text-input
                id="password"
                class="block mt-1.5 w-full"
                type="password"
                name="password"
                required
                autofocus
                placeholder="••••••••" />

            <x-input-error
                :messages="$errors->get('password')" />

        </div>

        <!-- Confirm Password -->
        <div>

            <x-input-label
                for="password_confirmation"
                :value="__('Confirm Password')" />

            <x-text-input
                id="password_confirmation"
                class="block mt-1.5 w-full"
                type="password"
                name="password_confirmation"
                required
                placeholder="••••••••" />

        </div>

        <div class="flex justify-end pt-2">

            <x-primary-button>
                {{ __('Save Password') }}
            </x-primary-button>

        </div>

    </form>

</x-guest-layout>