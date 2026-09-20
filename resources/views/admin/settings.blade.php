<x-app-layout :role="'admin'" title="System Settings">
    <div class="-mt-1 sm:-mt-2">
    <div x-data="{ settingsTab: 'category', deleteModalOpen: false, deleteCategoryName: '', deleteCategoryUrl: '', addCategoryOpen: @js(request()->boolean('add_category')), editCategoryOpen: false, editCategory: { id: null, name: '', description: '', recipientId: '' }, suggestedRecipientsOpen: false, recipientSearch: '', selectedRecipientIds: [], recipientDraftIds: [], hierarchyLevels: [{ level: 1, recipientIds: [] }], recipientOptions: @js($recipients->map(fn ($recipient) => ['id' => $recipient->id, 'first_name' => $recipient->user?->first_name ?: $recipient->user?->name, 'name' => $recipient->user?->display_name, 'department' => $recipient->department, 'avatar' => $recipient->user?->avatar_path ? asset('storage/' . $recipient->user->avatar_path) : null])->values()), categoryOptions: @js($categories->map(fn ($category) => ['id' => $category->id, 'name' => $category->name, 'description' => $category->description, 'recipientId' => $category->recipient_id, 'suggestedRecipientIds' => $category->suggestedRecipients->pluck('id')->values(), 'hierarchyLevels' => $category->escalationHierarchies->groupBy('level')->map(fn ($items, $level) => ['level' => (int) $level, 'recipientIds' => $items->pluck('recipient_id')->values()])->values()])->values()), openEditCategory(categoryId) { const category = this.categoryOptions.find(item => item.id === categoryId); if (!category) return; this.selectedRecipientIds = [...(category.suggestedRecipientIds || [])]; this.hierarchyLevels = (category.hierarchyLevels?.length ? category.hierarchyLevels : [{ level: 1, recipientIds: [] }]).map(level => ({ level: level.level, recipientIds: [...level.recipientIds] })); this.recipientDraftIds = []; this.recipientSearch = ''; this.editCategory = { id: category.id, name: category.name, description: category.description || '', recipientId: category.recipientId || '' }; this.editCategoryOpen = true; }, resetEditCategory() { this.editCategory = { id: null, name: '', description: '', recipientId: '' }; this.hierarchyLevels = [{ level: 1, recipientIds: [] }]; }, closeEditCategory() { this.resetEditCategory(); this.selectedRecipientIds = []; this.recipientDraftIds = []; this.recipientSearch = ''; this.suggestedRecipientsOpen = false; this.editCategoryOpen = false; }, addHierarchyLevel() { const highest = this.hierarchyLevels.reduce((max, level) => Math.max(max, Number(level.level) || 0), 0); this.hierarchyLevels.push({ level: highest + 1, recipientIds: [] }); }, removeHierarchyLevel(index) { if (this.hierarchyLevels.length > 1) this.hierarchyLevels.splice(index, 1); }, openSuggestedRecipients() { this.recipientDraftIds = [...this.selectedRecipientIds]; this.recipientSearch = ''; this.suggestedRecipientsOpen = true; }, selectSuggestedRecipients() { this.selectedRecipientIds = [...new Set(this.recipientDraftIds)]; this.suggestedRecipientsOpen = false; this.recipientSearch = ''; }, closeSuggestedRecipients() { this.recipientDraftIds = []; this.recipientSearch = ''; this.suggestedRecipientsOpen = false; }, resetCategoryForm() { this.$refs.categoryForm?.reset(); this.selectedRecipientIds = []; this.hierarchyLevels = [{ level: 1, recipientIds: [] }]; this.recipientDraftIds = []; this.recipientSearch = ''; this.suggestedRecipientsOpen = false; }, closeCategoryModal() { this.resetCategoryForm(); this.addCategoryOpen = false; } }" class="grid gap-6">
        <span x-init="settingsTab = @js(request('settings_tab', 'category'))" class="hidden"></span>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="inline-flex items-center gap-6 border-b border-border">
                <button type="button" @click="settingsTab = 'category'" :aria-selected="settingsTab === 'category'" class="inline-flex items-center border-b-2 px-1 pb-2 text-sm transition-colors" :class="settingsTab === 'category' ? 'border-[#7a1d2a] font-semibold text-[#7a1d2a]' : 'border-transparent font-medium text-muted-foreground hover:text-foreground'">Category</button>
                <button type="button" @click="settingsTab = 'escalation'" :aria-selected="settingsTab === 'escalation'" class="inline-flex items-center border-b-2 px-1 pb-2 text-sm transition-colors" :class="settingsTab === 'escalation' ? 'border-[#7a1d2a] font-semibold text-[#7a1d2a]' : 'border-transparent font-medium text-muted-foreground hover:text-foreground'">Escalation Hierarchy</button>
                <button type="button" @click="settingsTab = 'department'" :aria-selected="settingsTab === 'department'" class="inline-flex items-center border-b-2 px-1 pb-2 text-sm transition-colors" :class="settingsTab === 'department' ? 'border-[#7a1d2a] font-semibold text-[#7a1d2a]' : 'border-transparent font-medium text-muted-foreground hover:text-foreground'">Department</button>
            </div>
            <div class="relative h-9 min-w-[150px]">
                <button type="button" x-show="settingsTab === 'category'" x-cloak x-on:click.prevent.stop="addCategoryOpen = true" class="absolute top-0 right-0 inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-sm font-semibold text-primary-foreground transition-opacity duration-150 hover:bg-primary/90"><x-icons.plus class="h-4 w-4" /> Add category</button>
                <button type="button" x-show="settingsTab === 'department'" x-cloak x-on:click.prevent.stop="$dispatch('open-department-modal')" class="absolute top-0 right-0 inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-sm font-semibold text-primary-foreground transition-opacity duration-150 hover:bg-primary/90"><x-icons.plus class="h-4 w-4" /> Add department</button>
            </div>
        </div>

        <div class="grid min-h-[28rem]">
        <div x-cloak x-bind:class="settingsTab === 'category' ? 'opacity-100' : 'pointer-events-none opacity-0'" class="col-start-1 row-start-1 transition-opacity duration-150 ease-out">
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
                            @forelse ($category->suggestedRecipients as $recipient)
                                <span class="group relative">
                                    <span class="grid h-8 w-8 shrink-0 place-items-center overflow-hidden rounded-full border-2 border-white bg-primary-soft text-[10px] font-bold text-primary shadow-sm" title="{{ $recipient->user?->table_name ?? 'Recipient' }}">
                                        @if ($recipient->user?->avatar_path)
                                            <img src="{{ asset('storage/' . $recipient->user->avatar_path) }}" alt="{{ $recipient->user?->display_name }}" class="h-full w-full object-cover">
                                        @else
                                            {{ $recipient->user?->name_initials ?? '?' }}
                                        @endif
                                    </span>
                                    <span class="absolute top-10 left-0 z-30 hidden w-72 max-w-[calc(100vw-2rem)] rounded-xl brand-gradient p-4 text-left text-primary-foreground shadow-lg group-hover:block">
                                        <span class="flex items-center gap-3">
                                            <span class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-foreground/15 text-sm font-bold ring-2 ring-primary-foreground/30">
                                                @if ($recipient->user?->avatar_path)
                                                    <img src="{{ asset('storage/' . $recipient->user->avatar_path) }}" alt="{{ $recipient->user?->table_name }}" class="h-full w-full object-cover">
                                                @else
                                                    {{ $recipient->user?->name_initials ?? '?' }}
                                                @endif
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block wrap-break-word text-sm font-bold">{{ $recipient->user?->table_name ?? 'Recipient' }}</span>
                                                <span class="mt-1 block wrap-break-word text-[11px] leading-relaxed opacity-95">ID {{ $recipient->staff_id ?? $recipient->user?->username ?? $recipient->user?->id }} · {{ $recipient->department ?? 'Department not specified' }} · {{ $recipient->designation ?? 'Designation not specified' }}</span>
                                            </span>
                                        </span>
                                        <span class="mt-3 inline-flex rounded-full border border-primary-foreground/40 bg-primary-foreground/15 px-3 py-1 text-[10px] font-semibold">{{ $recipient->user?->role === \App\Models\User::ROLE_SDS_ADMIN ? 'Admin' : 'Recipient' }}</span>
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

        <div x-cloak x-bind:class="settingsTab === 'escalation' ? 'opacity-100' : 'pointer-events-none opacity-0'" class="col-start-1 row-start-1 grid gap-3 transition-opacity duration-150 ease-out">
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
                                @foreach ($category->escalationHierarchies->groupBy('level') as $level => $hierarchies)
                                    <li class="flex min-w-0 items-start gap-2 rounded-md bg-muted/60 px-3 py-2 text-xs">
                                        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-primary text-[11px] font-bold text-primary-foreground">{{ $level }}</span>
                                        <span class="min-w-0">@foreach ($hierarchies as $hierarchy)<span class="block truncate text-foreground">{{ $hierarchy->recipient?->department ?? 'Recipient not assigned' }} · {{ $hierarchy->recipient?->user?->table_name ?? 'Unknown recipient' }}</span>@endforeach</span>
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

        <div x-data="{ addDepartmentOpen: false, departmentName: '', departmentNameError: '', departmentPositions: [], departmentPositionDraft: '', positionDraftOpen: false, positionDraftError: '', addPosition() { const position = this.departmentPositionDraft.trim(); if (position === '') { this.positionDraftError = 'Input position name'; return; } this.departmentPositions.push(position); this.departmentPositionDraft = ''; this.positionDraftError = ''; this.positionDraftOpen = false; }, saveDepartment(event) { if (this.departmentName.trim() === '') { event.preventDefault(); this.departmentNameError = 'Input department name'; return; } this.departmentNameError = ''; }, removePosition(index) { this.departmentPositions.splice(index, 1); }, resetDepartmentForm() { this.departmentName = ''; this.departmentNameError = ''; this.departmentPositions = []; this.departmentPositionDraft = ''; this.positionDraftError = ''; this.positionDraftOpen = false; this.addDepartmentOpen = false; } }" @open-department-modal.window="addDepartmentOpen = true; positionDraftError = ''; departmentNameError = ''" x-cloak x-bind:class="settingsTab === 'department' ? 'opacity-100' : 'pointer-events-none opacity-0'" class="col-start-1 row-start-1 transition-opacity duration-150 ease-out">
            <ul class="grid gap-2 lg:grid-cols-2">
                @forelse ($departments as $department)
                    <li class="rounded-lg border p-3.5">
                        <p class="text-sm font-bold text-primary">{{ $department->name }}</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @forelse ($department->positions as $position)
                                <span class="rounded-full border border-border bg-muted/50 px-2.5 py-1 text-xs text-foreground">{{ $position->name }}</span>
                            @empty
                                <span class="text-xs text-muted-foreground">No positions configured.</span>
                            @endforelse
                        </div>
                    </li>
                @empty
                    <li class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">No departments configured.</li>
                @endforelse
            </ul>

            <div x-show="addDepartmentOpen" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div @click.outside="resetDepartmentForm()" class="w-full max-w-xl rounded-2xl border border-border bg-white shadow-2xl" role="dialog" aria-modal="true" aria-label="Add department">
                    <div class="flex items-start justify-between border-b border-border px-5 py-4">
                        <div><p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">Department Management</p><h2 class="mt-1 text-lg font-bold text-foreground">Add Department</h2></div>
                        <button type="button" @click="resetDepartmentForm()" class="grid h-8 w-8 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground" aria-label="Close add department form"><x-icons.x class="h-4 w-4" /></button>
                    </div>
                    <form action="{{ route('admin.departments.store') }}" method="POST" @submit="saveDepartment($event)" class="grid gap-4 p-5">
                        @csrf
                        <div>
                            <label for="settings-department-name" class="mb-1.5 block text-sm font-semibold text-foreground">Department Name</label>
                            <input id="settings-department-name" name="name" type="text" x-model="departmentName" @input="departmentNameError = ''" class="h-8 w-full rounded-md border border-input bg-white px-3 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring">
                            <p x-show="departmentNameError" x-text="departmentNameError" class="mt-1 text-xs text-destructive"></p>
                        </div>
                        <div>
                            <div class="mb-1.5 flex items-center gap-2"><p class="text-sm font-semibold text-foreground">Position</p><button type="button" @click="positionDraftOpen = true; positionDraftError = ''; $nextTick(() => $refs.positionInput?.focus())" class="grid h-6 w-6 place-items-center rounded-md border border-gray-300 bg-white text-primary transition-colors hover:bg-primary-soft" aria-label="Add position" title="Add position"><x-icons.plus class="h-3.5 w-3.5" /></button></div>
                            <div class="grid gap-2" x-show="positionDraftOpen">
                                <div class="flex items-center gap-2">
                                    <input x-ref="positionInput" type="text" x-model="departmentPositionDraft" @input="positionDraftError = ''" @keydown.enter.prevent="addPosition()" class="h-8 min-w-0 flex-1 rounded-md border border-input bg-white px-3 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring">
                                    <button type="button" @click="addPosition()" class="grid h-8 w-8 shrink-0 place-items-center rounded-md text-primary transition-colors hover:bg-primary-soft" aria-label="Confirm position" title="Confirm position"><x-icons.check class="h-4 w-4" /></button>
                                    <button type="button" @click="departmentPositionDraft = ''; positionDraftError = ''; positionDraftOpen = false" class="grid h-8 w-8 shrink-0 place-items-center rounded-md text-primary transition-colors hover:bg-primary-soft" aria-label="Cancel position" title="Cancel position"><x-icons.x class="h-4 w-4" /></button>
                                </div>
                                <p x-show="positionDraftError" x-text="positionDraftError" class="text-xs text-destructive"></p>
                            </div>
                            <div class="mt-2 grid gap-2"><template x-for="(position, index) in departmentPositions" :key="`${position}-${index}`"><div class="flex items-center justify-between rounded-md bg-muted/50 px-3 py-2 text-sm"><input type="hidden" name="positions[]" :value="position"><span x-text="position"></span><button type="button" @click="removePosition(index)" class="text-muted-foreground hover:text-destructive" aria-label="Remove position"><x-icons.x class="h-4 w-4" /></button></div></template></div>
                        </div>
                        <div class="flex justify-end gap-2 pt-2"><button type="button" @click="resetDepartmentForm()" class="inline-flex h-8 items-center justify-center rounded-full border border-border bg-white px-4 text-[11px] font-semibold text-foreground hover:bg-muted">Cancel</button><button type="submit" class="inline-flex h-8 items-center justify-center rounded-full bg-primary px-4 text-[11px] font-semibold text-primary-foreground hover:bg-primary/90">Save Department</button></div>
                    </form>
                </div>
            </div>
        </div>
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
                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-foreground">Escalation Hierarchy</p>
                            <button type="button" @click="addHierarchyLevel()" class="text-xs font-semibold text-primary hover:underline">Add level</button>
                        </div>
                        <div class="grid gap-2">
                            <template x-for="(level, levelIndex) in hierarchyLevels" :key="`new-level-${levelIndex}`">
                                <div class="rounded-lg border border-border bg-muted/30 p-3">
                                    <div class="mb-2 flex items-center justify-between gap-2">
                                        <span class="text-xs font-semibold text-foreground" x-text="`Level ${level.level}`"></span>
                                        <button type="button" x-show="hierarchyLevels.length > 1" @click="removeHierarchyLevel(levelIndex)" class="text-[11px] font-semibold text-destructive hover:underline">Remove</button>
                                    </div>
                                    <input type="hidden" :name="`hierarchy_levels[${levelIndex}][level]`" :value="level.level">
                                    <div class="grid max-h-36 gap-1 overflow-y-auto sm:grid-cols-2">
                                        <template x-for="recipient in recipientOptions" :key="`new-level-${levelIndex}-${recipient.id}`">
                                            <label class="flex items-center gap-2 rounded-md px-2 py-1 text-xs hover:bg-primary-soft">
                                                <input type="checkbox" :name="`hierarchy_levels[${levelIndex}][recipient_ids][]`" :value="recipient.id" x-model="level.recipientIds" class="rounded border-input text-primary focus:ring-primary">
                                                <span class="min-w-0 truncate" x-text="recipient.name"></span>
                                            </label>
                                        </template>
                                    </div>
                                </div>
                            </template>
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
                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-foreground">Escalation Hierarchy</p>
                            <button type="button" @click="addHierarchyLevel()" class="text-xs font-semibold text-primary hover:underline">Add level</button>
                        </div>
                        <div class="grid gap-2">
                            <template x-for="(level, levelIndex) in hierarchyLevels" :key="`edit-level-${levelIndex}`">
                                <div class="rounded-lg border border-border bg-muted/30 p-3">
                                    <div class="mb-2 flex items-center justify-between gap-2">
                                        <span class="text-xs font-semibold text-foreground" x-text="`Level ${level.level}`"></span>
                                        <button type="button" x-show="hierarchyLevels.length > 1" @click="removeHierarchyLevel(levelIndex)" class="text-[11px] font-semibold text-destructive hover:underline">Remove</button>
                                    </div>
                                    <input type="hidden" :name="`hierarchy_levels[${levelIndex}][level]`" :value="level.level">
                                    <div class="grid max-h-36 gap-1 overflow-y-auto sm:grid-cols-2">
                                        <template x-for="recipient in recipientOptions" :key="`edit-level-${levelIndex}-${recipient.id}`">
                                            <label class="flex items-center gap-2 rounded-md px-2 py-1 text-xs hover:bg-primary-soft">
                                                <input type="checkbox" :name="`hierarchy_levels[${levelIndex}][recipient_ids][]`" :value="recipient.id" x-model="level.recipientIds" class="rounded border-input text-primary focus:ring-primary">
                                                <span class="min-w-0 truncate" x-text="recipient.name"></span>
                                            </label>
                                        </template>
                                    </div>
                                </div>
                            </template>
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
</div>
</x-app-layout>
