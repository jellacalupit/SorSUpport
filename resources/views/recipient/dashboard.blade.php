<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Recipient Dashboard
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!-- Statistics Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Assigned -->
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Total Assigned</p>
                            <p class="text-3xl font-bold text-gray-900">{{ $totalAssigned }}</p>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-lg">
                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Pending -->
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Pending</p>
                            <p class="text-3xl font-bold text-yellow-600">{{ $pendingCount }}</p>
                        </div>
                        <div class="bg-yellow-100 p-3 rounded-lg">
                            <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- In Progress -->
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">In Progress</p>
                            <p class="text-3xl font-bold text-blue-600">{{ $inProgressCount }}</p>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-lg">
                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Resolved -->
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Resolved</p>
                            <p class="text-3xl font-bold text-green-600">{{ $resolvedCount }}</p>
                        </div>
                        <div class="bg-green-100 p-3 rounded-lg">
                            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Latest Complaints Section -->
            <div class="bg-white shadow-sm rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">Latest Assigned Complaints</h3>
                        <a href="{{ route('recipient.complaints.index') }}"
                           class="text-blue-600 hover:text-blue-800 font-semibold text-sm">
                            View All →
                        </a>
                    </div>
                </div>

                @if ($latestComplaints->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50">
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">
                                        Reference Number
                                    </th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">
                                        Student Name
                                    </th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">
                                        Category
                                    </th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">
                                        Status
                                    </th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">
                                        Date Submitted
                                    </th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">
                                        Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($latestComplaints as $ticket)
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 text-sm font-mono text-gray-900">
                                            {{ $ticket->complaint->reference_number }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            {{ $ticket->complaint->student->user->name }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            {{ $ticket->complaint->category->name }}
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
                @else
                    <div class="p-6 text-center text-gray-500">
                        <p>No complaints assigned yet.</p>
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>