@props(['ticket', 'viewerRole' => 'student'])

@php
    $thread = $ticket->thread;
    $messages = $thread?->messages()->with('sender')->orderBy('created_at')->orderBy('id')->get() ?? collect([]);
    $isPendingReview = $ticket->status === 'pending' && $ticket->classification === null;
    $conversationHeight = $isPendingReview
        ? 'min-h-[10rem]'
        : ($viewerRole === 'admin' ? 'h-[28rem] min-h-0' : 'h-[28rem] min-h-0');
    $recipient = $ticket->currentHandler
        ?? $ticket->complaint?->category?->recipient?->user;
    $recipientName = trim((string) ($recipient?->name ?? 'Agatha Nicole'));
    $recipientFirstName = collect(explode(' ', $recipientName))->filter()->take(2)->join(' ') ?: 'Agatha Nicole';
@endphp

<h2 id="in-ticket-communication" class="mb-0.5 font-display text-base font-bold">In-Ticket Communication</h2>

<style>
    .communication-card .message-scrollbar {
        scrollbar-width: none !important;
        min-height: 0;
        overflow-y: scroll !important;
    }

    .communication-card:hover .message-scrollbar,
    .communication-card:focus .message-scrollbar,
    .communication-card:focus-within .message-scrollbar {
        scrollbar-width: thin !important;
        scrollbar-color: oklch(0.62 0 0) oklch(0.92 0 0) !important;
    }

    .communication-card .message-scrollbar:not(.overflow-visible) {
        flex: 1 1 auto;
        margin-right: -0.75rem;
        padding-right: 0.75rem;
        min-height: 0;
        max-height: 100%;
        overflow-y: scroll !important;
        overscroll-behavior: contain;
    }

    @media (min-width: 640px) {
        .communication-card .message-scrollbar:not(.overflow-visible) {
            margin-right: -1.25rem;
            padding-right: 1.25rem;
        }
    }

    .communication-card.admin-communication-card .message-scrollbar:not(.overflow-visible) {
        margin-right: -0.75rem;
        padding-right: 1rem;
    }

    .communication-card .message-scrollbar:not(.overflow-visible) > [data-message-row] {
        flex-shrink: 0;
    }

    .communication-card .message-scrollbar::-webkit-scrollbar {
        width: 0 !important;
        height: 0 !important;
    }

    .communication-card:hover .message-scrollbar::-webkit-scrollbar,
    .communication-card:focus .message-scrollbar::-webkit-scrollbar,
    .communication-card:focus-within .message-scrollbar::-webkit-scrollbar {
        width: 8px !important;
        height: 8px !important;
    }

    .communication-card.admin-communication-card .message-scrollbar::-webkit-scrollbar {
        width: 0 !important;
        height: 0 !important;
    }

    .communication-card.admin-communication-card:hover .message-scrollbar::-webkit-scrollbar,
    .communication-card.admin-communication-card:focus .message-scrollbar::-webkit-scrollbar,
    .communication-card.admin-communication-card:focus-within .message-scrollbar::-webkit-scrollbar {
        width: 2px !important;
        height: 2px !important;
    }

    .communication-card .message-scrollbar::-webkit-scrollbar-button {
        display: none !important;
        width: 0 !important;
        height: 0 !important;
        min-width: 0 !important;
        min-height: 0 !important;
        background: transparent !important;
        border: 0 !important;
        -webkit-appearance: none !important;
    }

    .communication-card .message-scrollbar::-webkit-scrollbar-button:single-button,
    .communication-card .message-scrollbar::-webkit-scrollbar-button:start:decrement,
    .communication-card .message-scrollbar::-webkit-scrollbar-button:end:increment,
    .communication-card .message-scrollbar::-webkit-scrollbar-button:vertical:start:decrement,
    .communication-card .message-scrollbar::-webkit-scrollbar-button:vertical:end:increment {
        display: none !important;
        width: 0 !important;
        height: 0 !important;
    }

    .communication-card .message-scrollbar::-webkit-scrollbar-track,
    .communication-card .message-scrollbar::-webkit-scrollbar-corner {
        background: oklch(0.92 0 0) !important;
        border-radius: 999px !important;
    }

    .communication-card .message-scrollbar::-webkit-scrollbar-thumb {
        min-height: 24px !important;
        border-radius: 999px !important;
        background: oklch(0.62 0 0) !important;
    }

