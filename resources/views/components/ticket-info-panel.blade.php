@props([
    'ticket',
    'showFiledBy' => false,
    'studentView' => false,
    'showClosedAt' => false,
    // Admin review only: lets the category and suggested recipient be corrected before classifying.
    'editable' => false,
    'categories' => [],
    'recipients' => [],
])

@php
    $complaint = $ticket->complaint;
    $isInvalid = $ticket->classification === 'invalid';
    $canEditDetails = $editable && $ticket->isAwaitingReview();

    $displayTimezone = $studentView ? config('app.timezone') : 'Asia/Manila';
    $submittedAt = $complaint?->created_at?->copy()->setTimezone($displayTimezone);
    $assignedUser = $ticket->isAwaitingReview() && ! $ticket->assignee
        ? null
        : ($ticket->currentHandler ?? $ticket->assignee);
    $assignedRecipient = $assignedUser?->recipient;
    $creator = $complaint?->hidesIdentityFrom(\Illuminate\Support\Facades\Auth::user())
        ? null
        : $complaint?->student?->user;
    $creatorStudent = $creator?->student;
    $suggestedRecipientName = $complaint?->suggestedRecipient?->user?->table_name;
    $createdBy = $complaint?->filerNameFor(\Illuminate\Support\Facades\Auth::user()) ?? 'Unknown';
    $attachments = $complaint?->attachment_files ?? [];
    $holder = $ticket->isAwaitingReview()
        ? \App\Models\User::query()->where('role', \App\Models\User::ROLE_SDS_ADMIN)->orderBy('id')->first()
        : ($isInvalid ? null : ($ticket->currentHandler
            ?? $ticket->assignee
            ?? $complaint?->category?->recipient?->user));
    $holderDisplayName = $holder?->table_name ?? '—';
    $viewer = \Illuminate\Support\Facades\Auth::user();
    $showForwardedInformational = $ticket->isForwardedInformational()
        && ($viewer?->isStudent() || $viewer?->isRecipient());
    $statusDisplay = $ticket->displayStatus()
        ?? (($viewer?->isStudent() || $viewer?->isRecipient()) ? $ticket->outcomeLabel() : null);
    $forwardedOffice = $showForwardedInformational ? $ticket->forwardedRecipient?->unit : null;
    $forwardedDate = $showForwardedInformational
        ? $ticket->forwarded_at?->copy()->setTimezone($displayTimezone)->format('M d, Y')
        : null;
    $studentActionLabels = [
        'complaint_submitted' => 'Ticket submitted',
        'ticket_assigned' => 'Ticket assigned',
        'ticket_acknowledged' => 'Office started handling the ticket',
        'ticket_resolved' => 'Ticket resolved',
        'complaint_resolved' => 'Ticket resolved',
        'ticket_closed' => 'Ticket closed',
        'complaint_closed' => 'Ticket closed',
        'ticket_closed_invalid' => 'Ticket closed',
        'ticket_escalated' => 'Ticket escalated',
        'complaint_rejected' => 'Ticket closed',
        'clarification_requested' => 'SDS asked for clarification',
        'ticket_referred' => 'Ticket referred to a committee',
        'referral_outcome_recorded' => 'Committee outcome recorded',
        'ticket_reopened_from_resolved' => 'Ticket reopened',
        'message_posted' => 'Message posted',
    ];
    $studentTimeline = $studentView
        ? $ticket->auditLogs
            ->whereIn('action', \App\Services\TicketUnreadService::STUDENT_CHANGE_ACTIONS)
            ->filter(fn ($log) => isset($studentActionLabels[$log->action])
                && ($log->action !== 'message_posted' || $log->performed_by !== \Illuminate\Support\Facades\Auth::id()))
            ->sortBy(fn ($log) => [$log->created_at?->timestamp ?? 0, $log->id])
            ->values()
        : collect();
    $lastStudentEvent = $studentTimeline->last();
    $studentLastUpdatedAt = $lastStudentEvent?->created_at;
    $currentOffice = match (true) {
        $showForwardedInformational => $ticket->forwardedRecipient?->unit ?: 'Receiving office',
        $ticket->status === \App\Models\Ticket::STATUS_REFERRED => $ticket->referred_to ?: 'Committee',
        ($ticket->currentHandler ?? $ticket->assignee)?->isRecipient() => ($ticket->currentHandler ?? $ticket->assignee)?->recipient?->unit ?: 'Receiving office',
        default => 'SDS Office',
    };
    $waitingFor = match ($ticket->status) {
        \App\Models\Ticket::STATUS_SUBMITTED => $showForwardedInformational
            ? 'Forwarded for information'
            : 'Waiting for SDS review',
        \App\Models\Ticket::STATUS_NEEDS_CLARIFICATION => 'Waiting for your reply',
        \App\Models\Ticket::STATUS_ASSIGNED => "Waiting for {$currentOffice} to acknowledge",
        \App\Models\Ticket::STATUS_IN_PROGRESS => "Being handled by {$currentOffice}",
        \App\Models\Ticket::STATUS_ESCALATED => "Escalated to {$currentOffice}",
        \App\Models\Ticket::STATUS_REFERRED => 'Referred to a committee. SDS will record the outcome',
        \App\Models\Ticket::STATUS_RESOLVED => 'Waiting for your response. You can accept or ask for further action until ' . ($ticket->furtherActionDeadline()?->copy()->setTimezone($displayTimezone)->format('M d, Y') ?? 'the response window ends'),
        \App\Models\Ticket::STATUS_CLOSED => 'Closed: ' . ($ticket->closure_label ?? 'Closed'),
        default => $ticket->status_label,
    };
