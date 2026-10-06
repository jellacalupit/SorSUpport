<x-app-layout :role="'recipient'" title="Ticket Details">
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php $hasThread = $complaint->ticket->hasConversation(); @endphp
    <div x-data="{ tab: window.location.hash === '#in-ticket-communication' ? 'thread' : 'details' }">
        <!-- Back link, section switch and resolve action -->
        <div class="mb-2 grid h-9 grid-cols-[1fr_auto_1fr] items-center gap-2">
            <div class="justify-self-start">
            <a href="{{ route('recipient.tickets.index') }}" aria-label="Back" onclick="event.preventDefault(); if (window.history.length > 1) { window.history.back(); } else { window.location.href = '{{ route('recipient.tickets.index') }}'; }" class="inline-flex items-center gap-1 rounded-md px-1.5 py-1 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground sm:text-base">
                <span aria-hidden="true" class="scale-y-150 text-lg leading-none">&lt;</span>
                Back
            </a>
            </div>
            @if ($hasThread)
                <div class="inline-flex justify-self-center rounded-full border border-border bg-muted p-0.5 lg:hidden" role="tablist" aria-label="Ticket sections">
                <button type="button" role="tab" x-bind:aria-selected="tab === 'details'" x-on:click="tab = 'details'" class="rounded-full px-3.5 py-1.5 text-xs font-semibold transition-colors" x-bind:class="tab === 'details' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground'">Details</button>
                <button type="button" role="tab" x-bind:aria-selected="tab === 'thread'" x-on:click="tab = 'thread'; $nextTick(() => { const list = $root.querySelector('[data-ticket-message-list]'); if (list) list.scrollTop = list.scrollHeight; })" class="rounded-full px-3.5 py-1.5 text-xs font-semibold transition-colors" x-bind:class="tab === 'thread' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground'">Thread</button>
            </div>
            @else
                <span></span>
            @endif
            <div class="justify-self-end">
            </div>
        </div>

        <div class="grid gap-3 {{ $hasThread ? 'lg:grid-cols-2 lg:items-start lg:gap-5' : '' }}">
            <!-- Ticket Details -->
            <div class="min-w-0 lg:block!" x-show="tab === 'details'">
                <h2 class="mb-0.5 hidden font-display text-base font-bold lg:block">Ticket Details</h2>
                <div class="grid gap-3">
                    @if ((int) $complaint->ticket->assigned_to !== (int) Auth::id())
                        <div class="rounded-lg border border-border bg-muted px-3 py-2 text-xs text-muted-foreground" data-ticket-notice="forwarded">
                            <p class="font-semibold text-foreground">Forwarded for your information</p>
                            <p class="mt-0.5">The SDS Office shared this concern with your office. It is a record only: no reply or action is needed.</p>
                        </div>
                    @endif
                    <x-ticket-info-panel :ticket="$complaint->ticket" :show-filed-by="true" />
                    <x-ticket-actions :ticket="$complaint->ticket" role="recipient" />
                </div>
            </div>

            @if ($hasThread)
                <!-- Ticket Thread -->
                <div class="ticket-thread-pane min-w-0 lg:block!" x-show="tab === 'thread'" x-cloak>
                    <x-ticket-thread :ticket="$complaint->ticket" viewerRole="recipient" />
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
