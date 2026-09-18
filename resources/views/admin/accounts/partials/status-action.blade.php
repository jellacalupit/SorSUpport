@if ($user->is_active)
    <div x-data="{ deactivateModalOpen: false }" class="inline-block">
        <button type="button" @click="deactivateModalOpen = true" class="inline-flex h-6 items-center justify-center rounded-md bg-red-600 px-2 text-[11px] font-semibold text-white transition-colors hover:bg-red-700" aria-label="Deactivate account">
            Deactivate
        </button>

        <div x-show="deactivateModalOpen" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4">
            <div @click.outside="deactivateModalOpen = false" class="w-full max-w-xl min-w-0 rounded-2xl border border-border bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-border px-5 py-4">
                    <div class="min-w-0 max-w-full flex-1">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">Account Status</p>
                        <h3 class="mt-1 max-w-full whitespace-normal wrap-break-word text-lg font-bold text-destructive">Deactivate Account</h3>
                    </div>
                    <button type="button" @click="deactivateModalOpen = false" class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground" aria-label="Close confirmation">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="h-4 w-4"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>

                <p class="max-w-full whitespace-normal wrap-break-word px-5 pt-4 text-sm leading-6 text-gray-700">
                    Are you sure you want to deactivate this user? They will no longer be able to access the system until reactivated.
                </p>

                <form method="POST" action="{{ route('admin.accounts.deactivate', $user) }}" class="p-5 pt-4">
                    @csrf
                    @method('PATCH')
                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" @click="deactivateModalOpen = false" class="inline-flex h-8 items-center justify-center rounded-full border border-border bg-white px-4 text-[11px] font-semibold text-foreground transition-colors hover:bg-muted">Cancel</button>
                        <button type="submit" class="inline-flex h-8 items-center justify-center rounded-full bg-primary px-4 text-[11px] font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Deactivate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@else
    <div x-data="{ reactivateModalOpen: false }" class="inline-block">
        <button type="button" @click="reactivateModalOpen = true" class="inline-flex h-6 items-center justify-center rounded-md bg-green-700 px-2 text-[11px] font-semibold text-white transition-colors hover:bg-green-800" aria-label="Activate account">
            Activate
        </button>

        <div x-show="reactivateModalOpen" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4">
            <div @click.outside="reactivateModalOpen = false" class="w-full max-w-xl min-w-0 rounded-2xl border border-border bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-border px-5 py-4">
                    <div class="min-w-0 max-w-full flex-1">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">Account Status</p>
                        <h3 class="mt-1 max-w-full whitespace-normal wrap-break-word text-lg font-bold text-foreground">Reactivate Account</h3>
                    </div>
                    <button type="button" @click="reactivateModalOpen = false" class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground" aria-label="Close confirmation">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="h-4 w-4"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>

                <p class="max-w-full whitespace-normal wrap-break-word px-5 pt-4 text-sm leading-6 text-muted-foreground">
                    Are you sure you want to reactivate this account? The user will regain access to the system.
                </p>

                <form method="POST" action="{{ route('admin.accounts.reactivate', $user) }}" class="p-5 pt-4">
                    @csrf
                    @method('PATCH')
                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" @click="reactivateModalOpen = false" class="inline-flex h-8 items-center justify-center rounded-full border border-border bg-white px-4 text-[11px] font-semibold text-foreground transition-colors hover:bg-muted">Cancel</button>
                        <button type="submit" class="inline-flex h-8 items-center justify-center rounded-full bg-primary px-4 text-[11px] font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Reactivate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
