@props([
    'ticket',
    // Pending review: allow correcting the category and suggested recipient.
    'editable' => false,
    'categories' => [],
    'recipients' => [],
    // Resolved review: show the "Not yet Resolved" / "Close Ticket" buttons under the audit activity.
    'reviewActions' => false,
])

{{--
    The inside of an admin ticket drawer, laid out like the student and recipient ticket page:
    details first, then the description, then the last five audit entries and any actions.
    Tickets with a conversation get a Details / Thread switch below desktop width.
    A ticket still waiting for review has no conversation or audit yet, so whatever is passed
    in the slot (the review steps) simply continues below the details.
--}}

@php
    $isPendingReview = $ticket->status === 'pending' && $ticket->classification === null;
    $hasThread = ! $isPendingReview && $ticket->classification === 'needs_resolution';
    $hasSideColumn = $hasThread || $slot->hasActualContent();
    $auditLogs = $isPendingReview
        ? collect()
        : $ticket->auditLogs()->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get();
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
    ];
@endphp

<div x-data="{ tab: 'details' }" class="grid gap-3">
    @if ($hasThread)
        <div class="flex justify-center lg:hidden">
            <div class="inline-flex rounded-full border border-border bg-muted p-0.5" role="tablist" aria-label="Ticket sections">
                <button type="button" role="tab" x-bind:aria-selected="tab === 'details'" x-on:click="tab = 'details'" class="rounded-full px-3.5 py-1.5 text-xs font-semibold transition-colors" x-bind:class="tab === 'details' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground'">Details</button>
                <button type="button" role="tab" x-bind:aria-selected="tab === 'thread'" x-on:click="tab = 'thread'; $nextTick(() => { const list = $root.querySelector('[data-ticket-message-list]'); if (list) list.scrollTop = list.scrollHeight; })" class="rounded-full px-3.5 py-1.5 text-xs font-semibold transition-colors" x-bind:class="tab === 'thread' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground'">Thread</button>
            </div>
        </div>
    @endif

    <div class="grid gap-3 {{ $hasSideColumn ? 'lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] lg:items-start lg:gap-4' : '' }}">
        <!-- Details, audit activity and actions -->
        <div class="min-w-0 lg:block!" x-show="tab === 'details'">
            <div class="grid gap-3">
                <x-ticket-info-panel :ticket="$ticket" :show-filed-by="true" :show-closed-at="true" :editable="$editable" :categories="$categories" :recipients="$recipients" />

                @unless ($isPendingReview)
                    <div class="surface p-4">
                        <h3 class="font-display text-sm font-semibold leading-tight text-foreground">Audit Activity</h3>
                        <ol class="mt-2 grid gap-2">
                            @forelse ($auditLogs as $log)
                                <li class="flex min-w-0 gap-2">
                                    <span class="mt-0.5 h-8 w-0.5 shrink-0 rounded-full bg-[#800000]"></span>
                                    <div class="mt-0.5 min-w-0 flex-1">
                                        <p class="truncate text-xs font-semibold leading-tight text-foreground">{{ $auditLabels[$log->action] ?? ucfirst(str_replace('_', ' ', (string) ($log->action ?? 'Action'))) }}</p>
                                        <p class="mt-0.5 truncate text-[11px] leading-snug text-muted-foreground">
                                            <span>{{ $log->performer?->table_name ?? 'System' }}</span>
                                            <span class="text-border"> · </span>
                                            <span>{{ $log->created_at?->copy()->setTimezone('Asia/Manila')->format('M d, Y · g:i A') }}</span>
                                        </p>
                                    </div>
                                </li>
                            @empty
                                <li class="py-3 text-center text-xs text-muted-foreground">No recent audit activity.</li>
                            @endforelse
                        </ol>
                    </div>

                    <x-ticket-escalate-form :ticket="$ticket" class="surface p-4" />

                    @if ($reviewActions && $ticket->status === 'resolved')
                        <div class="grid grid-cols-2 gap-2">
                            <form method="POST" action="{{ route('admin.tickets.not-yet-resolved', $ticket) }}">
                                @csrf
                                <button type="submit" class="w-full rounded-full border border-primary bg-white px-2.5 py-2 text-center text-xs font-semibold text-primary transition-colors hover:bg-primary-soft">Not yet Resolved</button>
                            </form>
                            <form method="POST" action="{{ route('admin.tickets.close', $ticket) }}">
                                @csrf
                                <button type="submit" class="w-full rounded-full border border-primary bg-primary px-2.5 py-2 text-center text-xs font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Close Ticket</button>
                            </form>
                        </div>
                    @endif
                @endunless
            </div>
        </div>

        @if ($hasThread)
            <!-- Conversation -->
            <div class="ticket-thread-pane admin-ticket-thread min-w-0 lg:sticky lg:top-[4.5rem] lg:block!" x-show="tab === 'thread'" x-cloak>
                <x-ticket-thread :ticket="$ticket" viewerRole="admin" />
            </div>
        @elseif ($slot->hasActualContent())
            <div class="min-w-0">
                {{ $slot }}
            </div>
        @endif
    </div>
</div>
