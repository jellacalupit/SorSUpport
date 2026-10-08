@props([
    'ticket',
    'role' => 'student', // 'student', 'recipient' or 'admin'
])

{{--
    Every action the signed-in user can take on a ticket in its current status, as decided by
    App\Services\TicketWorkflow. Simple actions submit straight away; the rest unfold a short
    form. Assigning and the first review of a ticket have their own screens and are not here.
--}}

@php
    use App\Models\Ticket;
    use App\Services\TicketWorkflow;

    $workflow = app(TicketWorkflow::class);
    $available = Auth::user() ? $workflow->availableActions(Auth::user(), $ticket) : [];
    $can = fn (string $action): bool => in_array($action, $available, true);
    $complaint = $ticket->complaint;

    // The first review (valid or not, where it goes) closes or assigns a ticket on its own screen.
    $canClose = $can(TicketWorkflow::CLOSE) && ! $ticket->isAwaitingReview();
    $canEscalate = $role === 'admin' && $can(TicketWorkflow::ESCALATE) && $ticket->classification === Ticket::CLASSIFICATION_NEEDS_RESOLUTION;

    $resolveUrl = $role === 'admin' ? route('admin.tickets.resolve', $ticket) : route('recipient.complaints.update-status', $complaint);
    $acknowledgeUrl = $role === 'admin' ? route('admin.tickets.acknowledge', $ticket) : route('recipient.complaints.acknowledge', $complaint);

    $buttons = collect([
        'clarify' => $role === 'admin' && $can(TicketWorkflow::REQUEST_CLARIFICATION),
        'acknowledge' => $role !== 'student' && $can(TicketWorkflow::ACKNOWLEDGE),
        'resolve' => $role !== 'student' && $can(TicketWorkflow::RESOLVE),
        'escalate' => $canEscalate,
        'refer' => $role === 'admin' && $can(TicketWorkflow::REFER),
        'outcome' => $role === 'admin' && $can(TicketWorkflow::RECORD_OUTCOME),
        'reopen' => $role === 'admin' && $can(TicketWorkflow::REOPEN),
        'close' => $role === 'admin' && $canClose,
        'accept' => $role === 'student' && $can(TicketWorkflow::ACCEPT_RESOLUTION),
        'further' => $role === 'student' && $can(TicketWorkflow::REQUEST_FURTHER_ACTION),
        'withdraw' => $role === 'student' && $can(TicketWorkflow::WITHDRAW),
    ])->filter();

    $fields = ['clarification_message', 'resolution_type', 'resolution_message', 'referred_to', 'referral_note', 'outcome', 'closure_type', 'closure_reason', 'further_action_reason', 'withdraw_reason', 'status'];
    $openPanel = match (true) {
        $errors->hasAny(['clarification_message']) => 'clarify',
        $errors->hasAny(['resolution_type', 'resolution_message']) => 'resolve',
        $errors->hasAny(['referred_to', 'referral_note']) => 'refer',
        $errors->has('outcome') => 'outcome',
        $errors->hasAny(['closure_type', 'closure_reason']) => 'close',
        $errors->has('further_action_reason') => 'further',
        $errors->has('withdraw_reason') => 'withdraw',
        $errors->has('recipient_id') => 'escalate',
        default => null,
    };
    $openPanel = $buttons->has($openPanel) ? $openPanel : null;

    $furtherActionDeadline = $ticket->furtherActionDeadline()?->copy()->setTimezone('Asia/Manila');
    $furtherActionExpired = $ticket->status === Ticket::STATUS_RESOLVED && ! $workflow->withinFurtherActionWindow($ticket);

    $primaryButton = 'h-10 w-full rounded-full border border-primary bg-primary px-3 text-center text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90';
    $outlineButton = 'h-10 w-full rounded-full border border-primary bg-white px-3 text-center text-sm font-semibold text-primary transition-colors hover:bg-primary-soft';
    $smallPrimary = 'h-9 w-full rounded-full bg-primary px-3 text-xs font-semibold text-primary-foreground transition-colors hover:bg-primary/90';
    $smallCancel = 'h-9 w-full rounded-full border border-border bg-white px-3 text-xs font-semibold text-foreground transition-colors hover:bg-muted';
    $input = 'mt-2 w-full rounded-md border border-input bg-white px-3 py-2 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring';
    $panelTitle = 'text-sm font-semibold text-foreground';
    $panelHint = 'mt-0.5 text-xs text-muted-foreground';
