<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Anonymous Complaint — {{ $complaint->reference_number }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
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

                    <div class="border-t pt-4">
                        <p class="text-sm text-gray-600">
                            This submission was marked anonymous. No ticket is generated and no student identity is displayed.
                        </p>
                    </div>

                    <div class="flex gap-4 pt-2">
                        <a href="{{ route('admin.complaints.anonymous') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-semibold">
                            Back to Anonymous List
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

