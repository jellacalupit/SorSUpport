@props(['ticket', 'showFiledBy' => false, 'studentView' => false, 'showClosedAt' => false])

@php
    $complaint = $ticket->complaint;
    $isInvalid = $ticket->classification === 'invalid';

    $submittedAt = $complaint?->created_at?->copy()->setTimezone('Asia/Manila');
    $lastUpdate = 'Unknown';
    $holder = $ticket->status === 'pending'
        ? \App\Models\User::query()->where('role', \App\Models\User::ROLE_SDS_ADMIN)->orderBy('id')->first()
        : ($isInvalid ? null : ($ticket->currentHandler
            ?? $ticket->assignee
            ?? $complaint?->category?->recipient?->user));
    $holderRecipient = $holder?->recipient;
    $holderDisplayName = $holder?->table_name ?? '—';
    $student = $complaint?->student;
    $studentUser = $student?->user;
    $studentDisplayName = $complaint?->is_anonymous ? 'Anonymous' : ($studentUser?->table_name ?? 'Anonymous');
    $studentCourseYearBlock = trim(($student->course ?? 'Course not specified') . ' ' . ($student->year_level ?? 'N/A') . (($student->block ?? '') !== '' ? '-' . $student->block : ''));
    $resolutionDays = $complaint?->created_at && $ticket->deadline
        ? (int) $complaint->created_at->diffInDays($ticket->deadline)
        : null;
    $daysRemaining = in_array($ticket->status, ['pending', 'closed', 'rejected', 'resolved'], true)
        || in_array($ticket->classification, ['informational', 'invalid'], true)
        ? null
        : $ticket->remainingDays();
    $statusDisplay = $studentView && $ticket->classification === 'informational' && $ticket->status === 'pending'
        ? 'Closed'
        : ($studentView
        ? match ($ticket->status) {
            'assigned', 'in_progress' => 'In Progress',
            'escalated' => 'Escalated',
            'resolved' => 'Resolved',
            'rejected', 'closed' => 'Closed',
            default => 'Pending',
        }
        : match ($ticket->status) {
            'assigned', 'in_progress' => 'In Progress',
            'resolved' => 'Resolved',
            'escalated' => 'Escalated',
            'rejected', 'closed' => 'Closed',
            default => ucfirst(str_replace('_', ' ', $ticket->status)),
        });
    if ($ticket->updated_at) {
        $updatedAt = $ticket->updated_at->copy()->setTimezone('Asia/Manila');
        $nowInManila = now()->setTimezone('Asia/Manila');
        $ageInMinutes = $updatedAt->diffInMinutes($nowInManila);
        $relativeTime = $updatedAt->diffForHumans();
        $lastUpdate = $ageInMinutes < 60
            ? str_replace(' from now', ' ago', $relativeTime)
            : ($ageInMinutes < 1440
                ? $updatedAt->format('g:i A')
                : $updatedAt->format('M d, g:i A'));
    }
@endphp