@endphp

@if ($role === 'student' && $ticket->status === Ticket::STATUS_NEEDS_CLARIFICATION)
    <div {{ $attributes->merge(['class' => 'rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800']) }} data-ticket-notice="clarification">
        <p class="font-semibold">The SDS Office needs more details</p>
        <p class="mt-0.5">Read their message in the conversation and reply there. Your ticket continues once you answer.</p>
    </div>
@endif

@if ($role === 'student' && $ticket->status === Ticket::STATUS_RESOLVED)
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800" data-ticket-notice="resolved">
        <p class="font-semibold">Your ticket has been resolved</p>
        @if ($furtherActionExpired)
            <p class="mt-0.5">The {{ Ticket::FURTHER_ACTION_DAYS }} days for requesting further action have passed. You can still accept the resolution to close the ticket.</p>
        @else
            <p class="mt-0.5">Accept the resolution to close the ticket, or request further action{{ $furtherActionDeadline ? ' until ' . $furtherActionDeadline->format('M d, Y') : '' }} if the concern is not settled.</p>
        @endif
    </div>
@endif

@if ($role === 'student' && $ticket->canBeRated() && $workflow->isOwner(Auth::user(), $ticket))
    <form method="POST" action="{{ route('student.complaints.rate', $complaint) }}" class="surface p-4" x-data="{ rating: @js((int) old('satisfaction_rating', 0)) }" data-ticket-rating-form>
        @csrf
        <p class="{{ $panelTitle }}">How satisfied are you with how this was handled?</p>
        <p class="{{ $panelHint }}">Your rating helps the SDS Office improve. 1 is very dissatisfied and 5 is very satisfied.</p>
        <input type="hidden" name="satisfaction_rating" x-bind:value="rating || ''">
        <div class="mt-3 flex gap-2" role="radiogroup" aria-label="Satisfaction rating">
            @foreach (range(1, 5) as $value)
                <button type="button" role="radio" x-bind:aria-checked="rating === {{ $value }}" x-on:click="rating = {{ $value }}" class="grid h-10 w-10 place-items-center rounded-full border text-sm font-semibold transition-colors" x-bind:class="rating >= {{ $value }} ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-white text-foreground hover:bg-muted'">{{ $value }}</button>
            @endforeach
        </div>
        @error('satisfaction_rating')
            <p class="mt-1 text-xs font-medium text-destructive">{{ $message }}</p>
        @enderror
        <textarea name="satisfaction_comment" rows="2" maxlength="1000" class="{{ $input }}" placeholder="Anything you want to add? (optional)">{{ old('satisfaction_comment') }}</textarea>
        <button type="submit" class="{{ $smallPrimary }} mt-3" x-bind:disabled="! rating" x-bind:class="rating ? '' : 'opacity-50'">Submit Rating</button>
    </form>
@endif

