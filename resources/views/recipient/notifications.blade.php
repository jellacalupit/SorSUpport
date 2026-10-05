<x-app-layout :role="'recipient'" title="Notifications">
    <section>
        <form method="POST" action="{{ route('recipient.notifications.delete-selected') }}" class="space-y-2">
            @csrf
            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <input type="checkbox" id="select-all-recipient-notifications" class="h-4 w-4 shrink-0 rounded-[6px] accent-red-800" aria-label="Select all notifications" title="Select all notifications">
                    <label for="select-all-recipient-notifications" class="truncate text-xs font-medium text-muted-foreground">{{ $notifications->count() }} notification(s) · {{ $unread }} unread</label>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <button type="submit" formaction="{{ route('recipient.notifications.read-all') }}" formmethod="POST" class="mark-selected-read-btn inline-flex h-9 w-9 shrink-0 items-center justify-center gap-2 rounded-md border border-emerald-800 bg-emerald-800 text-xs font-medium text-white transition-colors hover:bg-emerald-700 disabled:pointer-events-none disabled:opacity-50 sm:w-auto sm:px-3" aria-label="Mark as read" title="Mark as read" disabled>
                        <x-icons.mail-open class="h-4 w-4" />
                        <span class="hidden sm:inline">Mark as Read</span>
                    </button>
                    <button type="submit" class="delete-selected-btn inline-flex h-9 w-9 shrink-0 items-center justify-center gap-2 rounded-md border border-red-800 bg-red-800 text-xs font-medium text-white transition-colors hover:bg-red-900 disabled:pointer-events-none disabled:opacity-50 sm:w-auto sm:px-3" aria-label="Delete" title="Delete" disabled>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                            <path d="M3 6h18"/>
                            <path d="M8 6V4h8v2"/>
                            <path d="M19 6l-1 14H6L5 6"/>
                            <path d="M10 11v6"/>
                            <path d="M14 11v6"/>
                        </svg>
                        <span class="hidden sm:inline">Delete</span>
                    </button>
                </div>
            </div>

            <ul class="grid gap-0.5">
                @forelse ($notifications as $notification)
                    <li class="flex items-center gap-3">
                        <input type="checkbox" name="notification_ids[]" value="{{ $notification['id'] }}" class="notification-checkbox h-4 w-4 shrink-0 rounded-[6px] accent-red-800" aria-label="Select notification">
                        <button type="submit" formaction="{{ route('recipient.notifications.open', $notification['id']) }}" formmethod="POST" class="relative grid w-full grid-cols-[auto_minmax(0,1fr)] items-start gap-2 overflow-hidden rounded-xl p-2.5 text-left transition-transform duration-200 ease-out hover:scale-[1.01] {{ $notification['read'] ? 'border border-border bg-card hover:bg-muted/80' : 'border-0 bg-primary-soft hover:bg-primary-soft/90' }}">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full {{ $notification['read'] ? 'bg-muted text-muted-foreground' : 'bg-[#7d1f2a] text-white' }}">
                                <x-icons.mail-open class="h-4 w-4" />
                            </span>
                            <div class="min-w-0 wrap-break-word pr-20">
                                <p class="truncate text-sm font-semibold sm:pr-24">{{ $notification['title'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ $notification['body'] }}</p>
                            </div>
                            <p class="absolute right-2 top-2.5 whitespace-nowrap text-[11px] text-muted-foreground">{{ $notification['displayAt'] }}</p>
                        </button>
                    </li>
                @empty
                    <li class="flex flex-col items-center gap-2 rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">
                        <x-icons.bell class="h-5 w-5" /> No notifications yet.
                    </li>
                @endforelse
            </ul>
        </form>
    </section>
</x-app-layout>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const selectAll = document.getElementById('select-all-recipient-notifications');
        const checkboxes = document.querySelectorAll('.notification-checkbox');
        const markSelectedReadBtn = document.querySelector('.mark-selected-read-btn');
        const deleteSelectedBtn = document.querySelector('.delete-selected-btn');

        const syncControls = function () {
            const selectedCount = Array.from(checkboxes).filter((checkbox) => checkbox.checked).length;
            const hasSelection = selectedCount > 0;

            if (markSelectedReadBtn) {
                markSelectedReadBtn.disabled = !hasSelection;
            }

            if (deleteSelectedBtn) {
                deleteSelectedBtn.disabled = !hasSelection;
            }

            if (selectAll) {
                selectAll.checked = checkboxes.length > 0 && Array.from(checkboxes).every((checkbox) => checkbox.checked);
            }
        };

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                checkboxes.forEach((checkbox) => {
                    checkbox.checked = selectAll.checked;
                });
                syncControls();
            });
        }

        checkboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', syncControls);
        });

        syncControls();
    });
</script>
