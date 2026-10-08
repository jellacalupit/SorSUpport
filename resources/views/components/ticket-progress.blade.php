@props(['ticket'])

{{-- Tells the student, in plain words, who their ticket is waiting on and for how long, and
     lists each step so far with its date. Staff notes are never shown here. --}}

@php
    $ticket->loadMissing(['auditLogs', 'assignee.recipient', 'currentHandler.recipient', 'complaint']);
    $waitingLine = \App\Support\TicketProgress::waitingLine($ticket, forStudent: true);
    $steps = \App\Support\TicketProgress::studentSteps($ticket);
@endphp

<section {{ $attributes->merge(['class' => 'surface p-4']) }} data-ticket-progress>
    <p class="text-xs font-semibold leading-tight tracking-wide text-muted-foreground uppercase">Where your ticket is</p>
    @if ($waitingLine)
        <p class="mt-1 text-sm font-semibold leading-snug text-primary wrap-break-word" data-ticket-waiting>{{ $waitingLine }}</p>
    @else
        <p class="mt-1 text-sm font-semibold leading-snug text-foreground" data-ticket-waiting>Closed after {{ $ticket->daysOpen() }} {{ \Illuminate\Support\Str::plural('day', $ticket->daysOpen()) }}</p>
    @endif

    @if ($steps->isNotEmpty())
        <ol class="mt-3 grid gap-2.5">
            @foreach ($steps as $step)
                <li class="relative grid grid-cols-[0.75rem_minmax(0,1fr)] gap-2.5">
                    @unless ($loop->last)
                        <span class="absolute top-3 -bottom-2.5 left-[0.3rem] w-px bg-primary/25" aria-hidden="true"></span>
                    @endunless
                    <span class="relative mt-1 h-2.5 w-2.5 rounded-full {{ $loop->last ? 'bg-primary' : 'border border-primary bg-white' }}" aria-hidden="true"></span>
                    <div class="min-w-0">
                        <p class="text-xs leading-snug text-foreground wrap-break-word {{ $loop->last ? 'font-semibold' : '' }}">{{ $step['label'] }}</p>
                        <p class="text-[11px] leading-snug text-muted-foreground">{{ $step['at']->copy()->setTimezone('Asia/Manila')->format('M j, Y · g:i A') }}@if ($step['gap']) · {{ $step['gap'] }}@endif</p>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</section>
