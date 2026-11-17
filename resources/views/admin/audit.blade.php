<x-app-layout :role="'admin'" title="Audit Trail">
    <div>
        @php
            $query = fn (array $values) => route('admin.audit', array_filter($values, fn ($value) => $value !== null && $value !== ''));
        @endphp
        <form method="GET" action="{{ route('admin.audit') }}" class="relative z-20 mb-3 sm:mb-4 lg:mb-5" data-ticket-filter-form>
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="w-full sm:min-w-[280px] sm:flex-1">
            <div class="relative min-w-0">
                <x-icons.search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <input id="audit-search" name="search" value="{{ request('search') }}" autocomplete="off" placeholder="Search action, ticket id or user name" class="h-9 w-full rounded-md border border-input bg-transparent pl-9 pr-3 text-sm shadow-sm outline-none placeholder:text-xs placeholder:text-muted-foreground focus:ring-1 focus:ring-ring" />
            </div>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:gap-3">
                @php
                    $accountTypes = ['' => 'All Account Types', 'sds_admin' => 'Administrator', 'student' => 'Student', 'recipient' => 'Recipient', 'system' => 'System'];
                @endphp
                <details x-data="{}" class="group relative w-full sm:w-48 sm:shrink-0" x-on:click.outside="$el.removeAttribute('open')">
                    <summary class="flex h-9 cursor-pointer list-none items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden"><span class="truncate">{{ $accountTypes[request('account_type', '')] ?? 'All Account Types' }}</span><svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg></summary>
                    <div class="absolute top-full z-50 mt-1 w-full rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                        @foreach ($accountTypes as $value => $label)
                            <a href="{{ $query(['search' => request('search'), 'account_type' => $value, 'from' => request('from'), 'to' => request('to')]) }}" class="relative flex w-full items-center rounded-sm py-1.5 px-2 text-xs {{ request('account_type', '') === $value ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">{{ $label }}</a>
                        @endforeach
                    </div>
                </details>
                <label for="audit-from" class="text-xs font-medium">From</label>
                <input id="audit-from" type="date" name="from" value="{{ request('from') }}" aria-label="From date" class="h-9 w-36 min-w-36 rounded-md border border-input bg-transparent px-2 text-xs focus:ring-1 focus:ring-ring" />
                <label for="audit-to" class="text-xs font-medium">To</label>
                <input id="audit-to" type="date" name="to" value="{{ request('to') }}" aria-label="To date" class="h-9 w-36 min-w-36 rounded-md border border-input bg-transparent px-2 text-xs focus:ring-1 focus:ring-ring" />
                <a data-reset-filters href="{{ route('admin.audit') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md bg-primary text-primary-foreground transition-colors hover:bg-primary/90" aria-label="Refresh audit trail" title="Refresh audit trail">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 12a9 9 0 1 1-2.64-6.36L21 8" />
                        <path d="M21 3v5h-5" />
                    </svg>
                </a>
            </div>
            </div>
        </form>

        <div data-ticket-results>
        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full min-w-[960px] table-fixed text-xs">
                <colgroup>
                    <col style="width: 9rem;">
                    <col style="width: 7rem;">
                    <col>
                    <col style="width: 10rem;">
                    <col style="width: 7rem;">
                    <col style="width: 9rem;">
                </colgroup>
                <thead class="border-b bg-primary text-white">
                    <tr class="text-left">
                        <th class="rounded-tl-lg px-3 py-2 font-semibold">Action</th>
                        <th class="px-3 py-2 font-semibold">Ticket ID</th>
                        <th class="px-3 py-2 font-semibold">Details</th>
                        <th class="px-3 py-2 font-semibold">User</th>
                        <th class="px-3 py-2 font-semibold">Account Type</th>
                        <th class="rounded-tr-lg px-3 py-2 font-semibold">Date &amp; Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($auditLogs as $log)
                        @php
                            $isAnonymousComplaint = (bool) $log->ticket?->complaint?->is_anonymous;
                            $performerName = $isAnonymousComplaint ? '—' : ($log->display_performer?->table_name ?? 'System');
                            $accountType = $isAnonymousComplaint
                                ? 'Student'
                                : match ($log->display_performer?->role) {
                                    'sds_admin' => 'Administrator',
                                    'student' => 'Student',
                                    'recipient' => 'Recipient',
                                    default => 'System',
                                };
                        @endphp
                        <tr class="align-top transition-colors hover:bg-primary-soft">
                            <td class="break-words px-3 py-2.5 font-semibold">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5 font-mono text-primary">{{ $log->ticket?->complaint?->reference_number ?? $log->ticket_id ?? '—' }}</td>
                            <td class="break-words px-3 py-2.5 text-muted-foreground">{{ $log->details ?: '—' }}</td>
                            <td class="break-words px-3 py-2.5">{{ $performerName }}</td>
                            <td class="break-words px-3 py-2.5 text-muted-foreground">{{ $accountType }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-muted-foreground">{{ $log->created_at?->copy()->setTimezone('Asia/Manila')->format('m/d/y h:i A') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-10 text-center text-sm text-muted-foreground"><x-icons.history class="mx-auto mb-2 h-5 w-5" /> No audit entries match this search.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </div>

        @if ($auditLogs->hasPages())
            <div class="mt-2">{{ $auditLogs->links() }}</div>
        @endif
    </div>
</x-app-layout>
