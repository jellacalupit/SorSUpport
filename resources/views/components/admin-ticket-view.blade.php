@props([
    'ticket',
    'backUrl',
    // For correcting the category and suggested recipient of a ticket awaiting review.
    'categories' => [],
    'recipients' => [],
])

{{--
    The admin ticket page, used below desktop width (on desktop the lists open tickets in their
    side panels). Laid out like the student and recipient one: a Back link, details,
    description, then the last five audit entries and the actions for the ticket's current state
    (on desktop the audit entries sit under the conversation, in the second column).
    Tickets with a conversation get a Details / Thread switch below desktop width.
    A ticket still waiting for review has no conversation or audit yet, so the review steps
    simply continue below the details.
--}}

@php
    $isPendingReview = $ticket->status === 'pending' && $ticket->classification === null;
    $hasThread = ! $isPendingReview && $ticket->classification === 'needs_resolution';
    $isActive = $hasThread && in_array($ticket->status, ['assigned', 'in_progress', 'escalated'], true);
    $isMine = (int) ($ticket->current_handler_id ?: $ticket->assigned_to) === (int) Auth::id();
    $canEscalate = $isActive;
    $canClose = $isActive && $isMine;
    $awaitingClosure = $ticket->status === 'resolved';
    $hasSideColumn = $hasThread || $isPendingReview;
    $auditLogs = $isPendingReview
        ? collect()
        : $ticket->auditLogs()->with('performer')->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get();
    $auditLabels = [
        'ticket_assigned' => 'Ticket assigned',
        'ticket_started' => 'Ticket started',
        'message_posted' => 'Message posted',
        'ticket_resolved' => 'Ticket resolved',
        'ticket_escalated' => 'Ticket escalated',
        'ticket_classified' => 'Ticket classified',
        'ticket_closed' => 'Ticket closed',
        'status_updated' => 'Status updated',
        'classification_changed' => 'Classification changed',
        'ticket_details_updated' => 'Details corrected',
    ];
@endphp

