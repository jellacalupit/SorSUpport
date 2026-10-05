<div class="grid gap-5 lg:grid-cols-[1.6fr_1fr]">
    <div>
        <div class="relative mb-4 flex items-center justify-between gap-3" x-data="{ ticketInfoOpen: false }" x-on:click.outside="ticketInfoOpen = false">
            <div class="flex min-w-0 items-center gap-2">
            <a href="{{ route('student.dashboard') }}" aria-label="Back to dashboard" class="grid h-9 w-9 shrink-0 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground">
                <span aria-hidden="true" class="scale-y-150 text-2xl leading-none">&lt;</span>
            </a>
            <h2 class="font-display text-xl font-bold leading-9 sm:text-2xl sm:leading-9">Create Ticket</h2>
            </div>
            <div class="relative grid h-9 w-9 shrink-0 place-items-center">
                <button type="button" class="grid h-5 w-5 place-items-center rounded-full text-primary transition-colors hover:bg-primary-soft" x-on:click="ticketInfoOpen = !ticketInfoOpen" x-bind:aria-expanded="ticketInfoOpen" aria-label="How tickets are handled">
                    <x-icons.info class="h-5 w-5" />
                </button>
                <span x-show="ticketInfoOpen" x-cloak class="pointer-events-none absolute left-1/2 top-full z-51 mt-1 h-3 w-3 -translate-x-1/2 -translate-y-1/2 rotate-45 border-l border-t border-primary/40 bg-popover" aria-hidden="true"></span>
            </div>
            <div x-show="ticketInfoOpen" x-cloak class="absolute right-0 top-full z-50 mt-1 w-80 max-w-full rounded-xl rounded-tr-md border border-primary/40 bg-popover p-3 text-xs text-popover-foreground shadow-lg">
                <p class="font-bold text-foreground">What happens after you submit?</p>
                <ol class="mt-2 grid list-decimal gap-1.5 pl-4 text-muted-foreground">
                    <li>The SDS Office reviews and classifies your concern.</li>
                    <li>It is handled by SDS or assigned to the appropriate office or personnel.</li>
                    <li>You can follow its status and talk with the handler in the ticket's conversation thread.</li>
                    <li>If it is taking too long, message the SDS Office in the thread. SDS decides if the ticket should be escalated and to whom, and the escalation date is recorded on your ticket.</li>
                </ol>
            </div>
        </div>
        <div class="surface p-4 sm:p-6">
        <div class="grid gap-4">
            <div class="grid gap-1.5">
                <label for="subject_title" class="text-sm font-semibold">Subject title <span class="text-destructive" aria-hidden="true">*</span></label>
                <input id="subject_title" name="subject_title" value="{{ old('subject_title') }}" placeholder="Short summary of your concern" maxlength="50" required class="h-11 w-full rounded-xl border {{ $errors->has('subject_title') ? 'border-destructive !border-destructive password-error-border' : 'border-input' }} bg-muted px-3 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" x-bind:class="showValidation && ! $el.value.trim() ? 'border-destructive !border-destructive password-error-border' : ''" />
                <x-input-error :messages="$errors->get('subject_title')" />
                <p x-show="showValidation && !document.getElementById('subject_title').value.trim()" x-cloak class="text-xs font-medium text-destructive">This field is required.</p>
            </div>
            <div class="grid gap-1.5">
                <label for="category_id" class="text-sm font-semibold">Complaint category <span class="text-destructive" aria-hidden="true">*</span></label>
                <details class="group relative" x-on:click.outside="$el.removeAttribute('open')">
                    <summary class="flex h-11 w-full cursor-pointer list-none items-center justify-between rounded-xl border {{ $errors->has('category_id') ? 'border-destructive !border-destructive password-error-border' : 'border-input' }} bg-muted px-3 text-sm outline-none transition-colors hover:bg-accent focus-visible:ring-1 focus-visible:ring-ring [&::-webkit-details-marker]:hidden" x-bind:class="showValidation && !document.getElementById('category_id').value ? 'border-destructive !border-destructive password-error-border' : ''">
                        <span class="truncate" x-text="categoryLabel" x-bind:class="categoryLabel === 'Select a category' ? 'text-muted-foreground' : 'text-foreground'"></span>
                        <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m6 9 6 6 6-6" />
                        </svg>
                    </summary>
                    <div class="absolute top-full z-50 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border bg-popover p-1 text-popover-foreground shadow-lg">
                        <button type="button" class="flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm hover:bg-accent hover:text-accent-foreground" x-bind:class="categoryId === '' ? 'bg-primary-soft text-primary' : ''" x-on:click="categoryId = ''; categoryLabel = 'Select a category'; document.getElementById('category_id').value = ''; $el.closest('details').removeAttribute('open')">
                            <span>Select a category</span>
                            <svg x-show="categoryId === ''" x-cloak class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6" /></svg>
                        </button>
                        @foreach ($categories as $category)
                            <button type="button" class="flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm hover:bg-accent hover:text-accent-foreground" x-bind:class="categoryId === '{{ $category->id }}' ? 'bg-primary-soft text-primary' : ''" x-on:click="categoryId = '{{ $category->id }}'; categoryLabel = '{{ addslashes($category->name) }}'; document.getElementById('category_id').value = '{{ $category->id }}'; $el.closest('details').removeAttribute('open')">
                                <span>{{ $category->name }}</span>
                                <svg x-show="categoryId === '{{ $category->id }}'" x-cloak class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6" /></svg>
                            </button>
                        @endforeach
                    </div>
                </details>
                <input id="category_id" name="category_id" type="hidden" value="{{ old('category_id') }}" required />
                <x-input-error :messages="$errors->get('category_id')" />
                <p x-show="showValidation && !document.getElementById('category_id').value" x-cloak class="text-xs font-medium text-destructive">Please select an option.</p>
            </div>
            <div class="grid gap-1.5" x-show="recipients.length" x-cloak x-effect="if (recipientId && !recipientChoices.some((recipient) => recipient.id === recipientId)) recipientId = ''">
                <span id="suggested-recipient-label" class="text-sm font-semibold">Suggested recipient <span class="font-normal text-muted-foreground">(optional)</span></span>
                <details class="group relative" x-on:click.outside="$el.removeAttribute('open')">
                    <summary aria-labelledby="suggested-recipient-label" class="flex h-11 w-full cursor-pointer list-none items-center justify-between gap-2 rounded-xl border {{ $errors->has('suggested_recipient_id') ? 'border-destructive !border-destructive password-error-border' : 'border-input' }} bg-muted px-3 text-sm outline-none transition-colors hover:bg-accent focus-visible:ring-1 focus-visible:ring-ring [&::-webkit-details-marker]:hidden">
                        <span class="min-w-0 truncate" x-text="recipientLabel" x-bind:class="recipientId === '' ? 'text-muted-foreground' : 'text-foreground'"></span>
                        <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m6 9 6 6 6-6" />
                        </svg>
                    </summary>
                    <div class="absolute top-full z-50 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border bg-popover p-1 text-popover-foreground shadow-lg">
                        <button type="button" class="flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm hover:bg-accent hover:text-accent-foreground" x-bind:class="recipientId === '' ? 'bg-primary-soft text-primary' : ''" x-on:click="recipientId = ''; $el.closest('details').removeAttribute('open')">
                            <span>No suggestion</span>
                            <svg x-show="recipientId === ''" x-cloak class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6" /></svg>
                        </button>
                        <template x-for="recipient in recipientChoices" :key="recipient.id">
                            <button type="button" class="flex w-full items-center justify-between gap-2 rounded-md px-3 py-2 text-left text-sm hover:bg-accent hover:text-accent-foreground" x-bind:class="recipientId === recipient.id ? 'bg-primary-soft text-primary' : ''" x-on:click="recipientId = recipient.id; $el.closest('details').removeAttribute('open')">
                                <span class="min-w-0">
                                    <span class="block wrap-break-word" x-text="recipient.name"></span>
                                    <span class="block wrap-break-word text-xs text-muted-foreground" x-show="recipient.detail" x-text="recipient.detail"></span>
                                </span>
                                <svg x-show="recipientId === recipient.id" x-cloak class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6" /></svg>
                            </button>
                        </template>
                    </div>
                </details>
                <input name="suggested_recipient_id" type="hidden" x-bind:value="recipientId" />
                <p class="text-xs text-muted-foreground">Choose who you think should handle this, if you know. The SDS Office makes the final assignment.</p>
                <x-input-error :messages="$errors->get('suggested_recipient_id')" />
            </div>
            <div class="grid gap-1.5 pt-2">
                <label for="description" class="text-sm font-semibold">Description <span class="text-destructive" aria-hidden="true">*</span></label>
                <textarea id="description" name="description" rows="9" required placeholder="Describe your concern in detail. If you can, include:&#10;• What happened&#10;• When and where it happened&#10;• Who was involved (name, position, or office)&#10;• What you have already done about it&#10;• What outcome you are hoping for" class="min-h-40 w-full rounded-xl border {{ $errors->has('description') ? 'border-destructive !border-destructive password-error-border' : 'border-input' }} bg-muted px-3 py-3 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" x-bind:class="showValidation && ! $el.value.trim() ? 'border-destructive !border-destructive password-error-border' : ''">{{ old('description') }}</textarea>
                <x-input-error :messages="$errors->get('description')" />
                <p x-show="showValidation && !document.getElementById('description').value.trim()" x-cloak class="text-xs font-medium text-destructive">This field is required.</p>
            </div>
            <div class="grid gap-1.5">
                <span class="text-sm font-semibold">Attachment <span class="font-normal text-muted-foreground">(optional)</span></span>
                <div x-show="attachmentFiles.length" x-cloak class="grid gap-2">
                    <template x-for="(file, index) in attachmentFiles" :key="`${file.name}-${file.lastModified}-${index}`">
                        <div class="group relative inline-flex w-fit max-w-full items-center gap-3 rounded-lg border bg-muted px-3 py-2.5 text-sm">
                            <x-icons.file-text x-show="!['jpg', 'jpeg', 'png', 'heic'].includes(file.name.split('.').pop().toLowerCase())" class="h-4 w-4 shrink-0 text-muted-foreground" />
                            <x-icons.image x-show="['jpg', 'jpeg', 'png', 'heic'].includes(file.name.split('.').pop().toLowerCase())" class="h-4 w-4 shrink-0 text-muted-foreground" />
                            <span class="min-w-0 truncate" x-text="file.name"></span>
                            <button type="button" class="absolute -top-2 -right-2 z-10 grid h-6 w-6 place-items-center rounded-full border border-border bg-background text-muted-foreground opacity-0 shadow-sm transition-opacity hover:text-foreground group-hover:opacity-100 focus-visible:opacity-100" x-on:click="removeAttachment(index)" :aria-label="`Remove ${file.name}`">
                                <span aria-hidden="true" class="text-lg leading-none">&times;</span>
                            </button>
                        </div>
                    </template>
                </div>
                <label for="file_attachment" class="flex cursor-pointer items-center gap-3 rounded-lg border border-dashed px-3.5 py-3 text-sm text-muted-foreground transition-colors hover:border-primary hover:bg-primary-soft">
                    <x-icons.paperclip class="h-4 w-4 shrink-0" />
                    <span>PDF, DOCX, JPG, PNG, or HEIC, max 10MB</span>
                    <input id="file_attachment" name="file_attachment[]" type="file" accept=".pdf,.docx,.jpg,.jpeg,.png,.heic" multiple class="hidden" x-on:change="addAttachments($event)" />
                </label>
                <x-input-error :messages="$errors->get('file_attachment')" />
            </div>
            <div class="grid gap-1.5 pt-2">
                <div class="relative flex items-center gap-2" x-data="{ privacyPinned: false, privacyHover: false }" x-on:click.outside="privacyPinned = false; privacyHover = false">
                    <label for="is_anonymous" class="text-sm font-semibold leading-5">Privacy option</label>
                    <div class="relative grid h-5 w-4 shrink-0 place-items-center">
                        <button type="button" class="grid h-4 w-4 place-items-center rounded-full text-primary transition-colors hover:bg-primary-soft" x-on:click="privacyPinned = !privacyPinned" x-on:mouseenter="privacyHover = true" x-on:mouseleave="privacyHover = false" x-bind:aria-expanded="privacyPinned || privacyHover" aria-label="Privacy option details">
                            <x-icons.info class="h-4 w-4" />
                        </button>
                        <span x-show="privacyPinned || privacyHover" x-cloak class="pointer-events-none absolute left-1/2 top-full z-51 mt-2 h-3 w-3 -translate-x-1/2 -translate-y-1/2 rotate-45 border-l border-t border-primary/40 bg-popover" aria-hidden="true"></span>
                    </div>
                    <div x-show="privacyPinned || privacyHover" x-cloak x-on:mouseenter="privacyHover = true" x-on:mouseleave="privacyHover = false" class="absolute left-0 top-full z-50 mt-2 w-full max-w-sm rounded-xl border border-primary/40 bg-popover p-3 text-xs text-popover-foreground shadow-lg">
                        <div>
                            <p class="font-bold text-foreground">If you submit anonymously</p>
                            <p class="mt-1.5 text-muted-foreground">Your name and student ID are not shown on the submission. The SDS Office still reviews it, then keeps it as an informational record or forwards it to the office concerned. There is no conversation thread and no escalation.</p>
                        </div>
                        <div class="mt-3 border-t border-border pt-3">
                            <p class="font-bold text-foreground">If you submit with your name</p>
                            <p class="mt-1.5 text-muted-foreground">You get a ticket ID to track. The SDS Office reviews your concern and assigns it to the right office. You receive status updates, can message the handler, and can ask SDS to escalate it.</p>
                        </div>
                    </div>
                </div>
                <label class="flex items-center gap-3 text-sm">
                    <input id="is_anonymous" name="is_anonymous" type="checkbox" value="1" x-model="anonymous" class="peer sr-only" />
                    <span class="grid h-4 w-4 shrink-0 place-items-center rounded-full border border-primary text-[11px] font-bold leading-none text-white transition-colors peer-focus-visible:ring-1 peer-focus-visible:ring-ring" x-bind:class="anonymous ? 'bg-primary' : 'bg-transparent'" aria-hidden="true">
                        <svg x-show="anonymous" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="h-3 w-3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" />
                        </svg>
                    </span>
                    <span>Submit anonymously</span>
                </label>
                <x-input-error :messages="$errors->get('is_anonymous')" />
            </div>
        </div>
        </div>
    </div>

    <div class="grid content-start gap-5">
        <div class="flex w-full gap-2">
            <button type="submit" class="inline-flex h-10 flex-1 items-center justify-center gap-2 rounded-md bg-primary px-8 text-sm font-medium text-primary-foreground shadow transition-colors hover:bg-primary/90">
                {{ $buttonText }}
            </button>
            <a href="{{ route('student.dashboard') }}" class="inline-flex h-10 flex-1 items-center justify-center rounded-md border border-input bg-background px-6 text-sm font-medium text-foreground shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground">
                Cancel
            </a>
        </div>
    </div>
</div>