@if ($buttons->isNotEmpty())
<div {{ $attributes->merge(['class' => 'min-w-0']) }} x-data="{ action: @js($openPanel) }" data-ticket-actions>
    @if ($errors->hasAny($fields))
        <ul class="mb-2 rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-xs text-destructive">
            @foreach ($fields as $field)
                @foreach ($errors->get($field) as $message)
                    <li>{{ $message }}</li>
                @endforeach
            @endforeach
        </ul>
    @endif

    <!-- Buttons -->
    <div class="grid gap-2 {{ $buttons->count() > 1 ? 'grid-cols-2' : '' }}" x-show="action === null">
        @if ($buttons->has('acknowledge'))
            <form method="POST" action="{{ $acknowledgeUrl }}">
                @csrf
                <button type="submit" class="{{ $primaryButton }}">Acknowledge Ticket</button>
            </form>
        @endif
        @if ($buttons->has('clarify'))
            <button type="button" x-on:click="action = 'clarify'" class="{{ $outlineButton }}">Ask for Details</button>
        @endif
        @if ($buttons->has('resolve'))
            <button type="button" x-on:click="action = 'resolve'" class="h-10 w-full rounded-full border border-green-800 bg-green-800 px-3 text-center text-sm font-semibold text-white transition-colors hover:bg-green-900">Mark as Resolved</button>
        @endif
        @if ($buttons->has('escalate'))
            <button type="button" x-on:click="action = 'escalate'" class="inline-flex h-10 w-full items-center justify-center gap-1.5 rounded-full border border-red-800 bg-white px-3 text-sm font-semibold text-red-800 transition-colors hover:bg-red-50">
                <x-icons.alert-triangle class="h-4 w-4" />
                Escalate
            </button>
        @endif
        @if ($buttons->has('refer'))
            <button type="button" x-on:click="action = 'refer'" class="{{ $outlineButton }}">Refer to Committee</button>
        @endif
        @if ($buttons->has('outcome'))
            <button type="button" x-on:click="action = 'outcome'" class="{{ $primaryButton }}">Record Outcome</button>
        @endif
        @if ($buttons->has('reopen'))
            <form method="POST" action="{{ route('admin.tickets.not-yet-resolved', $ticket) }}">
                @csrf
                <button type="submit" class="{{ $outlineButton }}">Not yet Resolved</button>
            </form>
        @endif
        @if ($buttons->has('close'))
            <button type="button" x-on:click="action = 'close'" class="{{ $primaryButton }}">Close Ticket</button>
        @endif
        @if ($buttons->has('accept'))
            <button type="button" x-on:click="action = 'accept'" class="{{ $primaryButton }}">Accept Resolution</button>
        @endif
        @if ($buttons->has('further'))
            <button type="button" x-on:click="action = 'further'" class="{{ $outlineButton }}">Request Further Action</button>
        @endif
        @if ($buttons->has('withdraw'))
            <button type="button" x-on:click="action = 'withdraw'" class="h-10 w-full rounded-full border border-border bg-white px-3 text-center text-sm font-semibold text-muted-foreground transition-colors hover:bg-muted hover:text-foreground">Withdraw Ticket</button>
        @endif
    </div>

    <!-- Forms -->
    @if ($buttons->has('clarify'))
        <form x-show="action === 'clarify'" x-cloak method="POST" action="{{ route('admin.tickets.clarification', $ticket) }}" class="surface p-4">
            @csrf
            <p class="{{ $panelTitle }}">Ask the student for more details</p>
            <p class="{{ $panelHint }}">The ticket waits as Needs Clarification until the student replies in the conversation.</p>
            <textarea name="clarification_message" rows="3" required maxlength="2000" class="{{ $input }}" placeholder="What does the student need to clarify?">{{ old('clarification_message') }}</textarea>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <button type="button" x-on:click="action = null" class="{{ $smallCancel }}">Cancel</button>
                <button type="submit" class="{{ $smallPrimary }}">Send Request</button>
            </div>
        </form>
    @endif

    @if ($buttons->has('resolve'))
        <form x-show="action === 'resolve'" x-cloak method="POST" action="{{ $resolveUrl }}" class="surface p-4">
            @csrf
            @if ($role === 'recipient')
                @method('PATCH')
                <input type="hidden" name="status" value="resolved">
            @endif
            <p class="{{ $panelTitle }}">Mark this ticket as resolved</p>
            <p class="{{ $panelHint }}">The student is asked to accept the resolution or request further action.</p>
            <x-picker name="resolution_type" required id="resolution-type-{{ $ticket->id }}" label="How was it resolved?" class="mt-2" placeholder="How was it resolved?" :selected="old('resolution_type', '')" :options="collect(Ticket::RESOLUTION_LABELS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all()" />
            <textarea name="resolution_message" rows="3" required maxlength="2000" class="{{ $input }}" placeholder="Describe the resolution for the student.">{{ old('resolution_message') }}</textarea>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <button type="button" x-on:click="action = null" class="{{ $smallCancel }}">Cancel</button>
                <button type="submit" class="h-9 w-full rounded-full bg-green-800 px-3 text-xs font-semibold text-white transition-colors hover:bg-green-900">Mark as Resolved</button>
            </div>
        </form>
    @endif

    @if ($buttons->has('escalate'))
        <div x-show="action === 'escalate'" x-cloak>
            <x-ticket-escalate-form :ticket="$ticket" :cancellable="true" class="surface p-4" />
        </div>
    @endif

    @if ($buttons->has('refer'))
        <form x-show="action === 'refer'" x-cloak method="POST" action="{{ route('admin.tickets.refer', $ticket) }}" class="surface p-4">
            @csrf
            <p class="{{ $panelTitle }}">Refer to a committee or board</p>
            <p class="{{ $panelHint }}">For cases decided outside the system, such as discipline or harassment cases. Record the outcome here once it is decided.</p>
            {{-- The committees the Student Handbook names; any other is typed in. --}}
            <div x-data="{ committee: @js(old('referred_to', '')) }" x-on:picked="committee = $event.detail">
                <x-picker name="referred_to" required id="referred-to-{{ $ticket->id }}" label="Referred to" class="mt-2" placeholder="Select the committee or board" :selected="old('referred_to', '')" :options="[
                    ['value' => 'Committee on Decorum and Investigation', 'label' => 'Committee on Decorum and Investigation', 'detail' => 'Sexual harassment and gender-based cases'],
                    ['value' => 'Campus Disciplinary Committee', 'label' => 'Campus Disciplinary Committee', 'detail' => 'Student discipline cases on this campus'],
                    ['value' => 'University Investigation and Disciplinary Committee', 'label' => 'University Investigation and Disciplinary Committee', 'detail' => 'Appeals and university-level cases'],
                    ['value' => 'other', 'label' => 'Other committee or board', 'detail' => 'Type its name below'],
                ]" />
                <input x-show="committee === 'other'" x-cloak x-bind:required="committee === 'other'" x-bind:disabled="committee !== 'other'" name="referred_to_other" type="text" maxlength="255" value="{{ old('referred_to_other') }}" class="{{ $input }}" placeholder="Name of the committee or board" aria-label="Name of the committee or board">
            </div>
            <textarea name="referral_note" rows="3" required maxlength="1000" class="{{ $input }}" placeholder="Why is this ticket being referred? This is recorded on the ticket.">{{ old('referral_note') }}</textarea>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <button type="button" x-on:click="action = null" class="{{ $smallCancel }}">Cancel</button>
                <button type="submit" class="{{ $smallPrimary }}">Refer Ticket</button>
            </div>
        </form>
    @endif

    @if ($buttons->has('outcome'))
        <form x-show="action === 'outcome'" x-cloak method="POST" action="{{ route('admin.tickets.outcome', $ticket) }}" class="surface p-4">
            @csrf
            <p class="{{ $panelTitle }}">Record the outcome{{ $ticket->referred_to ? ' from ' . $ticket->referred_to : '' }}</p>
            <p class="{{ $panelHint }}">The ticket becomes Resolved and the student is told the outcome.</p>
            <x-picker name="resolution_type" id="outcome-type-{{ $ticket->id }}" label="Type of resolution" class="mt-2" :selected="old('resolution_type', Ticket::RESOLUTION_COMMITTEE_DECISION)" :options="collect(Ticket::RESOLUTION_LABELS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all()" />
            <textarea name="outcome" rows="3" required maxlength="2000" class="{{ $input }}" placeholder="What was decided?">{{ old('outcome') }}</textarea>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <button type="button" x-on:click="action = null" class="{{ $smallCancel }}">Cancel</button>
                <button type="submit" class="{{ $smallPrimary }}">Save Outcome</button>
            </div>
        </form>
    @endif

    @if ($buttons->has('close'))
        <form x-show="action === 'close'" x-cloak method="POST" action="{{ route('admin.tickets.close', $ticket) }}" class="surface p-4">
            @csrf
            @if ($ticket->status === Ticket::STATUS_RESOLVED)
                <p class="{{ $panelTitle }}">Close this ticket?</p>
                <p class="{{ $panelHint }}">This confirms the resolution on the student's behalf. The conversation is locked and this cannot be undone.</p>
            @else
                <p class="{{ $panelTitle }}">Close without a resolution</p>
                <p class="{{ $panelHint }}">Use this only when the ticket cannot continue. The student is notified with the reason. This cannot be undone.</p>
                <x-picker name="closure_type" required id="closure-type-{{ $ticket->id }}" label="Reason for closing" class="mt-2" placeholder="Why is it being closed?" :selected="old('closure_type', '')" :options="collect(Ticket::ADMIN_CLOSURE_TYPES)->map(fn ($value) => ['value' => $value, 'label' => Ticket::CLOSURE_LABELS[$value]])->all()" />
                <textarea name="closure_reason" rows="2" required maxlength="1000" class="{{ $input }}" placeholder="Explain the reason to the student.">{{ old('closure_reason') }}</textarea>
            @endif
            <div class="mt-3 grid grid-cols-2 gap-2">
                <button type="button" x-on:click="action = null" class="{{ $smallCancel }}">Cancel</button>
                <button type="submit" class="{{ $smallPrimary }}">Yes, close ticket</button>
            </div>
        </form>
    @endif

    @if ($buttons->has('accept'))
        <form x-show="action === 'accept'" x-cloak method="POST" action="{{ route('student.complaints.accept-resolution', $complaint) }}" class="surface p-4">
            @csrf
            <p class="{{ $panelTitle }}">Accept the resolution?</p>
            <p class="{{ $panelHint }}">Your ticket will be closed. This cannot be undone.</p>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <button type="button" x-on:click="action = null" class="{{ $smallCancel }}">Cancel</button>
                <button type="submit" class="{{ $smallPrimary }}">Yes, accept</button>
            </div>
        </form>
    @endif

    @if ($buttons->has('further'))
        <form x-show="action === 'further'" x-cloak method="POST" action="{{ route('student.complaints.further-action', $complaint) }}" class="surface p-4">
            @csrf
            <p class="{{ $panelTitle }}">Request further action</p>
            <p class="{{ $panelHint }}">Your ticket goes back to the office handling it.{{ $furtherActionDeadline ? ' You can do this until ' . $furtherActionDeadline->format('M d, Y') . '.' : '' }}</p>
            <textarea name="further_action_reason" rows="3" required maxlength="2000" class="{{ $input }}" placeholder="What still needs to be addressed?">{{ old('further_action_reason') }}</textarea>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <button type="button" x-on:click="action = null" class="{{ $smallCancel }}">Cancel</button>
                <button type="submit" class="{{ $smallPrimary }}">Send Request</button>
            </div>
        </form>
    @endif

    @if ($buttons->has('withdraw'))
        <form x-show="action === 'withdraw'" x-cloak method="POST" action="{{ route('student.complaints.withdraw', $complaint) }}" class="surface p-4">
            @csrf
            <p class="{{ $panelTitle }}">Withdraw this ticket?</p>
            <p class="{{ $panelHint }}">The ticket is closed and no further action is taken. This cannot be undone.</p>
            <textarea name="withdraw_reason" rows="2" maxlength="1000" class="{{ $input }}" placeholder="Reason (optional)">{{ old('withdraw_reason') }}</textarea>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <button type="button" x-on:click="action = null" class="{{ $smallCancel }}">Cancel</button>
                <button type="submit" class="{{ $smallPrimary }}">Yes, withdraw</button>
            </div>
        </form>
    @endif
</div>
@endif
