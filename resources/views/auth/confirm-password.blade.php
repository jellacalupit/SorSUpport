<x-guest-layout>
    <!-- Page Title -->
    <div class="text-center mb-8">
        <h1 class="text-2xl font-semibold text-foreground mb-2">{{ __('Confirm Password') }}</h1>
        <p class="text-sm text-muted-foreground">{{ __('This is a secure area. Please confirm your password to continue.') }}</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-6">
        @csrf

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1.5 w-full" type="password" name="password" required autocomplete="current-password" placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="flex justify-end pt-2">
            <x-primary-button>
                {{ __('Confirm') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
