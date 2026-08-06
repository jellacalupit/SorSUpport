<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Review Ticket: {{ $ticket->complaint->reference_number }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            @if($errors->any())
                <div class="mb-6 rounded-lg bg-red-100 border border-red-300 text-red-800 px-4 py-3">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Complaint Details -->
            <div class="bg-white shadow-sm rounded-lg mb-6 overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Complaint Details</h3>

                    <div class="grid grid-cols-2 gap-6 text-sm">
                        <div>
                            <label class="block text-gray-600 font-semibold">Reference Number</label>
                            <p class="text-gray-900 font-mono">{{ $ticket->complaint->reference_number }}</p>
                        </div>
                        <div>
                            <label class="block text-gray-600 font-semibold">Student</label>
                            <p class="text-gray-900">{{ $ticket->complaint->student->user->name }}</p>
                        </div>
                        <div>
                            <label class="block text-gray-600 font-semibold">Category</label>
                            <p class="text-gray-900">{{ $ticket->complaint->category->name }}</p>
                        </div>
                        <div>
                            <label class="block text-gray-600 font-semibold">Submitted</label>
                            <p class="text-gray-900">{{ $ticket->complaint->created_at->format('M d, Y \a\t H:i') }}</p>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <h4 class="text-gray-900 font-semibold mb-2">Subject</h4>
                    <p class="text-gray-700 mb-4">{{ $ticket->complaint->subject_title }}</p>

                    @if($ticket->complaint->personnel_involved)
                        <h4 class="text-gray-900 font-semibold mb-2">Personnel Involved</h4>
                        <p class="text-gray-700 mb-4">{{ $ticket->complaint->personnel_involved }}</p>
                    @endif

                    <h4 class="text-gray-900 font-semibold mb-2">Description</h4>
                    <p class="text-gray-700 mb-4 whitespace-pre-wrap">{{ $ticket->complaint->description }}</p>

                    @if($ticket->complaint->file_attachment)
                        <h4 class="text-gray-900 font-semibold mb-2">Attachment</h4>
                        <a href="{{ Storage::url($ticket->complaint->file_attachment) }}"
                           target="_blank"
                           class="text-blue-600 hover:text-blue-800 underline">
                            Download File
                        </a>
                    @endif
                </div>
            </div>

            <!-- Decision Panel -->
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="p-6">

                    @if($ticket->classification === null)
                        <!-- Unclassified Ticket: Review & Determine Validity -->
                        <h3 class="text-lg font-bold text-gray-900 mb-6">Validity Determination</h3>

                        <div class="space-y-6">
                            <!-- Option 1: Reject (Invalid) -->
                            <div class="border-l-4 border-red-500 pl-6 py-4">
                                <h4 class="font-semibold text-gray-900 mb-3">Mark as Invalid</h4>
                                <p class="text-sm text-gray-600 mb-4">
                                    Close this ticket and notify the student with a written reason.
                                </p>

                                <form action="{{ route('admin.tickets.reject', $ticket) }}" method="POST">
                                    @csrf
                                    <div class="mb-4">
                                        <label for="closure_reason" class="block text-gray-700 font-semibold mb-2">
                                            Reason for Closure
                                        </label>
                                        <textarea
                                            id="closure_reason"
                                            name="closure_reason"
                                            rows="4"
                                            required
                                            class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white"
                                            placeholder="Explain why this complaint is invalid..."></textarea>
                                        <p class="text-xs text-gray-500 mt-1">This will be sent to the student.</p>
                                    </div>
                                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold px-6 py-2 rounded-lg">
                                        Reject & Close
                                    </button>
                                </form>
                            </div>

                            <!-- Option 2: Classify as Valid -->
                            <div class="border-l-4 border-blue-500 pl-6 py-4">
                                <h4 class="font-semibold text-gray-900 mb-3">Classify as Valid</h4>
                                <p class="text-sm text-gray-600 mb-4">
                                    Proceed with classification and routing.
                                </p>

                                <form action="{{ route('admin.tickets.classify', $ticket) }}" method="POST" class="space-y-4">
                                    @csrf

                                    <!-- Classification Type -->
                                    <div>
                                        <label class="block text-gray-700 font-semibold mb-3">Classification Type</label>
                                        <div class="space-y-2">
                                            <label class="flex items-start gap-3 cursor-pointer">
                                                <input type="radio" name="classification" value="needs_resolution" required
                                                       class="mt-1">
                                                <span>
                                                    <span class="block font-semibold text-gray-900">Needs Resolution</span>
                                                    <span class="block text-sm text-gray-500">Requires ticket thread, deadline, and recipient follow-up</span>
                                                </span>
                                            </label>
                                            <label class="flex items-start gap-3 cursor-pointer">
                                                <input type="radio" name="classification" value="informational" required
                                                       class="mt-1">
                                                <span>
                                                    <span class="block font-semibold text-gray-900">Informational Only</span>
                                                    <span class="block text-sm text-gray-500">Record for SDS knowledge base only</span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Jurisdiction -->
                                    <div>
                                        <label class="block text-gray-700 font-semibold mb-3">Jurisdiction</label>
                                        <p class="text-sm text-gray-600 mb-3">
                                            @if($defaultJurisdiction)
                                                (Category suggests: <strong>{{ ucfirst($defaultJurisdiction) }}</strong>)
                                            @endif
                                        </p>
                                        <div class="space-y-2">
                                            <label class="flex items-start gap-3 cursor-pointer">
                                                <input type="radio" name="jurisdiction" value="sds" required
                                                       @checked($defaultJurisdiction === 'sds')
                                                       class="mt-1">
                                                <span>
                                                    <span class="block font-semibold text-gray-900">SDS Jurisdiction</span>
                                                    <span class="block text-sm text-gray-500">Handle within SDS (or forward to Recipient optionally if Informational)</span>
                                                </span>
                                            </label>
                                            <label class="flex items-start gap-3 cursor-pointer">
                                                <input type="radio" name="jurisdiction" value="recipient" required
                                                       @checked($defaultJurisdiction === 'recipient')
                                                       class="mt-1">
                                                <span>
                                                    <span class="block font-semibold text-gray-900">Recipient Jurisdiction</span>
                                                    <span class="block text-sm text-gray-500">Route to assigned recipient</span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>

                                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg">
                                        Classify & Proceed
                                    </button>
                                </form>
                            </div>

                        </div>

                    @elseif($ticket->classification === 'informational' && $ticket->jurisdiction === 'recipient' && $ticket->forwarded_at === null)
                        <!-- Classified Informational with Recipient Jurisdiction: Forward Option -->
                        <h3 class="text-lg font-bold text-gray-900 mb-6">Forward to Recipient</h3>
                        <p class="text-sm text-gray-600 mb-6">
                            This informational complaint has been classified for recipient routing. Choose a recipient to forward this to (one-time only).
                        </p>

                        <form action="{{ route('admin.tickets.forward', $ticket) }}" method="POST" class="space-y-4">
                            @csrf

                            <div>
                                <label for="recipient_id" class="block text-gray-700 font-semibold mb-2">
                                    Forward To
                                </label>
                                <select id="recipient_id" name="recipient_id" required
                                        class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white">
                                    <option value="">-- Select Recipient --</option>
                                    @foreach($recipients as $recipient)
                                        <option value="{{ $recipient->id }}">
                                            {{ $recipient->user->name }} ({{ $recipient->staff_id }}) — {{ $recipient->department }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-2 rounded-lg">
                                Forward to Recipient
                            </button>
                        </form>

                    @elseif($ticket->forwarded_at !== null)
                        <!-- Already Forwarded -->
                        <h3 class="text-lg font-bold text-gray-900 mb-6">Forwarded</h3>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <p class="text-green-800">
                                <strong>✓ This ticket was forwarded to {{ $ticket->forwardedRecipient->user->name }}</strong>
                                on {{ $ticket->forwarded_at->format('M d, Y \a\t H:i') }}.
                            </p>
                        </div>

                    @else
                        <!-- Classified but not forwarded (Informational + SDS, or Needs Resolution) -->
                        <h3 class="text-lg font-bold text-gray-900 mb-6">Classification Complete</h3>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                            <p class="text-blue-800">
                                <strong>Classification:</strong> {{ ucfirst($ticket->classification) }}<br>
                                <strong>Jurisdiction:</strong> {{ ucfirst($ticket->jurisdiction) }}
                            </p>
                        </div>

                        @if($ticket->classification === 'needs_resolution')
                            <div class="border border-gray-200 rounded-lg p-6">
                                <h4 class="text-lg font-semibold text-gray-900 mb-2">Assignment & Deadline</h4>
                                @if(in_array($ticket->status, [\App\Models\Ticket::STATUS_ASSIGNED, \App\Models\Ticket::STATUS_IN_PROGRESS, \App\Models\Ticket::STATUS_ESCALATED]))
                                    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
                                        <p class="text-sm font-semibold text-amber-800">Escalation Options</p>
                                        <p class="text-sm text-amber-700 mb-2">Configured targets for this category:</p>
                                        <ul class="list-disc list-inside text-sm text-amber-700">
                                            @foreach($ticket->complaint->category->escalationHierarchies as $hierarchy)
                                                <li>{{ $hierarchy->recipient->user->name ?? 'Unnamed recipient' }}</li>
                                            @endforeach
                                        </ul>
                                        <form action="{{ route('admin.tickets.escalate', $ticket) }}" method="POST" class="mt-3">
                                            @csrf
                                            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold px-4 py-2 rounded-lg">
                                                Escalate Ticket
                                            </button>
                                        </form>
                                    </div>
                                @endif
                                <p class="text-sm text-gray-600 mb-4">
                                    Assign this ticket to a recipient or keep it with SDS. The deadline will be set from the complaint category configuration.
                                </p>

                                <form action="{{ route('admin.tickets.assign', $ticket) }}" method="POST" class="space-y-4">
                                    @csrf

                                    <div class="space-y-2">
                                        <label class="flex items-start gap-3 cursor-pointer">
                                            <input type="radio" name="assignment_mode" value="direct" checked class="mt-1">
                                            <span>
                                                <span class="block font-semibold text-gray-900">Handle Directly</span>
                                                <span class="block text-sm text-gray-500">Keep ownership with SDS and update the internal status only.</span>
                                            </span>
                                        </label>
                                        <label class="flex items-start gap-3 cursor-pointer">
                                            <input type="radio" name="assignment_mode" value="recipient" class="mt-1">
                                            <span>
                                                <span class="block font-semibold text-gray-900">Assign to Recipient</span>
                                                <span class="block text-sm text-gray-500">Grant the recipient access to the ticket thread and notify them.</span>
                                            </span>
                                        </label>
                                    </div>

                                    <div>
                                        <label for="recipient_id" class="block text-gray-700 font-semibold mb-2">
                                            Recipient
                                        </label>
                                        <select id="recipient_id" name="recipient_id"
                                                class="w-full border border-gray-300 rounded-lg p-3 text-gray-900 bg-white">
                                            <option value="">-- Select Recipient --</option>
                                            @foreach($recipients as $recipient)
                                                <option value="{{ $recipient->id }}">
                                                    {{ $recipient->user->name }} ({{ $recipient->staff_id }}) — {{ $recipient->department }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-2 rounded-lg">
                                        Assign Ticket
                                    </button>
                                </form>
                            </div>
                        @endif
                    @endif

                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg mt-6">
                <div class="p-6 border-t border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Audit Trail</h3>

                    @if ($ticket->auditLogs && $ticket->auditLogs->count())
                        <div class="space-y-4">
                            @foreach ($ticket->auditLogs as $log)
                                <div class="flex items-start gap-4">
                                    <div class="text-sm text-gray-500 w-36">
                                        <div>{{ $log->created_at->format('M d, Y') }}</div>
                                        <div class="mt-1">{{ $log->created_at->format('h:i A') }}</div>
                                    </div>
                                    <div class="flex-1 bg-gray-50 rounded-lg p-4 border">
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
                        <p class="text-gray-500">No audit history recorded for this ticket.</p>
                    @endif
                </div>
            </div>

            <!-- Back Link -->
            <div class="mt-6">
                <a href="{{ route('admin.tickets.review.index') }}"
                   class="text-blue-600 hover:text-blue-800 underline">
                    ← Back to Pending Tickets
                </a>
            </div>

        </div>
    </div>

</x-app-layout>
