@props([
    'ticket',
    'showClosedAt' => false,
])

@php
    $showResolutionDetails = in_array($ticket->status, ['resolved', 'closed'], true);
    $resolutionLog = $showResolutionDetails
        ? $ticket->auditLogs()
            ->whereIn('action', ['complaint_resolved', 'referral_outcome_recorded'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first()
        : null;
@endphp

@if ($showResolutionDetails || $ticket->referred_to)
    <div class="surface p-4 sm:p-6">
        @if ($ticket->referred_to)
            <div>
                <p class="text-sm font-semibold text-foreground">Referral</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ $ticket->referred_to }}@if ($ticket->referred_at) · {{ $ticket->referred_at->copy()->setTimezone('Asia/Manila')->format('M d, Y · g:i A') }}@endif
                </p>
            </div>
        @endif

        @if ($showResolutionDetails)
            <div class="{{ $ticket->referred_to ? 'mt-4 border-t border-border pt-3' : '' }}">
                <p class="text-sm font-semibold text-foreground">Resolution and Feedback</p>
                <div class="mt-2 grid gap-y-1.5">
                    @if ($ticket->resolution_label)
                        <div class="grid grid-cols-[7rem_minmax(0,1fr)] items-baseline gap-2">
                            <p class="text-xs font-semibold text-muted-foreground">Resolution</p>
                            <p class="text-sm font-medium text-foreground">{{ $ticket->resolution_label }}</p>
                        </div>
                    @endif
                    @if (filled($resolutionLog?->details))
                        <div class="grid grid-cols-[7rem_minmax(0,1fr)] items-baseline gap-2">
                            <p class="text-xs font-semibold text-muted-foreground">Resolution Details</p>
                            <p class="text-sm font-medium leading-tight whitespace-pre-line text-foreground">{{ $resolutionLog->details }}</p>
                        </div>
                    @endif
                    @if ($ticket->closure_label)
                        <div class="grid grid-cols-[7rem_minmax(0,1fr)] items-baseline gap-2">
                            <p class="text-xs font-semibold text-muted-foreground">Closure</p>
                            <p class="text-sm font-medium text-foreground">{{ $ticket->closure_label }}</p>
                        </div>
                    @endif
                    @if (filled($ticket->closure_reason))
                        <div class="grid grid-cols-[7rem_minmax(0,1fr)] items-baseline gap-2">
                            <p class="text-xs font-semibold text-muted-foreground">Closure Details</p>
                            <p class="text-sm font-medium leading-tight whitespace-pre-line text-foreground">{{ $ticket->closure_reason }}</p>
                        </div>
                    @endif
                    @if ($ticket->resolved_at)
                        <div class="grid grid-cols-[7rem_minmax(0,1fr)] items-baseline gap-2">
                            <p class="text-xs font-semibold text-muted-foreground">Resolved At</p>
                            <p class="text-sm font-medium text-foreground">{{ $ticket->resolved_at->copy()->setTimezone('Asia/Manila')->format('M d, Y · g:i A') }}</p>
                        </div>
                    @endif
                    @if ($showClosedAt && $ticket->status === 'closed' && $ticket->closed_at)
                        <div class="grid grid-cols-[7rem_minmax(0,1fr)] items-baseline gap-2">
                            <p class="text-xs font-semibold text-muted-foreground">Closed At</p>
                            <p class="text-sm font-medium text-foreground">{{ $ticket->closed_at->copy()->setTimezone('Asia/Manila')->format('M d, Y · g:i A') }}</p>
                        </div>
                    @endif
                    @if ($ticket->satisfaction_rating !== null)
                        <div class="grid grid-cols-[7rem_minmax(0,1fr)] items-baseline gap-2">
                            <p class="text-xs font-semibold text-muted-foreground">Feedback Rate</p>
                            <p class="text-sm font-medium text-foreground" data-ticket-rating>{{ $ticket->satisfaction_rating }} out of 5</p>
                        </div>
                    @endif
                    @if (filled($ticket->satisfaction_comment))
                        <div class="grid grid-cols-[7rem_minmax(0,1fr)] items-baseline gap-2">
                            <p class="text-xs font-semibold text-muted-foreground">Feedback Comment</p>
                            <p class="text-sm font-medium leading-tight whitespace-pre-line text-foreground">{{ $ticket->satisfaction_comment }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endif
