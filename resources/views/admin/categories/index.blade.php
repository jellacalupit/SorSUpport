<x-app-layout>

    <x-slot name="header">
        <div class="flex justify-between items-center">

            <h2 class="text-2xl font-bold text-gray-900">
                Complaint Categories
            </h2>

            <a
                href="{{ route('admin.categories.create') }}"
                class="bg-blue-600 hover:bg-blue-700 text-black px-5 py-2 rounded-lg font-semibold">

                Create Category

            </a>

        </div>
    </x-slot>

    <div class="py-8">

        <div class="max-w-7xl mx-auto">

            <div class="bg-white shadow rounded-lg overflow-hidden">

                <table class="min-w-full">

                    <thead class="bg-gray-100">

                        <tr>

                            <th class="px-6 py-3 text-left">Category</th>

                            <th class="px-6 py-3 text-left">Recipient</th>

                            <th class="px-6 py-3 text-left">Deadline</th>

                            <th class="px-6 py-3 text-left">Status</th>

                            <th class="px-6 py-3 text-left">Actions</th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse($categories as $category)

                            <tr class="border-t">

                                <td class="px-6 py-4">
                                    {{ $category->name }}
                                </td>

                                <td class="px-6 py-4">
                                    @if($category->recipient)
                                        {{ $category->recipient->department }}
                                        <br>
                                        <small class="text-gray-500">
                                            {{ $category->recipient->user->name }}
                                        </small>
                                    @else
                                        Not Assigned
                                    @endif
                                </td>

                                <td class="px-6 py-4">
                                    {{ $category->resolution_deadline_days }} days
                                </td>

                                <td class="px-6 py-4">

                                    @if($category->is_active)

                                        <span class="text-green-600 font-semibold">
                                            Active
                                        </span>

                                    @else

                                        <span class="text-red-600 font-semibold">
                                            Inactive
                                        </span>

                                    @endif

                                </td>

                                <td class="px-6 py-4">

                                    <div class="flex items-center gap-4">

                                        <a
                                            href="{{ route('admin.categories.edit', $category) }}"
                                            class="text-blue-600 font-semibold">

                                            Edit

                                        </a>

                                        <form
                                            action="{{ route('admin.categories.toggle-status', $category) }}"
                                            method="POST"
                                            onsubmit="return confirm('Are you sure you want to {{ $category->is_active ? 'deactivate' : 'activate' }} this category?');">

                                            @csrf
                                            @method('PATCH')

                                            <button
                                                type="submit"
                                                class="{{ $category->is_active ? 'text-red-600' : 'text-green-600' }} font-semibold">

                                                {{ $category->is_active ? 'Deactivate' : 'Activate' }}

                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="5" class="text-center py-8 text-gray-500">

                                    No complaint categories found.

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</x-app-layout>