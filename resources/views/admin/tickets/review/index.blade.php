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

                <div class="grid grid-cols-[minmax(0,3fr)_minmax(0,2fr)] gap-2 sm:flex sm:flex-wrap sm:items-center sm:gap-3">
                    <div class="flex min-w-0 items-center gap-2">
                        <label for="pending-category" class="hidden sm:block shrink-0 text-sm font-medium">Filter</label>
                        @php
                            $selectedCategoryId = (string) request('category_filter', '');
                            $selectedCategory = $categories->first(fn ($category) => (string) $category->id === $selectedCategoryId);
                            $longestCategory = $categories->sortByDesc(fn ($category) => strlen($category->name))->first()?->name ?? 'All categories';
                        @endphp
                        <details x-data="{}" class="group relative w-full sm:w-56 sm:shrink-0" x-on:click.outside="$el.removeAttribute('open')">
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

                    <div class="flex min-w-0 items-center gap-2">
                        <label for="pending-sort" class="hidden sm:block shrink-0 text-sm font-medium">Sort by date</label>
                        <details x-data="{}" class="group relative w-full sm:w-32 sm:shrink-0" x-on:click.outside="$el.removeAttribute('open')">
                                                        <summary id="pending-sort" class="flex h-9 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden">
                                <span class="truncate">{{ request('sort') === 'oldest' ? 'Oldest first' : 'Newest first' }}</span>
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
                    <li>
                        <x-ticket-card :item="$ticket" role="admin" :first="$loop->first" :last="$loop->last">
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
                            <tr class="align-top transition-colors hover:bg-primary-soft {{ $isUnread ? 'bg-primary-soft/70' : '' }}">
                                <td class="whitespace-nowrap px-2 py-2 font-mono font-semibold text-primary sm:px-3">
                                    <a href="{{ route('admin.complaints.show', $ticket->complaint) }}" class="hover:underline">{{ $ticket->complaint->reference_number }}</a>
                                </td>
                                <td class="px-2 py-2 sm:px-3 {{ $isUnread ? 'text-black' : 'text-muted-foreground' }}">
                                    <a href="{{ route('admin.complaints.show', $ticket->complaint) }}" class="block truncate hover:text-primary hover:underline">{{ $ticket->complaint->subject_title }}</a>
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

        <x-admin-ticket-drawer />
    </div>
</x-app-layout>
