<x-app-layout :role="'admin'" title="Ticket Review">
    <div class="-mt-1 sm:-mt-2">
        <form method="GET" action="{{ route('admin.tickets.review.index') }}" class="relative z-20 mb-2" data-ticket-filter-form>
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <div class="w-full sm:w-auto sm:flex-1 sm:min-w-[280px]">
                    <div class="relative min-w-0">
                        <x-icons.search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <label for="pending-ticket-search" class="sr-only">Search tickets</label>
                        <input id="pending-ticket-search" name="search" value="{{ request('search') }}" autocomplete="off" placeholder="Search ticket id, subject title or student name" class="h-9 w-full rounded-md border border-input bg-transparent pl-9 pr-3 text-sm shadow-sm outline-none placeholder:text-xs placeholder:text-muted-foreground focus:ring-1 focus:ring-ring" />
                    </div>
                </div>

                <div class="flex flex-col gap-2 sm:gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <div class="flex items-center gap-2">
                        <label for="pending-category" class="hidden sm:block shrink-0 text-sm font-medium">Filter</label>
                        @php
                            $selectedCategoryId = (string) request('category_filter', '');
                            $selectedCategory = $categories->first(fn ($category) => (string) $category->id === $selectedCategoryId);
                            $longestCategory = $categories->sortByDesc(fn ($category) => strlen($category->name))->first()?->name ?? 'All categories';
                        @endphp
                        <details x-data="{}" class="group relative w-full sm:w-56 sm:shrink-0" x-on:click.outside="$el.removeAttribute('open')">
                            <span aria-hidden="true" class="invisible block h-0 whitespace-nowrap">{{ $longestCategory }}</span>
                            <summary id="pending-category" class="flex h-9 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden">
                                <span class="truncate">{{ $selectedCategory?->name ?? ($selectedCategoryId === '' ? 'All categories' : 'Selected category') }}</span>
                                <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                            </summary>
                            <div class="absolute top-full z-50 mt-1 max-h-72 w-full overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                <a href="{{ route('admin.tickets.review.index', array_filter(['search' => request('search'), 'sort' => request('sort')])) }}" class="relative flex w-full items-center rounded-sm py-1.5 pl-2 pr-8 text-xs {{ $selectedCategoryId === '' ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">
                                    @if ($selectedCategoryId === '') <x-icons.check class="absolute right-2 h-4 w-4" /> @endif
                                    All categories
                                </a>
                                @foreach ($categories as $category)
                                    <a href="{{ route('admin.tickets.review.index', array_filter(['search' => request('search'), 'category_filter' => $category->id, 'sort' => request('sort')])) }}" class="relative flex w-full items-center rounded-sm py-1.5 pl-2 pr-8 text-xs {{ $selectedCategoryId === (string) $category->id ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">
                                        @if ($selectedCategoryId === (string) $category->id) <x-icons.check class="absolute right-2 h-4 w-4" /> @endif
                                        <span class="whitespace-normal break-words">{{ $category->name }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </details>
                    </div>

                    <div class="flex items-center gap-2">
                        <label for="pending-sort" class="hidden sm:block shrink-0 text-sm font-medium">Sort by date</label>
                        <details x-data="{}" class="group relative w-full sm:w-28 sm:shrink-0" x-on:click.outside="$el.removeAttribute('open')">
                            <span aria-hidden="true" class="invisible block h-0 whitespace-nowrap">Oldest first</span>
                            <summary id="pending-sort" class="flex h-8 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-transparent px-2.5 py-1.5 text-[11px] shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden">
                                <span>{{ request('sort') === 'oldest' ? 'Oldest first' : 'Newest first' }}</span>
                                <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                            </summary>
                            <div class="absolute top-full z-50 mt-1 w-full rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                @foreach (['newest' => 'Newest first', 'oldest' => 'Oldest first'] as $sortValue => $sortLabel)
                                    <a href="{{ route('admin.tickets.review.index', array_filter(['search' => request('search'), 'category_filter' => request('category_filter'), 'sort' => $sortValue])) }}" class="relative flex w-full items-center whitespace-nowrap rounded-sm py-1.5 pl-2 pr-8 text-xs {{ request('sort', 'newest') === $sortValue ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">
                                        @if (request('sort', 'newest') === $sortValue) <x-icons.check class="absolute right-2 h-4 w-4" /> @endif
                                        {{ $sortLabel }}
                                    </a>
                                @endforeach
                            </div>
                        </details>
                    </div>
                </div>
            </div>
        </form>

        <div data-ticket-results>
        <p class="mb-2 text-xs text-muted-foreground">{{ $tickets->total() }} ticket(s)</p>

        @if ($tickets->isEmpty())
            <div class="flex flex-col items-center gap-2 rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">
                <x-icons.inbox class="h-5 w-5" />
                No tickets are awaiting review.
            </div>
        @else
            {{-- Phones: the same tappable cards students and recipients get. --}}
            <ul class="grid grid-cols-1 gap-0.5 md:hidden">
                @foreach ($tickets as $ticket)
                    @php $isUnread = app(\App\Services\TicketUnreadService::class)->unreadCountForTicket(Auth::user(), $ticket) > 0; @endphp
                    <li data-ticket-review-row data-read-url="{{ route('admin.tickets.read', $ticket) }}" data-unread="{{ $isUnread ? 'true' : 'false' }}">
                        <x-ticket-card :item="$ticket" role="admin" :first="$loop->first" :last="$loop->last" data-ticket-review-link="ticket-{{ $ticket->id }}" data-ticket-details-link="complaint-{{ $ticket->complaint->id }}" data-ticket-status="{{ $ticket->status }}">
                            Filed by {{ $ticket->complaint->is_anonymous ? 'Anonymous' : ($ticket->complaint->student?->user?->table_name ?? 'Anonymous') }}
                        </x-ticket-card>
                    </li>
                @endforeach
            </ul>

            <div class="hidden w-full rounded-lg border md:block">
                <table class="w-full table-auto text-xs">
                    <thead class="border-b bg-primary text-white">
                        <tr class="text-left">
                            <th class="whitespace-nowrap rounded-tl-lg px-2 py-2 font-semibold sm:px-3">Ticket ID</th>
                            <th class="px-2 py-2 font-semibold sm:px-3">Subject Title</th>
                            <th class="hidden whitespace-nowrap px-2 py-2 font-semibold sm:px-3 lg:table-cell">Category</th>
                            <th class="hidden whitespace-nowrap px-2 py-2 font-semibold sm:px-3 lg:table-cell">Status</th>
                            <th class="hidden px-2 py-2 font-semibold sm:px-3 lg:table-cell">Current Holder</th>
                            <th class="hidden px-2 py-2 font-semibold sm:px-3 md:table-cell">Filed by</th>
                            <th class="whitespace-nowrap rounded-tr-lg px-2 py-2 font-semibold sm:px-3">Date Submitted</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($tickets as $ticket)
                            @php $isUnread = app(\App\Services\TicketUnreadService::class)->unreadCountForTicket(Auth::user(), $ticket) > 0; @endphp
                            <tr data-ticket-review-row data-read-url="{{ route('admin.tickets.read', $ticket) }}" data-unread="{{ $isUnread ? 'true' : 'false' }}" data-ticket-status="{{ $ticket->status }}" class="align-top transition-colors hover:bg-primary-soft {{ $isUnread ? 'bg-primary-soft/70' : '' }}">
                                <td class="whitespace-nowrap px-2 py-2 font-mono font-semibold text-primary sm:px-3">
                                    <a data-ticket-review-link="ticket-{{ $ticket->id }}" data-ticket-details-link="complaint-{{ $ticket->complaint->id }}" data-ticket-status="{{ $ticket->status }}" href="#" class="hover:underline">{{ $ticket->complaint->reference_number }}</a>
                                </td>
                                <td class="px-2 py-2 sm:px-3 {{ $isUnread ? 'text-black' : 'text-muted-foreground' }}">
                                    <a data-ticket-review-link="ticket-{{ $ticket->id }}" data-ticket-details-link="complaint-{{ $ticket->complaint->id }}" data-ticket-status="{{ $ticket->status }}" href="#" class="block truncate hover:text-primary hover:underline">{{ $ticket->complaint->subject_title }}</a>
                                </td>
                                <td class="hidden break-words px-2 py-2 sm:px-3 lg:table-cell {{ $isUnread ? 'text-black' : 'text-muted-foreground' }}">{{ $ticket->complaint->category->name ?? 'Uncategorized' }}</td>
                                <td class="hidden px-2 py-2 sm:px-3 lg:table-cell {{ $isUnread ? 'text-black' : 'text-muted-foreground' }}">
                                    <x-status-badge :status="ucwords(str_replace(['_', '-'], ' ', $ticket->status))" :show-icon="false" class="px-2 py-0.5 text-[10px]" />
                                </td>
                                @php
                                    $holderUser = $ticket->currentHandler ?? $ticket->assignee ?? Auth::user();
                                    $holderName = $holderUser?->table_name ?? '—';
                                    $holderRecipient = $holderUser?->recipient;
                                    $student = $ticket->complaint->student;
                                    $studentUser = $ticket->complaint->student?->user;
                                    $studentTableName = $ticket->complaint->is_anonymous ? 'Anonymous' : ($studentUser?->table_name ?? 'Anonymous');
                                    $studentCourseYearBlock = trim(($student?->course ?? 'Course not specified') . ' ' . ($student?->year_level ?? 'N/A') . (($student?->block ?? '') !== '' ? '-' . $student->block : ''));
                                @endphp
                                <td class="hidden break-words px-2 py-2 sm:px-3 lg:table-cell {{ $isUnread ? 'text-black' : 'text-muted-foreground' }}">
                                    <span class="relative inline-block" x-data="{ holderProfileOpen: false }" x-on:mouseenter="holderProfileOpen = true" x-on:mouseleave="holderProfileOpen = false">
                                        <span class="min-w-0 break-words cursor-pointer hover:text-primary hover:underline">{{ $holderName }}</span>
                                        @if ($holderUser)
                                            <span x-show="holderProfileOpen" x-cloak data-keep-in-view class="brand-gradient absolute top-6 left-0 z-[100] w-72 max-w-[calc(100vw-2rem)] rounded-xl p-4 text-left text-primary-foreground shadow-lg">
                                                <span class="flex items-center gap-3">
                                                    <span class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-foreground/15 text-sm font-bold ring-2 ring-primary-foreground/30">
                                                        @if ($holderUser->avatar_path)
                                                            <img src="{{ asset('storage/' . $holderUser->avatar_path) }}" alt="{{ $holderName }}" class="h-full w-full object-cover">
                                                        @else
                                                            {{ $holderUser->name_initials }}
                                                        @endif
                                                    </span>
                                                    <span class="min-w-0">
                                                        <span class="block wrap-break-word text-sm font-bold">{{ $holderName }}</span>
                                                        <span class="mt-1 block wrap-break-word text-[11px] leading-relaxed opacity-95">ID {{ $holderRecipient?->staff_id ?? $holderUser->username ?? $holderUser->id }} · {{ $holderRecipient?->department ?? 'Department not specified' }} · {{ $holderRecipient?->designation ?? 'Designation not specified' }}</span>
                                                    </span>
                                                </span>
                                                <span class="mt-3 inline-flex rounded-full border border-primary-foreground/40 bg-primary-foreground/15 px-3 py-1 text-[10px] font-semibold">{{ $holderUser->role === \App\Models\User::ROLE_SDS_ADMIN ? 'Admin' : 'Recipient' }}</span>
                                            </span>
                                        @endif
                                    </span>
                                </td>
                                <td class="hidden break-words px-2 py-2 sm:px-3 md:table-cell {{ $isUnread ? 'text-black' : 'text-muted-foreground' }}">
                                    <span class="group relative inline-block">
                                        <span class="min-w-0 break-words {{ $ticket->complaint->is_anonymous ? '' : 'cursor-pointer hover:text-primary hover:underline' }}">{{ $studentTableName }}</span>
                                        @if ($studentUser && ! $ticket->complaint->is_anonymous)
                                            <span data-keep-in-view class="brand-gradient absolute top-6 right-0 z-[100] hidden w-72 max-w-[calc(100vw-2rem)] rounded-xl p-4 text-left text-primary-foreground shadow-lg group-hover:block sm:right-auto sm:left-0">
                                                <span class="flex items-center gap-3">
                                                    <span class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-foreground/15 text-sm font-bold ring-2 ring-primary-foreground/30">
                                                        @if ($studentUser->avatar_path)
                                                            <img src="{{ asset('storage/' . $studentUser->avatar_path) }}" alt="{{ $studentTableName }}" class="h-full w-full object-cover">
                                                        @else
                                                            {{ $studentUser->name_initials }}
                                                        @endif
                                                    </span>
                                                    <span class="min-w-0">
                                                        <span class="block wrap-break-word text-sm font-bold">{{ $studentTableName }}</span>
                                                        <span class="mt-1 block wrap-break-word text-[11px] leading-relaxed opacity-95">ID {{ $student?->student_id ?? 'N/A' }} · {{ $student?->department ?? 'Department not specified' }} · {{ $studentCourseYearBlock }}</span>
                                                    </span>
                                                </span>
                                                <span class="mt-3 inline-flex rounded-full border border-primary-foreground/40 bg-primary-foreground/15 px-3 py-1 text-[10px] font-semibold">Student</span>
                                            </span>
                                        @endif
                                    </span>
                                </td>
                                <td class="break-words px-2 py-2 sm:px-3 {{ $isUnread ? 'text-black' : 'text-muted-foreground' }}">{{ $ticket->complaint->created_at?->copy()->setTimezone('Asia/Manila')->format('m/d/Y h:i A') ?? 'Unknown' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-5">{{ $tickets->links() }}</div>
        @endif
        </div>
    </div>

    <div id="admin-ticket-drawer" class="pointer-events-none invisible fixed inset-0 z-50" aria-hidden="true">
        <div data-admin-ticket-backdrop class="absolute inset-0 bg-black/35 opacity-0 transition-opacity"></div>
        <aside data-admin-ticket-panel class="absolute top-0 right-0 flex h-full w-full max-w-full translate-x-full flex-col overflow-y-auto bg-background shadow-2xl transition-transform duration-200 lg:max-w-[72vw] xl:max-w-[58vw]" role="dialog" aria-modal="true" aria-label="Ticket details">
            <div class="sticky top-0 z-10 flex items-start justify-between border-b border-border bg-card px-4 py-3">
                <div>
                    <h2 class="font-display text-lg font-bold text-primary">Ticket Details</h2>
                </div>
                <button type="button" data-admin-ticket-close class="grid h-8 w-8 shrink-0 place-items-center rounded-md text-xl leading-none text-muted-foreground hover:bg-muted hover:text-primary" aria-label="Close ticket details">&times;</button>
            </div>
            <div data-admin-ticket-body class="p-4"></div>
        </aside>
    </div>

    <div id="ticket-review-drawer" class="pointer-events-none invisible fixed inset-0 z-50" aria-hidden="true">
        <div data-ticket-review-backdrop class="absolute inset-0 bg-black/35 opacity-0 transition-opacity"></div>
        <aside data-ticket-review-panel class="absolute top-0 right-0 flex h-full w-full max-w-full translate-x-full flex-col overflow-y-auto bg-background shadow-2xl transition-transform duration-200 lg:max-w-[72vw] xl:max-w-[58vw]" role="dialog" aria-modal="true" aria-label="Ticket review">
            <div class="sticky top-0 z-10 flex items-start justify-between border-b border-border bg-card px-4 py-3">
                <div>
                    <h2 class="font-display text-lg font-bold text-primary">Ticket Review</h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">Check the details, then step 1 validity and step 2 classification and routing.</p>
                </div>
                <button type="button" data-ticket-review-close class="grid h-8 w-8 shrink-0 place-items-center rounded-md text-xl leading-none text-muted-foreground hover:bg-muted hover:text-primary" aria-label="Close ticket review">&times;</button>
            </div>
            <div data-ticket-review-body class="p-4"></div>
        </aside>
    </div>

    @foreach ($tickets as $ticket)
        @php
            $category = $ticket->complaint->category;
            $categoryRecipients = collect();
            if ($category) {
                $categoryRecipients = collect([$category->recipient])
                    ->merge($category->suggestedRecipients)
                    ->merge($category->escalationHierarchies->pluck('recipient'))
                    ->push($ticket->complaint->suggestedRecipient)
                    ->filter(fn ($recipient) => $recipient && $recipient->user && $recipient->user->is_active)
                    ->unique('id')
                    ->values();
            }

            $availableRecipients = $categoryRecipients;
            $isAnonymousTicket = (bool) $ticket->complaint->is_anonymous;
        @endphp
        @if ($ticket->status === 'resolved')
            <template id="complaint-{{ $ticket->complaint->id }}">
                <x-admin-ticket-view :ticket="$ticket" :review-actions="true" />
            </template>
        @endif
        <template id="ticket-{{ $ticket->id }}">
            <x-admin-ticket-view :ticket="$ticket" :editable="true" :categories="$categories" :recipients="$recipients" :review-actions="true">
                @if ($ticket->status === 'pending')
                <div x-data="{ validity: null, classification: {{ $isAnonymousTicket ? "'informational'" : 'null' }}, jurisdiction: null, informationalDisposition: null, selectedRecipientId: null, selectedRecipient: '', invalidReason: '', invalidReasonError: false }">
                    <div class="rounded-lg border border-border bg-white p-4 text-foreground shadow-sm">
                    <h3 class="flex items-center gap-2 font-display text-base font-bold text-black">
                        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-primary text-xs font-bold text-primary-foreground">1</span>
                        <span>Validity</span>
                    </h3>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <button type="button" x-on:click="validity = 'valid'" class="w-full rounded-full border border-primary px-2.5 py-1.5 text-center text-xs font-semibold transition-colors" x-bind:class="validity === 'valid' ? 'bg-primary text-primary-foreground' : 'bg-white text-primary hover:bg-primary-soft'">Valid</button>
                        <button type="button" x-on:click="validity = 'invalid'" class="w-full rounded-full border border-primary px-2.5 py-1.5 text-center text-xs font-semibold transition-colors" x-bind:class="validity === 'invalid' ? 'bg-primary text-primary-foreground' : 'bg-white text-primary hover:bg-primary-soft'">Invalid</button>
                    </div>

                    <div x-show="validity === 'valid'" x-cloak class="mt-5 border-t border-border pt-4">
                        <h3 class="flex items-center gap-2 font-display text-base font-bold text-black">
                            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-primary text-xs font-bold text-primary-foreground">2</span>
                            <span>Classification</span>
                        </h3>
                        @if ($isAnonymousTicket)
                            <p class="mt-2 text-xs text-muted-foreground">Anonymous submissions are kept as informational records. Choose whether to retain it in SDS records or forward it to a recipient.</p>
                        @endif
                        <div class="mt-4 grid gap-2">
                            @unless ($isAnonymousTicket)
                                <button type="button" x-on:click="classification = 'needs_resolution'" class="w-full rounded-full border border-primary px-2.5 py-1.5 text-center text-xs font-semibold transition-colors" x-bind:class="classification === 'needs_resolution' ? 'bg-primary text-primary-foreground' : 'bg-white text-primary hover:bg-primary-soft'">Needs Resolution</button>
                            @endunless
                            <button type="button" x-on:click="classification = 'informational'" class="w-full rounded-full border border-primary px-2.5 py-1.5 text-center text-xs font-semibold transition-colors" x-bind:class="classification === 'informational' ? 'bg-primary text-primary-foreground' : 'bg-white text-primary hover:bg-primary-soft'">Informational</button>
                        </div>
                        <div x-show="classification === 'needs_resolution'" x-cloak class="mt-5 border-t border-border pt-4">
                            <p class="text-sm font-semibold text-black">Does this fall under SDS jurisdiction or under a different office?</p>
                            <div class="mt-4 grid gap-2">
                                <button type="button" x-on:click="jurisdiction = 'sds'" class="w-full rounded-full border border-primary px-2.5 py-1.5 text-center text-xs font-semibold transition-colors" x-bind:class="jurisdiction === 'sds' ? 'bg-primary text-primary-foreground' : 'bg-white text-primary hover:bg-primary-soft'">SDS Jurisdiction</button>
                                <button type="button" x-on:click="jurisdiction = 'different_office'" class="w-full rounded-full border border-primary px-2.5 py-1.5 text-center text-xs font-semibold transition-colors" x-bind:class="jurisdiction === 'different_office' ? 'bg-primary text-primary-foreground' : 'bg-white text-primary hover:bg-primary-soft'">Different Office</button>
                            </div>
                            <details x-show="jurisdiction === 'different_office'" x-cloak x-data="{}" class="group relative mt-4" x-on:click.outside="$el.removeAttribute('open')">
                                <summary class="flex h-9 w-full cursor-pointer list-none items-center justify-between rounded-full border border-primary bg-white px-3 py-2 text-xs font-semibold text-primary [&::-webkit-details-marker]:hidden">
                                    <span class="truncate" x-text="selectedRecipient || 'Select recipient account'"></span>
                                    <svg class="h-4 w-4 shrink-0 opacity-60 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                                </summary>
                                <div class="absolute top-full z-40 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-border bg-white p-1 text-foreground shadow-lg">
                                    @forelse ($availableRecipients as $recipient)
                                        @php $recipientName = $recipient->user?->display_name ?? $recipient->user?->name ?? $recipient->department; @endphp
                                        <button type="button" x-on:click="selectedRecipientId = {{ $recipient->id }}; selectedRecipient = @js($recipientName); $el.closest('details').removeAttribute('open')" class="flex w-full items-start rounded-md px-2 py-1.5 text-left text-xs transition-colors hover:bg-primary-soft" x-bind:class="selectedRecipientId === {{ $recipient->id }} ? 'bg-primary-soft text-primary' : ''">
                                            <span class="min-w-0">
                                                <span class="block truncate font-semibold">{{ $recipientName }}</span>
                                                <span class="block truncate text-muted-foreground">{{ $recipient->staff_id }} · {{ $recipient->department }} · {{ $recipient->designation }}</span>
                                            </span>
                                        </button>
                                    @empty
                                        <span class="block px-2 py-2 text-xs text-muted-foreground">No recipient accounts available.</span>
                                    @endforelse
                                </div>
                            </details>
                        </div>
                        <div x-show="classification === 'informational'" x-cloak class="mt-5 border-t border-border pt-4">
                            <p class="text-sm font-semibold text-black">Should the ticket retain in SDS records or should it be forwarded to a recipient?</p>
                            <div class="mt-4 grid gap-2">
                                <button type="button" x-on:click="informationalDisposition = 'retain'; selectedRecipientId = null; selectedRecipient = ''" class="w-full rounded-full border border-primary px-2.5 py-1.5 text-center text-xs font-semibold transition-colors" x-bind:class="informationalDisposition === 'retain' ? 'bg-primary text-primary-foreground' : 'bg-white text-primary hover:bg-primary-soft'">Retain in SDS Records</button>
                                <button type="button" x-on:click="informationalDisposition = 'forward'" class="w-full rounded-full border border-primary px-2.5 py-1.5 text-center text-xs font-semibold transition-colors" x-bind:class="informationalDisposition === 'forward' ? 'bg-primary text-primary-foreground' : 'bg-white text-primary hover:bg-primary-soft'">Forward to Recipient</button>
                            </div>
                            <details x-show="informationalDisposition === 'forward'" x-cloak x-data="{}" class="group relative mt-4" x-on:click.outside="$el.removeAttribute('open')">
                                <summary class="flex h-9 w-full cursor-pointer list-none items-center justify-between rounded-full border border-primary bg-white px-3 py-2 text-xs font-semibold text-primary [&::-webkit-details-marker]:hidden">
                                    <span class="truncate" x-text="selectedRecipient || 'Select recipient account'"></span>
                                    <svg class="h-4 w-4 shrink-0 opacity-60 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                                </summary>
                                <div class="absolute top-full z-40 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-border bg-white p-1 text-foreground shadow-lg">
                                    @forelse ($availableRecipients as $recipient)
                                        @php $recipientName = $recipient->user?->display_name ?? $recipient->user?->name ?? $recipient->department; @endphp
                                        <button type="button" x-on:click="selectedRecipientId = {{ $recipient->id }}; selectedRecipient = @js($recipientName); $el.closest('details').removeAttribute('open')" class="flex w-full items-start rounded-md px-2 py-1.5 text-left text-xs transition-colors hover:bg-primary-soft">
                                            <span class="min-w-0">
                                                <span class="block truncate font-semibold">{{ $recipientName }}</span>
                                                <span class="block truncate text-muted-foreground">{{ $recipient->staff_id }} · {{ $recipient->department }} · {{ $recipient->designation }}</span>
                                            </span>
                                        </button>
                                    @empty
                                        <span class="block px-2 py-2 text-xs text-muted-foreground">No recipient accounts available.</span>
                                    @endforelse
                                </div>
                            </details>
                        </div>
                    </div>

                    <form x-show="validity === 'invalid'" x-cloak method="POST" action="{{ route('admin.tickets.reject', $ticket) }}" class="mt-5 border-t border-border pt-4" x-on:submit="if (!invalidReason.trim()) { invalidReasonError = true; $event.preventDefault(); }">
                        @csrf
                        <label for="closure-reason-{{ $ticket->id }}" class="text-sm font-semibold text-black">Reason of Invalidity <span class="text-destructive" aria-hidden="true">*</span></label>
                        <textarea id="closure-reason-{{ $ticket->id }}" name="closure_reason" rows="4" x-model="invalidReason" x-on:input="invalidReasonError = false" x-bind:class="invalidReasonError ? 'border-destructive' : 'border-input'" class="mt-2 w-full rounded-lg border bg-muted px-3 py-2.5 text-sm text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" placeholder="Enter the reason for invalidity."></textarea>
                        <p x-show="invalidReasonError" x-cloak class="mt-1 text-xs font-medium text-destructive">This field is required.</p>
                        <button type="submit" class="mt-3 w-full rounded-full border border-primary bg-primary px-2.5 py-1.5 text-center text-xs font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Close Ticket</button>
                    </form>
                    </div>
                    <form x-show="validity === 'valid' && classification === 'needs_resolution' && jurisdiction === 'sds'" x-cloak method="POST" action="{{ route('admin.tickets.acknowledge', $ticket) }}" class="mt-3">
                        @csrf
                        <input type="hidden" name="return_to_pending" value="1">
                        <button type="submit" class="w-full rounded-full border border-primary bg-primary px-2.5 py-1.5 text-center text-xs font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Acknowledge Ticket</button>
                    </form>
                    <form x-show="validity === 'valid' && classification === 'needs_resolution' && jurisdiction === 'different_office' && selectedRecipientId" x-cloak method="POST" action="{{ route('admin.tickets.assign', $ticket) }}" class="mt-3">
                        @csrf
                        <input type="hidden" name="return_to_pending" value="1">
                        <input type="hidden" name="assignment_mode" value="recipient">
                        <input type="hidden" name="recipient_id" x-bind:value="selectedRecipientId">
                        <button type="submit" class="w-full rounded-full border border-primary bg-primary px-2.5 py-1.5 text-center text-xs font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Assign Ticket to Recipient</button>
                    </form>
                    <form x-show="validity === 'valid' && classification === 'informational' && informationalDisposition === 'retain'" x-cloak method="POST" action="{{ route('admin.tickets.retain-informational', $ticket) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="w-full rounded-full border border-primary bg-primary px-2.5 py-1.5 text-center text-xs font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Save &amp; Close Ticket</button>
                    </form>
                    <form x-show="validity === 'valid' && classification === 'informational' && informationalDisposition === 'forward' && selectedRecipientId" x-cloak method="POST" action="{{ route('admin.tickets.forward-informational-close', $ticket) }}" class="mt-3">
                        @csrf
                        <input type="hidden" name="recipient_id" x-bind:value="selectedRecipientId">
                        <button type="submit" class="w-full rounded-full border border-primary bg-primary px-2.5 py-1.5 text-center text-xs font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Forward &amp; Close Ticket</button>
                    </form>
                </div>
                @endif
            </x-admin-ticket-view>
        </template>
    @endforeach

    <script>
        (() => {
            const adminDrawer = document.getElementById('admin-ticket-drawer');
            if (adminDrawer && !adminDrawer.dataset.ready) {
                adminDrawer.dataset.ready = 'true';
                const adminPanel = adminDrawer.querySelector('[data-admin-ticket-panel]');
                const adminBody = adminDrawer.querySelector('[data-admin-ticket-body]');
                const adminBackdrop = adminDrawer.querySelector('[data-admin-ticket-backdrop]');
                const adminCloseButton = adminDrawer.querySelector('[data-admin-ticket-close]');
                const openAdminTicket = (templateId) => {
                    const template = document.getElementById(templateId);
                    if (!template) return;
                    adminBody.innerHTML = '';
                    adminBody.appendChild(template.content.cloneNode(true));
                    if (window.Alpine) window.Alpine.initTree(adminBody);
                    adminDrawer.classList.remove('pointer-events-none', 'invisible');
                    adminDrawer.setAttribute('aria-hidden', 'false');
                    adminBackdrop.classList.add('opacity-100');
                    adminBackdrop.classList.remove('opacity-0');
                    adminPanel.classList.add('translate-x-0');
                    adminPanel.classList.remove('translate-x-full');
                    window.requestAnimationFrame(() => window.requestAnimationFrame(() => {
                        const messageList = adminBody.querySelector('[data-ticket-message-list]');
                        if (messageList) messageList.scrollTop = messageList.scrollHeight;
                    }));
                };
                const closeAdminTicket = () => {
                    adminDrawer.classList.add('pointer-events-none', 'invisible');
                    adminDrawer.setAttribute('aria-hidden', 'true');
                    adminBackdrop.classList.remove('opacity-100');
                    adminBackdrop.classList.add('opacity-0');
                    adminPanel.classList.remove('translate-x-0');
                    adminPanel.classList.add('translate-x-full');
                };
                adminCloseButton.addEventListener('click', closeAdminTicket);
                adminBackdrop.addEventListener('click', closeAdminTicket);
            }

            const drawer = document.getElementById('ticket-review-drawer');
            if (!drawer || drawer.dataset.ready) return;
            drawer.dataset.ready = 'true';
            const panel = drawer.querySelector('[data-ticket-review-panel]');
            const body = drawer.querySelector('[data-ticket-review-body]');
            const backdrop = drawer.querySelector('[data-ticket-review-backdrop]');
            const closeButton = drawer.querySelector('[data-ticket-review-close]');
            const close = () => {
                panel.classList.add('translate-x-full');
                backdrop.classList.remove('opacity-100');
                backdrop.classList.add('opacity-0');
                drawer.classList.add('invisible', 'pointer-events-none');
                drawer.setAttribute('aria-hidden', 'true');
            };
            const open = (templateId) => {
                const template = document.getElementById(templateId);
                if (!template) return;
                body.replaceChildren(template.content.cloneNode(true));
                drawer.classList.remove('invisible', 'pointer-events-none');
                drawer.setAttribute('aria-hidden', 'false');
                requestAnimationFrame(() => {
                    panel.classList.remove('translate-x-full');
                    backdrop.classList.remove('opacity-0');
                    backdrop.classList.add('opacity-100');
                });
            };
            document.addEventListener('click', (event) => {
                const link = event.target.closest('[data-ticket-review-link]');
                if (!link) return;
                if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

                if (link.dataset.ticketStatus === '{{ \App\Models\Ticket::STATUS_RESOLVED }}') {
                    event.preventDefault();
                    const detailTemplateId = link.dataset.ticketDetailsLink;
                    const adminDrawer = document.getElementById('admin-ticket-drawer');
                    if (!adminDrawer || !detailTemplateId) return;
                    const adminPanel = adminDrawer.querySelector('[data-admin-ticket-panel]');
                    const adminBody = adminDrawer.querySelector('[data-admin-ticket-body]');
                    const adminBackdrop = adminDrawer.querySelector('[data-admin-ticket-backdrop]');
                    const template = document.getElementById(detailTemplateId);
                    if (!template) return;
                    adminBody.innerHTML = '';
                    adminBody.appendChild(template.content.cloneNode(true));
                    if (window.Alpine) window.Alpine.initTree(adminBody);
                    adminDrawer.classList.remove('pointer-events-none', 'invisible');
                    adminDrawer.setAttribute('aria-hidden', 'false');
                    adminBackdrop.classList.add('opacity-100');
                    adminBackdrop.classList.remove('opacity-0');
                    adminPanel.classList.add('translate-x-0');
                    adminPanel.classList.remove('translate-x-full');
                    return;
                }

                event.preventDefault();
                const row = link.closest('[data-ticket-review-row]');
                if (row?.dataset.unread === 'true') {
                    row.dataset.unread = 'false';
                    row.classList.remove('bg-primary-soft/70');
                    row.querySelector('[data-unread-badge]')?.remove();
                    row.querySelector('a.surface')?.classList.replace('bg-primary-soft', 'bg-card');
                    row.querySelector('td:first-child')?.classList.remove('font-bold');
                    row.querySelectorAll('td:nth-child(2), td:nth-child(3), td:nth-child(4), td:nth-child(5)').forEach((cell) => {
                        cell.classList.remove('text-black');
                        cell.classList.add('text-muted-foreground');
                    });
                }
                if (row?.dataset.readUrl) {
                    fetch(row.dataset.readUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    }).catch(() => {});
                }
                open(link.dataset.ticketReviewLink);
            });
            closeButton.addEventListener('click', close);
            backdrop.addEventListener('click', close);
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !drawer.classList.contains('invisible')) close();
            });
        })();
    </script>
</x-app-layout>
