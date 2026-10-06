{{-- The latest audit entries of a ticket, as shown on the admin ticket view. --}}
<div class="surface p-4 {{ $class ?? '' }}">
    <h3 class="font-display text-sm font-semibold leading-tight text-foreground">Audit Activity</h3>
    <ol class="mt-2 grid gap-2">
        @forelse ($auditLogs as $log)
            <li class="flex min-w-0 gap-2">
                <span class="mt-0.5 h-8 w-0.5 shrink-0 rounded-full bg-[#800000]"></span>
                <div class="mt-0.5 min-w-0 flex-1">
                    <p class="truncate text-xs font-semibold leading-tight text-foreground">{{ $auditLabels[$log->action] ?? ucfirst(str_replace('_', ' ', (string) ($log->action ?? 'Action'))) }}</p>
                    <p class="mt-0.5 truncate text-[11px] leading-snug text-muted-foreground">
                        <span>{{ $log->display_performer?->table_name ?? 'System' }}</span>
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
