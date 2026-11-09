<ol class="divide-y divide-border">
    @forelse ($tickets as $row)
        @php
            $ticket = $row['ticket'];
            $complaint = $ticket->complaint;
            $handler = $ticket->currentHandler ?? $ticket->assignee;
            $handlerName = $handler?->isSdsAdmin() ? 'SDS Office' : ($handler?->display_name ?? 'SDS Office');
            $handlerUnit = $handler?->recipient?->unit;
        @endphp
        <li class="px-3 py-3 transition-colors hover:bg-primary-soft/50 sm:px-4" data-needs-attention-ticket>
            <a href="{{ route('admin.complaints.show', $complaint) }}" class="block rounded-sm outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-mono text-xs font-semibold text-primary">{{ $complaint?->reference_number ?? $ticket->id }}</p>
                        <p class="mt-0.5 truncate text-sm font-semibold text-foreground">{{ $complaint?->public_subject ?? 'Untitled' }}</p>
                    </div>
                    <x-status-badge :status="$ticket->status_label" :show-icon="false" class="shrink-0 px-2 py-0.5 text-[10px]" />
                </div>
                <div class="mt-2 grid gap-x-4 gap-y-1 text-xs text-muted-foreground sm:grid-cols-2 lg:grid-cols-4">
                    <p><span class="font-semibold text-foreground">Category:</span> {{ $complaint?->category?->name ?? 'Uncategorized' }}</p>
                    <p><span class="font-semibold text-foreground">Filed by:</span> {{ $complaint?->filerNameFor(Auth::user()) ?? 'Unknown' }}</p>
                    <p><span class="font-semibold text-foreground">Handler:</span> {{ $handlerName }}{{ $handlerUnit ? ' · ' . $handlerUnit : '' }}</p>
                    <p><span class="font-semibold text-foreground">Age:</span> {{ $row['age'] }} working {{ \Illuminate\Support\Str::plural('day', $row['age']) }}</p>
                </div>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach ($row['reasons'] as $reason)
                        <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-900">{{ $reason }}</span>
                    @endforeach
                </div>
            </a>
        </li>
    @empty
        <li class="px-4 py-8 text-center text-sm text-muted-foreground">No tickets need attention right now.</li>
    @endforelse
</ol>
