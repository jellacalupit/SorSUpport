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

            @if ($errors->any())
                <div class="mb-6 rounded-lg bg-red-100 border border-red-300 text-red-800 px-4 py-3">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Complaint Information -->
            <div class="bg-white shadow-sm rounded-lg mb-6">
                <div class="p-6 space-y-6">

                    <div>
                        <p class="text-sm text-gray-500 font-medium">Reference Number</p>
                        <p class="text-2xl font-bold text-gray-900 font-mono">{{ $complaint->reference_number }}</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Status</p>
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
                            <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold {{ $badgeClass }} mt-2">
                                {{ str_replace('_', ' ', $complaint->status) }}
                            </span>
                        </div>

                        <div>
                            <p class="text-sm text-gray-500 font-medium">Submitted On</p>
                            <p class="font-semibold text-gray-900 mt-2">
                                {{ $complaint->created_at->format('M d, Y \a\t h:i A') }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500 font-medium">Student Name</p>
                        <p class="font-semibold text-gray-900 mt-2">{{ $complaint->student->user->name }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500 font-medium">Student ID</p>
                        <p class="font-semibold text-gray-900 mt-2">{{ $complaint->student->student_id }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500 font-medium">Category</p>
                        <p class="font-semibold text-gray-900 mt-2">{{ $complaint->category->name }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500 font-medium">Subject</p>
                        <p class="font-semibold text-gray-900 mt-2">{{ $complaint->subject_title }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500 font-medium">Description</p>
                        <p class="text-gray-900 mt-2 whitespace-pre-wrap">{{ $complaint->description }}</p>
                    </div>

                    @if ($complaint->file_attachment)
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Attachment</p>
                            <a href="{{ asset('storage/'.$complaint->file_attachment) }}"
                               target="_blank"
                               class="text-blue-600 hover:text-blue-800 font-semibold mt-2 inline-block">
                                Download Attachment
                            </a>
                        </div>
                    @endif

                    @if ($complaint->ticket)
                        <div class="border-t pt-6 space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div>
                                    <p class="text-sm text-gray-500 font-medium">Deadline</p>
                                    <p class="font-semibold text-gray-900 mt-2">
                                        @if ($complaint->ticket->deadline)
                                            {{ $complaint->ticket->deadline->format('M d, Y \a\t h:i A') }}
                                        @else
                                            <span class="text-gray-400">Not set</span>
                                        @endif
                                    </p>
                                </div>

                                @if ($complaint->ticket->acknowledged_at)
                                    <div>
                                        <p class="text-sm text-gray-500 font-medium">Acknowledged On</p>
                                        <p class="font-semibold text-gray-900 mt-2">
                                            {{ $complaint->ticket->acknowledged_at->format('M d, Y \a\t h:i A') }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                </div>
            </div>

            <!-- Status Management -->
            <div class="bg-white shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Update Status</h3>

                    <form method="POST" action="{{ route('recipient.complaints.update-status', $complaint) }}" class="space-y-4">
                        @csrf
                        @method('PATCH')

                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                                New Status
                            </label>
                            <select id="status"
                                    name="status"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    required>
                                <option value="">-- Select Status --</option>
                                <option value="pending" {{ $complaint->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="in_progress" {{ $complaint->status === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                <option value="resolved" {{ $complaint->status === 'resolved' ? 'selected' : '' }}>Resolved</option>
                                <option value="rejected" {{ $complaint->status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                                <option value="closed" {{ $complaint->status === 'closed' ? 'selected' : '' }}>Closed</option>
                            </select>
                        </div>

                        <div>
                            <label for="details" class="block text-sm font-medium text-gray-700 mb-2">
                                Details (Optional)
                            </label>
                            <textarea id="details"
                                      name="details"
                                      rows="3"
                                      placeholder="Add details about the status change..."
                                      class="w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>

                        <button type="submit"
                                class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg">
                            Update Status
                        </button>
                    </form>
                </div>
            </div>

            <!-- Conversation History -->
            <div class="bg-white shadow-sm rounded-lg mb-6">
                <div class="p-6 space-y-6">
                    <h3 class="text-lg font-semibold text-gray-900">Conversation History</h3>

                    @if ($messages->count() > 0)
                        <div class="space-y-4 max-h-96 overflow-y-auto">
                            @foreach ($messages as $message)
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-start justify-between mb-2">
                                        <div>
                                            <p class="font-semibold text-gray-900">{{ $message->sender->name }}</p>
                                            <p class="text-xs text-gray-500">
                                                {{ $message->sender->role === 'recipient' ? 'Recipient' : 'Student' }} · 
                                                {{ $message->created_at->format('M d, Y \a\t h:i A') }}
                                            </p>
                                        </div>
                                    </div>
                                    <p class="text-gray-900 whitespace-pre-wrap mb-3">{{ $message->content }}</p>
                                    @if ($message->file_attachment)
                                        <a href="{{ asset('storage/'.$message->file_attachment) }}"
                                           target="_blank"
                                           class="text-blue-600 hover:text-blue-800 font-semibold text-sm">
                                            View Attachment
                                        </a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500">No messages yet.</p>
                    @endif
                </div>
            </div>

            <!-- Send Reply -->
            <div class="bg-white shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Send Reply</h3>

                    <form method="POST" action="{{ route('recipient.complaints.reply', $complaint) }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf

                        <div>
                            <label for="content" class="block text-sm font-medium text-gray-700 mb-2">
                                Message
                            </label>
                            <textarea id="content"
                                      name="content"
                                      rows="4"
                                      placeholder="Type your reply..."
                                      class="w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                      required></textarea>
                        </div>

                        <div>
                            <label for="file_attachment" class="block text-sm font-medium text-gray-700 mb-2">
                                Attachment (Optional)
                            </label>
                            <input type="file"
                                   id="file_attachment"
                                   name="file_attachment"
                                   accept=".pdf,.jpg,.jpeg,.png"
                                   class="w-full rounded-lg border border-gray-300 px-4 py-2 text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-2">
                                Allowed: PDF, JPG, JPEG, PNG (Max 5 MB)
                            </p>
                        </div>

                        <button type="submit"
                                class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg">
                            Send Reply
                        </button>
                    </form>
                </div>
            </div>

            <!-- Timeline -->
            <div class="bg-white shadow-sm rounded-lg">
                <div class="p-6 space-y-6">
                    <h3 class="text-lg font-semibold text-gray-900">Timeline</h3>

                    @if ($auditLogs->count() > 0)
                        <div class="space-y-4">
                            @foreach ($auditLogs as $log)
                                <div class="flex gap-4">
                                    <div class="flex flex-col items-center">
                                        <div class="w-4 h-4 bg-blue-600 rounded-full"></div>
                                        @if (!$loop->last)
                                            <div class="w-1 h-16 bg-gray-200 mt-2"></div>
                                        @endif
                                    </div>
                                    <div class="pb-4">
                                        <div>
                                            <p class="font-semibold text-gray-900">
                                                {{ str_replace('_', ' ', $log->action) }}
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                {{ $log->created_at->format('M d, Y \a\t h:i A') }}
                                            </p>
                                        </div>
                                        <div class="mt-2">
                                            <p class="text-sm text-gray-900">
                                                <strong>By:</strong> {{ $log->performer->name }}
                                            </p>
                                            @if ($log->details)
                                                <p class="text-sm text-gray-700 mt-1">
                                                    <strong>Details:</strong> {{ $log->details }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500">No activity yet.</p>
                    @endif
                </div>
            </div>

            <!-- Back Button -->
            <div class="mt-6">
                <a href="{{ route('recipient.complaints.index') }}"
                   class="inline-block text-blue-600 hover:text-blue-800 font-semibold">
                    ← Back to Complaints
                </a>
            </div>

        </div>
    </div>
</x-app-layout>