@endphp

<div class="surface p-4 sm:p-6" x-data="{ showAllAttachments: false, editingDetails: false, attachmentPreview: null }">
    <div class="flex items-center justify-between gap-3">
        <p class="inline-flex min-w-0 items-center gap-1.5 font-mono text-xs font-semibold leading-tight text-primary">
            <x-icons.ticket class="h-4 w-4 shrink-0 text-primary" />
            <span class="truncate">
                {{ $complaint?->reference_number ?? $ticket->id }}
            </span>
        </p>
        <div class="flex shrink-0 flex-col items-end gap-1">
            @if ($statusDisplay !== null)
                <x-status-badge :status="$statusDisplay" :classification="$ticket->classification" :show-icon="false" class="px-3 py-1 text-xs" />
            @endif
            @if ($showForwardedInformational)
                <p class="text-right text-[10px] text-muted-foreground">
                    {{ $forwardedOffice ?: 'Receiving office' }} · {{ $forwardedDate }}
                </p>
            @endif
            @if ($canEditDetails)
                <button type="button" x-show="!editingDetails" x-on:click="editingDetails = true" aria-label="Edit ticket details" class="inline-flex shrink-0 items-center gap-1 rounded-full border border-primary px-2.5 py-1 text-[11px] font-semibold text-primary transition-colors hover:bg-primary-soft">
                    <x-icons.pencil class="h-3 w-3" />
                    Edit
                </button>
            @endif
        </div>
    </div>

    <div class="mt-2">
        <h2 class="min-w-0 truncate font-display text-[15px] font-bold leading-tight sm:text-base" title="{{ $studentView ? ($complaint?->subject_title ?? 'Untitled') : ($complaint?->public_subject ?? 'Untitled') }}">
            {{ $studentView ? ($complaint?->subject_title ?? 'Untitled') : ($complaint?->public_subject ?? 'Untitled') }}
        </h2>
    </div>
    <div class="mt-1">
        <p class="text-sm leading-relaxed whitespace-pre-line text-foreground">{{ trim($complaint?->description ?? 'No description provided') }}</p>
    </div>

    <div class="mt-2">
    <div class="grid gap-y-2">
        <div class="grid grid-cols-[7rem_minmax(0,1fr)] items-baseline gap-2">
            <p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground">Category</p>
            <p class="min-w-0 text-sm font-medium leading-tight wrap-break-word">{{ $complaint?->category?->name ?? 'Uncategorized' }}</p>
        </div>
        <div class="grid grid-cols-[7rem_minmax(0,1fr)] items-baseline gap-2">
            <p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground">{{ $assignedRecipient ? 'Assigned Recipient' : ($assignedUser ? 'Assigned To' : 'Suggested Recipient') }}</p>
            <p class="min-w-0 text-sm font-medium leading-tight wrap-break-word">
                @if ($assignedUser && $assignedRecipient)
                    <span class="relative" x-data="{ holderProfileOpen: false }" x-on:click.outside="holderProfileOpen = false">
                        <button type="button" class="text-left text-primary hover:underline" x-on:click="holderProfileOpen = !holderProfileOpen" aria-label="View {{ $assignedUser->table_name }} profile">
                            {{ $assignedUser->table_name }}
                        </button>
                        <span x-show="holderProfileOpen" x-cloak data-keep-in-view class="brand-gradient absolute top-7 left-0 z-30 w-72 max-w-[calc(100vw-2rem)] rounded-xl p-4 text-primary-foreground shadow-lg">
                            <span class="flex items-center gap-3">
                                <span class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-foreground/15 text-sm font-bold ring-2 ring-primary-foreground/30">
                                    @if ($assignedUser->avatar_path)
                                        <img src="{{ asset('storage/' . $assignedUser->avatar_path) }}" alt="{{ $assignedUser->table_name }}" class="h-full w-full object-cover">
                                    @else
                                        {{ $assignedUser->name_initials }}
                                    @endif
                                </span>
                                <span class="min-w-0">
                                    <span class="block wrap-break-word text-sm font-bold">{{ $assignedUser->table_name }}</span>
                                    <span class="mt-1 block wrap-break-word text-[11px] leading-relaxed opacity-95">ID {{ $assignedRecipient->staff_id }} · {{ $assignedRecipient->unit }} · {{ $assignedRecipient->designation }}</span>
                                </span>
                            </span>
                            <span class="mt-3 inline-flex rounded-full border border-primary-foreground/40 bg-primary-foreground/15 px-3 py-1 text-[10px] font-semibold">{{ $assignedUser->isSdsAdmin() ? 'Admin' : 'Recipient' }}</span>
                        </span>
                    </span>
                @elseif ($assignedUser)
                    {{ $assignedUser->table_name }}
                @else
                    {{ $suggestedRecipientName ?? 'None' }}
                @endif
            </p>
        </div>
        <div class="grid grid-cols-[7rem_minmax(0,1fr)] items-baseline gap-2">
            <p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground">Classification</p>
            <p class="min-w-0 text-sm font-medium leading-tight wrap-break-word">
                {{ $ticket->classification ? ucwords(str_replace('_', ' ', $ticket->classification)) : 'Not yet classified' }}
            </p>
        </div>
        <div class="grid grid-cols-[7rem_minmax(0,1fr)] items-baseline gap-2">
            <p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground">{{ $studentView ? 'Last updated' : 'Last Updated At' }}</p>
            <p class="min-w-0 text-sm font-medium leading-tight wrap-break-word">{{ ($studentView ? $studentLastUpdatedAt : ($ticket->updated_at ?? $complaint?->updated_at))?->copy()->setTimezone($displayTimezone)->format($studentView ? 'M d, Y' : 'M d, Y · g:i A') ?? 'Unknown' }}</p>
        </div>
        @if ($studentView)
            <div class="grid grid-cols-[7rem_minmax(0,1fr)] items-baseline gap-2">
                <p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground">Current office</p>
                <p class="min-w-0 text-sm font-medium leading-tight wrap-break-word">{{ $currentOffice }}</p>
            </div>
            @if ($ticket->displayStatus() !== null)
                <div class="grid grid-cols-[7rem_minmax(0,1fr)] items-baseline gap-2">
                    <p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground">Waiting for</p>
                    <p class="min-w-0 text-sm font-medium leading-tight wrap-break-word">{{ $waitingFor }}</p>
                </div>
            @endif
        @endif
        <div class="grid grid-cols-[7rem_minmax(0,1fr)] items-baseline gap-2">
            <p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground">Created At</p>
            <p class="min-w-0 text-sm font-medium leading-tight wrap-break-word">{{ $submittedAt?->format('M d, Y · g:i A') ?? 'Unknown' }}</p>
        </div>
        @if ($ticket->escalated_at)
            <div class="grid grid-cols-[7rem_minmax(0,1fr)] items-baseline gap-2">
                <p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground">Escalated At</p>
                <p class="min-w-0 text-sm font-medium leading-tight wrap-break-word">{{ $ticket->escalated_at->copy()->setTimezone($displayTimezone)->format('M d, Y · g:i A') }}</p>
            </div>
        @endif
        <div class="grid grid-cols-[7rem_minmax(0,1fr)] items-baseline gap-2">
            <p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground">Created By</p>
            <p class="min-w-0 text-sm font-medium leading-tight wrap-break-word">
                @if ($creator)
                    <span class="relative inline-block" x-data="{ creatorProfileOpen: false }" x-on:click.outside="creatorProfileOpen = false">
                        <button type="button" class="text-left text-primary hover:underline" x-on:click="creatorProfileOpen = !creatorProfileOpen" aria-label="View {{ $createdBy }} profile">
                            {{ $createdBy }}
                        </button>
                        <span x-show="creatorProfileOpen" x-cloak data-keep-in-view class="brand-gradient absolute top-7 left-0 z-30 w-72 max-w-[calc(100vw-2rem)] rounded-xl p-4 text-primary-foreground shadow-lg">
                            <span class="flex items-center gap-3">
                                <span class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-foreground/15 text-sm font-bold ring-2 ring-primary-foreground/30">
                                    @if ($creator->avatar_path)
                                        <img src="{{ asset('storage/' . $creator->avatar_path) }}" alt="{{ $createdBy }}" class="h-full w-full object-cover">
                                    @else
                                        {{ $creator->name_initials }}
                                    @endif
                                </span>
                                <span class="min-w-0">
                                    <span class="block wrap-break-word text-sm font-bold">{{ $createdBy }}</span>
                                    <span class="mt-1 block wrap-break-word text-[11px] leading-relaxed opacity-95">ID {{ $creatorStudent?->student_id ?? 'N/A' }} · {{ $creatorStudent?->college ?? 'College not specified' }} · {{ trim(($creatorStudent?->program ?? 'Program not specified') . ' ' . ($creatorStudent?->year_level ?? 'N/A') . (($creatorStudent?->block ?? '') !== '' ? '-' . $creatorStudent->block : '')) }}</span>
                                </span>
                            </span>
                            <span class="mt-3 inline-flex rounded-full border border-primary-foreground/40 bg-primary-foreground/15 px-3 py-1 text-[10px] font-semibold">Student</span>
                        </span>
                    </span>
                @else
                    {{ $createdBy }}
                @endif
            </p>
        </div>
        <div class="grid grid-cols-[7rem_minmax(0,1fr)] items-baseline gap-2">
            <p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground">Days Open</p>
            <p class="min-w-0 text-sm font-medium leading-tight wrap-break-word" data-ticket-days-open>{{ $ticket->daysOpen() }} {{ \Illuminate\Support\Str::plural('working day', $ticket->daysOpen()) }}</p>
        </div>
    </div>

    @if ($studentView && $studentTimeline->isNotEmpty())
        <section class="mt-4 border-t border-border pt-4" aria-label="Ticket timeline">
            <h3 class="text-sm font-semibold text-foreground">Timeline</h3>
            <ol class="mt-2 grid gap-2" data-student-ticket-timeline>
                @foreach ($studentTimeline as $event)
                    <li class="flex min-w-0 gap-2">
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary"></span>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-foreground">{{ $studentActionLabels[$event->action] }}</p>
                            <p class="text-xs text-muted-foreground">{{ $event->created_at?->copy()->setTimezone($displayTimezone)->format('M d, Y · g:i A') }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    @unless ($studentView)
        @if ($complaint?->isSensitive())
            <div class="mt-4 rounded-lg border border-primary/30 bg-primary-soft px-3 py-2 text-xs text-primary" data-ticket-notice="sensitive">
                <p class="font-semibold">Sensitive ticket</p>
                <p class="mt-0.5">Only the SDS Office and the assigned handler can open this ticket. Do not share its details with anyone else.</p>
            </div>
        @endif
        @if ($complaint?->is_anonymous)
            <div class="mt-3 rounded-lg border border-border bg-muted px-3 py-2 text-xs text-muted-foreground" data-ticket-notice="hidden-identity">
                <p class="font-semibold text-foreground">Identity hidden</p>
                <p class="mt-0.5">The student chose not to show their name. You can still message them in the conversation and resolve the ticket as usual.</p>
            </div>
        @endif
        @if ($holder && ! $ticket->isAwaitingReview() && $complaint?->names($holder))
            <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800" data-ticket-notice="conflict">
                <p class="font-semibold">Possible conflict of interest</p>
                <p class="mt-0.5">{{ $holderDisplayName }} appears to be the person named in this complaint. The SDS Office should assign it to someone else.</p>
            </div>
        @endif
    @endunless

    @if ($canEditDetails)
        <form x-show="editingDetails" x-cloak method="POST" action="{{ route('admin.tickets.update-details', $ticket) }}" class="mt-3 grid gap-2 rounded-lg border border-border bg-muted/50 p-3">
            @csrf
            @method('PATCH')
            <p class="text-xs text-muted-foreground">Correct these if the student picked the wrong category or recipient.</p>
            <div class="text-xs font-semibold leading-tight text-muted-foreground">
                Category
                <x-picker name="category_id" required label="Category" class="mt-1" :selected="$complaint->category_id" :options="$categories->map(fn ($categoryOption) => ['value' => $categoryOption->id, 'label' => $categoryOption->name])->all()" />
            </div>
            <div class="text-xs font-semibold leading-tight text-muted-foreground">
                Suggested recipient
                <x-picker name="suggested_recipient_id" label="Suggested recipient" class="mt-1" placeholder="None" :selected="$complaint->suggested_recipient_id ?? ''" :options="collect([['value' => '', 'label' => 'None']])->merge($recipients->map(fn ($recipientOption) => ['value' => $recipientOption->id, 'label' => $recipientOption->user?->table_name ?? 'Recipient', 'detail' => trim($recipientOption->designation . ', ' . $recipientOption->unit, ', ')]))->all()" />
            </div>
            <div class="mt-1 grid grid-cols-2 gap-2">
                <button type="button" x-on:click="editingDetails = false" class="w-full rounded-full border border-primary bg-white px-2.5 py-1.5 text-center text-xs font-semibold text-primary transition-colors hover:bg-primary-soft">Cancel</button>
                <button type="submit" class="w-full rounded-full border border-primary bg-primary px-2.5 py-1.5 text-center text-xs font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Save Changes</button>
            </div>
        </form>
    @endif

    @if (count($attachments))
        <div class="mt-4">
                <div class="flex items-center justify-between gap-3">
                    <p class="flex items-center gap-2 text-sm font-semibold text-foreground">
                        <x-icons.paperclip class="h-4 w-4 shrink-0 text-muted-foreground" />
                        Attachments ({{ count($attachments) }})
                    </p>
                    @if (count($attachments) > 2)
                        <button type="button" x-on:click="showAllAttachments = !showAllAttachments" x-text="showAllAttachments ? 'Hide' : 'Show all'" class="shrink-0 text-xs font-medium text-muted-foreground underline-offset-2 hover:text-primary hover:underline"></button>
                    @endif
                </div>
                <div class="mt-2 grid gap-2">
                    @foreach ($attachments as $index => $attachment)
                        @php
                            $attachmentName = $attachment['name'] ?? basename($attachment['path'] ?? '');
                            $attachmentExtension = strtoupper(pathinfo($attachmentName, PATHINFO_EXTENSION));
                            $isImageAttachment = in_array(strtolower($attachmentExtension), ['jpg', 'jpeg', 'png', 'heic']);
                            $attachmentSize = $complaint->attachmentFileSize($attachment);
                        @endphp
                        <div @if ($index >= 2) x-show="showAllAttachments" x-cloak @endif class="flex min-w-0 items-center justify-between gap-3 py-1">
                            <button type="button" x-on:click="attachmentPreview = { name: @js($attachmentName), url: @js($attachment['url']), type: @js(strtolower($attachmentExtension)) }" class="flex min-w-0 items-center gap-3 text-left hover:text-primary">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-muted text-primary">
                                    @if ($isImageAttachment)
                                        <x-icons.image class="h-5 w-5" />
                                    @else
                                        <x-icons.file-text class="h-5 w-5" />
                                    @endif
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-foreground">{{ $attachmentName }}</span>
                                    <span class="block text-xs text-muted-foreground">
                                        {{ $attachmentSize !== null ? number_format($attachmentSize / 1048576, 2) . ' MB' : 'Size unavailable' }}
                                    </span>
                                </span>
                            </button>
                            <a href="{{ $attachment['url'] }}" download class="inline-flex shrink-0 items-center gap-1 text-xs font-medium text-primary hover:underline">
                                <x-icons.download class="h-3.5 w-3.5" />
                                Download
                            </a>
                        </div>
                    @endforeach
                </div>
        </div>
    @endif

    <x-ticket-resolution-panel :ticket="$ticket" :show-closed-at="$showClosedAt" />

    <div x-show="attachmentPreview" x-cloak x-on:click.self="attachmentPreview = null" x-on:keydown.escape.window="attachmentPreview = null" class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-3 sm:p-6" role="dialog" aria-modal="true" aria-label="Attachment preview">
        <div class="flex max-h-full w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
            <div class="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
                <p class="min-w-0 truncate text-sm font-semibold text-foreground" x-text="attachmentPreview?.name"></p>
                <button type="button" x-on:click="attachmentPreview = null" aria-label="Close attachment preview" class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground">
                    <x-icons.x class="h-5 w-5" />
                </button>
            </div>
            <div class="min-h-0 flex-1 overflow-auto bg-muted/40 p-3 sm:p-5">
                <template x-if="['jpg', 'jpeg', 'png'].includes(attachmentPreview?.type)">
                    <img :src="attachmentPreview.url + '?preview=1'" :alt="attachmentPreview.name" class="mx-auto max-h-[75vh] max-w-full rounded-md object-contain">
                </template>
                <template x-if="attachmentPreview?.type === 'pdf'">
                    <iframe :src="attachmentPreview.url + '?preview=1'" :title="attachmentPreview.name" class="h-[75vh] w-full rounded-md bg-white"></iframe>
                </template>
                <template x-if="!['jpg', 'jpeg', 'png', 'pdf'].includes(attachmentPreview?.type)">
                    <div class="mx-auto flex min-h-48 max-w-lg flex-col items-center justify-center gap-3 text-center">
                        <x-icons.file-text class="h-10 w-10 text-primary" />
                        <p class="text-sm font-semibold text-foreground">Preview isn’t available in this browser</p>
                        <p class="text-sm text-muted-foreground">Download the file to open it with Word or another app available on your device. Ticket attachments stay private and aren’t sent to an external document service.</p>
                        <a :href="attachmentPreview.url" :download="attachmentPreview.name" class="inline-flex items-center gap-2 rounded-full bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90">
                            <x-icons.download class="h-4 w-4" />
                            Download file
                        </a>
                    </div>
                </template>
            </div>
        </div>
    </div>
    </div>
</div>
