<x-app-layout :role="'student'" :title="$complaint->reference_number">
    <!-- Back Link -->
    <div class="mb-2 flex items-center">
        <a href="{{ route('student.complaints.index') }}" aria-label="Back" onclick="event.preventDefault(); if (window.history.length > 1) { window.history.back(); } else { window.location.href = '{{ route('student.complaints.index') }}'; }" class="inline-flex items-center gap-1 rounded-md px-1.5 py-1 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground sm:text-base">
            <span aria-hidden="true" class="scale-y-150 text-lg leading-none">&lt;</span>
            Back
        </a>
    </div>

    @if ($complaint->ticket)
        <!-- Ticket Info Panel -->
        <x-ticket-info-panel :ticket="$complaint->ticket" student-view />

        @if ($complaint->ticket->classification === 'needs_resolution')
            <!-- Thread Section -->
            <div class="mt-3">
                <x-ticket-thread :ticket="$complaint->ticket" viewerRole="student" />
            </div>
        @endif
    @else
        <!-- Anonymous Complaint Notice -->
        <x-page-section title="Anonymous Complaint">
            <p class="text-sm text-muted-foreground">
                This is an anonymous complaint. It was not converted to a ticket and does not have a communication thread or deadline.
            </p>
        </x-page-section>

        <!-- Basic Complaint Info (for anonymous) -->
        <div class="mt-5 surface p-4 sm:p-6">
            <div class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3">
                <div class="min-w-0">
                    <h2 class="font-display text-xl font-bold leading-tight wrap-break-word">
                        {{ $complaint->subject_title }}
                    </h2>
                    <p class="font-mono text-xs leading-tight text-muted-foreground">
                        {{ $complaint->reference_number }}
                    </p>
                </div>
            </div>

            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Category</p>
                    <p class="text-sm font-medium leading-tight wrap-break-word">{{ $complaint->category->name }}</p>
                </div>

                <div class="min-w-0">
                    <p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Date Submitted</p>
                    <p class="text-sm font-medium leading-tight wrap-break-word">
                        {{ $complaint->created_at->copy()->setTimezone('Asia/Manila')->format('M d, Y') }}
                    </p>
                </div>

                <div class="min-w-0">
                    <p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Person Involved</p>
                    <p class="text-sm font-medium leading-tight wrap-break-word">
                        {{ filled($complaint->personnel_involved) ? $complaint->personnel_involved : '—' }}
                    </p>
                </div>
            </div>

            <div class="mt-3">
                <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    Description
                </p>
                <p class="mt-1.5 text-sm leading-relaxed whitespace-pre-line">{{ trim($complaint->description) }}</p>
            </div>

            @foreach ($complaint->attachment_files as $attachment)
                @php
                    $attachmentExtension = strtolower(pathinfo($attachment['name'] ?? $attachment['path'] ?? '', PATHINFO_EXTENSION));
                    $isImageAttachment = in_array($attachmentExtension, ['jpg', 'jpeg', 'png', 'heic']);
                @endphp
                <p class="group mt-3 inline-flex items-center gap-2 rounded-lg border border-border bg-muted px-3 py-1.5 text-xs transition-colors hover:border-primary hover:bg-primary-soft">
                    @if ($isImageAttachment)
                        <x-icons.image class="h-3.5 w-3.5 shrink-0 text-muted-foreground transition-colors group-hover:text-primary" />
                    @else
                        <x-icons.file-text class="h-3.5 w-3.5 shrink-0 text-muted-foreground transition-colors group-hover:text-primary" />
                    @endif
                    <a href="{{ asset('storage/' . $attachment['path']) }}" target="_blank" class="hover:underline">
                        {{ $attachment['name'] }}
                    </a>
                </p>
            @endforeach
        </div>
    @endif
</x-app-layout>
