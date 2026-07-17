<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Complaint Details — {{ $complaint->reference_number }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-6 rounded-lg bg-green-100 border border-green-300 text-green-800 px-4 py-3">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg">
                <div class="p-6 space-y-6">
                    <div>
                        <p class="text-sm text-gray-500">Reference Number</p>
                        <p class="text-2xl font-bold text-gray-900 font-mono">{{ $complaint->reference_number }}</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <p class="text-sm text-gray-500">Status</p>
                            <p class="font-semibold text-gray-900 capitalize mt-2">{{ str_replace('_', ' ', $complaint->status) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Submitted On</p>
                            <p class="font-semibold text-gray-900 mt-2">{{ $complaint->created_at->format('M d, Y \a\t h:i A') }}</p>
                        </div>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500">Student</p>
                        <p class="font-semibold text-gray-900 mt-2">{{ $complaint->student?->user?->name ?? '—' }}</p>
                        @if ($complaint->student)
                            <p class="text-sm text-gray-600">Student ID: {{ $complaint->student->student_id }}</p>
                        @endif
                    </div>

                    <div>
                        <p class="text-sm text-gray-500">Category</p>
                        <p class="font-semibold text-gray-900 mt-2">{{ $complaint->category?->name ?? '—' }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500">Subject</p>
                        <p class="text-gray-900 mt-2">{{ $complaint->subject_title }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500">Description</p>
                        <p class="text-gray-900 whitespace-pre-wrap mt-2">{{ $complaint->description }}</p>
                    </div>

                    @if ($complaint->file_attachment)
                        <div>
                            <p class="text-sm text-gray-500">Attachment</p>
                            <a href="{{ asset('storage/'.$complaint->file_attachment) }}" target="_blank" class="text-blue-600 hover:text-blue-800 font-semibold mt-2 inline-block">
                                View Attachment
                            </a>
                        </div>
                    @endif

                    @if ($complaint->ticket)
                        <div class="border-t pt-6 space-y-4">
                            <h3 class="text-lg font-bold text-gray-900">Ticket Information</h3>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div>
                                    <p class="text-sm text-gray-500">Ticket Status</p>
                                    <p class="font-semibold text-gray-900 mt-2 capitalize">{{ str_replace('_', ' ', $complaint->ticket->status) }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Assigned To</p>
                                    <p class="font-semibold text-gray-900 mt-2">{{ $complaint->ticket->assignee?->name ?? '—' }}</p>
                                </div>
                            </div>

                            <div>
                                <p class="text-sm text-gray-500">Deadline</p>
                                <p class="font-semibold text-gray-900 mt-2">{{ $complaint->ticket->deadline?->format('M d, Y \a\t h:i A') ?? 'N/A' }}</p>
                            </div>
                        </div>

                        <div class="border-t pt-6 space-y-4">
                            <h3 class="text-lg font-bold text-gray-900">Conversation Preview</h3>

                            @php
                                $messages = $complaint->ticket->thread?->messages ?? collect();
                            @endphp

                            @if ($messages->count() > 0)
                                <div class="space-y-4">
                                    @foreach ($messages as $message)
                                        <div class="bg-gray-50 rounded-lg p-4 border">
                                            <div class="flex items-start justify-between gap-4">
                                                <div>
                                                    <p class="font-semibold text-gray-900">{{ $message->sender?->name ?? 'Unknown' }}</p>
                                                    <p class="text-sm text-gray-500">{{ $message->sender?->role ?? 'user' }}</p>
                                                </div>
                                                <div class="text-sm text-gray-500">{{ $message->created_at->format('M d, Y h:i A') }}</div>
                                            </div>
                                            <div class="mt-3 text-gray-800 whitespace-pre-wrap">{{ $message->content }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-gray-500">No conversation yet.</p>
                            @endif
                        </div>


                        <div class="border-t pt-6 space-y-4">
                            <h3 class="text-lg font-bold text-gray-900">Audit Trail</h3>

                            @if ($complaint->ticket->auditLogs && $complaint->ticket->auditLogs->count())
                                <div class="space-y-3">
                                    @foreach ($complaint->ticket->auditLogs as $log)
                                        <div class="flex items-start gap-4">
                                            <div class="text-sm text-gray-500 w-36">
                                                <div>{{ $log->created_at->format('M d, Y') }}</div>
                                                <div class="mt-1">{{ $log->created_at->format('h:i A') }}</div>
                                            </div>
                                            <div class="flex-1 bg-gray-50 rounded-lg p-3 border">
                                                <div class="flex items-center justify-between">
                                                    <div class="font-semibold text-gray-900">{{ str_replace('_', ' ', $log->action) }}</div>
                                                    <div class="text-sm text-gray-500">{{ $log->performer?->name ?? 'System' }}</div>
                                                </div>
                                                <div class="text-gray-700 text-sm mt-2">{{ $log->details }}</div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-gray-500">No activity recorded.</p>
                            @endif
                        </div>
                    @else
                        <div class="border-t pt-6">
                            <p class="text-gray-500">No ticket has been generated for this complaint yet.</p>
                        </div>
                    @endif

                    <div class="flex gap-4 pt-2">
                        <a href="{{ route('admin.complaints.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-semibold">
                            Back to Queue
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

