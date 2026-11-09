@props(['ticket', 'viewerRole' => 'student'])

@php
    $thread = $ticket->thread;
    $messages = $thread?->messages()->with('sender')->orderBy('created_at')->orderBy('id')->get() ?? collect([]);
    // A ticket under review has no conversation yet, unless the SDS Office asked for more details.
    $isPendingReview = $ticket->isAwaitingReview() && ! $ticket->hasConversation();
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
        min-height: 0;
        overflow-y: scroll !important;
    }

    .communication-card .message-scrollbar:not(.overflow-visible) {
        flex: 1 1 0%;
        margin-right: -0.75rem;
        padding-right: 0.75rem;
        min-height: 0;
        max-height: 100%;
        overflow-y: scroll !important;
        justify-content: safe flex-end;
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
                            $senderFirstName = $message->display_sender?->given_name ?: 'Unknown';
                    @endphp
                    <div data-message-row class="flex w-full min-w-0 items-end gap-2 {{ $isOwnMessage ? 'justify-end' : 'justify-start' }}">
                        @unless ($isOwnMessage)
                            <div class="relative" x-data="{ senderProfileOpen: false }" x-on:click.outside="senderProfileOpen = false">
                                <button type="button" data-message-avatar class="grid h-6 w-6 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-soft text-[9px] font-bold text-primary" x-on:click="senderProfileOpen = !senderProfileOpen" aria-label="View sender profile">
                                    @if ($message->display_sender?->avatar_path)
                                        <img src="{{ asset('storage/' . $message->display_sender->avatar_path) }}" alt="" class="h-full w-full object-cover">
                                    @else
                                        {{ collect(explode(' ', $message->display_sender->name ?? 'Unknown'))->filter()->map(fn ($part) => substr($part, 0, 1))->take(2)->join('') }}
                                    @endif
                                </button>
                                @if ($message->display_sender?->recipient)
                                    <span x-show="senderProfileOpen" x-cloak data-keep-in-view class="brand-gradient absolute bottom-0 left-10 z-30 w-72 max-w-[calc(100vw-2rem)] rounded-xl p-4 text-left text-primary-foreground shadow-lg">
                                        <span class="flex items-center gap-3">
                                            <span class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-foreground/15 text-sm font-bold ring-2 ring-primary-foreground/30">
                                                @if ($message->display_sender->avatar_path)
                                                    <img src="{{ asset('storage/' . $message->display_sender->avatar_path) }}" alt="" class="h-full w-full object-cover">
                                                @else
                                                    {{ collect(explode(' ', $message->display_sender->name ?? 'Unknown'))->filter()->map(fn ($part) => substr($part, 0, 1))->take(2)->join('') }}
                                                @endif
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block wrap-break-word text-sm font-bold">{{ $message->display_sender->display_name }}</span>
                                                <span class="mt-1 block wrap-break-word text-[11px] leading-relaxed opacity-95">ID {{ $message->display_sender->recipient->staff_id }} · {{ $message->display_sender->recipient->unit }} · {{ $message->display_sender->recipient->designation }}</span>
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
                                        $isImageAttachment = in_array($attachmentExtension, ['jpg', 'jpeg', 'png', 'heic'], true);
                                        $attachmentSize = $message->file_attachment_size;
                                        $attachmentSizeLabel = $attachmentSize === null
                                            ? null
                                            : ($attachmentSize < 1048576
                                                ? number_format($attachmentSize / 1024, 1) . ' KB'
                                                : number_format($attachmentSize / 1048576, 2) . ' MB');
                                    @endphp
                                    <button
                                        type="button"
                                        data-thread-attachment-preview
                                        data-preview-url="{{ route('attachments.message', $message) }}?preview=1"
                                        data-download-url="{{ route('attachments.message', $message) }}"
                                        data-file-name="{{ $attachmentName }}"
                                        data-file-type="{{ $attachmentExtension }}"
                                        class="group mt-2 inline-flex w-fit max-w-full items-center rounded-xl border p-2 text-left transition-colors {{ $isOwnMessage ? 'border-primary/20 bg-primary/5 text-primary hover:border-primary/40 hover:bg-primary/10' : 'border-border bg-muted text-foreground hover:border-primary hover:bg-primary-soft' }}"
                                    >
                                        <span class="mr-2.5 grid h-8 w-8 shrink-0 place-items-center rounded-full {{ $isOwnMessage ? 'bg-primary/10 text-primary' : 'bg-background text-muted-foreground' }}">
                                            @if ($isImageAttachment)
                                                <x-icons.image class="h-4 w-4" />
                                            @else
                                                <x-icons.file-text class="h-4 w-4" />
                                            @endif
                                        </span>
                                        <span class="flex min-w-0 flex-col">
                                            <span class="max-w-56 truncate text-xs font-semibold">{{ $attachmentName }}</span>
                                            @if ($attachmentSizeLabel !== null)
                                                <span class="mt-0.5 text-[10px] leading-tight {{ $isOwnMessage ? 'text-primary/75' : 'text-muted-foreground' }}">{{ $attachmentSizeLabel }}</span>
                                            @endif
                                        </span>
                                    </button>
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
            $isReadOnly = ! $ticket->acceptsMessages();
            $messagePlaceholder = match (true) {
                $ticket->status === 'submitted' => 'Waiting for admin review',
                $ticket->status === 'resolved' => 'This ticket is resolved',
                $ticket->status === 'closed' => 'This ticket is closed',
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
                        <div id="ticket-file-preview" class="relative mx-2 mt-2 hidden w-fit max-w-full items-center gap-2 rounded-lg border border-border bg-background px-2.5 py-1.5 text-xs text-foreground">
                            <x-icons.file-text class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                            <span id="ticket-file-name" class="min-w-0 truncate"></span>
                            <button id="ticket-file-remove" type="button" class="absolute -top-2 -right-2 grid h-5 w-5 place-items-center rounded-full border border-border bg-background text-muted-foreground shadow-sm hover:text-foreground" aria-label="Remove selected file" title="Remove selected file">
                                <span aria-hidden="true" class="text-lg leading-none">&times;</span>
                            </button>
                        </div>
                        <textarea
                            name="content"
                            rows="1"
                            placeholder="{{ $messagePlaceholder }}"
                            @disabled($isReadOnly)
                            class="block min-h-9 w-full resize-none rounded-3xl border-0 bg-transparent px-3.5 py-2 {{ $viewerRole === 'admin' ? 'text-xs leading-normal placeholder:text-xs placeholder:leading-normal' : 'text-sm leading-5' }} text-foreground placeholder-muted-foreground focus:outline-none focus:ring-0 disabled:cursor-not-allowed"
                        ></textarea>
                    </div>

                    <label class="grid h-9 w-9 shrink-0 place-items-center rounded-full border border-border text-muted-foreground transition-colors {{ $isReadOnly ? 'cursor-not-allowed' : 'cursor-pointer hover:border-primary hover:bg-primary-soft hover:text-primary' }}" title="Attach a file">
                        <input type="file" name="file_attachment" accept=".pdf,.docx,.jpg,.jpeg,.png,.heic" class="hidden" @disabled($isReadOnly)>
                        <x-icons.paperclip class="h-4 w-4" />
                    </label>

                    <button type="submit" @disabled($isReadOnly) class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-muted text-muted-foreground transition-colors hover:bg-muted disabled:cursor-not-allowed" aria-label="Send message" title="Send message">
                        <x-icons.send class="h-4 w-4" />
                    </button>
                </form>
                <p id="ticket-reply-error" class="mt-1 hidden text-xs text-destructive" role="alert"></p>
                <div id="thread-attachment-modal-{{ $ticket->id }}" class="fixed inset-0 z-[100] flex hidden items-center justify-center bg-black/70 p-3 sm:p-6" role="dialog" aria-modal="true" aria-label="Attachment preview">
                    <div class="flex max-h-full w-full max-w-4xl flex-col overflow-hidden rounded-xl bg-card text-card-foreground shadow-xl">
                        <header class="flex min-w-0 items-center gap-3 border-b border-border px-4 py-3">
                            <h3 data-preview-title class="min-w-0 flex-1 truncate text-sm font-semibold"></h3>
                            <a data-preview-download class="shrink-0 text-xs font-semibold text-primary underline" href="#" download>Download</a>
                            <button type="button" data-close-thread-preview class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-muted-foreground hover:bg-muted" aria-label="Close preview">
                                <span aria-hidden="true" class="text-xl leading-none">&times;</span>
                            </button>
                        </header>
                        <div class="grid min-h-0 flex-1 place-items-center overflow-auto bg-muted p-2 sm:p-4">
                            <img data-preview-image alt="" class="hidden max-h-[75dvh] max-w-full object-contain">
                            <iframe data-preview-frame title="PDF preview" class="hidden h-[75dvh] w-full bg-white"></iframe>
                            <p data-preview-unavailable class="hidden max-w-md text-center text-sm text-muted-foreground">A preview is not available in this browser for this file type. Use Download to open it in a compatible app.</p>
                        </div>
                    </div>
                </div>
                <script>
                    {
                    const initialMessageList = document.getElementById('ticket-message-list-{{ $ticket->id }}');
                    const replyForm = document.getElementById('ticket-reply-form');
                    const fileField = replyForm?.elements.file_attachment;
                    const filePreview = document.getElementById('ticket-file-preview');
                    const fileName = document.getElementById('ticket-file-name');
                    const fileRemove = document.getElementById('ticket-file-remove');
                    const replyError = document.getElementById('ticket-reply-error');
                    const sendButton = replyForm?.querySelector('button[type="submit"]');
                    const updateSendButton = () => {
                        const messageField = replyForm?.elements.content;
                        const hasMessage = Boolean(messageField?.value.trim());
                        const hasAttachment = Boolean(fileField?.files?.length);
                        sendButton?.classList.toggle('bg-primary', hasMessage || hasAttachment);
                        sendButton?.classList.toggle('text-primary-foreground', hasMessage || hasAttachment);
                        sendButton?.classList.toggle('hover:bg-primary/90', hasMessage || hasAttachment);
                        sendButton?.classList.toggle('bg-muted', !hasMessage && !hasAttachment);
                        sendButton?.classList.toggle('text-muted-foreground', !hasMessage && !hasAttachment);
                        sendButton?.classList.toggle('hover:bg-muted', !hasMessage && !hasAttachment);
                    };
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
                        updateSendButton();
                        if (selectedFile) replyForm.requestSubmit();
                    });
                    replyForm?.elements.content?.addEventListener('input', updateSendButton);
                    fileRemove?.addEventListener('click', () => {
                        fileField.value = '';
                        fileName.textContent = '';
                        filePreview.classList.add('hidden');
                        filePreview.classList.remove('inline-flex');
                        updateSendButton();
                    });
                    updateSendButton();
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
                    const formatFileSize = (bytes) => bytes < 1048576
                        ? `${(bytes / 1024).toFixed(1)} KB`
                        : `${(bytes / 1048576).toFixed(2)} MB`;

                    const attachmentModal = document.getElementById('thread-attachment-modal-{{ $ticket->id }}');
                    const previewImage = attachmentModal?.querySelector('[data-preview-image]');
                    const previewFrame = attachmentModal?.querySelector('[data-preview-frame]');
                    const previewUnavailable = attachmentModal?.querySelector('[data-preview-unavailable]');
                    const previewDownload = attachmentModal?.querySelector('[data-preview-download]');
                    const previewTitle = attachmentModal?.querySelector('[data-preview-title]');
                    previewImage?.addEventListener('error', () => {
                        previewImage.classList.add('hidden');
                        previewUnavailable.classList.remove('hidden');
                    });
                    const closeAttachmentPreview = () => {
                        attachmentModal?.classList.add('hidden');
                        previewImage?.classList.add('hidden');
                        previewFrame?.classList.add('hidden');
                        previewUnavailable?.classList.add('hidden');
                        if (previewImage) previewImage.src = '';
                        if (previewFrame) previewFrame.src = 'about:blank';
                    };
                    document.addEventListener('click', (event) => {
                        if (event.target === attachmentModal) {
                            closeAttachmentPreview();
                            return;
                        }

                        const previewButton = event.target.closest('[data-thread-attachment-preview]');
                        if (previewButton) {
                            const type = previewButton.dataset.fileType;
                            const name = previewButton.dataset.fileName;
                            const previewUrl = previewButton.dataset.previewUrl;
                            const downloadUrl = previewButton.dataset.downloadUrl;

                            previewTitle.textContent = name;
                            previewDownload.href = downloadUrl;
                            previewImage.classList.add('hidden');
                            previewFrame.classList.add('hidden');
                            previewUnavailable.classList.add('hidden');
                            if (['jpg', 'jpeg', 'png', 'heic'].includes(type)) {
                                previewImage.src = previewUrl;
                                previewImage.classList.remove('hidden');
                            } else if (type === 'pdf') {
                                previewFrame.src = previewUrl;
                                previewFrame.classList.remove('hidden');
                            } else {
                                previewUnavailable.classList.remove('hidden');
                            }
                            attachmentModal.classList.remove('hidden');
                            return;
                        }
                        if (event.target.closest('[data-close-thread-preview]')) closeAttachmentPreview();
                    });
                    document.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape') closeAttachmentPreview();
                    });

                    const visualViewport = window.visualViewport;
                    let threadLayoutHeight = 0;
                    const threadCard = replyForm?.closest('.communication-card');
                    let threadCardBounds = null;
                    const setVisibleThreadHeight = () => {
                        if (!replyForm?.elements.content.matches(':focus')) return;
                        const viewportBottom = visualViewport
                            ? visualViewport.offsetTop + visualViewport.height
                            : window.innerHeight;
                        const keyboardOpen = threadLayoutHeight - viewportBottom > 120;
                        const wasKeyboardOpen = document.body.classList.contains('thread-keyboard-open');
                        if (keyboardOpen && !threadCardBounds && threadCard) {
                            const bounds = threadCard.getBoundingClientRect();
                            threadCardBounds = {
                                top: bounds.top,
                                left: bounds.left,
                                width: bounds.width,
                            };
                        }
                        document.body.classList.toggle('thread-keyboard-open', keyboardOpen);
                        if (keyboardOpen) {
                            document.documentElement.style.setProperty('--thread-card-top', `${threadCardBounds?.top ?? 0}px`);
                            document.documentElement.style.setProperty('--thread-card-left', `${threadCardBounds?.left ?? 0}px`);
                            document.documentElement.style.setProperty('--thread-card-width', `${threadCardBounds?.width ?? 0}px`);
                            document.documentElement.style.setProperty(
                                '--thread-keyboard-gap',
                                `${Math.max(0, threadLayoutHeight - viewportBottom)}px`
                            );
                        } else if (wasKeyboardOpen) {
                            document.documentElement.style.removeProperty('--thread-card-top');
                            document.documentElement.style.removeProperty('--thread-card-left');
                            document.documentElement.style.removeProperty('--thread-card-width');
                            document.documentElement.style.removeProperty('--thread-keyboard-gap');
                            threadCardBounds = null;
                        }
                    };
                    replyForm?.elements.content.addEventListener('focus', () => {
                        threadLayoutHeight = window.innerHeight;
                        const bounds = threadCard?.getBoundingClientRect();
                        threadCardBounds = bounds
                            ? { top: bounds.top, left: bounds.left, width: bounds.width }
                            : null;
                        setVisibleThreadHeight();
                        visualViewport?.addEventListener('resize', setVisibleThreadHeight);
                        visualViewport?.addEventListener('scroll', setVisibleThreadHeight);
                    });
                    replyForm?.elements.content.addEventListener('blur', () => {
                        visualViewport?.removeEventListener('resize', setVisibleThreadHeight);
                        visualViewport?.removeEventListener('scroll', setVisibleThreadHeight);
                        document.documentElement.style.removeProperty('--thread-card-top');
                        document.documentElement.style.removeProperty('--thread-card-left');
                        document.documentElement.style.removeProperty('--thread-card-width');
                        document.documentElement.style.removeProperty('--thread-keyboard-gap');
                        document.body.classList.remove('thread-keyboard-open');
                        threadCardBounds = null;
                        threadLayoutHeight = 0;
                    });

                    replyForm?.addEventListener('submit', async (event) => {
                        event.preventDefault();

                        const form = event.currentTarget;
                        const messageField = form.elements.content;
                        const message = messageField.value.trim();
                        const fileField = form.elements.file_attachment;
                        const hasAttachment = fileField?.files?.length > 0;
                        const formData = new FormData(form);

                        if ((!message && !hasAttachment) || sendButton.disabled) return;

                        sendButton.disabled = true;
                        messageField.disabled = true;
                        replyError.classList.add('hidden');
                        replyError.textContent = '';

                        try {
                            const response = await fetch(form.action, {
                                method: 'POST',
                                body: formData,
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });

                            const isJson = response.headers.get('content-type')?.includes('application/json');
                            const result = isJson ? await response.json() : null;
                            if (!response.ok) {
                                const validationError = Object.values(result?.errors ?? {}).flat()[0];
                                throw new Error(validationError || result?.message || 'Message could not be sent.');
                            }
                            if (!result?.message) {
                                throw new Error('Message could not be sent. Please reload the ticket and try again.');
                            }

                            const messageContent = result.message?.content ?? message;
                            const attachment = result.message?.attachment;
                            const attachmentName = attachment?.name ?? '';
                            const selectedFile = fileField?.files?.[0];
                            const avatarUrl = form.dataset.avatarUrl || '';
                            const avatarInitials = form.dataset.avatarInitials || '';

                            const row = document.createElement('div');
                            row.dataset.messageRow = '';
                            row.className = 'flex w-full min-w-0 items-end gap-2 justify-end';

                            const escapedContent = escapeHtml(messageContent);

                            const contentMarkup = messageContent
                                ? `<div class="relative rounded-[18px] rounded-br-[5px] bg-primary text-primary-foreground after:absolute after:right-[-4px] after:bottom-0 after:h-2.5 after:w-2.5 after:bg-primary after:[clip-path:polygon(0_0,100%_100%,0_100%)] w-fit max-w-full px-3.5 py-2 {{ $viewerRole === 'admin' ? 'text-xs' : 'text-sm' }} leading-normal"><p class="wrap-break-word whitespace-pre-line">${escapedContent}</p></div>`
                                : '';

                            const attachmentExtension = attachmentName.split('.').pop()?.toLowerCase() ?? '';
                            const isImageAttachment = ['jpg', 'jpeg', 'png', 'heic'].includes(attachmentExtension);
                            const attachmentSize = Number.isFinite(attachment?.size_bytes)
                                ? formatFileSize(attachment.size_bytes)
                                : selectedFile
                                    ? formatFileSize(selectedFile.size)
                                    : '';
                            const attachmentIcon = isImageAttachment
                                ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>'
                                : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/></svg>';
                            const previewUrl = attachment?.url ? `${attachment.url}?preview=1` : '';
                            const attachmentMarkup = attachment
                                ? `<button type="button" data-thread-attachment-preview data-preview-url="${escapeHtml(previewUrl)}" data-download-url="${escapeHtml(attachment.url)}" data-file-name="${escapeHtml(attachmentName)}" data-file-type="${escapeHtml(attachmentExtension)}" class="group mt-2 inline-flex w-fit max-w-full items-center rounded-xl border border-primary/20 bg-primary/5 p-2 text-left text-primary transition-colors hover:border-primary/40 hover:bg-primary/10"><span class="mr-2.5 grid h-8 w-8 shrink-0 place-items-center rounded-full bg-primary/10 text-primary">${attachmentIcon}</span><span class="flex min-w-0 flex-col"><span class="max-w-56 truncate text-xs font-semibold">${escapeHtml(attachmentName)}</span>${attachmentSize ? `<span class="mt-0.5 text-[10px] leading-tight text-primary/75">${attachmentSize}</span>` : ''}</span></button>`
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
                            updateSendButton();
                            messageField.focus();
                        } catch (error) {
                            messageField.value = message;
                            sendButton.disabled = false;
                            messageField.disabled = false;
                            replyError.textContent = error.message || 'Message could not be sent. Please try again.';
                            replyError.classList.remove('hidden');
                            updateSendButton();
                            messageField.focus();
                        }
                    });
                    }
                </script>
            </div>
    </div>
