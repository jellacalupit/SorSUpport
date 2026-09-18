<x-app-layout :role="'admin'" title="System Settings">
    <div x-data="{ settingsTab: 'category', deleteModalOpen: false, deleteCategoryName: '', deleteCategoryUrl: '', addCategoryOpen: @js(request()->boolean('add_category')), editCategoryOpen: false, editCategory: { id: null, name: '', description: '', recipientId: '', hierarchy: '' }, suggestedRecipientsOpen: false, recipientSearch: '', selectedRecipientIds: [], recipientDraftIds: [], recipientOptions: @js($recipients->map(fn ($recipient) => ['id' => $recipient->id, 'first_name' => $recipient->user?->first_name ?: $recipient->user?->name, 'name' => $recipient->user?->display_name, 'department' => $recipient->department, 'avatar' => $recipient->user?->avatar_path ? asset('storage/' . $recipient->user->avatar_path) : null])->values()), categoryOptions: @js($categories->map(fn ($category) => ['id' => $category->id, 'name' => $category->name, 'description' => $category->description, 'recipientId' => $category->recipient_id, 'hierarchy' => $category->escalationHierarchies->pluck('recipient_id')->implode(', ')])->values()), openEditCategory(categoryId) { const category = this.categoryOptions.find(item => item.id === categoryId); if (!category) return; this.selectedRecipientIds = category.hierarchy ? category.hierarchy.split(',').map(id => Number(id.trim())).filter(Boolean) : []; this.recipientDraftIds = []; this.recipientSearch = ''; this.editCategory = { ...category }; this.editCategoryOpen = true; }, resetEditCategory() { this.editCategory = { id: null, name: '', description: '', recipientId: '', hierarchy: '' }; }, closeEditCategory() { this.resetEditCategory(); this.selectedRecipientIds = []; this.recipientDraftIds = []; this.recipientSearch = ''; this.suggestedRecipientsOpen = false; this.editCategoryOpen = false; }, openSuggestedRecipients() { this.recipientDraftIds = [...this.selectedRecipientIds]; this.recipientSearch = ''; this.suggestedRecipientsOpen = true; }, selectSuggestedRecipients() { this.selectedRecipientIds = [...this.recipientDraftIds]; this.suggestedRecipientsOpen = false; this.recipientSearch = ''; }, closeSuggestedRecipients() { this.recipientDraftIds = []; this.recipientSearch = ''; this.suggestedRecipientsOpen = false; }, resetCategoryForm() { this.$refs.categoryForm?.reset(); this.selectedRecipientIds = []; this.recipientDraftIds = []; this.recipientSearch = ''; this.suggestedRecipientsOpen = false; }, closeCategoryModal() { this.resetCategoryForm(); this.addCategoryOpen = false; } }" class="grid gap-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="relative inline-flex h-10 w-full max-w-md items-center gap-1 rounded-xl border border-border bg-muted/60 p-1">
            <span class="absolute top-1 bottom-1 left-1 w-[calc(50%-0.25rem)] rounded-lg bg-[#7a1d2a] shadow-sm transition-transform duration-300 ease-out" :class="settingsTab === 'escalation' ? 'translate-x-full' : 'translate-x-0'"></span>
                <button type="button" @click="settingsTab = 'category'" :aria-selected="settingsTab === 'category'" class="relative z-10 inline-flex h-8 flex-1 items-center justify-center rounded-lg px-4 text-sm transition-colors" :class="settingsTab === 'category' ? 'font-semibold text-white' : 'font-medium text-muted-foreground hover:text-foreground'">Category</button>
                <button type="button" @click="settingsTab = 'escalation'" :aria-selected="settingsTab === 'escalation'" class="relative z-10 inline-flex h-8 flex-1 items-center justify-center rounded-lg px-4 text-sm transition-colors" :class="settingsTab === 'escalation' ? 'font-semibold text-white' : 'font-medium text-muted-foreground hover:text-foreground'">Escalation Hierarchy</button>
            </div>
            <button type="button" x-show="settingsTab === 'category'" x-on:click.prevent.stop="addCategoryOpen = true" class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-sm font-semibold text-primary-foreground hover:bg-primary/90"><x-icons.plus class="h-4 w-4" /> Add category</button>
        </div>

        <div x-show="settingsTab === 'category'" x-cloak>
            <ul class="grid gap-2 lg:grid-cols-2">
                @forelse ($categories as $category)
                    <li class="rounded-lg border p-3.5 transition-colors hover:border-primary hover:bg-primary-soft">
                        <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3">
                            <p class="truncate text-sm font-bold text-primary">{{ $category->name }}</p>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="openEditCategory({{ $category->id }})" class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-primary text-white transition-colors hover:bg-primary-dark" aria-label="Edit category" title="Edit category"><x-icons.pencil class="h-3.5 w-3.5" /></button>
                                <button type="button" @click="deleteCategoryName = @js($category->name); deleteCategoryUrl = @js(route('admin.categories.destroy', $category)); deleteModalOpen = true" class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-destructive text-white transition-colors hover:bg-destructive/90" aria-label="Delete category" title="Delete category"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5M14 11v5"/></svg></button>
                            </div>
                        </div>
                        <p class="mt-0 text-xs text-muted-foreground">{{ $category->description ?: 'No category description provided.' }}</p>
                        <div class="mt-3 flex items-center gap-1.5">
                            @forelse ($category->escalationHierarchies as $hierarchy)
                                <span class="group relative">
                                    <span class="grid h-8 w-8 shrink-0 place-items-center overflow-hidden rounded-full border-2 border-white bg-primary-soft text-[10px] font-bold text-primary shadow-sm" title="{{ $hierarchy->recipient?->user?->table_name ?? 'Recipient' }}">
                                        @if ($hierarchy->recipient?->user?->avatar_path)
                                            <img src="{{ asset('storage/' . $hierarchy->recipient->user->avatar_path) }}" alt="{{ $hierarchy->recipient->user->display_name }}" class="h-full w-full object-cover">
                                        @else
                                            {{ $hierarchy->recipient?->user?->name_initials ?? '?' }}
                                        @endif
                                    </span>
                                    <span class="absolute top-10 left-0 z-30 hidden w-72 max-w-[calc(100vw-2rem)] rounded-xl brand-gradient p-4 text-left text-primary-foreground shadow-lg group-hover:block">
                                        <span class="flex items-center gap-3">
                                            <span class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-foreground/15 text-sm font-bold ring-2 ring-primary-foreground/30">
                                                @if ($hierarchy->recipient?->user?->avatar_path)
                                                    <img src="{{ asset('storage/' . $hierarchy->recipient->user->avatar_path) }}" alt="{{ $hierarchy->recipient->user->table_name }}" class="h-full w-full object-cover">
                                                @else
                                                    {{ $hierarchy->recipient?->user?->name_initials ?? '?' }}
                                                @endif
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block wrap-break-word text-sm font-bold">{{ $hierarchy->recipient?->user?->table_name ?? 'Recipient' }}</span>
                                                <span class="mt-1 block wrap-break-word text-[11px] leading-relaxed opacity-95">ID {{ $hierarchy->recipient?->staff_id ?? $hierarchy->recipient?->user?->username ?? $hierarchy->recipient?->user?->id }} · {{ $hierarchy->recipient?->department ?? 'Department not specified' }} · {{ $hierarchy->recipient?->designation ?? 'Designation not specified' }}</span>
                                            </span>
                                        </span>
                                        <span class="mt-3 inline-flex rounded-full border border-primary-foreground/40 bg-primary-foreground/15 px-3 py-1 text-[10px] font-semibold">{{ $hierarchy->recipient?->user?->role === \App\Models\User::ROLE_SDS_ADMIN ? 'Admin' : 'Recipient' }}</span>
                                    </span>
                                </span>
                            @empty
                                <span class="text-[11px] text-muted-foreground">No recipients assigned</span>
                            @endforelse
                        </div>
                    </li>
                @empty
                    <li class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">No complaint categories configured.</li>
                @endforelse
            </ul>
        </div>

        <div x-show="settingsTab === 'escalation'" x-cloak class="grid gap-3">
            <ul class="grid gap-3">
                @forelse ($categories as $category)
                    <li class="rounded-lg border border-border bg-card p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h3 class="text-sm font-bold text-primary">{{ $category->name }}</h3>
                                <p class="mt-0.5 text-xs text-muted-foreground">{{ $category->escalationHierarchies->count() }} escalation level(s)</p>
                            </div>
                            <button type="button" @click="openEditCategory({{ $category->id }})" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border px-2.5 text-xs font-semibold text-primary transition-colors hover:bg-primary-soft"><x-icons.pencil class="h-3.5 w-3.5" /> Edit hierarchy</button>
                        </div>
                        @if ($category->escalationHierarchies->isNotEmpty())
                            <ol class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($category->escalationHierarchies as $hierarchy)
                                    <li class="flex min-w-0 items-center gap-2 rounded-md bg-muted/60 px-3 py-2 text-xs">
                                        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-primary text-[11px] font-bold text-primary-foreground">{{ $hierarchy->level }}</span>
                                        <span class="min-w-0 truncate text-foreground">{{ $hierarchy->recipient?->department ?? 'Recipient not assigned' }}<span class="block truncate text-[11px] text-muted-foreground">{{ $hierarchy->recipient?->user?->table_name ?? 'Unknown recipient' }}</span></span>
                                    </li>
                                @endforeach
                            </ol>
                        @else
                            <p class="mt-3 rounded-md border border-dashed border-border px-3 py-3 text-xs text-muted-foreground">No escalation levels configured.</p>
                        @endif
                    </li>
                @empty
                    <li class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">No complaint categories configured.</li>
                @endforelse
            </ul>
        </div>

        <div x-show="deleteModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div @click.outside="deleteModalOpen = false" class="w-full max-w-xl min-w-0 rounded-2xl border border-border bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-border px-5 py-4">
                    <div class="min-w-0 max-w-full flex-1">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">Category Management</p>
                        <h2 class="mt-1 max-w-full whitespace-normal wrap-break-word text-lg font-bold text-destructive">Delete Category</h2>
                    </div>
                    <button type="button" @click="deleteModalOpen = false" class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground" aria-label="Close confirmation">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="h-4 w-4"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>
                <p class="max-w-full whitespace-normal wrap-break-word px-5 pt-4 text-sm leading-6 text-gray-700">Are you sure you want to delete <span class="font-semibold" x-text="deleteCategoryName"></span>? Related records may also be removed.</p>
                <form method="POST" x-bind:action="deleteCategoryUrl" class="p-5 pt-4">
                    @csrf
                    @method('DELETE')
                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" @click="deleteModalOpen = false" class="inline-flex h-8 items-center justify-center rounded-full border border-border bg-white px-4 text-[11px] font-semibold text-foreground transition-colors hover:bg-muted">Cancel</button>
                        <button type="submit" class="inline-flex h-8 items-center justify-center rounded-full bg-destructive px-4 text-[11px] font-semibold text-white transition-colors hover:bg-destructive/90">Delete Category</button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="addCategoryOpen" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div @click.outside="closeCategoryModal()" class="max-h-[calc(100vh-2rem)] w-full max-w-2xl overflow-y-auto rounded-2xl border border-border bg-white shadow-2xl" role="dialog" aria-modal="true" aria-label="Add category">
                <div class="flex items-start justify-between border-b border-border px-5 py-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">Category Management</p>
                        <h2 class="mt-1 text-lg font-bold text-foreground">Add Category</h2>
                    </div>
                    <button type="button" @click="closeCategoryModal()" class="grid h-8 w-8 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground" aria-label="Close add category form">
                        <x-icons.x class="h-4 w-4" />
                    </button>
                </div>

                <form x-ref="categoryForm" action="{{ route('admin.categories.store') }}" method="POST" class="grid gap-4 p-5">
                    @csrf
                    <div>
                        <label for="settings-category-name" class="mb-1.5 block text-sm font-semibold text-foreground">Category Name</label>
                        <input id="settings-category-name" name="name" type="text" required class="h-8 w-full rounded-md border border-input bg-white px-3 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring">
                    </div>
                    <div>
                        <label for="settings-category-description" class="mb-1.5 block text-sm font-semibold text-foreground">Description</label>
                        <textarea id="settings-category-description" name="description" rows="4" class="w-full rounded-md border border-input bg-white px-3 py-2 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring"></textarea>
                    </div>
                    <div>
                        <p class="mb-1.5 text-sm font-semibold text-foreground">Suggested Recipients</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <template x-for="recipientId in selectedRecipientIds" :key="recipientId">
                                <div class="relative w-14 text-center">
                                    <button type="button" @click="selectedRecipientIds = selectedRecipientIds.filter(id => id !== recipientId)" class="absolute -top-1 right-0 z-10 grid h-4 w-4 place-items-center rounded-full bg-destructive text-white shadow-sm" aria-label="Remove suggested recipient">
                                        <x-icons.x class="h-2.5 w-2.5" />
                                    </button>
                                    <template x-for="recipient in recipientOptions.filter(item => item.id === recipientId)" :key="recipient.id">
                                        <div>
                                            <span class="mx-auto grid h-9 w-9 place-items-center overflow-hidden rounded-full bg-primary-soft text-xs font-bold text-primary">
                                                <template x-if="recipient.avatar"><img :src="recipient.avatar" :alt="recipient.name" class="h-full w-full object-cover"></template>
                                                <template x-if="!recipient.avatar"><span x-text="(recipient.first_name || '?').charAt(0).toUpperCase()"></span></template>
                                            </span>
                                            <span class="mt-1 block truncate text-[11px] text-foreground" x-text="(recipient.first_name || '').trim().split(/\s+/)[0]"></span>
                                            <input type="hidden" name="suggested_recipient_ids[]" :value="recipient.id">
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <button type="button" x-show="selectedRecipientIds.length === 0" @click="openSuggestedRecipients()" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-input px-2.5 text-xs font-semibold text-primary transition-colors hover:bg-primary-soft"><x-icons.plus class="h-3.5 w-3.5" /> Add recipient</button>
                            <button type="button" x-show="selectedRecipientIds.length > 0" x-cloak @click="openSuggestedRecipients()" class="grid h-9 w-9 place-items-center self-center rounded-full border border-input text-primary transition-colors hover:bg-primary-soft" aria-label="Add suggested recipient" title="Add suggested recipient"><x-icons.plus class="h-4 w-4" /></button>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="closeCategoryModal()" class="inline-flex h-8 items-center justify-center rounded-full border border-border bg-white px-4 text-[11px] font-semibold text-foreground hover:bg-muted">Cancel</button>
                        <button type="submit" class="inline-flex h-8 items-center justify-center rounded-full bg-primary px-4 text-[11px] font-semibold text-primary-foreground hover:bg-primary/90">Save Category</button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="editCategoryOpen" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div @click.outside="closeEditCategory()" class="max-h-[calc(100vh-2rem)] w-full max-w-2xl overflow-y-auto rounded-2xl border border-border bg-white shadow-2xl" role="dialog" aria-modal="true" aria-label="Edit category">
                <div class="flex items-start justify-between border-b border-border px-5 py-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">Category Management</p>
                        <h2 class="mt-1 text-lg font-bold text-foreground">Edit Category</h2>
                    </div>
                    <button type="button" @click="closeEditCategory()" class="grid h-8 w-8 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground" aria-label="Close edit category form">
                        <x-icons.x class="h-4 w-4" />
                    </button>
                </div>

                <form x-bind:action="`/admin/categories/${editCategory.id}`" method="POST" class="grid gap-4 p-5">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="edit-settings-category-name" class="mb-1.5 block text-sm font-semibold text-foreground">Category Name</label>
                        <input id="edit-settings-category-name" x-model="editCategory.name" name="name" type="text" required class="h-8 w-full rounded-md border border-input bg-white px-3 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring">
                    </div>
                    <div>
                        <label for="edit-settings-category-description" class="mb-1.5 block text-sm font-semibold text-foreground">Description</label>
                        <textarea id="edit-settings-category-description" x-model="editCategory.description" name="description" rows="4" class="w-full rounded-md border border-input bg-white px-3 py-2 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring"></textarea>
                    </div>
                    <input type="hidden" name="recipient_id" :value="editCategory.recipientId || ''">
                    <input type="hidden" name="escalation_hierarchy" :value="editCategory.hierarchy || ''">
                    <div>
                        <p class="mb-1.5 text-sm font-semibold text-foreground">Suggested Recipients</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <template x-for="recipientId in selectedRecipientIds" :key="`edit-selected-${recipientId}`">
                                <div class="relative w-14 text-center">
                                    <button type="button" @click="selectedRecipientIds = selectedRecipientIds.filter(id => id !== recipientId)" class="absolute -top-1 right-0 z-10 grid h-4 w-4 place-items-center rounded-full bg-destructive text-white shadow-sm" aria-label="Remove suggested recipient"><x-icons.x class="h-2.5 w-2.5" /></button>
                                    <template x-for="recipient in recipientOptions.filter(item => item.id === recipientId)" :key="recipient.id">
                                        <div>
                                            <span class="mx-auto grid h-9 w-9 place-items-center overflow-hidden rounded-full bg-primary-soft text-xs font-bold text-primary">
                                                <template x-if="recipient.avatar"><img :src="recipient.avatar" :alt="recipient.name" class="h-full w-full object-cover"></template>
                                                <template x-if="!recipient.avatar"><span x-text="(recipient.first_name || '?').charAt(0).toUpperCase()"></span></template>
                                            </span>
                                            <span class="mt-1 block truncate text-[11px] text-foreground" x-text="(recipient.first_name || '').trim().split(/\s+/)[0]"></span>
                                            <input type="hidden" name="suggested_recipient_ids[]" :value="recipient.id">
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <button type="button" x-show="selectedRecipientIds.length === 0" @click="openSuggestedRecipients()" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-input px-2.5 text-xs font-semibold text-primary transition-colors hover:bg-primary-soft"><x-icons.plus class="h-3.5 w-3.5" /> Add recipient</button>
                            <button type="button" x-show="selectedRecipientIds.length > 0" x-cloak @click="openSuggestedRecipients()" class="grid h-9 w-9 place-items-center self-center rounded-full border border-input text-primary transition-colors hover:bg-primary-soft" aria-label="Add suggested recipient" title="Add suggested recipient"><x-icons.plus class="h-4 w-4" /></button>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="closeEditCategory()" class="inline-flex h-8 items-center justify-center rounded-full border border-border bg-white px-4 text-[11px] font-semibold text-foreground hover:bg-muted">Cancel</button>
                        <button type="submit" class="inline-flex h-8 items-center justify-center rounded-full bg-primary px-4 text-[11px] font-semibold text-primary-foreground hover:bg-primary/90">Update Category</button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="suggestedRecipientsOpen" x-cloak x-transition.opacity @click.stop class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4">
            <div @click.outside="closeSuggestedRecipients()" class="flex max-h-[calc(100vh-2rem)] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-border bg-white shadow-2xl" role="dialog" aria-modal="true" aria-label="Suggested recipients">
                <div class="flex shrink-0 items-start justify-between border-b border-border px-5 py-4">
                    <h2 class="text-lg font-bold text-foreground">Suggested Recipients</h2>
                    <button type="button" @click="closeSuggestedRecipients()" class="grid h-8 w-8 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground" aria-label="Close suggested recipients">
                        <x-icons.x class="h-4 w-4" />
                    </button>
                </div>
                <div class="min-h-0 overflow-y-auto p-5">
                    <div class="flex items-center gap-2">
                        <div class="relative min-w-0 flex-1">
                            <x-icons.search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <label for="suggested-recipient-search" class="sr-only">Search recipients</label>
                            <input id="suggested-recipient-search" type="search" x-model="recipientSearch" placeholder="Search recipients" class="h-9 w-full rounded-md border border-input bg-white pl-9 pr-3 text-sm outline-none focus:ring-1 focus:ring-ring">
                        </div>
                        <button type="button" @click="selectSuggestedRecipients()" class="inline-flex h-9 shrink-0 items-center justify-center rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Select</button>
                    </div>
                    <div x-show="recipientDraftIds.length > 0" class="mt-4 flex flex-wrap gap-3 rounded-lg border border-border bg-muted/40 p-3">
                        <template x-for="recipientId in recipientDraftIds" :key="`selected-${recipientId}`">
                            <template x-for="recipient in recipientOptions.filter(item => item.id === recipientId)" :key="recipient.id">
                                <div class="relative w-14 text-center">
                                    <button type="button" @click="recipientDraftIds = recipientDraftIds.filter(id => id !== recipientId)" class="absolute -top-1 right-0 z-10 grid h-4 w-4 place-items-center rounded-full bg-destructive text-white shadow-sm" aria-label="Remove suggested recipient">
                                        <x-icons.x class="h-2.5 w-2.5" />
                                    </button>
                                    <span class="mx-auto grid h-9 w-9 place-items-center overflow-hidden rounded-full bg-primary-soft text-xs font-bold text-primary">
                                        <template x-if="recipient.avatar"><img :src="recipient.avatar" :alt="recipient.name" class="h-full w-full object-cover"></template>
                                        <template x-if="!recipient.avatar"><span x-text="(recipient.first_name || '?').charAt(0).toUpperCase()"></span></template>
                                    </span>
                                    <span class="mt-1 block truncate text-[11px] text-foreground" x-text="(recipient.first_name || '').trim().split(/\s+/)[0]"></span>
                                </div>
                            </template>
                        </template>
                    </div>
                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                        <template x-for="recipient in recipientOptions.filter(item => (item.name || '').toLowerCase().includes(recipientSearch.toLowerCase()) || (item.department || '').toLowerCase().includes(recipientSearch.toLowerCase()))" :key="recipient.id">
                            <button type="button" @click="recipientDraftIds.includes(recipient.id) ? recipientDraftIds = recipientDraftIds.filter(id => id !== recipient.id) : recipientDraftIds.push(recipient.id)" class="flex items-center gap-3 rounded-lg border p-3 text-left transition-colors hover:border-primary hover:bg-primary-soft" :class="recipientDraftIds.includes(recipient.id) ? 'border-primary bg-primary-soft' : 'border-border bg-card'">
                                <span class="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-soft text-xs font-bold text-primary">
                                    <template x-if="recipient.avatar"><img :src="recipient.avatar" :alt="recipient.name" class="h-full w-full object-cover"></template>
                                    <template x-if="!recipient.avatar"><span x-text="(recipient.first_name || '?').charAt(0).toUpperCase()"></span></template>
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold text-foreground" x-text="recipient.name"></span>
                                    <span class="block truncate text-xs text-muted-foreground" x-text="recipient.department || 'Department not specified'"></span>
                                </span>
                            </button>
                        </template>
                    </div>
                    <p x-show="recipientOptions.filter(item => (item.name || '').toLowerCase().includes(recipientSearch.toLowerCase()) || (item.department || '').toLowerCase().includes(recipientSearch.toLowerCase())).length === 0" class="py-8 text-center text-sm text-muted-foreground">No recipients found.</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
