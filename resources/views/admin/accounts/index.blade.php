<x-app-layout>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Manage Accounts
        </h2>
    </x-slot>

    @if(session('success'))

        <div class="mb-6 rounded-lg bg-green-100 border border-green-300 text-green-800 px-4 py-3">

            {{ session('success') }}

        </div>

    @endif

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow rounded-lg p-6">

                <div class="flex justify-between items-center mb-6">

                    <h2 class="text-2xl font-bold">
                        User Accounts
                    </h2>

                    <a href="{{ route('admin.accounts.create') }}"
                       class="bg-blue-600 hover:bg-blue-700 text-black px-4 py-2 rounded">

                        + Create Account

                    </a>

                    <a href="{{ route('admin.accounts.upload') }}"
                    class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">

                        Bulk Upload

                    </a>

                </div>

                <table class="w-full border">

                    <thead class="bg-gray-100">

                        <tr>

                            <th class="border p-3">ID</th>
                            <th class="border p-3">Name</th>
                            <th class="border p-3">Email</th>
                            <th class="border p-3">Role</th>
                            <th class="border p-3">Status</th>
                            <th class="border p-3">Actions</th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse($users as $user)

                            <tr>

                                <td class="border p-3">
                                    {{ $user->id }}
                                </td>

                                <td class="border p-3">
                                    {{ $user->name }}
                                </td>

                                <td class="border p-3">
                                    {{ $user->email }}
                                </td>

                                <td class="border p-3">
                                    {{ ucfirst(str_replace('_', ' ', $user->role)) }}
                                </td>

                                <td class="border p-3">

                                    @if($user->is_active)
                                        <span class="text-green-600 font-semibold">
                                            Active
                                        </span>
                                    @else
                                        <span class="text-red-600 font-semibold">
                                            Inactive
                                        </span>
                                    @endif

                                </td>

                                <td class="border p-3">

                                    <a href="{{ route('admin.accounts.edit', $user) }}"
                                       class="text-blue-600">

                                        Edit

                                    </a>

                                    <form method="POST"
                                        action="{{ route('admin.accounts.deactivate',$user->id) }}"
                                        class="inline">

                                        @csrf
                                        @method('PATCH')

                                        <button
                                            type="submit"
                                            class="bg-red-600 text-white px-3 py-2 rounded"
                                            onclick="return confirm('Deactivate this account?')">

                                            Deactivate

                                        </button>

                                    </form>

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="6" class="text-center p-5">

                                    No accounts found.

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>
    </div>

</x-app-layout>