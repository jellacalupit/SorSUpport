<x-guest-layout>

    <div class="mb-6">

        <h2 class="text-2xl font-bold text-gray-900">
            Change Your Password
        </h2>

        <p class="mt-2 text-sm text-gray-700">
            This is your first login.
            For security reasons, you must create a new password before continuing.
        </p>

    </div>

    <form method="POST" action="{{ route('password.force.update') }}">

        @csrf

        <div class="mt-4">

            <x-input-label
                for="password"
                :value="__('New Password')" />

            <x-text-input
                id="password"
                class="block mt-1 w-full"
                type="password"
                name="password"
                required
                autofocus />

            <x-input-error
                :messages="$errors->get('password')"
                class="mt-2" />

        </div>

        <div class="mt-4">

            <x-input-label
                for="password_confirmation"
                :value="__('Confirm Password')" />

            <x-text-input
                id="password_confirmation"
                class="block mt-1 w-full"
                type="password"
                name="password_confirmation"
                required />

        </div>

        <div class="flex justify-end mt-6">

            <x-primary-button>
                Save Password
            </x-primary-button>

        </div>

    </form>

</x-guest-layout>