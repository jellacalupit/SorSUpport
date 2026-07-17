<x-app-layout>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            My Complaints
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-6 rounded-lg bg-green-100 border border-green-300 text-green-800 px-4 py-3">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mb-6">
                <a href="{{ route('student.complaints.create') }}"
                   class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-lg">
                    Submit New Complaint
                </a>
            </div>

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
                                        Subject
                                    </th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">
                                        Category
                                    </th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">
                                        Assigned Office
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
                                @foreach ($complaints as $complaint)
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 text-sm font-mono text-gray-900">
                                            {{ $complaint->reference_number }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            {{ Str::limit($complaint->subject_title, 50) }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            {{ $complaint->category->name }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            {{ $complaint->category->recipient?->department ?? 'N/A' }}
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
                                                $badgeClass = $statusBadges[$complaint->status] ?? 'bg-gray-100 text-gray-800 border border-gray-300';
                                            @endphp
                                            <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold {{ $badgeClass }}">
                                                {{ str_replace('_', ' ', ucfirst($complaint->status)) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            {{ $complaint->created_at->format('M d, Y') }}
                                        </td>
                                        <td class="px-6 py-4 text-sm">
                                            <a href="{{ route('student.complaints.show', $complaint) }}"
                                               class="inline-block bg-gray-800 hover:bg-gray-900 text-white font-semibold px-4 py-2 rounded text-black-900">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-6">
                    {{ $complaints->links() }}
                </div>
            @else
                <div class="bg-white shadow-sm rounded-lg">
                    <div class="p-6 text-center">
                        <p class="text-gray-600 mb-4">
                            You haven't submitted any complaints yet.
                        </p>
                        <a href="{{ route('student.complaints.create') }}"
                           class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-lg">
                            Submit Your First Complaint
                        </a>
                    </div>
                </div>
            @endif

        </div>
    </div>

</x-app-layout>
