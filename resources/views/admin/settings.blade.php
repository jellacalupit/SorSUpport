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
            </div>
        </div>

        <div class="grid min-h-[28rem]">
        <div x-cloak x-bind:class="settingsTab === 'category' ? 'opacity-100' : 'pointer-events-none opacity-0'" class="col-start-1 row-start-1 transition-opacity duration-150 ease-out">
            <ul class="grid gap-2 lg:grid-cols-2">
                @forelse ($categories as $category)
                    <li x-data="{ expanded: false }" class="overflow-hidden rounded-lg border border-border bg-card">
                        <div @click="expanded = !expanded" class="flex cursor-pointer items-start justify-between gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-base font-bold text-primary">{{ $category->name }}</p>
                                <p class="mt-0.5 text-xs text-muted-foreground">{{ $category->description ?: 'No category description provided.' }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                    <button type="button" @click.stop="openEditCategory({{ $category->id }})" class="grid h-8 w-8 place-items-center rounded-md bg-transparent text-primary transition-colors hover:bg-primary-soft" aria-label="Edit category" title="Edit category"><x-icons.pencil class="h-3.5 w-3.5" /></button>
                                    <button type="button" @click.stop="deleteCategoryName = @js($category->name); deleteCategoryUrl = @js(route('admin.categories.destroy', $category)); deleteModalOpen = true" class="grid h-8 w-8 place-items-center rounded-md bg-transparent text-primary transition-colors hover:bg-primary-soft" aria-label="Delete category" title="Delete category"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5M14 11v5"/></svg></button>
                                <button type="button" @click.stop="expanded = !expanded" class="grid h-8 w-8 place-items-center rounded-md bg-transparent p-0 text-primary transition-colors hover:bg-primary-soft" aria-label="Toggle category details" title="Toggle category details"><x-icons.arrow-down class="h-4 w-4 transition-transform" ::class="expanded ? '' : 'rotate-180'" /></button>
                            </div>
                        </div>
                        <div x-show="expanded" x-collapse class="mx-4 my-3 overflow-hidden rounded-md border border-border">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-[#F5F2F3] text-[10px] font-semibold uppercase tracking-wide text-primary">
                                    <tr><th class="px-4 py-2.5">Recipient Name</th><th class="px-4 py-2.5">Department</th><th class="px-4 py-2.5">Position</th></tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    @forelse ($category->suggestedRecipients as $recipient)
                                        @php
                                            $recipientName = trim(implode(' ', array_filter([
                                                $recipient->user?->first_name ?: $recipient->user?->name ?: $recipient->user?->username,
                                                $recipient->user?->middle_name ? strtoupper(substr(trim($recipient->user->middle_name), 0, 1)) . '.' : null,
                                                $recipient->user?->last_name,
                                            ])));
                                        @endphp
                                        <tr class="bg-white hover:bg-muted/30">
                                            <td class="px-4 py-2">
                                                <div class="flex items-center gap-2">
                                                    <span class="grid h-6 w-6 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-soft text-[9px] font-bold text-primary">
                                                        <?php if ($recipient->user?->avatar_path): ?>
                                                            <img src="{{ asset('storage/' . $recipient->user->avatar_path) }}" alt="{{ $recipientName ?: 'Recipient avatar' }}" class="h-full w-full object-cover">
                                                        <?php else: ?>
                                                            {{ $recipient->user?->name_initials ?? '?' }}
                                                        <?php endif; ?>
                                                    </span>
                                                    <span class="truncate text-xs text-foreground">{{ $recipientName ?: 'Recipient' }}</span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-2 text-xs text-muted-foreground">{{ trim((string) $recipient->department) !== '' ? $recipient->department : 'Department not specified' }}</td>
                                            <td class="px-4 py-2 text-xs text-muted-foreground">{{ trim((string) $recipient->designation) !== '' ? $recipient->designation : 'Position not specified' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="px-4 py-2 text-center text-muted-foreground">No recipients assigned</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </li>
                @empty
                    <li class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">No complaint categories configured.</li>
                @endforelse
            </ul>
        </div>

        <div x-cloak x-data="{
            categoryOptions: @js($categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'statusLabel' => $category->escalationHierarchies->isEmpty()
                    ? 'Not configured'
                    : $category->escalationHierarchies->count() . ' level' . ($category->escalationHierarchies->count() === 1 ? '' : 's') . ' configured',
                'statusTone' => $category->escalationHierarchies->isEmpty() ? 'warning' : 'success',
                'levels' => $category->escalationHierarchies
                    ->sortBy('level')
                    ->values()
                    ->map(fn ($level) => [
                        'id' => $level->id,
                        'type' => 'recipient',
                        'recipientId' => $level->recipient_id,
                        'role' => '',
                        'isTerminal' => false,
                    ])
                    ->all(),
            ])->values()),
            recipientOptions: @js($recipients->map(fn ($recipient) => [
                'id' => $recipient->id,
                'name' => trim(implode(' ', array_filter([
                    $recipient->user?->first_name ?: $recipient->user?->name ?: $recipient->user?->username,
                    $recipient->user?->middle_name ? strtoupper(substr(trim($recipient->user->middle_name), 0, 1)) . '.' : null,
                    $recipient->user?->last_name,
                ]))) ?: 'Recipient',
                'department' => trim((string) $recipient->department) !== '' ? $recipient->department : 'Department not specified',
                'position' => trim((string) $recipient->designation) !== '' ? $recipient->designation : 'Position not specified',
                'designation' => trim((string) $recipient->designation) !== '' ? $recipient->designation : 'Position not specified',
            ])->values()),
            roleOptions: [
                { value: 'Department Head', label: 'Department Head' },
                { value: 'Dean', label: 'Dean' },
                { value: 'Registrar', label: 'Registrar' },
                { value: 'Vice Chancellor', label: 'Vice Chancellor' },
            ],
            selectedCategoryId: @js($categories->first()?->id ?? null),
            selectedCategoryName: @js($categories->first()?->name ?? 'No category selected'),
            levels: [],
            isLoading: true,
            hasUnsavedChanges: false,
            init() {
                this.loadCategory(this.selectedCategoryId);
            },
            loadCategory(categoryId) {
                const category = this.categoryOptions.find(item => item.id === categoryId) ?? this.categoryOptions[0] ?? null;
                this.selectedCategoryId = category ? category.id : null;
                this.selectedCategoryName = category ? category.name : 'No category selected';
                this.isLoading = true;
                this.hasUnsavedChanges = false;

                if (!category) {
                    this.levels = [];
                    this.isLoading = false;
                    return;
                }

                this.levels = (category.levels || []).map((level, index) => ({
                    ...level,
                    type: level.type ?? 'recipient',
                    recipientId: level.recipientId ?? '',
                    role: level.role ?? '',
                    isTerminal: false,
                }));

                this.ensureTerminalState();
                setTimeout(() => this.isLoading = false, 200);
            },
            addLevel() {
                this.levels.push({
                    id: Date.now() + Math.random(),
                    type: 'recipient',
                    recipientId: '',
                    role: '',
                    isTerminal: false,
                });
                this.ensureTerminalState();
                this.hasUnsavedChanges = true;
            },
            removeLevel(index) {
                if (this.levels.length <= 1) {
                    this.levels = [];
                    this.hasUnsavedChanges = true;
                    return;
                }

                this.levels.splice(index, 1);
                this.ensureTerminalState();
                this.hasUnsavedChanges = true;
            },
            ensureTerminalState() {
                if (!this.levels.length) return;
                this.levels = this.levels.map((level, index) => ({
                    ...level,
                    isTerminal: index === this.levels.length - 1,
                }));
            },
            reorderLevel(fromIndex, toIndex) {
                if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0 || fromIndex >= this.levels.length || toIndex >= this.levels.length) {
                    return;
                }

                const [moved] = this.levels.splice(fromIndex, 1);
                this.levels.splice(toIndex, 0, moved);
                this.ensureTerminalState();
                this.hasUnsavedChanges = true;
            },
            markDirty() {
                this.hasUnsavedChanges = true;
            },
            saveChanges() {
                this.hasUnsavedChanges = false;
            },
            isLastLevel(index) {
                return index === this.levels.length - 1;
            },
        }" x-bind:class="settingsTab === 'escalation' ? 'opacity-100' : 'pointer-events-none opacity-0'" class="col-start-1 row-start-1 transition-opacity duration-150 ease-out">
            <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-panel lg:grid lg:grid-cols-[minmax(260px,0.9fr)_minmax(0,2.1fr)]">
                <aside class="border-b border-border bg-muted/20 lg:border-b-0 lg:border-r">
                    <div class="flex items-center justify-between border-b border-border px-4 py-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">Escalation</p>
                            <h2 class="mt-1 text-base font-bold text-foreground">Categories</h2>
                        </div>
                        <span class="rounded-full bg-primary-soft px-2 py-1 text-[10px] font-semibold text-primary" x-text="categoryOptions.length"></span>
                    </div>
                    <div class="max-h-[32rem] overflow-y-auto p-3">
                        <ul class="space-y-2">
                            <template x-for="category in categoryOptions" :key="category.id">
                                <li>
                                    <button type="button" @click="loadCategory(category.id)" class="flex w-full items-start justify-between gap-3 rounded-xl border px-3 py-3 text-left transition-colors" :class="selectedCategoryId === category.id ? 'border-primary bg-primary-soft shadow-sm' : 'border-transparent bg-transparent hover:bg-muted/60'">
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-semibold text-foreground" x-text="category.name"></span>
                                            <span class="mt-1 block text-[11px] text-muted-foreground" x-text="category.statusLabel"></span>
                                        </span>
                                        <span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-semibold" :class="category.statusTone === 'success' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'" x-text="category.statusTone === 'success' ? 'Configured' : 'Pending'"></span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </aside>

                <section class="relative min-h-[30rem] bg-white">
                    <div x-show="isLoading" x-transition class="flex min-h-[30rem] items-center justify-center">
                        <div class="flex items-center gap-3 rounded-full border border-border bg-muted/40 px-4 py-2 text-sm text-muted-foreground">
                            <span class="h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent"></span>
                            Loading escalation chain...
                        </div>
                    </div>

                    <div x-show="!isLoading && levels.length === 0" x-transition class="flex min-h-[30rem] items-center justify-center p-6">
                        <div class="max-w-md rounded-2xl border border-dashed border-border bg-muted/20 p-8 text-center">
                            <p class="text-lg font-bold text-foreground">No escalation levels set up for this category yet</p>
                            <p class="mt-2 text-sm text-muted-foreground">Create the first step in the escalation chain to define how cases should move forward.</p>
                            <button type="button" @click="addLevel()" class="mt-5 inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground hover:bg-primary/90">Add first level</button>
                        </div>
                    </div>

                    <div x-show="!isLoading && levels.length > 0" x-transition class="flex min-h-[30rem] flex-col">
                        <div class="flex items-center justify-between gap-4 border-b border-border px-5 py-4">
                            <div class="min-w-0">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">Selected category</p>
                                <h2 class="mt-1 truncate text-xl font-bold text-foreground" x-text="selectedCategoryName"></h2>
                                <p class="mt-1 text-sm text-muted-foreground">Define the order recipients are escalated to when a deadline is breached.</p>
                            </div>
                            <button type="button" x-show="hasUnsavedChanges" x-cloak class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-[11px] font-semibold text-amber-700">
                                <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                Unsaved changes
                            </button>
                        </div>

                        <div class="relative flex-1 p-5">
                            <div class="absolute left-[1.45rem] top-9 bottom-9 w-px bg-border"></div>
                            <div class="space-y-4">
                                <template x-for="(level, index) in levels" :key="level.id ?? index">
                                    <div class="relative pl-12">
                                        <div class="absolute left-0 top-1/2 flex -translate-y-1/2 items-center justify-center">
                                            <span class="grid h-8 w-8 place-items-center rounded-full bg-primary text-sm font-bold text-primary-foreground shadow-sm" x-text="index + 1"></span>
                                        </div>

                                        <div class="rounded-2xl border p-4 shadow-sm transition-colors" :class="isLastLevel(index) ? 'border-amber-200 bg-amber-50/30' : 'border-border bg-card'">
                                            <div class="flex items-start gap-3">
                                                <button type="button" draggable="true" @dragstart="$event.dataTransfer.setData('text/plain', index)" @dragover.prevent @drop="reorderLevel(Number($event.dataTransfer.getData('text/plain')), index)" class="mt-1 grid h-8 w-8 shrink-0 cursor-grab place-items-center rounded-md border border-border bg-white text-muted-foreground transition-colors hover:bg-muted" aria-label="Reorder escalation level" title="Reorder escalation level">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" class="h-4 w-4"><path d="M9 7h.01M9 12h.01M9 17h.01M15 7h.01M15 12h.01M15 17h.01"/></svg>
                                                </button>

                                                <div class="grid flex-1 gap-3 md:grid-cols-2">
                                                    <div>
                                                        <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">Assignment type</label>
                                                        <select x-model="level.type" @change="markDirty()" class="h-9 w-full rounded-md border border-input bg-white px-3 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring">
                                                            <option value="recipient">Specific Recipient</option>
                                                            <option value="role">Generic Role</option>
                                                        </select>
                                                    </div>

                                                    <div x-show="level.type === 'recipient'" x-cloak>
                                                        <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">Recipient</label>
                                                        <select x-model="level.recipientId" @change="markDirty()" class="h-9 w-full rounded-md border border-input bg-white px-3 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring">
                                                            <option value="" disabled>Select recipient</option>
                                                            <template x-for="recipient in recipientOptions" :key="recipient.id">
                                                                <option :value="recipient.id" x-text="recipient.name + (recipient.department ? ' · ' + recipient.department : '')"></option>
                                                            </template>
                                                        </select>
                                                    </div>

                                                    <div x-show="level.type === 'role'" x-cloak>
                                                        <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">Role label</label>
                                                        <select x-model="level.role" @change="markDirty()" class="h-9 w-full rounded-md border border-input bg-white px-3 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring">
                                                            <option value="" disabled>Select role</option>
                                                            <template x-for="role in roleOptions" :key="role.value">
                                                                <option :value="role.value" x-text="role.label"></option>
                                                            </template>
                                                        </select>
                                                    </div>
                                                </div>

                                                <button type="button" @click="removeLevel(index)" class="mt-1 grid h-8 w-8 shrink-0 place-items-center rounded-md border border-border bg-white text-muted-foreground transition-colors hover:border-destructive hover:text-destructive" aria-label="Remove escalation level" title="Remove escalation level">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5M14 11v5"/></svg>
                                                </button>
                                            </div>

                                            <div x-show="isLastLevel(index)" x-cloak class="mt-4 rounded-xl border border-amber-200 bg-white/70 px-3 py-2">
                                                <label class="flex items-start gap-3 text-sm text-amber-800">
                                                    <input type="checkbox" x-model="level.isTerminal" @change="markDirty(); ensureTerminalState();" class="mt-1 h-4 w-4 rounded border-input text-amber-600 focus:ring-amber-500">
                                                    <span>Terminal level, flag for manual SDS Administrator intervention if breached here.</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div class="mt-5 flex justify-center">
                                <button type="button" @click="addLevel()" class="inline-flex h-10 items-center justify-center rounded-xl border border-dashed border-primary/60 bg-primary-soft px-4 text-sm font-semibold text-primary transition-colors hover:bg-primary/10">+ Add Escalation Level</button>
                            </div>
                        </div>

                        <div class="sticky bottom-0 z-10 flex justify-end border-t border-border bg-white/95 px-5 py-4 backdrop-blur-sm">
                            <button type="button" @click="saveChanges()" class="inline-flex h-10 items-center justify-center rounded-md bg-primary px-5 text-sm font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90">Save changes</button>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div x-data="{ addDepartmentOpen: false, deleteDepartmentOpen: false, deleteDepartmentId: null, deleteDepartmentName: '', departmentEditId: null, departmentType: 'student', departmentName: '', departmentDescription: '', departmentNameError: '', departmentCourses: [], departmentPositions: [], courseDraft: { course: '', year_level: '', block: '', description: '' }, positionDraft: '', courseDraftError: '', positionDraftError: '', openDepartmentModal(type) { this.departmentEditId = null; this.departmentType = type; this.departmentName = ''; this.departmentDescription = ''; this.departmentCourses = []; this.departmentPositions = []; this.addDepartmentOpen = true; this.resetCourseDraft(); this.positionDraft = ''; this.positionDraftError = ''; this.departmentNameError = ''; }, openEditDepartment(department) { this.departmentEditId = department.id; this.departmentType = department.type; this.departmentName = department.name; this.departmentDescription = department.description || ''; this.departmentCourses = (department.courses || []).map(course => ({ course: course.course, year_level: String(course.year_level), block: course.block === null ? '' : String(course.block), description: course.description || '' })); this.departmentPositions = department.positions || []; this.resetCourseDraft(); this.positionDraft = ''; this.positionDraftError = ''; this.departmentNameError = ''; this.addDepartmentOpen = true; }, openDeleteDepartment(department) { this.deleteDepartmentId = department.id; this.deleteDepartmentName = department.name; this.deleteDepartmentOpen = true; }, closeDeleteDepartment() { this.deleteDepartmentId = null; this.deleteDepartmentName = ''; this.deleteDepartmentOpen = false; }, addCourse() { const hasCourse = this.courseDraft.course.trim() !== ''; const hasYear = this.courseDraft.year_level !== ''; if (!hasCourse && !hasYear) { this.courseDraftError = 'Course and year level are required'; return; } if (!hasCourse) { this.courseDraftError = 'Input course name'; return; } if (!hasYear) { this.courseDraftError = 'Input number of year levels'; return; } this.departmentCourses.push({ ...this.courseDraft, course: this.courseDraft.course.trim(), description: (this.courseDraft.description || '').trim(), block: this.courseDraft.block === '' ? null : this.courseDraft.block }); this.courseDraftError = ''; this.resetCourseDraft(); }, addPosition() { const position = this.positionDraft.trim(); if (position === '') { this.positionDraftError = 'Input position name'; return; } this.departmentPositions.push(position); this.positionDraft = ''; this.positionDraftError = ''; }, resetCourseDraft() { this.courseDraft = { course: '', year_level: '', block: '', description: '' }; this.courseDraftError = ''; }, removeCourse(index) { this.departmentCourses.splice(index, 1); }, removePosition(index) { this.departmentPositions.splice(index, 1); }, saveDepartment(event) { if (this.departmentName.trim() === '') { event.preventDefault(); this.departmentNameError = 'Input department name'; return; } if (this.departmentType === 'student' && this.departmentCourses.length === 0) { event.preventDefault(); this.courseDraftError = 'Add at least one course'; } }, resetDepartmentForm() { this.departmentEditId = null; this.departmentName = ''; this.departmentNameError = ''; this.departmentCourses = []; this.departmentPositions = []; this.resetCourseDraft(); this.positionDraft = ''; this.positionDraftError = ''; this.addDepartmentOpen = false; } }" x-cloak x-bind:class="settingsTab === 'department' ? 'opacity-100' : 'pointer-events-none opacity-0'" class="col-start-1 row-start-1 transition-opacity duration-150 ease-out">
            <div class="grid gap-6">
                <style>
                    .student-departments table thead th { padding-top: 0.5rem; padding-bottom: 0.5rem; }
                    .student-departments table tbody td { padding-top: 0.375rem; padding-bottom: 0.375rem; }
                    .student-departments table tbody td > div { gap: 0.375rem; }
                    .student-departments table tbody td span.grid { width: 1.5rem; height: 1.5rem; font-size: 0.6875rem; }
                    .student-departments table tbody td p:last-child { margin-top: -0.5rem; font-size: 0.6875rem; line-height: 1.1; }
                    .student-departments table tbody td:not(:first-child) { vertical-align: middle; }
                    .student-departments > ul > li > div:first-child { border-bottom-width: 0; }
                    .student-departments table { border: 1px solid var(--border); border-radius: 0.375rem; border-collapse: separate; border-spacing: 0; overflow: hidden; background: var(--card); }
                    .student-departments table tbody tr + tr td { border-top: 1px solid var(--border); }
                    .student-departments table thead,
                    .recipient-departments table thead { background-color: #F5F2F3; color: var(--primary); }
                    .student-departments table th:first-child,
                    .student-departments table td:first-child { width: 46%; }
                    .student-departments table th:nth-child(2),
                    .student-departments table td:nth-child(2),
                    .student-departments table th:nth-child(3),
                    .student-departments table td:nth-child(3) { width: 27%; }
                    @media (min-width: 1024px) {
                        .student-departments table { min-width: 0; }
                    }
                    .student-departments > ul > li > div:first-child > div:first-child > span:first-child,
                    .recipient-departments > ul > li > div:first-child > div:first-child > span:first-child { display: none; }
                    .student-departments > ul > li > div:first-child p:first-child,
                    .recipient-departments > ul > li > div:first-child p:first-child { margin-bottom: 0; }
                    .student-departments > ul > li > div:first-child p:first-child,
                    .recipient-departments > ul > li > div:first-child p:first-child { font-size: 0.875rem; }
                    .student-departments > ul > li > div:first-child p + p,
                    .recipient-departments > ul > li > div:first-child p + p { margin-top: -0.25rem; line-height: 1.15; }
                    .student-departments table tbody td:first-child p:first-child { font-size: 0.75rem; }
                    .student-departments table tbody td:first-child p + p { margin-top: 0; }
                    .recipient-departments table tbody td:first-child div:first-child { font-size: 0.75rem; line-height: 1.25; }
                    .student-departments > ul > li > div:first-child > div:nth-child(1),
                    .recipient-departments > ul > li > div:first-child > div:nth-child(1) { min-width: 0; overflow-wrap: break-word; }
                    .student-departments > ul > li > div:first-child p,
                    .recipient-departments > ul > li > div:first-child p,
                    .student-departments table th,
                    .student-departments table td,
                    .recipient-departments table th,
                    .recipient-departments table td { overflow-wrap: break-word; word-break: normal; white-space: normal; }
                    .student-departments table,
                    .recipient-departments table { table-layout: fixed; }
                    .student-departments > ul,
                    .recipient-departments > ul { align-items: start; }
                    .student-departments > ul > li,
                    .recipient-departments > ul > li { align-self: start; }
                    .student-departments > ul > li,
                    .recipient-departments > ul > li { height: auto; }
                    .student-departments button[aria-label="Edit department"],
                    .recipient-departments button[aria-label="Edit department"],
                    .student-departments button[aria-label="Delete department"],
                    .recipient-departments button[aria-label="Delete department"] { background-color: transparent; }
                    .student-departments button[aria-label="Edit department"]:hover,
                    .recipient-departments button[aria-label="Edit department"]:hover,
                    .student-departments button[aria-label="Delete department"]:hover,
                    .recipient-departments button[aria-label="Delete department"]:hover { background-color: var(--primary-soft); }
                    .student-departments button[aria-label="Toggle department details"],
                    .recipient-departments button[aria-label="Toggle department details"] { width: 1.5rem; height: 1.5rem; padding: 0; background: transparent; color: var(--primary); }
                    button[aria-label="Edit course"] { color: var(--muted-foreground); }
                    button[aria-label="Edit course"]:hover { color: var(--primary); }
                    button[aria-label="Edit course"] + button[aria-label="Remove course"] { margin-left: 1rem; }
                    button[aria-label="Edit position"] + button[aria-label="Remove position"] { margin-left: 0.5rem; }
                </style>
                <section class="student-departments">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-start gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center text-primary"><x-icons.users class="h-7 w-7" /></span><div><h2 class="font-display text-lg font-bold text-foreground">Student Departments</h2><p class="text-xs text-muted-foreground">Configure the courses, year levels, and blocks available under each department.</p></div></div>
                        <button type="button" @click="openDepartmentModal('student')" class="inline-flex h-8 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-semibold text-primary-foreground hover:bg-primary/90"><x-icons.plus class="h-3.5 w-3.5" /> Add department</button>
                    </div>
                    <ul class="grid w-full gap-3 lg:grid-cols-2">
                        @forelse ($departments->where('type', 'student') as $department)
                            <li x-data="{ expanded: false }" class="overflow-hidden rounded-lg border border-border bg-card"><div class="flex items-start justify-between gap-3 border-b border-border px-4 py-3"><div class="flex min-w-0 items-start gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-md bg-primary-soft text-primary"><x-icons.briefcase class="h-5 w-5" /></span><div class="min-w-0"><p class="text-base font-bold text-primary">{{ $department->name }}</p><p class="mt-0.5 text-xs text-muted-foreground">{{ $department->description ?: 'No department description provided.' }}</p></div></div><div class="flex shrink-0 items-center gap-2"><button type="button" @click="openEditDepartment({ id: {{ $department->id }}, type: @js($department->type), name: @js($department->name), description: @js($department->description), courses: @js($department->courses->map(fn ($course) => ['course' => $course->course, 'year_level' => $course->year_level, 'block' => $course->block, 'description' => $course->description])->values()) })" class="grid h-8 w-8 place-items-center rounded-md bg-primary-soft text-primary transition-colors hover:bg-primary/15" aria-label="Edit department" title="Edit department"><x-icons.pencil class="h-3.5 w-3.5" /></button><button type="button" @click="openDeleteDepartment({ id: {{ $department->id }}, name: @js($department->name) })" class="grid h-8 w-8 place-items-center rounded-md bg-primary-soft text-primary transition-colors hover:bg-primary/15" aria-label="Delete department" title="Delete department"><x-icons.trash class="h-3.5 w-3.5" /></button><button type="button" @click="expanded = !expanded" class="grid h-8 w-8 place-items-center rounded-md bg-primary-soft text-primary transition-colors hover:bg-primary/15" aria-label="Toggle department details" title="Toggle department details"><x-icons.arrow-down class="h-4 w-4 transition-transform" ::class="expanded ? '' : 'rotate-180'" /></button></div></div><div x-show="expanded" x-collapse class="overflow-x-auto px-4 pb-4"><table class="w-full min-w-[42rem] text-left"><thead class="border-b border-border bg-primary-soft text-[10px] font-semibold uppercase tracking-wide text-muted-foreground"><tr><th class="px-4 py-2.5">Course</th><th class="px-4 py-2.5">Year levels</th><th class="px-4 py-2.5">Blocks</th></tr></thead><tbody class="divide-y divide-border">@forelse ($department->courses as $course)<tr class="align-top"><td class="px-4 py-3"><p class="text-sm font-bold text-foreground">{{ $course->course }}</p><p class="mt-0.5 text-[11px] leading-relaxed text-muted-foreground">{{ $course->description ?: 'No course description provided.' }}</p></td><td class="px-4 py-3"><div class="flex flex-wrap gap-2">@for ($year = 1; $year <= (int) $course->year_level; $year++)<span class="grid h-6 w-6 place-items-center rounded-full border border-primary/10 bg-primary-soft text-[10px] font-bold text-primary" title="Year {{ $year }}">{{ $year }}</span>@endfor</div></td><td class="px-4 py-3"><div class="flex flex-wrap gap-2">@if ($course->block !== null) @for ($block = 1; $block <= (int) $course->block; $block++)<span class="grid h-6 w-6 place-items-center rounded-full border border-primary/10 bg-primary-soft text-[10px] font-bold text-primary" title="Block {{ $block }}">{{ $block }}</span>@endfor @else <span class="text-xs text-muted-foreground">None</span> @endif</div></td></tr>@empty<tr><td colspan="3" class="px-4 py-6 text-center text-xs text-muted-foreground">No courses configured.</td></tr>@endforelse</tbody></table></div></li>
                        @empty
                            <li class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground lg:col-span-2">No student departments configured.</li>
                        @endforelse
                    </ul>
                </section>

                <section x-init="departmentPositions = departmentPositions.map(position => typeof position === 'string' ? { name: position, description: '' } : position); positionDescriptionDraft = ''; positionEditingPosition = null; positionEditingIndex = null; addPosition = () => { const name = positionDraft.trim(); if (name === '') { positionDraftError = 'Input position name'; return; } const position = { name, description: (positionDescriptionDraft || '').trim() }; if (positionEditingPosition) { departmentPositions.splice(positionEditingIndex, 0, position); positionEditingPosition = null; positionEditingIndex = null; } else { departmentPositions.push(position); } positionDraft = ''; positionDescriptionDraft = ''; positionDraftError = ''; }; const originalOpenEditDepartment = openEditDepartment; openEditDepartment = (department) => { if (department.type === 'recipient') { department.positions = @js($departments->where('type', 'recipient')->mapWithKeys(fn ($item) => [$item->id => $item->positions->map(fn ($position) => ['name' => $position->name, 'description' => $position->description])->values()])->all())[department.id] || []; positionDescriptionDraft = ''; positionEditingPosition = null; positionEditingIndex = null; } originalOpenEditDepartment(department); }" class="recipient-departments">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-start gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center text-primary"><x-icons.users class="h-7 w-7" /></span><div><h2 class="font-display text-lg font-bold text-foreground">Recipient Departments</h2><p class="text-xs text-muted-foreground">Configure the departments available for recipient accounts.</p></div></div>
                        <button type="button" @click="openDepartmentModal('recipient')" class="inline-flex h-8 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-semibold text-primary-foreground hover:bg-primary/90"><x-icons.plus class="h-3.5 w-3.5" /> Add department</button>
                    </div>
                    <ul class="grid w-full gap-3 lg:grid-cols-2">
                        @forelse ($departments->where('type', 'recipient') as $department)
                            <li x-data="{ expanded: false }" class="rounded-lg border border-border bg-card p-4"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="text-base font-bold text-primary">{{ $department->name }}</p><p class="mt-0.5 text-xs text-muted-foreground">{{ $department->description ?: 'Recipient department' }}</p></div><div class="flex shrink-0 items-center gap-2"><button type="button" @click="openEditDepartment({ id: {{ $department->id }}, type: @js($department->type), name: @js($department->name), description: @js($department->description), courses: [], positions: @js($department->positions->pluck('name')->values()) })" class="grid h-8 w-8 place-items-center rounded-md bg-primary-soft text-primary transition-colors hover:bg-primary/15" aria-label="Edit department" title="Edit department"><x-icons.pencil class="h-3.5 w-3.5" /></button><button type="button" @click="openDeleteDepartment({ id: {{ $department->id }}, name: @js($department->name) })" class="grid h-8 w-8 place-items-center rounded-md bg-primary-soft text-primary transition-colors hover:bg-primary/15" aria-label="Delete department" title="Delete department"><x-icons.trash class="h-3.5 w-3.5" /></button><button type="button" @click="expanded = !expanded" class="grid h-8 w-8 place-items-center rounded-md bg-primary-soft text-primary transition-colors hover:bg-primary/15" aria-label="Toggle department details" title="Toggle department details"><x-icons.arrow-down class="h-4 w-4 transition-transform" ::class="expanded ? '' : 'rotate-180'" /></button></div></div><div x-show="expanded" x-collapse class="mt-3 overflow-hidden rounded-md border border-border"><table class="w-full text-left"><thead class="bg-primary-soft text-[10px] font-semibold uppercase tracking-wide text-muted-foreground"><tr><th class="px-4 py-2.5">Recipient Positions</th></tr></thead><tbody class="divide-y divide-border">@forelse ($department->positions as $position)<tr><td class="px-4 py-2 text-xs font-semibold text-muted-foreground"><span class="mr-2 text-primary">&bull;</span>{{ $position->name }}</td></tr>@empty<tr><td class="px-4 py-3 text-xs text-muted-foreground">No positions configured.</td></tr>@endforelse</tbody></table></div></li>
                        @empty
                            <li class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground lg:col-span-2">No recipient departments configured.</li>
                        @endforelse
                    </ul>
                </section>
            </div>

            <script>
                document.addEventListener('click', (event) => {
                    const confirmButton = event.target.closest('button[aria-label="Confirm position"]');
                    if (!confirmButton) return;

                    requestAnimationFrame(() => {
                        const description = confirmButton.closest('form')?.querySelector('textarea[placeholder="Position description"]');
                        if (description) description.value = '';
                    });
                });

                document.addEventListener('click', (event) => {
                    const editButton = event.target.closest('button[aria-label="Edit position"]');
                    if (!editButton) return;

                    const row = editButton.closest('div.flex.items-start');
                    const savedDescription = row?.querySelector('input[name*="[description]"]')?.value || '';
                    requestAnimationFrame(() => {
                        const description = document.querySelector('textarea[placeholder="Position description"]');
                        if (description) {
                            description.value = savedDescription;
                            description.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    });
                });

                document.addEventListener('click', (event) => {
                    const cancelButton = event.target.closest('button[aria-label="Cancel position"]');
                    if (!cancelButton) return;

                    requestAnimationFrame(() => {
                        const description = document.querySelector('textarea[placeholder="Position description"]');
                        if (description) description.value = '';
                    });
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key !== 'Enter' || !event.target.matches('input[placeholder="Position name"]')) return;

                    requestAnimationFrame(() => {
                        const description = event.target.closest('form')?.querySelector('textarea[placeholder="Position description"]');
                        if (description) description.value = '';
                    });
                });
            </script>

            <div x-show="deleteDepartmentOpen" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div @click.outside="closeDeleteDepartment()" class="w-full max-w-md rounded-2xl border border-border bg-white shadow-2xl" role="dialog" aria-modal="true" aria-label="Delete department">
                    <div class="flex items-start justify-between border-b border-border px-5 py-4">
                        <div><p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">Department Management</p><h2 class="mt-1 text-lg font-bold text-destructive">Delete Department</h2></div>
                        <button type="button" @click="closeDeleteDepartment()" class="grid h-8 w-8 place-items-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground" aria-label="Close delete confirmation"><x-icons.x class="h-4 w-4" /></button>
                    </div>
                    <p class="px-5 pt-4 text-sm leading-6 text-gray-700">Are you sure you want to delete <span class="font-semibold" x-text="deleteDepartmentName"></span>? Its configured courses and positions will also be removed.</p>
                    <form method="POST" x-bind:action="`/admin/departments/${deleteDepartmentId}`" class="flex justify-end gap-2 p-5 pt-4">
                        @csrf
                        @method('DELETE')
                        <button type="button" @click="closeDeleteDepartment()" class="inline-flex h-8 items-center justify-center rounded-full border border-border bg-white px-4 text-[11px] font-semibold text-foreground hover:bg-muted">Cancel</button>
                        <button type="submit" class="inline-flex h-8 items-center justify-center rounded-full bg-destructive px-4 text-[11px] font-semibold text-white hover:bg-destructive/90">Delete department</button>
                    </form>
                </div>
            </div>

            <div x-show="addDepartmentOpen" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div @click.outside="resetDepartmentForm()" class="max-h-[min(75vh,45rem)] w-full max-w-2xl overflow-y-auto rounded-2xl border border-border bg-white shadow-2xl" role="dialog" aria-modal="true" aria-label="Add department">
                    <div class="flex items-start justify-between border-b border-border px-5 py-4">
                        <div><p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">Department Management</p><h2 class="mt-1 text-lg font-bold text-foreground" x-text="departmentEditId ? 'Edit Department' : (departmentType === 'student' ? 'Add Student Department' : 'Add Recipient Department')"></h2></div>
                        <button type="button" @click="resetDepartmentForm()" class="grid h-8 w-8 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground" aria-label="Close add department form"><x-icons.x class="h-4 w-4" /></button>
                    </div>
                    <form x-bind:action="departmentEditId ? '{{ route('admin.departments.update', ['department' => '__DEPARTMENT__']) }}'.replace('__DEPARTMENT__', departmentEditId) : '{{ route('admin.departments.store') }}'" method="POST" @submit="saveDepartment($event)" class="min-h-0 overflow-y-auto grid gap-4 p-5">
                        @csrf
                        <input type="hidden" name="_method" value="PUT" x-bind:disabled="!departmentEditId">
                        <input type="hidden" name="type" :value="departmentType">
                        <div>
                            <label for="settings-department-name" class="mb-1.5 block text-sm font-semibold text-foreground">Department Name</label>
                            <input id="settings-department-name" name="name" type="text" x-model="departmentName" @input="departmentNameError = ''" class="h-8 w-full rounded-md border border-input bg-white px-3 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring">
                            <p x-show="departmentNameError" x-text="departmentNameError" class="mt-1 text-xs text-destructive"></p>
                        </div>
                        <div>
                            <label for="settings-department-description" class="mb-1.5 block text-sm font-semibold text-foreground">Description</label>
                            <textarea id="settings-department-description" name="description" x-model="departmentDescription" rows="3" class="w-full rounded-md border border-input bg-white px-3 py-2 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring"></textarea>
                        </div>
                        <div x-show="departmentType === 'student'">
                            <div class="mb-1.5 flex items-center gap-2"><p class="text-sm font-semibold text-foreground">Courses</p><button type="button" @click="courseDraftError = ''; $nextTick(() => $refs.courseInput?.focus())" class="grid h-6 w-6 place-items-center rounded-md border border-gray-300 bg-white text-primary transition-colors hover:bg-primary-soft" aria-label="Add course" title="Add course"><x-icons.plus class="h-3.5 w-3.5" /></button></div>
                            <div class="grid gap-2">
                                <input x-ref="courseInput" type="text" x-model="courseDraft.course" placeholder="Course name" class="h-8 w-full rounded-md border border-input bg-white px-3 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring">
                                <textarea x-model="courseDraft.description" rows="2" placeholder="Course description or definition" class="w-full rounded-md border border-input bg-white px-3 py-2 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring"></textarea>
                                <div class="grid grid-cols-[7rem_7rem_auto_auto] items-center gap-2">
                                    <input type="number" min="1" max="6" step="1" x-model="courseDraft.year_level" placeholder="Year levels" class="h-8 w-full rounded-md border border-input bg-white px-2 text-sm text-foreground">
                                    <input type="number" min="1" max="10" step="1" x-model="courseDraft.block" placeholder="Blocks" class="h-8 w-full rounded-md border border-input bg-white px-2 text-sm text-foreground">
                                    <button type="button" @click="addCourse()" class="grid h-8 w-8 shrink-0 place-items-center rounded-md text-primary transition-colors hover:bg-primary-soft" aria-label="Confirm course" title="Confirm course"><x-icons.check class="h-4 w-4" /></button>
                                    <button type="button" @click="courseDraft._editingCourse ? (departmentCourses.splice(courseDraft._editingIndex, 0, courseDraft._editingCourse), resetCourseDraft()) : resetCourseDraft()" class="grid h-8 w-8 shrink-0 place-items-center rounded-md text-primary transition-colors hover:bg-primary-soft" aria-label="Cancel course" title="Cancel course"><x-icons.x class="h-4 w-4" /></button>
                                </div>
                                <p x-show="courseDraftError" x-text="courseDraftError" class="text-xs text-destructive"></p>
                            </div>
                            <div class="mt-2 grid gap-2"><template x-for="(course, index) in departmentCourses" :key="`${course.course}-${index}`"><div class="flex items-start justify-between gap-2 rounded-md bg-muted/50 px-3 py-2 text-sm"><div class="min-w-0"><input type="hidden" :name="`courses[${index}][course]`" :value="course.course"><input type="hidden" :name="`courses[${index}][year_level]`" :value="course.year_level"><input type="hidden" :name="`courses[${index}][block]`" :value="course.block"><input type="hidden" :name="`courses[${index}][description]`" :value="course.description"><div x-text="course.course" class="font-semibold"></div><div x-show="course.description" x-text="course.description" class="text-[11px] leading-relaxed text-muted-foreground"></div><div x-text="`${course.year_level} Year Levels${course.block ? ` · ${course.block} Blocks` : ''}`" class="text-[11px] text-muted-foreground"></div></div><div class="flex shrink-0 items-center gap-1"><button type="button" @click="courseDraft = { ...course, _editingCourse: { ...course }, _editingIndex: index }; departmentCourses.splice(index, 1); $nextTick(() => $refs.courseInput?.focus())" class="text-primary hover:text-primary-dark" aria-label="Edit course" title="Edit course"><x-icons.pencil class="h-4 w-4" /></button><button type="button" @click="removeCourse(index)" class="text-muted-foreground hover:text-destructive" aria-label="Remove course" title="Remove course"><x-icons.x class="h-4 w-4" /></button></div></div></template></div>
                        </div>
                        <div x-show="departmentType === 'recipient'">
                            <div class="mb-1.5 flex items-center gap-2"><p class="text-sm font-semibold text-foreground">Position Names</p><button type="button" @click="positionDraftError = ''; $nextTick(() => $refs.positionInput?.focus())" class="grid h-6 w-6 place-items-center rounded-md border border-gray-300 bg-white text-primary transition-colors hover:bg-primary-soft" aria-label="Add position" title="Add position"><x-icons.plus class="h-3.5 w-3.5" /></button></div>
                            <div class="grid gap-2"><input x-ref="positionInput" type="text" x-model="positionDraft" @input="positionDraftError = ''" @keydown.enter.prevent="addPosition()" placeholder="Position name" class="h-8 w-full rounded-md border border-input bg-white px-3 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring"><textarea x-model="positionDescriptionDraft" @input="positionDraftError = ''" rows="2" placeholder="Position description" class="w-full rounded-md border border-input bg-white px-3 py-2 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring"></textarea><p x-show="positionDraftError" x-text="positionDraftError" class="text-xs text-destructive"></p><div class="flex items-center justify-end gap-2"><button type="button" @click="addPosition()" class="grid h-8 w-8 shrink-0 place-items-center rounded-md text-primary hover:bg-primary-soft" aria-label="Confirm position" title="Confirm position"><x-icons.check class="h-4 w-4" /></button><button type="button" @click="positionEditingPosition ? (departmentPositions.splice(positionEditingIndex, 0, positionEditingPosition), positionEditingPosition = null, positionEditingIndex = null, positionDraft = '', positionDescriptionDraft = '', positionDraftError = '') : (positionDraft = '', positionDescriptionDraft = '', positionDraftError = '')" class="grid h-8 w-8 shrink-0 place-items-center rounded-md text-primary hover:bg-primary-soft" aria-label="Cancel position" title="Cancel position"><x-icons.x class="h-4 w-4" /></button></div></div>
                            <div class="mt-2 grid gap-2"><template x-for="(position, index) in departmentPositions" :key="`${position.name}-${index}`"><div class="flex items-start justify-between gap-2 rounded-md bg-muted/50 px-3 py-2 text-sm"><div class="min-w-0"><input type="hidden" :name="`positions[${index}][name]`" :value="position.name"><input type="hidden" :name="`positions[${index}][description]`" :value="position.description"><div x-text="position.name" class="font-semibold"></div><div x-show="position.description" x-text="position.description" class="text-[11px] leading-relaxed text-muted-foreground"></div></div><div class="flex shrink-0 items-center gap-4"><button type="button" @click="positionDraft = position.name; positionDescriptionDraft = position.description || ''; positionEditingPosition = { ...position }; positionEditingIndex = index; departmentPositions.splice(index, 1); $nextTick(() => $refs.positionInput?.focus())" class="text-muted-foreground hover:text-primary" aria-label="Edit position" title="Edit position"><x-icons.pencil class="h-4 w-4" /></button><button type="button" @click="removePosition(index)" class="text-muted-foreground hover:text-destructive" aria-label="Remove position" title="Remove position"><x-icons.x class="h-4 w-4" /></button></div></div></template></div>
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
                                                <template x-if="!recipient.avatar"><span x-text="(recipient.name || '?').charAt(0).toUpperCase()"></span></template>
                                            </span>
                                            <span class="mt-1 block truncate text-[10px] text-foreground" x-text="(recipient.name || 'Recipient').trim().split(/\s+/)[0]"></span>
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
                                                <template x-if="!recipient.avatar"><span x-text="(recipient.name || '?').charAt(0).toUpperCase()"></span></template>
                                            </span>
                                            <span class="mt-1 block truncate text-[10px] text-foreground" x-text="(recipient.name || 'Recipient').trim().split(/\s+/)[0]"></span>
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

        <div x-show="suggestedRecipientsOpen" x-init="recipientOptions = @js($recipients->map(fn ($recipient) => ['id' => $recipient->id, 'name' => trim(implode(' ', array_filter([$recipient->user?->first_name ?: $recipient->user?->name ?: $recipient->user?->username, $recipient->user?->middle_name ? strtoupper(substr(trim($recipient->user->middle_name), 0, 1)) . '.' : null, $recipient->user?->last_name]))) ?: 'Recipient', 'department' => trim((string) $recipient->department) !== '' ? $recipient->department : 'Department not specified', 'position' => trim((string) $recipient->designation) !== '' ? $recipient->designation : 'Position not specified', 'designation' => trim((string) $recipient->designation) !== '' ? $recipient->designation : 'Position not specified', 'avatar' => $recipient->user?->avatar_path ? asset('storage/' . $recipient->user->avatar_path) : null])->values())" x-cloak x-transition.opacity @click.stop class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4">
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
                                        <template x-if="!recipient.avatar"><span x-text="(recipient.name || '?').charAt(0).toUpperCase()"></span></template>
                                    </span>
                                    <span class="mt-1 block truncate text-[10px] text-foreground" x-text="recipient.name"></span>
                                </div>
                            </template>
                        </template>
                    </div>
                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                        <template x-for="recipient in recipientOptions.filter(item => (item.name || '').toLowerCase().includes(recipientSearch.toLowerCase()) || (item.department || '').toLowerCase().includes(recipientSearch.toLowerCase()))" :key="recipient.id">
                            <button type="button" @click="recipientDraftIds.includes(recipient.id) ? recipientDraftIds = recipientDraftIds.filter(id => id !== recipient.id) : recipientDraftIds.push(recipient.id)" class="flex items-center gap-3 rounded-lg border p-3 text-left transition-colors hover:border-primary hover:bg-primary-soft" :class="recipientDraftIds.includes(recipient.id) ? 'border-primary bg-primary-soft' : 'border-border bg-card'">
                                <span class="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-soft text-xs font-bold text-primary">
                                    <template x-if="recipient.avatar"><img :src="recipient.avatar" :alt="recipient.name" class="h-full w-full object-cover"></template>
                                    <template x-if="!recipient.avatar"><span x-text="(recipient.name || '?').charAt(0).toUpperCase()"></span></template>
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-xs font-semibold text-foreground" x-text="recipient.name"></span>
                                    <span class="block truncate text-[10px] text-muted-foreground" x-text="`${recipient.department || 'Department not specified'} · ${recipient.designation || recipient.position || 'Position not specified'}`"></span>
                                </span>
                            </button>
                        </template>
                    </div>
                    <p x-show="recipientOptions.filter(item => (item.name || '').toLowerCase().includes(recipientSearch.toLowerCase()) || (item.department || '').toLowerCase().includes(recipientSearch.toLowerCase())).length === 0" class="py-8 text-center text-sm text-muted-foreground">No recipients found.</p>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('alpine:initialized', () => {
            const departmentSettingsRoot = document.querySelector('[x-data*="addDepartmentOpen"]');
            if (!departmentSettingsRoot || !window.Alpine) return;

            const state = Alpine.$data(departmentSettingsRoot);
            const recipientPositionData = @json($departments->where('type', 'recipient')->mapWithKeys(fn ($department) => [$department->id => $department->positions->map(fn ($position) => ['name' => $position->name, 'description' => $position->description])->values()])->all());
            state.openEditDepartment = function (department) {
                this.departmentEditId = department.id;
                this.departmentType = department.type;
                this.departmentName = department.name;
                this.departmentDescription = department.description || '';
                this.departmentCourses = (department.courses || []).map(course => ({ course: course.course, year_level: String(course.year_level), block: course.block === null ? '' : String(course.block), description: course.description || '' }));
                this.departmentPositions = department.type === 'recipient' ? (recipientPositionData[department.id] || []) : (department.positions || []);
                this.resetCourseDraft();
                this.positionDraft = '';
                this.positionDescriptionDraft = '';
                this.positionDraftError = '';
                this.departmentNameError = '';
                this.addDepartmentOpen = true;
            };

            const originalSaveDepartment = state.saveDepartment;
            state.saveDepartment = function (event) {
                if (this.departmentName.trim() === '') {
                    event.preventDefault();
                    this.departmentNameError = 'Input department name';
                    return;
                }

                if (this.departmentType === 'recipient' && this.departmentPositions.filter(position => (typeof position === 'string' ? position : position.name || '').trim() !== '').length === 0) {
                    event.preventDefault();
                    this.positionDraftError = "Add atleast one staff's position";
                    return;
                }

                return originalSaveDepartment.call(this, event);
            };
        });

        document.querySelectorAll('.student-departments > ul > li, .recipient-departments > ul > li').forEach((card) => {
            card.addEventListener('click', (event) => {
                if (event.target.closest('button, a, input, textarea, select')) return;

                card.querySelector('[aria-label="Toggle department details"]')?.click();
            });
        });

        document.querySelectorAll('.recipient-departments table').forEach((table) => {
            const header = table.querySelector('thead tr');
            if (header) header.innerHTML = '<th class="px-4 py-2.5">Position</th><th class="px-4 py-2.5">Description</th>';
            table.querySelectorAll('tbody tr').forEach((row) => {
                const cell = row.querySelector('td');
                if (!cell || row.querySelectorAll('td').length > 1) return;
                const position = cell.textContent.trim().replace(/^•\s*/, '');
                cell.outerHTML = `<td class="px-4 py-2 text-xs font-bold text-foreground">${position}</td><td class="px-4 py-2 text-xs text-muted-foreground">No position description provided.</td>`;
            });
        });

        const recipientPositionData = @json($departments->where('type', 'recipient')->values()->map(fn ($department) => $department->positions->map(fn ($position) => ['name' => $position->name, 'description' => $position->description])->values())->values());
        document.querySelectorAll('.recipient-departments > ul > li').forEach((card, departmentIndex) => {
            const positions = recipientPositionData[departmentIndex] || [];
            card.querySelectorAll('tbody tr').forEach((row, positionIndex) => {
                const descriptionCell = row.querySelectorAll('td')[1];
                if (descriptionCell && positions[positionIndex]) {
                    descriptionCell.textContent = positions[positionIndex].description || 'No position description provided.';
                }
            });
        });

        document.querySelectorAll('.recipient-departments > ul > li p').forEach((description) => {
            if (description.textContent.trim() === 'Recipient department') description.textContent = 'No department description provided.';
        });
    </script>
</div>
</x-app-layout>
