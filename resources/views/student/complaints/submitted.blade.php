@php
    $submittedComplaint = $submittedComplaint ?? session('submittedComplaint');
@endphp

@if ($submittedComplaint)
    <div
        x-show="showSubmitted"
        x-cloak
        class="fixed inset-0 z-[100] grid place-items-center bg-black/50 px-5 py-12"
        x-on:click.self="showSubmitted = false"
        role="dialog"
        aria-modal="true"
        aria-labelledby="complaint-submitted-title"
    >
        <div class="relative w-full max-w-md rounded-2xl bg-card p-5 text-center shadow-lg sm:p-6">
            <button type="button" x-on:click="showSubmitted = false" aria-label="Close" class="absolute top-4 right-4 text-muted-foreground">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>

            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-primary text-primary-foreground shadow-lg">
                <x-icons.check class="h-6 w-6" strokeWidth="3" />
            </div>

            <h1 id="complaint-submitted-title" class="mt-4 font-display text-lg font-bold sm:text-xl">Complaint Submitted!</h1>
            <p class="mt-2 text-xs leading-relaxed text-muted-foreground sm:text-sm">
                Your ticket has been successfully submitted and pending for admin's review.
            </p>

            <section class="mt-4 overflow-hidden rounded-xl border border-border text-left">
                <div class="grid divide-y divide-border">
                    <div class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-3 px-4 py-2.5">
                        <span class="text-xs text-muted-foreground">Ticket ID</span>
                        <span class="min-w-0 text-right font-mono text-xs font-bold text-primary wrap-break-word">{{ $submittedComplaint['reference_number'] }}</span>
                    </div>
                    <div class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-3 px-4 py-2.5">
                        <span class="text-xs text-muted-foreground">Subject</span>
                        <span class="min-w-0 text-right text-xs font-semibold wrap-break-word">{{ $submittedComplaint['subject_title'] }}</span>
                    </div>
                    <div class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-3 px-4 py-2.5">
                        <span class="text-xs text-muted-foreground">Category</span>
                        <span class="min-w-0 text-right text-xs font-semibold wrap-break-word">{{ $submittedComplaint['category_name'] ?? '—' }}</span>
                    </div>
                    @if (filled($submittedComplaint['suggested_recipient'] ?? null))
                        <div class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-3 px-4 py-2.5">
                            <span class="text-xs text-muted-foreground">Suggested recipient</span>
                            <span class="min-w-0 text-right text-xs font-semibold wrap-break-word">{{ $submittedComplaint['suggested_recipient'] }}</span>
                        </div>
                    @endif
                    @if (($submittedComplaint['attachment_count'] ?? 0) > 0)
                        <div class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-3 px-4 py-2.5">
                            <span class="text-xs text-muted-foreground">Attachments</span>
                            <span class="min-w-0 text-right text-xs font-semibold wrap-break-word">{{ $submittedComplaint['attachment_count'] }} {{ Str::plural('file', $submittedComplaint['attachment_count']) }}</span>
                        </div>
                    @endif
                    @if ($submittedComplaint['is_anonymous'] ?? false)
                        <div class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-3 px-4 py-2.5">
                            <span class="text-xs text-muted-foreground">Privacy</span>
                            <span class="min-w-0 text-right text-xs font-semibold wrap-break-word">Submitted anonymously</span>
                        </div>
                    @endif
                    <div class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-3 px-4 py-2.5">
                        <span class="text-xs text-muted-foreground">Status</span>
                        <span class="justify-self-end"><x-status-badge :status="$submittedComplaint['status'] ?? 'Submitted'" :show-icon="false" class="px-2 py-0 text-[10px]" /></span>
                    </div>
                </div>
            </section>

            @unless ($submittedComplaint['is_anonymous'] ?? false)
                <div class="mt-3 flex items-start gap-2.5 rounded-xl bg-muted p-3 text-left text-xs text-muted-foreground">
                    <x-icons.mail-open class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                    <p>An email notification has been sent to your registered email address.</p>
                </div>
            @endunless

            <div class="mt-5 grid w-full gap-2">
                <a href="{{ route('student.complaints.show', $submittedComplaint['id']) }}" class="inline-flex h-11 items-center justify-center rounded-full bg-primary px-4 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90">
                    Track My Ticket
                </a>
                <button type="button" x-on:click="showSubmitted = false; $dispatch('reset-create-form')" class="inline-flex h-11 items-center justify-center rounded-full border border-border px-4 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground">
                    Submit Another Ticket
                </button>
            </div>
        </div>
    </div>
@endif