<div class="surface p-4 sm:p-6">
    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3">
        <div class="min-w-0">
            <h2 class="font-display text-lg font-bold leading-tight wrap-break-word">
                {{ $complaint?->subject_title ?? 'Untitled' }}
            </h2>
            <p class="font-mono text-[11px] font-semibold leading-tight text-primary">
                {{ $complaint?->reference_number ?? $ticket->id }}
            </p>
            @if ($showFiledBy)
                <p class="mt-1 text-xs leading-tight text-muted-foreground">
                    Filed by
                    @if ($studentUser && ! $complaint?->is_anonymous)
                        <span class="relative inline-block" x-data="{ studentProfileOpen: false }" x-on:click.outside="studentProfileOpen = false">
                            <button type="button" class="text-left font-medium text-primary hover:underline" x-on:click="studentProfileOpen = !studentProfileOpen" aria-label="View {{ $studentDisplayName }} profile">
                                {{ $studentDisplayName }}
                            </button>
                            <span x-show="studentProfileOpen" x-cloak class="brand-gradient absolute top-6 left-0 z-30 w-72 max-w-[calc(100vw-2rem)] rounded-xl p-4 text-primary-foreground shadow-lg">
                                <span class="flex items-center gap-3">
                                    <span class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-foreground/15 text-sm font-bold ring-2 ring-primary-foreground/30">
                                        @if ($studentUser->avatar_path)
                                            <img src="{{ asset('storage/' . $studentUser->avatar_path) }}" alt="{{ $studentDisplayName }}" class="h-full w-full object-cover">
                                        @else
                                            {{ $studentUser->name_initials }}
                                        @endif
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block wrap-break-word text-sm font-bold">{{ $studentDisplayName }}</span>
                                        <span class="mt-1 block wrap-break-word text-[11px] leading-relaxed opacity-95">ID {{ $student->student_id ?? 'N/A' }} · {{ $student->department ?? 'Department not specified' }} · {{ $studentCourseYearBlock }}</span>
                                    </span>
                                </span>
                                <span class="mt-3 inline-flex rounded-full border border-primary-foreground/40 bg-primary-foreground/15 px-3 py-1 text-[10px] font-semibold">Student</span>
                            </span>
                        </span>
                    @else
                        Anonymous
                    @endif
                </p>
            @endif
        </div>
        <div class="flex flex-col items-end gap-2">
            <x-status-badge :status="$statusDisplay" :classification="$ticket->classification" :show-icon="false" class="px-3 py-1 text-xs" />
        </div>
    </div>

    <div class="mt-3 grid grid-flow-col grid-cols-2 grid-rows-5 gap-x-3 gap-y-1 sm:grid-cols-3 sm:grid-rows-3">
        <!-- Category -->
        <div class="min-w-0">
            <p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Category</p>
            <p class="text-sm font-medium leading-tight wrap-break-word">{{ $complaint?->category?->name ?? 'Uncategorized' }}</p>
        </div>

        <!-- Person Involved -->
        <div class="min-w-0">
            <p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Person Involved</p>
            <p class="text-sm font-medium leading-tight wrap-break-word">
                {{ filled($complaint?->personnel_involved) ? $complaint->personnel_involved : '—' }}
            </p>
        </div>

        <!-- Current Holder -->
        <div class="min-w-0">
            <p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Current Holder</p>
            <p class="text-sm font-medium leading-tight wrap-break-word">
                @if ($holder && $holderRecipient)
                    <span class="relative" x-data="{ holderProfileOpen: false }" x-on:click.outside="holderProfileOpen = false">
                        <button type="button" class="text-left text-primary hover:underline" x-on:click="holderProfileOpen = !holderProfileOpen" aria-label="View {{ $holderDisplayName }} profile">
                            {{ $holderDisplayName }}
                        </button>
                        <span x-show="holderProfileOpen" x-cloak class="brand-gradient absolute top-7 left-0 z-30 w-72 max-w-[calc(100vw-2rem)] rounded-xl p-4 text-primary-foreground shadow-lg">
                            <span class="flex items-center gap-3">
                                <span class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-foreground/15 text-sm font-bold ring-2 ring-primary-foreground/30">
                                    @if ($holder->avatar_path)
                                        <img src="{{ asset('storage/' . $holder->avatar_path) }}" alt="{{ $holderDisplayName }}" class="h-full w-full object-cover">
                                    @else
                                        {{ $holder->name_initials }}
                                    @endif
                                </span>
                                <span class="min-w-0">
                                    <span class="block wrap-break-word text-sm font-bold">{{ $holderDisplayName }}</span>
                                    <span class="mt-1 block wrap-break-word text-[11px] leading-relaxed opacity-95">ID {{ $holderRecipient->staff_id }} · {{ $holderRecipient->department }} · {{ $holderRecipient->designation }}</span>
                                </span>
                            </span>
                            <span class="mt-3 inline-flex rounded-full border border-primary-foreground/40 bg-primary-foreground/15 px-3 py-1 text-[10px] font-semibold">{{ $holder?->role === \App\Models\User::ROLE_SDS_ADMIN ? 'Admin' : 'Recipient' }}</span>
                        </span>
                    </span>
                @else
                    {{ $holderDisplayName }}
                @endif
            </p>
        </div>

        <!-- Resolution Deadline -->
        @if ($ticket->status !== 'closed')
            <div class="min-w-0">
                <p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Resolution Deadline</p>
                <p class="text-sm font-medium leading-tight wrap-break-word">
                    @if ($isInvalid)
                        —
                    @elseif (in_array($ticket->classification, ['informational', 'invalid'], true))
                        —
                    @elseif ($ticket->status === 'pending')
                        —
                    @elseif ($ticket->status === 'resolved')
                        Paused
                    @elseif ($ticket->deadline)
                        {{ $ticket->deadline->copy()->setTimezone('Asia/Manila')->format('M d, Y · g:i A') }}
                    @else
                        —
                    @endif
                </p>
            </div>
        @endif

        @if ($showClosedAt && $ticket->status === 'closed' && $ticket->closed_at)
            <div class="min-w-0">
                <p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Closed at</p>
                <p class="text-sm font-medium leading-tight wrap-break-word">
                    {{ $ticket->closed_at->copy()->setTimezone('Asia/Manila')->format('M d, Y · g:i A') }}
                </p>
            </div>
        @endif

        <!-- Classification -->
        <div class="min-w-0">
            <p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Classification</p>
            <p class="text-sm font-medium leading-tight wrap-break-word">
                {{ $ticket->classification ? ucwords(str_replace('_', ' ', $ticket->classification)) : 'Not yet classified' }}
            </p>
        </div>

        <!-- Date Submitted -->
        <div class="min-w-0">
            <p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Date Submitted</p>
            <p class="text-sm font-medium leading-tight wrap-break-word">
                {{ $submittedAt?->copy()->setTimezone('Asia/Manila')->format('M d, Y · g:i A') ?? 'Unknown' }}
            </p>
        </div>

        <!-- Last Updated -->
        <div class="min-w-0">
            <p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Last Updated</p>
            <p class="text-sm font-medium leading-tight wrap-break-word">
                {{ $lastUpdate }}
            </p>
        </div>
    </div>

    <!-- Description -->
    <div class="mt-3">
        <p class="text-xs font-semibold tracking-wide text-foreground uppercase">Description</p>
        <p class="text-sm leading-relaxed whitespace-pre-line text-foreground">{{ trim($complaint?->description ?? 'No description provided') }}</p>
    </div>

    <!-- Attachments -->
    @if ($complaint?->attachment_files)
        <div class="mt-3">
            <p class="flex items-center gap-2 text-xs font-semibold tracking-wide text-foreground uppercase">
                <x-icons.paperclip class="h-4 w-4 shrink-0 text-muted-foreground" />
                Attachment
            </p>
            <div class="mt-2 grid gap-2">
                @foreach ($complaint->attachment_files as $attachment)
                    @php
                        $attachmentExtension = strtolower(pathinfo($attachment['name'] ?? $attachment['path'] ?? '', PATHINFO_EXTENSION));
                        $isImageAttachment = in_array($attachmentExtension, ['jpg', 'jpeg', 'png', 'heic']);
                    @endphp
                    <a href="{{ asset('storage/' . $attachment['path']) }}" target="_blank" class="group flex w-fit max-w-full items-center gap-2 rounded-md border border-border bg-gray-200 p-1.5 text-black transition-colors hover:border-primary hover:bg-primary-soft">
                        <span class="flex min-w-0 items-center gap-2">
                            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-gray-400 text-primary">
                                @if ($isImageAttachment)
                                    <x-icons.image class="h-3.5 w-3.5 fill-muted text-gray-700 transition-colors" />
                                @else
                                    <x-icons.file-text class="h-3.5 w-3.5 fill-muted text-gray-700 transition-colors" />
                                @endif
                            </span>
                            <span class="min-w-0 truncate text-sm font-semibold">{{ $attachment['name'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Closed Reason (if applicable) -->
    @if ($ticket->status === 'closed' && $ticket->closure_reason)
        <div class="mt-4 rounded-lg border bg-secondary p-3 text-sm">
            <p class="font-semibold">Closure Reason</p>
            <p class="mt-1 text-muted-foreground">{{ $ticket->closure_reason }}</p>
        </div>
    @endif
</div>
