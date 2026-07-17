<x-app-layout>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Assigned Complaints
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!-- Filters Section -->
            <div class="bg-white shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Search & Filter</h3>

                    <form method="GET" action="{{ route('recipient.complaints.index') }}" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- Search Reference Number -->
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

                            <!-- Search Student Name -->
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

                            <!-- Search Subject -->
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

                            <!-- Filter by Status -->
                            <div>
                                <label for="status_filter" class="block text-sm font-medium text-gray-700 mb-2">
                                    Status
                                </label>
                                <select id="status_filter"
                                        name="status_filter"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">All Statuses</option>
                                    <option value="pending" {{ request()->input('status_filter') === 'pending' ? 'selected' : '' }}>
                                        Pending
                                    </option>
                                    <option value="in_progress" {{ request()->input('status_filter') === 'in_progress' ? 'selected' : '' }}>
                                        In Progress
                                    </option>
                                    <option value="resolved" {{ request()->input('status_filter') === 'resolved' ? 'selected' : '' }}>
                                        Resolved
                                    </option>
                                    <option value="rejected" {{ request()->input('status_filter') === 'rejected' ? 'selected' : '' }}>
                                        Rejected
                                    </option>
                                    <option value="closed" {{ request()->input('status_filter') === 'closed' ? 'selected' : '' }}>
                                        Closed
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button type="submit"
                                    class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg">
                                Search
                            </button>
                            <a href="{{ route('recipient.complaints.index') }}"
                               class="inline-block bg-gray-300 hover:bg-gray-400 text-gray-900 font-semibold px-6 py-2 rounded-lg">
                                Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Complaints Table -->
            @if ($complaints->count() > 0)
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50">
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">
                                        Reference Number
                                    </th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">
                                        Student
                                    </th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">
                                        Category
                                    </th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">
                                        Subject
                                    </th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">
                                        Status
                                    </th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">
                                        Deadline
                                    </th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">
                                        Submitted Date
                                    </th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($complaints as $ticket)
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 text-sm font-mono font-semibold text-gray-900">
                                            {{ $ticket->complaint->reference_number }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            {{ $ticket->complaint->student->user->name }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            {{ $ticket->complaint->category->name }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            {{ Str::limit($ticket->complaint->subject_title, 40) }}
                                        </td>
                                        <td class="px-6 py-4 text-sm">
                                            @php
                                                $statusBadges = [
                                                    'pending' => 'bg-yellow-100 text-yellow-800 border border-yellow-300',
                                                    'in_progress' => 'bg-blue-100 text-blue-800 border border-blue-300',
                                                    'resolved' => 'bg-green-100 text-green-800 border border-green-300',
                                                    'rejected' => 'bg-red-100 text-red-800 border border-red-300',
                                                    'closed' => 'bg-gray-100 text-gray-800 border border-gray-300',
                                                ];
                                                $badgeClass = $statusBadges[$ticket->status] ?? 'bg-gray-100 text-gray-800 border border-gray-300';
                                            @endphp
                                            <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold {{ $badgeClass }}">
                                                {{ str_replace('_', ' ', $ticket->status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            @if ($ticket->deadline)
                                                {{ $ticket->deadline->format('M d, Y') }}
                                            @else
                                                <span class="text-gray-400">N/A</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            {{ $ticket->complaint->created_at->format('M d, Y') }}
                                        </td>
                                        <td class="px-6 py-4 text-sm">
                                            <a href="{{ route('recipient.complaints.show', $ticket->complaint) }}"
                                               class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-lg text-xs">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="border-t border-gray-200 px-6 py-4">
                        {{ $complaints->links() }}
                    </div>
                </div>
            @else
                <div class="bg-white shadow-sm rounded-lg p-6 text-center">
                    <p class="text-gray-500">No complaints found.</p>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>