</style>

    <div class="surface communication-card flex min-w-0 {{ $conversationHeight }} min-h-0 flex-col overflow-hidden p-4 pt-3 sm:p-4 sm:pt-3 {{ $viewerRole === 'admin' ? 'admin-communication-card' : '' }}" tabindex="0">
        <!-- Messages -->
        <div id="ticket-message-list-{{ $ticket->id }}" data-ticket-message-list class="message-scrollbar relative mt-1 min-w-0 {{ $viewerRole === 'admin' ? 'admin-message-scrollbar' : '' }} {{ $isPendingReview ? 'flex-none overflow-visible' : 'flex flex-col justify-end overflow-y-scroll' }} space-y-2 pr-1">
                @if ($isPendingReview)
                <div class="grid min-h-[10rem] place-items-center px-6 text-center">
                        <div class="max-w-sm">
                            <p class="text-sm leading-relaxed text-muted-foreground">Ticket under review of Administrator. Messaging will be enabled once classified as Needs Resolution ticket.</p>
                        </div>
                    </div>
                @endif
                @foreach ($messages as $message)
                    @php
                        $isOwnMessage = (int) $message->sender_id === (int) Auth::id();
                        $isLastMessage = $loop->last;
                        $messageTime = $message->created_at?->copy()->setTimezone('Asia/Manila');
                        $messageAgeInMinutes = $messageTime?->diffInMinutes(now());
                        $messageTimeLabel = $messageTime
                            ? ($messageAgeInMinutes < 60
                                ? $messageTime->diffForHumans()
                                : ($messageTime->isSameDay(now()->setTimezone('Asia/Manila'))
                                    ? $messageTime->format('g:i A')
                                    : $messageTime->format('M d, g:i A')))
                            : 'Unknown';
                            $senderFirstName = $message->sender?->given_name ?: 'Unknown';
                    @endphp
                    <div data-message-row class="flex w-full min-w-0 items-end gap-2 {{ $isOwnMessage ? 'justify-end' : 'justify-start' }}">
                        @unless ($isOwnMessage)
                            <div class="relative" x-data="{ senderProfileOpen: false }" x-on:click.outside="senderProfileOpen = false">
                                <button type="button" data-message-avatar class="grid h-6 w-6 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-soft text-[9px] font-bold text-primary" x-on:click="senderProfileOpen = !senderProfileOpen" aria-label="View sender profile">
                                    @if ($message->sender?->avatar_path)
                                        <img src="{{ asset('storage/' . $message->sender->avatar_path) }}" alt="" class="h-full w-full object-cover">
                                    @else
                                        {{ collect(explode(' ', $message->sender->name ?? 'Unknown'))->filter()->map(fn ($part) => substr($part, 0, 1))->take(2)->join('') }}
                                    @endif
                                </button>
                                @if ($message->sender?->recipient)
                                    <span x-show="senderProfileOpen" x-cloak data-keep-in-view class="brand-gradient absolute bottom-0 left-10 z-30 w-72 max-w-[calc(100vw-2rem)] rounded-xl p-4 text-left text-primary-foreground shadow-lg">
                                        <span class="flex items-center gap-3">
                                            <span class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-foreground/15 text-sm font-bold ring-2 ring-primary-foreground/30">
                                                @if ($message->sender->avatar_path)
                                                    <img src="{{ asset('storage/' . $message->sender->avatar_path) }}" alt="" class="h-full w-full object-cover">
                                                @else
                                                    {{ collect(explode(' ', $message->sender->name ?? 'Unknown'))->filter()->map(fn ($part) => substr($part, 0, 1))->take(2)->join('') }}
                                                @endif
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block wrap-break-word text-sm font-bold">{{ $message->sender->display_name }}</span>
                                                <span class="mt-1 block wrap-break-word text-[11px] leading-relaxed opacity-95">ID {{ $message->sender->recipient->staff_id }} · {{ $message->sender->recipient->department }} · {{ $message->sender->recipient->designation }}</span>
                                            </span>
                                        </span>
                                        <span class="mt-3 inline-flex rounded-full border border-primary-foreground/40 bg-primary-foreground/15 px-3 py-1 text-[10px] font-semibold">Recipient</span>
                                    </span>
                                @endif
                            </div>
                        @endunless
                        <div data-message-item class="flex w-full min-w-0 flex-col {{ $isOwnMessage ? 'ml-auto max-w-[85%] items-end sm:max-w-2/3' : 'max-w-[85%] items-start sm:max-w-2/3' }}">
                            <p data-message-sender hidden class="mb-0 px-1 text-[10px] font-semibold text-muted-foreground {{ $isOwnMessage ? 'text-right' : 'text-left' }}">{{ $senderFirstName }}</p>
                            <div data-message-bubble class="{{ $isOwnMessage ? 'ml-auto' : '' }} mb-0.5">
                                @if (filled($message->content))
                                    <div class="relative {{ $isOwnMessage ? 'rounded-[18px] rounded-br-[5px] bg-primary text-primary-foreground after:absolute after:right-[-4px] after:bottom-0 after:h-2.5 after:w-2.5 after:bg-primary after:[clip-path:polygon(0_0,100%_100%,0_100%)]' : 'rounded-[18px] rounded-bl-[5px] bg-muted text-foreground after:absolute after:bottom-0 after:left-[-4px] after:h-2.5 after:w-2.5 after:bg-muted after:[clip-path:polygon(0_100%,100%_0,100%_100%)]' }} w-fit max-w-full px-3.5 py-2 {{ $viewerRole === 'admin' ? 'text-xs' : 'text-sm' }} leading-normal">
                                        <p class="wrap-break-word whitespace-pre-line">{{ trim($message->content) }}</p>
                                    </div>
                                @endif
                                @if ($message->file_attachment)
                                    @php
                                        $attachmentName = $message->file_attachment_name ?? basename($message->file_attachment);
                                        $attachmentExtension = strtolower(pathinfo($attachmentName, PATHINFO_EXTENSION));
                                        $isImageAttachment = in_array($attachmentExtension, ['jpg', 'jpeg', 'png', 'heic']);
                                    @endphp
                                    <a href="{{ asset('storage/' . $message->file_attachment) }}" target="_blank" class="group mt-2 inline-flex w-fit max-w-full items-start rounded-xl border border-border bg-gray-200 p-1.5 text-black hover:border-primary hover:bg-primary-soft">
                                        <span class="mr-2 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-gray-400 text-primary">
                                            @if ($isImageAttachment)
                                                <x-icons.image class="h-3.5 w-3.5 fill-muted text-gray-700 transition-colors" />
                                            @else
                                                <x-icons.file-text class="h-3.5 w-3.5 fill-muted text-gray-700 transition-colors" />
                                            @endif
                                        </span>
                                        <span class="min-w-0 break-all {{ $viewerRole === 'admin' ? 'text-xs' : 'text-sm' }} font-semibold">{{ $message->file_attachment_name ?? basename($message->file_attachment) }}</span>
                                    </a>
                                @endif
                            </div>
                            <p data-message-time hidden class="mt-0 px-1 pb-0.5 text-[10px] leading-none text-muted-foreground {{ $isOwnMessage ? 'text-right' : 'text-left' }}">{{ $messageTimeLabel }}</p>
                        </div>
                        @if ($isOwnMessage)
                            <div data-message-avatar class="relative grid h-6 w-6 shrink-0 place-items-center overflow-hidden rounded-full bg-primary text-[9px] font-bold text-primary-foreground" aria-hidden="true">
                                @if (Auth::user()->avatar_path)
                                    <img src="{{ asset('storage/' . Auth::user()->avatar_path) }}" alt="" class="h-full w-full object-cover">
                                @else
                                    {{ collect(explode(' ', Auth::user()->name))->filter()->map(fn ($part) => substr($part, 0, 1))->take(2)->join('') }}
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
        </div>

        <!-- Reply Form -->
        @php
            $isClosedOrResolved = in_array($ticket->status, ['closed', 'resolved', 'rejected'], true);
            $isReadOnly = $isClosedOrResolved || $isPendingReview;
            $messagePlaceholder = match (true) {
                $isPendingReview => 'Waiting for admin review',
                $ticket->status === 'resolved' => 'This ticket is resolved',
                $ticket->status === 'closed' => 'This ticket is closed',
                $ticket->status === 'rejected' => 'This ticket was rejected',
                default => 'Type your message...',
            };
            $replyRouteName = match($viewerRole) {
                'admin' => 'admin.complaints.reply',
                'recipient' => 'recipient.complaints.reply',
                default => 'student.complaints.reply',
            };
        @endphp
            <div class="mt-2">
                <form id="ticket-reply-form" method="POST" action="{{ route($replyRouteName, $ticket->complaint->id) }}#in-ticket-communication" enctype="multipart/form-data" class="flex w-full min-w-0 items-end gap-2 {{ $isReadOnly ? 'opacity-60' : '' }}" data-avatar-url="{{ Auth::user()->avatar_path ? asset('storage/' . Auth::user()->avatar_path) : '' }}" data-avatar-initials="{{ collect(explode(' ', Auth::user()->name))->filter()->map(fn ($part) => substr($part, 0, 1))->take(2)->join('') }}">
                    @csrf
                    
                    <div class="min-w-0 flex flex-1 flex-col justify-center rounded-3xl border border-border bg-muted transition-colors focus-within:border-primary focus-within:ring-1 focus-within:ring-primary/50">
                        <div id="ticket-file-preview" class="relative mx-2 mt-2 hidden w-fit max-w-full items-center gap-2 rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground">
                            <x-icons.file-text class="h-4 w-4 shrink-0 text-muted-foreground" />
                            <span id="ticket-file-name" class="min-w-0 truncate"></span>
                            <button id="ticket-file-remove" type="button" class="absolute -top-2 -right-2 grid h-6 w-6 place-items-center rounded-full border border-border bg-background text-muted-foreground shadow-sm hover:text-foreground" aria-label="Remove selected file" title="Remove selected file">
                                <span aria-hidden="true" class="text-lg leading-none">&times;</span>
                            </button>
                        </div>
                        <textarea
                            name="content"
                            rows="1"
                            placeholder="{{ $messagePlaceholder }}"
                            @disabled($isReadOnly)
                            class="block min-h-10 w-full resize-none rounded-3xl border-0 bg-transparent px-4 py-2.5 text-[15px] leading-5 text-foreground placeholder-muted-foreground focus:outline-none focus:ring-0 disabled:cursor-not-allowed"
                        ></textarea>
                    </div>

                    <label class="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-border text-muted-foreground transition-colors {{ $isReadOnly ? 'cursor-not-allowed' : 'cursor-pointer hover:border-primary hover:bg-primary-soft hover:text-primary' }}" title="Attach a file">
                        <input type="file" name="file_attachment" accept=".pdf,.docx,.jpg,.jpeg,.png,.heic" class="hidden" @disabled($isReadOnly)>
                        <x-icons.paperclip class="h-[18px] w-[18px]" />
                    </label>

                    <button type="submit" @disabled($isReadOnly) class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-primary text-primary-foreground transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:bg-muted disabled:text-muted-foreground" aria-label="Send message" title="Send message">
                        <x-icons.send class="h-[18px] w-[18px]" />
                    </button>
                </form>
                <script>
                    {
                    const initialMessageList = document.getElementById('ticket-message-list-{{ $ticket->id }}');
                    const replyForm = document.getElementById('ticket-reply-form');
                    const fileField = replyForm?.elements.file_attachment;
                    const filePreview = document.getElementById('ticket-file-preview');
                    const fileName = document.getElementById('ticket-file-name');
                    const fileRemove = document.getElementById('ticket-file-remove');
                    const formatMessageTime = (date) => {
                        const ageInMinutes = Math.floor((Date.now() - date.getTime()) / 60000);
                        if (ageInMinutes < 60) return ageInMinutes <= 0 ? 'just now' : `${ageInMinutes} minute${ageInMinutes === 1 ? '' : 's'} ago`;
                        const now = new Date();
                        if (date.toDateString() === now.toDateString()) return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
                    };
                    const scrollToLatestMessage = () => {
                        if (initialMessageList) initialMessageList.scrollTop = initialMessageList.scrollHeight;
                    };
                    if (initialMessageList) {
                        @if ($viewerRole === 'admin')
                            window.requestAnimationFrame(() => window.requestAnimationFrame(scrollToLatestMessage));
                        @else
                            scrollToLatestMessage();
                        @endif
                    }
                    fileField?.addEventListener('change', () => {
                        const selectedFile = fileField.files?.[0];
                        fileName.textContent = selectedFile?.name ?? '';
                        filePreview.classList.toggle('hidden', !selectedFile);
                        filePreview.classList.toggle('inline-flex', Boolean(selectedFile));
                        if (selectedFile) replyForm.requestSubmit();
                    });
                    fileRemove?.addEventListener('click', () => {
                        fileField.value = '';
                        fileName.textContent = '';
                        filePreview.classList.add('hidden');
                        filePreview.classList.remove('inline-flex');
                    });
                    document.addEventListener('click', (event) => {
                        const clickedBubble = event.target.closest('[data-message-bubble]');
                        const clickedMessage = clickedBubble?.closest('[data-message-item]');
                        const messageMetadata = document.querySelectorAll('[data-message-sender], [data-message-time]');
                        const selectedRow = clickedMessage?.closest('[data-message-row]');
                        const wasSelected = clickedMessage?.querySelector('[data-message-sender]')?.hidden === false;
                        const showSelected = Boolean(clickedMessage && !wasSelected);

                        const rowHeight = selectedRow?.offsetHeight ?? 0;

                        messageMetadata.forEach((messageMeta) => {
                            messageMeta.hidden = !(showSelected && messageMeta.closest('[data-message-item]') === clickedMessage);
                        });

                        if (showSelected) {
                            window.requestAnimationFrame(() => {
                                const expansion = (selectedRow?.offsetHeight ?? rowHeight) - rowHeight;
                                if (initialMessageList && expansion > 0) {
                                    initialMessageList.scrollTop += expansion + 8;
                                }
                            });
                        } else {
                            messageMetadata.forEach((messageMeta) => { messageMeta.hidden = true; });
                        }
                    });
                    let scrollTimeout;
                    initialMessageList?.addEventListener('scroll', () => {
                        initialMessageList.classList.add('is-scrolling');
                        clearTimeout(scrollTimeout);
                        scrollTimeout = setTimeout(() => initialMessageList.classList.remove('is-scrolling'), 700);
                    });

                    const escapeHtml = (value = '') => String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\"/g, '&quot;').replace(/'/g, '&#039;');

                    replyForm?.addEventListener('submit', async (event) => {
                        event.preventDefault();

                        const form = event.currentTarget;
                        const messageField = form.elements.content;
                        const message = messageField.value.trim();
                        const fileField = form.elements.file_attachment;
                        const hasAttachment = fileField?.files?.length > 0;
                        const sendButton = form.querySelector('button[type="submit"]');
                        const formData = new FormData(form);

                        if ((!message && !hasAttachment) || sendButton.disabled) return;

                        sendButton.disabled = true;
                        messageField.disabled = true;

                        try {
                            const response = await fetch(form.action, {
                                method: 'POST',
                                body: formData,
                                headers: { 'X-Requested-With': 'XMLHttpRequest' }
                            });

                            if (!response.ok) throw new Error('Message could not be sent');

                            const messageContent = message.trim();
                            const selectedFile = fileField?.files?.[0];
                            const attachmentName = selectedFile?.name ?? '';
                            const avatarUrl = form.dataset.avatarUrl || '';
                            const avatarInitials = form.dataset.avatarInitials || '';

                            const row = document.createElement('div');
                            row.dataset.messageRow = '';
                            row.className = 'flex w-full min-w-0 items-end gap-2 justify-end';

                            const escapedContent = escapeHtml(messageContent);

                            const contentMarkup = messageContent
                                ? `<div class="relative rounded-[18px] rounded-br-[5px] bg-primary text-primary-foreground after:absolute after:right-[-4px] after:bottom-0 after:h-2.5 after:w-2.5 after:bg-primary after:[clip-path:polygon(0_0,100%_100%,0_100%)] w-fit max-w-full px-3.5 py-2 text-sm leading-normal"><p class="wrap-break-word whitespace-pre-line">${escapedContent}</p></div>`
                                : '';

                            const attachmentMarkup = attachmentName
                                ? `<a href="#" class="group mt-2 inline-flex w-fit max-w-full items-start rounded-xl border border-border bg-gray-200 p-1.5 text-black hover:border-primary hover:bg-primary-soft"><span class="mr-2 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-gray-400 text-primary">FILE</span><span class="min-w-0 break-all text-sm font-semibold">${escapeHtml(attachmentName)}</span></a>`
                                : '';

                            const avatarMarkup = avatarUrl
                                ? `<img src="${avatarUrl}" alt="" class="h-full w-full object-cover">`
                                : `<span class="text-[9px] font-bold">${escapeHtml(avatarInitials)}</span>`;

                            row.innerHTML = `<div data-message-item class="flex w-full min-w-0 flex-col ml-auto max-w-[85%] items-end sm:max-w-2/3">
                                <p data-message-sender hidden class="mb-0 px-1 text-[10px] font-semibold text-muted-foreground text-right"></p>
                                <div data-message-bubble class="ml-auto mb-0.5">
                                    ${contentMarkup}
                                    ${attachmentMarkup}
                                </div>
                                <p data-message-time hidden class="mt-0 px-1 pb-0.5 text-[10px] leading-none text-muted-foreground text-right">just now</p>
                            </div>
                            <div data-message-avatar class="relative grid h-6 w-6 shrink-0 place-items-center overflow-hidden rounded-full bg-primary text-[9px] font-bold text-primary-foreground" aria-hidden="true">
                                ${avatarMarkup}
                            </div>`;

                            initialMessageList?.appendChild(row);
                            initialMessageList.scrollTop = initialMessageList.scrollHeight;

                            fileField.value = '';
                            fileName.textContent = '';
                            filePreview.classList.add('hidden');
                            filePreview.classList.remove('inline-flex');

                            messageField.value = '';
                            sendButton.disabled = false;
                            messageField.disabled = false;
                            messageField.focus();
                        } catch (error) {
                            messageField.value = message;
                            sendButton.disabled = false;
                            messageField.disabled = false;
                            messageField.focus();
                        }
                    });
                    }
                </script>
            </div>
    </div>