<div x-data="{ tab: window.location.hash === '#in-ticket-communication' ? 'thread' : 'details', action: @js($errors->has('recipient_id') ? 'escalate' : null) }">
    <!-- Back link and section switch -->
    <div data-ticket-back-row class="mb-2 grid h-9 grid-cols-[1fr_auto_1fr] items-center gap-2">
        <div class="justify-self-start">
            <a href="{{ $backUrl }}" aria-label="Back" onclick="event.preventDefault(); if (window.history.length > 1) { window.history.back(); } else { window.location.href = '{{ $backUrl }}'; }" class="inline-flex items-center gap-1 rounded-md px-1.5 py-1 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground sm:text-base">
                <span aria-hidden="true" class="scale-y-150 text-lg leading-none">&lt;</span>
                Back
            </a>
        </div>
        @if ($hasThread)
            <div class="inline-flex justify-self-center rounded-full border border-border bg-muted p-0.5 lg:hidden" role="tablist" aria-label="Ticket sections">
                <button type="button" role="tab" x-bind:aria-selected="tab === 'details'" x-on:click="tab = 'details'" class="rounded-full px-3.5 py-1.5 text-xs font-semibold transition-colors" x-bind:class="tab === 'details' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground'">Details</button>
                <button type="button" role="tab" x-bind:aria-selected="tab === 'thread'" x-on:click="tab = 'thread'; $nextTick(() => { const list = $root.querySelector('[data-ticket-message-list]'); if (list) list.scrollTop = list.scrollHeight; })" class="rounded-full px-3.5 py-1.5 text-xs font-semibold transition-colors" x-bind:class="tab === 'thread' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground'">Thread</button>
            </div>
        @endif
    </div>

    <div class="grid gap-3 {{ $hasSideColumn ? 'lg:grid-cols-2 lg:items-start lg:gap-5' : 'mx-auto max-w-3xl' }}">
        <!-- Details, audit activity and actions -->
        <div class="min-w-0 lg:block!" x-show="tab === 'details'">
            <h2 class="mb-0.5 hidden font-display text-base font-bold lg:block">Ticket Details</h2>
            <div class="grid gap-3">
                <x-ticket-info-panel :ticket="$ticket" :show-filed-by="true" :show-closed-at="true" :editable="true" :categories="$categories" :recipients="$recipients" />

                @unless ($isPendingReview)
                    {{-- With a conversation, desktop shows this under the conversation instead. --}}
                    @include('admin.tickets.partials.audit-activity', ['class' => $hasThread ? 'lg:hidden' : ''])

                    @if ($awaitingClosure)
                        <!-- Resolved by the holder: confirm it or send it back -->
                        <div class="grid grid-cols-2 gap-2">
                            <form method="POST" action="{{ route('admin.tickets.not-yet-resolved', $ticket) }}">
                                @csrf
                                <button type="submit" class="h-10 w-full rounded-full border border-primary bg-white px-3 text-center text-sm font-semibold text-primary transition-colors hover:bg-primary-soft">Not yet Resolved</button>
                            </form>
                            <form method="POST" action="{{ route('admin.tickets.close', $ticket) }}">
                                @csrf
                                <button type="submit" class="h-10 w-full rounded-full border border-primary bg-primary px-3 text-center text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Close Ticket</button>
                            </form>
                        </div>
                    @elseif ($canEscalate || $canClose)
                        <!-- Active ticket: escalate it, and close it when the admin is the one handling it -->
                        <div>
                            <div class="grid gap-2 {{ $canClose ? 'grid-cols-2' : '' }}" x-show="action === null">
                                <button type="button" x-on:click="action = 'escalate'; $nextTick(() => $refs.escalatePanel.scrollIntoView({ block: 'nearest' }))" class="inline-flex h-10 w-full items-center justify-center gap-1.5 rounded-full border border-red-800 bg-white px-3 text-sm font-semibold text-red-800 transition-colors hover:bg-red-50">
                                    <x-icons.alert-triangle class="h-4 w-4" />
                                    Escalate
                                </button>
                                @if ($canClose)
                                    <button type="button" x-on:click="action = 'close'" class="h-10 w-full rounded-full border border-primary bg-primary px-3 text-center text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Close Ticket</button>
                                @endif
                            </div>

                            <div x-ref="escalatePanel" x-show="action === 'escalate'" x-cloak>
                                <x-ticket-escalate-form :ticket="$ticket" :cancellable="true" class="surface p-4" />
                            </div>

                            @if ($canClose)
                                <form x-show="action === 'close'" x-cloak method="POST" action="{{ route('admin.tickets.close', $ticket) }}" class="surface p-4">
                                    @csrf
                                    <p class="text-sm font-semibold text-foreground">Close this ticket?</p>
                                    <p class="mt-0.5 text-xs text-muted-foreground">The student is notified and the conversation is locked. This cannot be undone.</p>
                                    <div class="mt-3 grid grid-cols-2 gap-2">
                                        <button type="button" x-on:click="action = null" class="h-9 w-full rounded-full border border-border bg-white px-3 text-xs font-semibold text-foreground transition-colors hover:bg-muted">Cancel</button>
                                        <button type="submit" class="h-9 w-full rounded-full bg-primary px-3 text-xs font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Yes, close ticket</button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    @endif
                @endunless
            </div>
        </div>

        @if ($hasThread)
            <!-- Conversation -->
            <div class="min-w-0 lg:block!" x-show="tab === 'thread'" x-cloak>
                <div class="ticket-thread-pane admin-page-thread">
                    <x-ticket-thread :ticket="$ticket" viewerRole="admin" />
                </div>
                @include('admin.tickets.partials.audit-activity', ['class' => 'mt-3 hidden lg:block'])
            </div>
        @elseif ($isPendingReview)
            <div class="min-w-0">
                <h2 class="mb-0.5 hidden font-display text-base font-bold lg:block">Review</h2>
                @include('admin.tickets.partials.review-steps', ['ticket' => $ticket])
            </div>
        @endif
    </div>
</div>
