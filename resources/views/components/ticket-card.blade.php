@props([
    'item',
    'role' => 'student', // 'student' or 'recipient'
    'first' => true,
    'last' => true,
    'unread' => false,
])

@php
    // Handle both Complaint and Ticket objects
    $isComplaint = $item instanceof \App\Models\Complaint;
    $ticket = $isComplaint ? $item->ticket : $item;
    $complaint = $isComplaint ? $item : $item->complaint;
    
    $route = $isComplaint
        ? route('student.complaints.show', $item->id)
        : ($role === 'student'
            ? route('student.complaints.show', $complaint?->id)
            : ($role === 'admin'
                ? route('admin.complaints.show', $complaint?->id)
                : route('recipient.tickets.show', $complaint?->id)));
    
    $lastUpdate = 'Never';
    $lastUpdatedAt = null;

    if ($ticket?->updated_at && $complaint?->updated_at) {
        $lastUpdatedAt = $ticket->updated_at->greaterThan($complaint->updated_at)
            ? $ticket->updated_at
            : $complaint->updated_at;
    } elseif ($ticket?->updated_at) {
        $lastUpdatedAt = $ticket->updated_at;
    } elseif ($complaint?->updated_at) {
        $lastUpdatedAt = $complaint->updated_at;
    } elseif ($item?->updated_at) {
        $lastUpdatedAt = $item->updated_at;
    }

    if ($lastUpdatedAt) {
        $updatedAt = $lastUpdatedAt->copy()->setTimezone('Asia/Manila');
        $ageInMinutes = $updatedAt->diffInMinutes(now()->setTimezone('Asia/Manila'));
        $relativeTime = $updatedAt->diffForHumans();
        $lastUpdate = $ageInMinutes < 60
            ? str_replace(' from now', ' ago', $relativeTime)
            : ($updatedAt->isSameDay(now()->setTimezone('Asia/Manila'))
                ? $updatedAt->format('g:i A')
                : $updatedAt->format('M d, g:i A'));
    }
    
    // Get status from ticket if available, otherwise from complaint
    $status = $ticket ? $ticket->status : $item->status;
    $statusDisplay = $role === 'student' && $ticket?->classification === 'informational' && $status === 'pending'
        ? 'Closed'
        : ($role === 'student'
        ? match($status) {
            'assigned', 'in_progress' => 'In Progress',
            'escalated' => 'Escalated',
            'resolved' => 'Resolved',
            'rejected', 'closed' => 'Closed',
            default => 'Pending',
        }
        : match($status) {
        'pending' => 'Pending',
        'assigned' => 'In Progress',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
        'rejected' => 'Rejected',
        'closed' => 'Closed',
        'escalated' => 'Escalated',
        default => ucfirst(str_replace('_', ' ', $status)),
        });
    $classification = $ticket?->classification;
    $studentUser = $complaint?->student?->user;
    $radiusClass = match (true) {
        $first && $last => 'rounded-xl',
        $first => 'rounded-t-xl rounded-b-none',
        $last => 'rounded-t-none rounded-b-xl',
        default => 'rounded-none',
    };
    $recipientMetaText = null;
    if ($role === 'recipient' && $ticket?->status !== 'pending' && ! in_array($ticket?->status, ['resolved', 'closed', 'rejected'], true)) {
        if ($ticket?->escalated_at) {
            $recipientMetaText = 'Escalated ' . $ticket->escalated_at->copy()->setTimezone('Asia/Manila')->format('M d');
        }
    }
    $unreadService = app(\App\Services\TicketUnreadService::class);
    $badgeCount = Auth::user() ? $unreadService->unreadCountForTicket(Auth::user(), $ticket) : 0;
    $isUnread = $badgeCount > 0;
@endphp

<a 
    href="{{ $route }}"
    class="surface {{ $radiusClass }} {{ $isUnread ? 'border-0 bg-primary-soft' : 'border border-border bg-card' }} block w-full min-w-0 max-w-full overflow-hidden p-3 transition-transform duration-200 ease-out hover:scale-[1.01] hover:border-0 {{ $isUnread ? 'hover:bg-primary-soft' : 'hover:bg-muted/80' }}"
>
    <div class="min-w-0 {{ $role === 'recipient' ? 'grid grid-cols-[auto_minmax(0,1fr)] items-start gap-3' : '' }}">
        @if ($role === 'recipient')
            <span class="grid h-11 w-11 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-soft text-sm font-bold text-primary">
                @if ($studentUser?->avatar_path)
                    <img src="{{ asset('storage/' . $studentUser->avatar_path) }}" alt="" class="h-full w-full object-cover">
                @else
                    {{ $studentUser?->name_initials ?: '?' }}
                @endif
            </span>
        @endif
        <div class="min-w-0 space-y-0">
        <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-2 {{ $role === 'recipient' ? 'mt-0.5' : '' }}">
            <p class="flex min-w-0 items-center gap-1.5 font-mono text-[11px] font-semibold text-primary">
                <span class="truncate">{{ $complaint?->reference_number ?? $item->id }}</span>
                <span class="flex min-w-0 items-center gap-1.5 truncate font-sans font-normal text-muted-foreground">
                    @if ($role === 'recipient')
                        @if ($recipientMetaText)
                            <span class="truncate font-bold text-[#a1121a]">({{ $recipientMetaText }})</span>
                        @endif
                    @else
                        <span class="truncate">({{ $complaint?->category?->name ?? 'Uncategorized' }})</span>
                    @endif
                    @if ($isUnread && $badgeCount > 0)
                        <span class="grid min-h-4 min-w-4 shrink-0 place-items-center rounded-full bg-[#7d1f2a] px-1 text-[9px] font-bold leading-none text-white">{{ $badgeCount > 99 ? '99+' : $badgeCount }}</span>
                    @endif
                </span>
            </p>
            <x-status-badge :status="$statusDisplay" :classification="$classification" :show-icon="false" class="max-w-28 truncate px-2 py-0 text-[10px]" />
        </div>
        <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-2">
            <p class="line-clamp-2 min-w-0 break-words text-sm font-bold">
                {{ $complaint?->subject_title ?? 'Untitled' }}
            </p>
            <span class="shrink-0 text-right text-[11px] leading-4 text-muted-foreground">{{ $lastUpdate }}</span>
        </div>
        <p class="truncate text-xs text-muted-foreground">
            {{ $complaint?->description ?? 'No description provided.' }}
        </p>
        </div>
    </div>
    @if ($slot->isNotEmpty())
        <div class="inline-flex flex-wrap items-center gap-2 text-[11px] leading-4 text-muted-foreground">
            {{ $slot }}
        </div>
    @endif
</a>
