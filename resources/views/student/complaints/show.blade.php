<x-app-layout>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Complaint Details
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
                        <p class="text-xl font-bold text-gray-900">{{ $complaint->reference_number }}</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <p class="text-sm text-gray-500">Status</p>
                            <p class="font-semibold text-gray-900 capitalize">
                                {{ str_replace('_', ' ', $complaint->status) }}
                            </p>
                        </div>

                        <div>
                            <p class="text-sm text-gray-500">Submitted On</p>
                            <p class="font-semibold text-gray-900">
                                {{ $complaint->created_at->format('M d, Y h:i A') }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500">Category</p>
                        <p class="font-semibold text-gray-900">{{ $complaint->category->name }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500">Subject</p>
                        <p class="font-semibold text-gray-900">{{ $complaint->subject_title }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500">Description</p>
                        <p class="text-gray-900 whitespace-pre-wrap">{{ $complaint->description }}</p>
                    </div>

                    @if ($complaint->file_attachment)
                        <div>
                            <p class="text-sm text-gray-500">Attachment</p>
                            <a href="{{ asset('storage/'.$complaint->file_attachment) }}"
                               target="_blank"
                               class="text-blue-600 hover:text-blue-800 font-semibold">
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
                                    <p class="font-semibold text-gray-900 capitalize">
                                        {{ str_replace('_', ' ', $complaint->ticket->status) }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-sm text-gray-500">Resolution Deadline</p>
                                    <p class="font-semibold text-gray-900">
                                        {{ $complaint->ticket->deadline?->format('M d, Y h:i A') ?? 'N/A' }}
                                    </p>
                                </div>
                            </div>

                            @if ($complaint->ticket->assignee)
                                <div>
                                    <p class="text-sm text-gray-500">Assigned To</p>
                                    <p class="font-semibold text-gray-900">
                                        {{ $complaint->ticket->assignee->name }}
                                        @if ($complaint->category->recipient)
                                            — {{ $complaint->category->recipient->department }}
                                        @endif
                                    </p>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Conversation History --}}
                    <div class="border-t pt-6 space-y-4">
                        <h3 class="text-lg font-bold text-gray-900">Conversation History</h3>

                        @if ($complaint->ticket && $complaint->ticket->thread && $complaint->ticket->thread->messages->count())
                            <div class="space-y-4">
                                @foreach ($complaint->ticket->thread->messages as $message)
                                    <div class="bg-gray-50 rounded-lg p-4 border">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="font-semibold text-gray-900">{{ $message->sender->name ?? 'Unknown' }}</p>
                                                <p class="text-sm text-gray-500">{{ ucfirst($message->sender->role ?? 'user') }}</p>
                                            </div>

                                            <div class="text-sm text-gray-500">
                                                {{ $message->created_at->format('M d, Y h:i A') }}
                                            </div>
                                        </div>

                                        <div class="mt-3 text-gray-800 whitespace-pre-wrap">{{ $message->content }}</div>

                                        @if ($message->file_attachment)
                                            <div class="mt-3">
                                                <a href="{{ asset('storage/'.$message->file_attachment) }}" target="_blank" class="text-blue-600 hover:text-blue-800 font-semibold">View Attachment</a>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-500">No conversation yet.</p>
                        @endif

                        @if ($complaint->ticket && $complaint->ticket->thread)
                            @if ($complaint->ticket->thread->is_active)
                                <!-- Message Form -->
                                <div class="bg-gray-50 rounded-lg p-4 border mt-6">
                                    <h4 class="font-semibold text-gray-900 mb-4">Send a Message</h4>
                                    <form action="{{ route('student.complaints.reply', $complaint) }}" method="POST" enctype="multipart/form-data">
                                        @csrf
                                        <div class="mb-4">
                                            <label for="content" class="block text-gray-700 font-semibold mb-2">Message</label>
                                            <textarea
                                                id="content"
                                                name="content"
                                                rows="4"
                                                required
                                                class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white"
                                                placeholder="Type your message here..."></textarea>
                                            <x-input-error :messages="$errors->get('content')" class="mt-2" />
                                        </div>

                                        <div class="mb-4">
                                            <label for="file_attachment" class="block text-gray-700 font-semibold mb-2">Attachment (optional)</label>
                                            <input
                                                type="file"
                                                id="file_attachment"
                                                name="file_attachment"
                                                accept=".pdf,.jpg,.jpeg,.png"
                                                class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white">
                                            <p class="text-xs text-gray-500 mt-1">Allowed: PDF, JPG, PNG (max 5 MB)</p>
                                            <x-input-error :messages="$errors->get('file_attachment')" class="mt-2" />
                                        </div>

                                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg">
                                            Send Message
                                        </button>
                                    </form>
                                </div>
                            @else
                                <div class="bg-gray-100 rounded-lg p-4 border border-gray-300 mt-6">
                                    <p class="text-gray-600 font-semibold">This conversation has been closed.</p>
                                    <p class="text-gray-500 text-sm mt-1">No new messages can be sent at this time.</p>
                                </div>
                            @endif
                        @endif
                    </div>

                    {{-- Complaint Timeline / Audit Logs --}}
                    <div class="border-t pt-6 space-y-4">
                        <h3 class="text-lg font-bold text-gray-900">Complaint Timeline</h3>

                        @if ($complaint->ticket && $complaint->ticket->auditLogs && $complaint->ticket->auditLogs->count())
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

                    <div class="flex gap-4 pt-4">
                        <a href="{{ route('student.complaints.create') }}"
                           class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold">
                            Submit Another Complaint
                        </a>

                        <a href="{{ route('student.dashboard') }}"
                           class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-semibold">
                            Back to Dashboard
                        </a>
                    </div>

                </div>
            </div>

        </div>
    </div>

</x-app-layout>
