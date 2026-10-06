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
    $isPendingReview = $ticket->isAwaitingReview();
    $hasConversation = $ticket->hasConversation();
    // Under review the second column holds the review steps, so there is no Details / Thread switch.
    $hasThread = $hasConversation && ! $isPendingReview;
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

<div x-data="{ tab: @js($hasThread) && window.location.hash === '#in-ticket-communication' ? 'thread' : 'details' }">
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

                    <x-ticket-actions :ticket="$ticket" role="admin" />
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
                @if ($hasConversation)
                    <div class="ticket-thread-pane admin-page-thread mt-3">
                        <x-ticket-thread :ticket="$ticket" viewerRole="admin" />
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
