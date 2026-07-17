<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Identified Complaints Queue
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Search & Filter</h3>

                    <form method="GET" action="{{ route('admin.complaints.index') }}" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label for="search_reference" class="block text-sm font-medium text-gray-700 mb-2">
                                    Reference Number
                                </label>
                                <input type="text"
                                       id="search_reference"
                                       name="search_reference"
                                       value="{{ request()->input('search_reference') }}"
                                       placeholder="e.g. SOS-2026-000001"
                                       class="w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>

                            <div>
                                <label for="search_student" class="block text-sm font-medium text-gray-700 mb-2">
                                    Student Name
                                </label>
                                <input type="text"
                                       id="search_student"
                                       name="search_student"
                                       value="{{ request()->input('search_student') }}"
                                       placeholder="Enter student name"
                                       class="w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>

                            <div>
                                <label for="search_subject" class="block text-sm font-medium text-gray-700 mb-2">
                                    Subject
                                </label>
                                <input type="text"
                                       id="search_subject"
                                       name="search_subject"
                                       value="{{ request()->input('search_subject') }}"
                                       placeholder="Enter subject"
                                       class="w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>

                            <div>
                                <label for="status_filter" class="block text-sm font-medium text-gray-700 mb-2">
                                    Status
                                </label>
                                <select id="status_filter"
                                        name="status_filter"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">All Statuses</option>
                                    <option value="pending" {{ request()->input('status_filter') === 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="in_progress" {{ request()->input('status_filter') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                    <option value="resolved" {{ request()->input('status_filter') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                                    <option value="rejected" {{ request()->input('status_filter') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                                    <option value="closed" {{ request()->input('status_filter') === 'closed' ? 'selected' : '' }}>Closed</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button type="submit" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg">
                                Search
                            </button>
                            <a href="{{ route('admin.complaints.index') }}" class="inline-block bg-gray-300 hover:bg-gray-400 text-gray-900 font-semibold px-6 py-2 rounded-lg">
                                Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            @if ($complaints->count() > 0)
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50">
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Reference</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Student</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Category</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Subject</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Status</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Submitted</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($complaints as $complaint)
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 text-sm font-mono font-semibold text-gray-900">{{ $complaint->reference_number }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-900">{{ $complaint->student?->user?->name ?? '—' }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-900">{{ $complaint->category?->name ?? '—' }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-900">{{ Str::limit($complaint->subject_title, 40) }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-900 capitalize">{{ str_replace('_', ' ', $complaint->status) }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-900">{{ $complaint->created_at->format('M d, Y') }}</td>
                                        <td class="px-6 py-4 text-sm">
                                            <a href="{{ route('admin.complaints.show', $complaint) }}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-lg text-xs">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-gray-200 px-6 py-4">
                        {{ $complaints->links() }}
                    </div>
                </div>
            @else
                <div class="bg-white shadow-sm rounded-lg p-6 text-center">
                    <p class="text-gray-500">No identified complaints found.</p>
                </div>
            @endif

            <div class="mt-6">
                <a href="{{ route('admin.complaints.anonymous') }}" class="inline-block text-blue-600 hover:text-blue-800 font-semibold">
                    View Anonymous Complaints
                </a>
            </div>
        </div>
    </div>
</x-app-layout>

