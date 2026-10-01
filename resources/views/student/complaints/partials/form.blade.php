<div class="grid gap-5 lg:grid-cols-[1.6fr_1fr]">
    <div>
        <div class="mb-4 flex items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-2">
            <a href="{{ route('student.dashboard') }}" aria-label="Back to dashboard" class="grid h-9 w-9 shrink-0 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground">
                <span aria-hidden="true" class="scale-y-150 text-2xl leading-none">&lt;</span>
            </a>
            <h2 class="font-display text-xl font-bold sm:text-2xl">Create Ticket</h2>
            </div>
            <div class="relative shrink-0" x-data="{ ticketInfoOpen: false }" x-on:click.outside="ticketInfoOpen = false">
                <button type="button" class="grid h-5 w-5 place-items-center rounded-full border border-primary text-[10px] font-bold leading-none text-primary" x-on:click="ticketInfoOpen = !ticketInfoOpen" aria-label="Ticket process details">i</button>
                <div x-show="ticketInfoOpen" x-cloak class="absolute top-7 right-0 z-50 w-80 max-w-[calc(100vw-2rem)] rounded-xl border border-border bg-popover p-3 text-xs text-popover-foreground shadow-lg">
                    <p class="font-bold text-foreground">What happens to your ticket?</p>
                    <p class="mt-2 text-muted-foreground">After you submit, the SDS Office reviews your concern, classifies it, and routes it to the appropriate office. You will receive updates about its status, messages, and resolution deadline.</p>
                </div>
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
            <div class="grid gap-1.5">
                <label for="personnel_involved" class="text-sm font-semibold">Name of person involved (optional)</label>
                <input id="personnel_involved" name="personnel_involved" value="{{ old('personnel_involved') }}" placeholder="Leave blank if not applicable" class="h-11 w-full rounded-xl border border-input bg-muted px-3 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                <x-input-error :messages="$errors->get('personnel_involved')" />
            </div>
            <div class="grid gap-1.5 pt-2">
                <label for="description" class="text-sm font-semibold">Description <span class="text-destructive" aria-hidden="true">*</span></label>
                <textarea id="description" name="description" rows="7" required placeholder="Write the detailed description of your complaint." class="min-h-40 w-full rounded-xl border {{ $errors->has('description') ? 'border-destructive !border-destructive password-error-border' : 'border-input' }} bg-muted px-3 py-3 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" x-bind:class="showValidation && ! $el.value.trim() ? 'border-destructive !border-destructive password-error-border' : ''">{{ old('description') }}</textarea>
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
                <div class="flex items-center gap-2">
                    <label for="is_anonymous" class="text-sm font-semibold">Privacy option</label>
                    <div class="relative" x-data="{ privacyPinned: false, privacyHover: false }" x-on:click.outside="privacyPinned = false; privacyHover = false">
                        <button type="button" class="grid h-4 w-4 place-items-center rounded-full border border-primary text-[10px] font-bold leading-none text-primary" x-on:click="privacyPinned = !privacyPinned" x-on:mouseenter="privacyHover = true" x-on:mouseleave="privacyHover = false" aria-label="Privacy option details">i</button>
                        <div x-show="privacyPinned || privacyHover" x-on:mouseenter="privacyHover = true" x-on:mouseleave="privacyHover = false" class="absolute left-0 top-6 z-50 w-lg max-w-[calc(100vw-2rem)] rounded-xl border bg-popover p-3 text-xs text-popover-foreground shadow-lg before:absolute before:-top-1.5 before:left-2.5 before:h-3 before:w-3 before:rotate-45 before:border-l before:border-t before:border-border before:bg-popover">
                            <div>
                                <p class="font-bold text-foreground">If you submit anonymously</p>
                                <p class="mt-2 text-muted-foreground">Your name and student ID are not attached. It is recorded as an Informational record. No ticket ID, deadline, thread, or notifications are created.</p>
                            </div>
                            <div class="mt-3 border-t border-border pt-3">
                                <p class="font-bold text-foreground">If you submit with your name</p>
                                <p class="mt-2 text-muted-foreground">You receive a ticket ID to track. The SDS Office reviews and classifies the concern. You receive deadline, message, and status updates.</p>
                            </div>
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