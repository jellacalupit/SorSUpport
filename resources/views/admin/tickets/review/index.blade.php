<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Ticket Review & Classification
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-6 rounded-lg bg-green-100 border border-green-300 text-green-800 px-4 py-3">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="p-6">

                    @if($tickets->isEmpty())
                        <p class="text-gray-600">
                            No pending tickets awaiting validity determination.
                        </p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left text-gray-600">
                                <thead class="text-xs text-gray-700 bg-gray-100">
                                    <tr>
                                        <th class="px-4 py-3">Ref #</th>
                                        <th class="px-4 py-3">Student</th>
                                        <th class="px-4 py-3">Subject</th>
                                        <th class="px-4 py-3">Category</th>
                                        <th class="px-4 py-3">Submitted</th>
                                        <th class="px-4 py-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    @foreach($tickets as $ticket)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 font-mono text-xs">
                                                {{ $ticket->complaint->reference_number }}
                                            </td>
                                            <td class="px-4 py-3">
                                                {{ $ticket->complaint->student->user->name }}
                                            </td>
                                            <td class="px-4 py-3 truncate max-w-xs">
                                                {{ $ticket->complaint->subject_title }}
                                            </td>
                                            <td class="px-4 py-3">
                                                {{ $ticket->complaint->category->name }}
                                            </td>
                                            <td class="px-4 py-3">
                                                {{ $ticket->complaint->created_at->format('M d, Y') }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <a href="{{ route('admin.tickets.review.show', $ticket) }}"
                                                   class="text-blue-600 hover:text-blue-800 font-semibold">
                                                    Review
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6">
                            {{ $tickets->links() }}
                        </div>
                    @endif

                </div>
            </div>

        </div>
    </div>

</x-app-layout>
