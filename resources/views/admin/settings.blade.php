<x-app-layout :role="'admin'" title="System Settings">
    <div class="-mt-1 sm:-mt-2">
    <div x-data="{ settingsTab: 'category', deleteModalOpen: false, deleteCategoryName: '', deleteCategoryUrl: '', addCategoryOpen: @js(request()->boolean('add_category')), editCategoryOpen: false, editCategory: { id: null, name: '', description: '', recipientId: '', isSensitive: false, allowsHiddenIdentity: true }, suggestedRecipientsOpen: false, recipientSearch: '', selectedRecipientIds: [], recipientDraftIds: [], hierarchyLevels: [{ level: 1, recipientIds: [] }], recipientOptions: @js($recipients->map(fn ($recipient) => ['id' => $recipient->id, 'first_name' => $recipient->user?->first_name ?: $recipient->user?->name, 'name' => $recipient->user?->display_name, 'department' => $recipient->unit, 'avatar' => $recipient->user?->avatar_path ? asset('storage/' . $recipient->user->avatar_path) : null])->values()), categoryOptions: @js($categories->map(fn ($category) => ['id' => $category->id, 'name' => $category->name, 'description' => $category->description, 'recipientId' => $category->recipient_id, 'isSensitive' => (bool) $category->is_sensitive, 'allowsHiddenIdentity' => (bool) $category->allows_hidden_identity, 'suggestedRecipientIds' => $category->suggestedRecipients->pluck('id')->values(), 'hierarchyLevels' => $category->escalationHierarchies->groupBy('level')->map(fn ($items, $level) => ['level' => (int) $level, 'recipientIds' => $items->pluck('recipient_id')->values()])->values()])->values()), openEditCategory(categoryId) { const category = this.categoryOptions.find(item => item.id === categoryId); if (!category) return; this.selectedRecipientIds = [...(category.suggestedRecipientIds || [])]; this.hierarchyLevels = (category.hierarchyLevels?.length ? category.hierarchyLevels : [{ level: 1, recipientIds: [] }]).map(level => ({ level: level.level, recipientIds: [...level.recipientIds] })); this.recipientDraftIds = []; this.recipientSearch = ''; this.editCategory = { id: category.id, name: category.name, description: category.description || '', recipientId: category.recipientId || '', isSensitive: !!category.isSensitive, allowsHiddenIdentity: !!category.allowsHiddenIdentity }; this.editCategoryOpen = true; }, resetEditCategory() { this.editCategory = { id: null, name: '', description: '', recipientId: '', isSensitive: false, allowsHiddenIdentity: true }; this.hierarchyLevels = [{ level: 1, recipientIds: [] }]; }, closeEditCategory() { this.resetEditCategory(); this.selectedRecipientIds = []; this.recipientDraftIds = []; this.recipientSearch = ''; this.suggestedRecipientsOpen = false; this.editCategoryOpen = false; }, addHierarchyLevel() { const highest = this.hierarchyLevels.reduce((max, level) => Math.max(max, Number(level.level) || 0), 0); this.hierarchyLevels.push({ level: highest + 1, recipientIds: [] }); }, removeHierarchyLevel(index) { if (this.hierarchyLevels.length > 1) this.hierarchyLevels.splice(index, 1); }, openSuggestedRecipients() { this.recipientDraftIds = [...this.selectedRecipientIds]; this.recipientSearch = ''; this.suggestedRecipientsOpen = true; }, selectSuggestedRecipients() { this.selectedRecipientIds = [...new Set(this.recipientDraftIds)]; this.suggestedRecipientsOpen = false; this.recipientSearch = ''; }, closeSuggestedRecipients() { this.recipientDraftIds = []; this.recipientSearch = ''; this.suggestedRecipientsOpen = false; }, resetCategoryForm() { this.$refs.categoryForm?.reset(); this.selectedRecipientIds = []; this.hierarchyLevels = [{ level: 1, recipientIds: [] }]; this.recipientDraftIds = []; this.recipientSearch = ''; this.suggestedRecipientsOpen = false; }, closeCategoryModal() { this.resetCategoryForm(); this.addCategoryOpen = false; } }" class="grid gap-4 sm:gap-6">
        <span x-init="settingsTab = @js(request('settings_tab', 'category'))" class="hidden"></span>
        <div class="flex items-end justify-between gap-2">
            <div class="inline-flex min-w-0 items-center gap-3 border-b border-border sm:gap-6">
                <button type="button" @click="settingsTab = 'category'" :aria-selected="settingsTab === 'category'" class="inline-flex items-center whitespace-nowrap border-b-2 px-0.5 pb-2 text-[13px] transition-colors sm:px-1 sm:text-sm" :class="settingsTab === 'category' ? 'border-[#7a1d2a] font-semibold text-[#7a1d2a]' : 'border-transparent font-medium text-muted-foreground hover:text-foreground'">Category</button>
                <button type="button" @click="settingsTab = 'escalation'" :aria-selected="settingsTab === 'escalation'" class="inline-flex items-center whitespace-nowrap border-b-2 px-0.5 pb-2 text-[13px] transition-colors sm:px-1 sm:text-sm" :class="settingsTab === 'escalation' ? 'border-[#7a1d2a] font-semibold text-[#7a1d2a]' : 'border-transparent font-medium text-muted-foreground hover:text-foreground'">Escalation<span class="hidden min-[400px]:inline">&nbsp;Hierarchy</span></button>
                <button type="button" @click="settingsTab = 'units'" :aria-selected="settingsTab === 'units'" class="inline-flex items-center whitespace-nowrap border-b-2 px-0.5 pb-2 text-[13px] transition-colors sm:px-1 sm:text-sm" :class="settingsTab === 'units' ? 'border-[#7a1d2a] font-semibold text-[#7a1d2a]' : 'border-transparent font-medium text-muted-foreground hover:text-foreground'">Colleges and Offices</button>
            </div>
            <button type="button" x-bind:class="settingsTab === 'category' ? '' : 'invisible'" x-on:click.prevent.stop="addCategoryOpen = true" class="mb-1 inline-flex h-8 shrink-0 items-center gap-1 rounded-md bg-primary px-2.5 text-xs font-semibold text-primary-foreground hover:bg-primary/90 sm:h-9 sm:gap-1.5 sm:px-3 sm:text-sm"><x-icons.plus class="h-4 w-4" /> Add<span class="hidden sm:inline">&nbsp;category</span></button>
        </div>

        <div class="grid min-h-[28rem]">
        <div x-cloak x-bind:class="settingsTab === 'category' ? 'opacity-100' : 'pointer-events-none opacity-0'" class="col-start-1 row-start-1 transition-opacity duration-150 ease-out">
            <ul class="grid gap-2 lg:grid-cols-2">
                @forelse ($categories as $category)
                    <li x-data="{ expanded: false }" class="overflow-hidden rounded-lg border border-border bg-card">
                        <div @click="expanded = !expanded" class="flex cursor-pointer items-start justify-between gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-base font-bold text-primary">
                                    <span class="min-w-0 truncate">{{ $category->name }}</span>
                                    @if ($category->is_sensitive)
                                        <span class="rounded-full bg-primary px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary-foreground">Sensitive</span>
                                    @endif
                                    @unless ($category->allows_hidden_identity)
                                        <span class="rounded-full border border-border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Name required</span>
                                    @endunless
                                </p>
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
                                    <tr><th class="px-4 py-2.5">Recipient Name</th><th class="px-4 py-2.5">College / Office</th><th class="px-4 py-2.5">Designation</th></tr>
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
                                            <td class="px-4 py-2 text-xs text-muted-foreground">{{ trim((string) $recipient->unit) !== '' ? $recipient->unit : 'College or office not specified' }}</td>
                                            <td class="px-4 py-2 text-xs text-muted-foreground">{{ trim((string) $recipient->designation) !== '' ? $recipient->designation : 'Designation not specified' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="px-4 py-2 text-center text-muted-foreground">No recipients assigned</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </li>
                @empty
                    <li class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground lg:col-span-2">
                        <p>No complaint categories configured.</p>
                        <p class="mt-1 text-xs">Add your own, or start from the categories based on the student handbook and adjust them.</p>
                        <form action="{{ route('admin.categories.starters') }}" method="POST" class="mt-4">
                            @csrf
                            <button type="submit" class="inline-flex h-8 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-semibold text-primary-foreground hover:bg-primary/90"><x-icons.plus class="h-3.5 w-3.5" /> Add starter categories</button>
                        </form>
                    </li>
                @endforelse
            </ul>
        </div>

        @php
            $escalationCategories = $categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'paths' => $category->escalationHierarchies
                    ->groupBy('path_number')
                    ->sortKeys()
                    ->values()
                    ->map(fn ($entries) => [
                        'name' => (string) ($entries->first()->path_name ?? ''),
                        'steps' => $entries->sortBy('level')->pluck('recipient_id')->map(fn ($id) => (string) $id)->values()->all(),
                    ])
                    ->all(),
            ])->values();
            $escalationRecipients = $recipients->map(fn ($recipient) => [
                'id' => (string) $recipient->id,
                'label' => trim(($recipient->user?->table_name ?? 'Recipient') . ' · ' . (trim((string) $recipient->designation) !== '' ? $recipient->designation : 'No designation') . ', ' . (trim((string) $recipient->unit) !== '' ? $recipient->unit : 'No department')),
            ])->values();
        @endphp
        <div x-cloak x-data="{
            categories: @js($escalationCategories),
            recipients: @js($escalationRecipients),
            selectedId: @js((int) request('category') ?: ($categories->first()?->id)),
            paths: [],
            dirty: false,
            init() {
                if (!this.categories.some((item) => item.id === this.selectedId)) this.selectedId = this.categories[0]?.id ?? null;
                this.load();
            },
            get category() {
                return this.categories.find((item) => item.id === this.selectedId) ?? null;
            },
            load() {
                this.paths = (this.category?.paths ?? []).map((path) => ({ name: path.name, steps: [...path.steps] }));
                this.dirty = false;
            },
            select(id) {
                id = Number(id);
                if (id === this.selectedId) return;
                if (this.dirty && !window.confirm('Leave this category without saving your changes?')) {
                    this.$nextTick(() => { if (this.$refs.categorySelect) this.$refs.categorySelect.value = this.selectedId; });
                    return;
                }
                this.selectedId = id;
                this.load();
            },
            summary(category) {
                const paths = category.paths.filter((path) => path.steps.length);
                if (!paths.length) return 'Not set';
                const levels = paths.reduce((total, path) => total + path.steps.length, 0);
                return `${paths.length} path${paths.length === 1 ? '' : 's'} · ${levels} level${levels === 1 ? '' : 's'}`;
            },
            addPath() { this.paths.push({ name: '', steps: [''] }); this.dirty = true; },
            removePath(index) { this.paths.splice(index, 1); this.dirty = true; },
            addStep(path) { path.steps.push(''); this.dirty = true; },
            removeStep(path, index) { path.steps.splice(index, 1); this.dirty = true; },
            moveStep(path, index, offset) {
                const target = index + offset;
                if (target < 0 || target >= path.steps.length) return;
                const [moved] = path.steps.splice(index, 1);
                path.steps.splice(target, 0, moved);
                this.dirty = true;
            },
        }" x-bind:class="settingsTab === 'escalation' ? 'opacity-100' : 'pointer-events-none opacity-0'" class="col-start-1 row-start-1 transition-opacity duration-150 ease-out">
            <div class="mb-3 rounded-xl border border-primary/15 bg-primary-soft/40 px-4 py-3 text-xs leading-relaxed text-foreground">
                <p class="font-semibold text-primary">How escalation works</p>
                <p class="mt-0.5 text-muted-foreground">When you tap <span class="font-semibold text-foreground">Escalate</span> on a ticket, only the people listed here for that ticket's category can be chosen. A <span class="font-semibold text-foreground">path</span> is one route a ticket can go up through, from Level 1 to the last level. Add another path when the same category can be escalated through a different office.</p>
            </div>

            <template x-if="categories.length === 0">
                <p class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">Add a category first, then set who its tickets can be escalated to.</p>
            </template>

            <div x-show="categories.length > 0" class="grid gap-3 lg:grid-cols-[17rem_minmax(0,1fr)] lg:items-start">
                <!-- Category picker: a dropdown on phones, a list on desktop -->
                <div class="lg:hidden">
                    <label for="escalation-category" class="mb-1 block text-xs font-semibold text-muted-foreground">Category</label>
                    <select id="escalation-category" x-ref="categorySelect" x-on:change="select($event.target.value)" class="h-10 w-full rounded-md border border-input bg-white px-3 text-sm font-semibold text-foreground outline-none focus:ring-1 focus:ring-ring">
                        <template x-for="item in categories" :key="item.id">
                            <option :value="item.id" :selected="item.id === selectedId" x-text="`${item.name} (${summary(item)})`"></option>
                        </template>
                    </select>
                </div>
                <aside class="hidden overflow-hidden rounded-xl border border-border bg-card lg:block">
                    <p class="border-b border-border bg-muted/50 px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Categories</p>
                    <ul class="max-h-[32rem] overflow-y-auto p-2">
                        <template x-for="item in categories" :key="item.id">
                            <li>
                                <button type="button" x-on:click="select(item.id)" class="flex w-full items-start justify-between gap-2 rounded-lg px-3 py-2.5 text-left transition-colors" :class="selectedId === item.id ? 'bg-primary-soft' : 'hover:bg-muted/60'">
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold leading-snug" :class="selectedId === item.id ? 'text-primary' : 'text-foreground'" x-text="item.name"></span>
                                        <span class="mt-0.5 block text-[11px]" :class="summary(item) === 'Not set' ? 'text-amber-700' : 'text-muted-foreground'" x-text="summary(item)"></span>
                                    </span>
                                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full" :class="summary(item) === 'Not set' ? 'bg-amber-400' : 'bg-emerald-500'"></span>
                                </button>
                            </li>
                        </template>
                    </ul>
                </aside>

                <!-- Paths of the selected category -->
                <form method="POST" x-bind:action="`/admin/categories/${selectedId}/escalation`" class="min-w-0 rounded-xl border border-border bg-card">
                    @csrf
                    @method('PUT')
                    <div class="flex flex-wrap items-start justify-between gap-2 border-b border-border px-4 py-3">
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Escalation hierarchy for</p>
                            <h2 class="font-display text-lg font-bold leading-tight text-primary" x-text="category?.name"></h2>
                        </div>
                        <span x-show="dirty" x-cloak class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700"><span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>Unsaved changes</span>
                    </div>

                    <div class="grid gap-3 p-4">
                        <div x-show="paths.length === 0" class="rounded-lg border border-dashed border-border p-6 text-center">
                            <p class="text-sm font-semibold text-foreground">No escalation path yet</p>
                            <p class="mt-1 text-xs text-muted-foreground">Tickets in this category cannot be escalated until at least one path is added.</p>
                        </div>

                        <template x-for="(path, pathIndex) in paths" :key="pathIndex">
                            <section class="rounded-lg border border-border bg-background">
                                <div class="flex items-center gap-2 border-b border-border px-3 py-2">
                                    <span class="shrink-0 rounded-full bg-primary px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-primary-foreground" x-text="`Path ${pathIndex + 1}`"></span>
                                    <input type="text" :name="`paths[${pathIndex}][name]`" x-model="path.name" x-on:input="dirty = true" maxlength="100" placeholder="Name this path, e.g. Through the Dean's office" aria-label="Path name" class="h-8 min-w-0 flex-1 rounded-md border border-transparent bg-transparent px-2 text-sm font-semibold text-foreground outline-none placeholder:font-normal placeholder:text-muted-foreground hover:border-input focus:border-input focus:bg-white focus:ring-1 focus:ring-ring">
                                    <button type="button" x-on:click="removePath(pathIndex)" class="grid h-8 w-8 shrink-0 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive" aria-label="Delete path" title="Delete path"><x-icons.trash class="h-4 w-4" /></button>
                                </div>

                                <ol class="grid gap-2 p-3">
                                    <template x-for="(recipientId, stepIndex) in path.steps" :key="stepIndex">
                                        <li class="relative grid grid-cols-[2rem_minmax(0,1fr)_auto] items-center gap-2">
                                            <span x-show="stepIndex < path.steps.length - 1" class="absolute top-8 -bottom-2 left-4 w-px bg-primary/25" aria-hidden="true"></span>
                                            <span class="relative grid h-8 w-8 place-items-center rounded-full bg-primary-soft text-xs font-bold text-primary" :title="`Level ${stepIndex + 1}`" x-text="stepIndex + 1"></span>
                                            <select :name="`paths[${pathIndex}][recipient_ids][]`" x-model="path.steps[stepIndex]" x-on:change="dirty = true" required :aria-label="`Level ${stepIndex + 1} recipient`" class="h-9 w-full min-w-0 rounded-md border border-input bg-white px-2 text-xs text-foreground outline-none focus:ring-1 focus:ring-ring">
                                                <option value="">Select recipient</option>
                                                <template x-for="recipient in recipients" :key="recipient.id">
                                                    <option :value="recipient.id" :selected="recipient.id === path.steps[stepIndex]" :disabled="path.steps.includes(recipient.id) && recipient.id !== path.steps[stepIndex]" x-text="recipient.label"></option>
                                                </template>
                                            </select>
                                            <span class="flex shrink-0 items-center">
                                                <button type="button" x-on:click="moveStep(path, stepIndex, -1)" :disabled="stepIndex === 0" class="grid h-8 w-7 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-primary disabled:opacity-30" aria-label="Move level up" title="Move up"><x-icons.arrow-down class="h-4 w-4" /></button>
                                                <button type="button" x-on:click="moveStep(path, stepIndex, 1)" :disabled="stepIndex === path.steps.length - 1" class="grid h-8 w-7 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-primary disabled:opacity-30" aria-label="Move level down" title="Move down"><x-icons.arrow-down class="h-4 w-4 rotate-180" /></button>
                                                <button type="button" x-on:click="removeStep(path, stepIndex)" class="grid h-8 w-7 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive" aria-label="Remove level" title="Remove level"><x-icons.x class="h-4 w-4" /></button>
                                            </span>
                                        </li>
                                    </template>
                                    <li x-show="path.steps.length === 0" class="text-xs text-muted-foreground">This path has no levels, so it will not be saved.</li>
                                </ol>

                                <div class="px-3 pb-3">
                                    <button type="button" x-on:click="addStep(path)" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-dashed border-primary/50 px-2.5 text-xs font-semibold text-primary transition-colors hover:bg-primary-soft"><x-icons.plus class="h-3.5 w-3.5" /> <span x-text="`Add level ${path.steps.length + 1}`"></span></button>
                                </div>
                            </section>
                        </template>

                        <button type="button" x-on:click="addPath()" class="inline-flex h-10 items-center justify-center gap-1.5 rounded-lg border border-dashed border-primary/60 bg-primary-soft/50 px-4 text-sm font-semibold text-primary transition-colors hover:bg-primary-soft"><x-icons.plus class="h-4 w-4" /> <span x-text="paths.length ? 'Add another path' : 'Add first path'"></span></button>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-border px-4 py-3">
                        <button type="button" x-show="dirty" x-cloak x-on:click="load()" class="inline-flex h-9 items-center justify-center rounded-full border border-border bg-white px-4 text-xs font-semibold text-foreground transition-colors hover:bg-muted">Discard</button>
                        <button type="submit" class="inline-flex h-9 items-center justify-center rounded-full bg-primary px-5 text-xs font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Save hierarchy</button>
                    </div>
                </form>
            </div>
        </div>

        <div x-data="{ addDepartmentOpen: false, deleteDepartmentOpen: false, deleteDepartmentId: null, deleteDepartmentName: '', departmentEditId: null, departmentType: 'college', departmentName: '', departmentDescription: '', departmentNameError: '', departmentCourses: [], departmentPositions: [], courseDraft: { course: '', year_level: '', block: '', description: '' }, positionDraft: '', courseDraftError: '', positionDraftError: '', openDepartmentModal(type) { this.departmentEditId = null; this.departmentType = type; this.departmentName = ''; this.departmentDescription = ''; this.departmentCourses = []; this.departmentPositions = []; this.addDepartmentOpen = true; this.resetCourseDraft(); this.positionDraft = ''; this.positionDraftError = ''; this.departmentNameError = ''; }, openEditDepartment(department) { this.departmentEditId = department.id; this.departmentType = department.type; this.departmentName = department.name; this.departmentDescription = department.description || ''; this.departmentCourses = (department.courses || []).map(course => ({ course: course.course, year_level: String(course.year_level), block: course.block === null ? '' : String(course.block), description: course.description || '' })); this.departmentPositions = department.positions || []; this.resetCourseDraft(); this.positionDraft = ''; this.positionDraftError = ''; this.departmentNameError = ''; this.addDepartmentOpen = true; }, openDeleteDepartment(department) { this.deleteDepartmentId = department.id; this.deleteDepartmentName = department.name; this.deleteDepartmentOpen = true; }, closeDeleteDepartment() { this.deleteDepartmentId = null; this.deleteDepartmentName = ''; this.deleteDepartmentOpen = false; }, addCourse() { const hasCourse = this.courseDraft.course.trim() !== ''; const hasYear = this.courseDraft.year_level !== ''; if (!hasCourse && !hasYear) { this.courseDraftError = 'Program and year level are required'; return; } if (!hasCourse) { this.courseDraftError = 'Input program name'; return; } if (!hasYear) { this.courseDraftError = 'Input number of year levels'; return; } this.departmentCourses.push({ ...this.courseDraft, course: this.courseDraft.course.trim(), description: (this.courseDraft.description || '').trim(), block: this.courseDraft.block === '' ? null : this.courseDraft.block }); this.courseDraftError = ''; this.resetCourseDraft(); }, addPosition() { const position = this.positionDraft.trim(); if (position === '') { this.positionDraftError = 'Input designation name'; return; } this.departmentPositions.push(position); this.positionDraft = ''; this.positionDraftError = ''; }, resetCourseDraft() { this.courseDraft = { course: '', year_level: '', block: '', description: '' }; this.courseDraftError = ''; }, removeCourse(index) { this.departmentCourses.splice(index, 1); }, removePosition(index) { this.departmentPositions.splice(index, 1); }, saveDepartment(event) { if (this.departmentName.trim() === '') { event.preventDefault(); this.departmentNameError = 'Input a name'; return; } if (this.departmentType === 'college' && this.departmentCourses.length === 0) { event.preventDefault(); this.courseDraftError = 'Add at least one program'; } }, resetDepartmentForm() { this.departmentEditId = null; this.departmentName = ''; this.departmentNameError = ''; this.departmentCourses = []; this.departmentPositions = []; this.resetCourseDraft(); this.positionDraft = ''; this.positionDraftError = ''; this.addDepartmentOpen = false; } }" x-cloak x-bind:class="settingsTab === 'units' ? 'opacity-100' : 'pointer-events-none opacity-0'" class="col-start-1 row-start-1 transition-opacity duration-150 ease-out">
            <div class="grid gap-6">
                <style>
                    .student-departments table thead th { padding-top: 0.5rem; padding-bottom: 0.5rem; }
                    .student-departments table tbody td { padding-top: 0.375rem; padding-bottom: 0.375rem; }
                    .student-departments table tbody td > div { display: grid; grid-template-columns: repeat(5, minmax(0, 1.5rem)); gap: 0.25rem; }
                    .student-departments table tbody td span.grid { width: 100%; height: auto; aspect-ratio: 1; font-size: 0.6875rem; }
                    .student-departments table tbody td > div > span:not(.grid) { grid-column: 1 / -1; }
                    .student-departments table tbody td p:last-child { margin-top: -0.5rem; font-size: 0.6875rem; line-height: 1.1; }
                    .student-departments table tbody td:not(:first-child) { vertical-align: middle; }
                    .student-departments > ul > li > div:first-child { border-bottom-width: 0; }
                    .student-departments table { border: 1px solid var(--border); border-radius: 0.375rem; border-collapse: separate; border-spacing: 0; overflow: hidden; background: var(--card); }
                    .student-departments table tbody tr + tr td { border-top: 1px solid var(--border); }
                    .student-departments table thead,
                    .recipient-departments table thead { background-color: #F5F2F3; color: var(--primary); }
                    .student-departments table th:first-child,
                    .student-departments table td:first-child { width: auto; }
                    .student-departments table th:nth-child(2),
                    .student-departments table td:nth-child(2),
                    .student-departments table th:nth-child(3),
                    .student-departments table td:nth-child(3) { width: 9.5rem; padding-left: 0.5rem; padding-right: 0.5rem; }
                    @media (min-width: 1024px) and (max-width: 1279px) {
                        .student-departments table th:nth-child(2),
                        .student-departments table td:nth-child(2),
                        .student-departments table th:nth-child(3),
                        .student-departments table td:nth-child(3) { width: 7.5rem; }
                    }
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
                </style>
                <section class="student-departments">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-start gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center text-primary"><x-icons.users class="h-7 w-7" /></span><div><h2 class="font-display text-lg font-bold text-foreground">Colleges</h2><p class="text-xs text-muted-foreground">Configure the programs, year levels, and blocks each college offers, and the designations of its staff.</p></div></div>
                        <button type="button" @click="openDepartmentModal('college')" class="inline-flex h-8 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-semibold text-primary-foreground hover:bg-primary/90"><x-icons.plus class="h-3.5 w-3.5" /> Add college</button>
                    </div>
                    <ul class="grid w-full gap-3 lg:grid-cols-2">
                        @forelse ($units->where('type', 'college') as $department)
                            <li x-data="{ expanded: false }" @click="if (! $event.target.closest('button, a, [data-unit-details]')) expanded = ! expanded" class="cursor-pointer overflow-hidden rounded-lg border border-border bg-card"><div class="flex items-start justify-between gap-3 border-b border-border px-4 py-3"><div class="flex min-w-0 items-start gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-md bg-primary-soft text-primary"><x-icons.briefcase class="h-5 w-5" /></span><div class="min-w-0"><p class="text-base font-bold text-primary">{{ $department->name }}</p><p class="mt-0.5 text-xs text-muted-foreground">{{ $department->description ?: 'No description provided.' }}</p></div></div><div class="flex shrink-0 items-center gap-2"><button type="button" @click="openEditDepartment({ id: {{ $department->id }}, type: @js($department->type), name: @js($department->name), description: @js($department->description), courses: @js($department->programs->map(fn ($course) => ['course' => $course->name, 'year_level' => $course->year_level, 'block' => $course->block, 'description' => $course->description])->values()), positions: @js($department->designations->map(fn ($designation) => ['name' => $designation->name, 'description' => $designation->description])->values()) })" class="grid h-8 w-8 place-items-center rounded-md bg-primary-soft text-primary transition-colors hover:bg-primary/15" aria-label="Edit department" title="Edit"><x-icons.pencil class="h-3.5 w-3.5" /></button><button type="button" @click="openDeleteDepartment({ id: {{ $department->id }}, name: @js($department->name) })" class="grid h-8 w-8 place-items-center rounded-md bg-primary-soft text-primary transition-colors hover:bg-primary/15" aria-label="Delete department" title="Delete"><x-icons.trash class="h-3.5 w-3.5" /></button><button type="button" @click="expanded = !expanded" class="grid h-8 w-8 place-items-center rounded-md bg-primary-soft text-primary transition-colors hover:bg-primary/15" aria-label="Toggle department details" title="Show details"><x-icons.arrow-down class="h-4 w-4 transition-transform" ::class="expanded ? '' : 'rotate-180'" /></button></div></div><div x-show="expanded" x-collapse data-unit-details class="cursor-default overflow-x-auto px-4 pb-4"><table class="w-full min-w-[42rem] text-left"><thead class="border-b border-border bg-primary-soft text-[10px] font-semibold uppercase tracking-wide text-muted-foreground"><tr><th class="px-4 py-2.5">Program</th><th class="px-4 py-2.5">Year levels</th><th class="px-4 py-2.5">Blocks</th></tr></thead><tbody class="divide-y divide-border">@forelse ($department->programs as $course)<tr class="align-top"><td class="px-4 py-3"><p class="text-sm font-bold text-foreground">{{ $course->name }}</p><p class="mt-0.5 text-[11px] leading-relaxed text-muted-foreground">{{ $course->description ?: 'No program description provided.' }}</p></td><td class="px-4 py-3"><div class="flex flex-wrap gap-2">@for ($year = 1; $year <= (int) $course->year_level; $year++)<span class="grid h-6 w-6 place-items-center rounded-full border border-primary/10 bg-primary-soft text-[10px] font-bold text-primary" title="Year {{ $year }}">{{ $year }}</span>@endfor</div></td><td class="px-4 py-3"><div class="flex flex-wrap gap-2">@if ($course->block !== null) @for ($block = 1; $block <= (int) $course->block; $block++)<span class="grid h-6 w-6 place-items-center rounded-full border border-primary/10 bg-primary-soft text-[10px] font-bold text-primary" title="Block {{ $block }}">{{ $block }}</span>@endfor @else <span class="text-xs text-muted-foreground">None</span> @endif</div></td></tr>@empty<tr><td colspan="3" class="px-4 py-6 text-center text-xs text-muted-foreground">No programs configured.</td></tr>@endforelse</tbody></table>@if ($department->designations->isNotEmpty())<p class="mt-3 text-[11px] leading-relaxed text-muted-foreground"><span class="font-semibold text-foreground">Designations:</span> {{ $department->designations->pluck('name')->join(', ') }}</p>@endif</div></li>
                        @empty
                            <li class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground lg:col-span-2">No colleges configured.</li>
                        @endforelse
                    </ul>
                </section>

                <section x-init="departmentPositions = departmentPositions.map(position => typeof position === 'string' ? { name: position, description: '' } : position); positionDescriptionDraft = ''; positionEditingPosition = null; positionEditingIndex = null; addPosition = () => { const name = positionDraft.trim(); if (name === '') { positionDraftError = 'Input designation name'; return; } const position = { name, description: (positionDescriptionDraft || '').trim() }; if (positionEditingPosition) { departmentPositions.splice(positionEditingIndex, 0, position); positionEditingPosition = null; positionEditingIndex = null; } else { departmentPositions.push(position); } positionDraft = ''; positionDescriptionDraft = ''; positionDraftError = ''; }; const originalOpenEditDepartment = openEditDepartment; openEditDepartment = (department) => { if (department.type === 'office') { department.positions = @js($units->where('type', 'office')->mapWithKeys(fn ($item) => [$item->id => $item->designations->map(fn ($position) => ['name' => $position->name, 'description' => $position->description])->values()])->all())[department.id] || []; positionDescriptionDraft = ''; positionEditingPosition = null; positionEditingIndex = null; } originalOpenEditDepartment(department); }" class="recipient-departments">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-start gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center text-primary"><x-icons.users class="h-7 w-7" /></span><div><h2 class="font-display text-lg font-bold text-foreground">Offices</h2><p class="text-xs text-muted-foreground">Configure the offices and service units that recipients belong to.</p></div></div>
                        <button type="button" @click="openDepartmentModal('office')" class="inline-flex h-8 items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-semibold text-primary-foreground hover:bg-primary/90"><x-icons.plus class="h-3.5 w-3.5" /> Add office</button>
                    </div>
                    <ul class="grid w-full gap-3 lg:grid-cols-2">
                        @forelse ($units->where('type', 'office') as $department)
                            <li x-data="{ expanded: false }" @click="if (! $event.target.closest('button, a, [data-unit-details]')) expanded = ! expanded" class="cursor-pointer rounded-lg border border-border bg-card p-4"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="text-base font-bold text-primary">{{ $department->name }}</p><p class="mt-0.5 text-xs text-muted-foreground">{{ $department->description ?: 'No description provided.' }}</p></div><div class="flex shrink-0 items-center gap-2"><button type="button" @click="openEditDepartment({ id: {{ $department->id }}, type: @js($department->type), name: @js($department->name), description: @js($department->description), courses: [], positions: @js($department->designations->pluck('name')->values()) })" class="grid h-8 w-8 place-items-center rounded-md bg-primary-soft text-primary transition-colors hover:bg-primary/15" aria-label="Edit department" title="Edit"><x-icons.pencil class="h-3.5 w-3.5" /></button><button type="button" @click="openDeleteDepartment({ id: {{ $department->id }}, name: @js($department->name) })" class="grid h-8 w-8 place-items-center rounded-md bg-primary-soft text-primary transition-colors hover:bg-primary/15" aria-label="Delete department" title="Delete"><x-icons.trash class="h-3.5 w-3.5" /></button><button type="button" @click="expanded = !expanded" class="grid h-8 w-8 place-items-center rounded-md bg-primary-soft text-primary transition-colors hover:bg-primary/15" aria-label="Toggle department details" title="Show details"><x-icons.arrow-down class="h-4 w-4 transition-transform" ::class="expanded ? '' : 'rotate-180'" /></button></div></div><div x-show="expanded" x-collapse data-unit-details class="mt-3 cursor-default overflow-hidden rounded-md border border-border"><table class="w-full text-left"><thead class="bg-primary-soft text-[10px] font-semibold uppercase tracking-wide text-muted-foreground"><tr><th class="px-4 py-2.5">Designations</th></tr></thead><tbody class="divide-y divide-border">@forelse ($department->designations as $position)<tr><td class="px-4 py-2 text-xs font-semibold text-muted-foreground"><span class="mr-2 text-primary">&bull;</span>{{ $position->name }}</td></tr>@empty<tr><td class="px-4 py-3 text-xs text-muted-foreground">No designations configured.</td></tr>@endforelse</tbody></table></div></li>
                        @empty
                            <li class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground lg:col-span-2">No offices configured.</li>
                        @endforelse
                    </ul>
                </section>
            </div>

            <script>
                document.addEventListener('click', (event) => {
                    const confirmButton = event.target.closest('[data-designation-confirm]');
                    if (!confirmButton) return;

                    requestAnimationFrame(() => {
                        const description = confirmButton.closest('form')?.querySelector('textarea[placeholder="Designation description"]');
                        if (description) description.value = '';
                    });
                });

                document.addEventListener('click', (event) => {
                    const editButton = event.target.closest('[data-designation-edit]');
                    if (!editButton) return;

                    const row = editButton.closest('[data-designation-row]');
                    const savedDescription = row?.querySelector('input[name*="[description]"]')?.value || '';
                    requestAnimationFrame(() => {
                        const description = document.querySelector('textarea[placeholder="Designation description"]');
                        if (description) {
                            description.value = savedDescription;
                            description.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    });
                });

                document.addEventListener('click', (event) => {
                    const cancelButton = event.target.closest('[data-designation-cancel]');
                    if (!cancelButton) return;

                    requestAnimationFrame(() => {
                        const description = document.querySelector('textarea[placeholder="Designation description"]');
                        if (description) description.value = '';
                    });
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key !== 'Enter' || !event.target.matches('input[placeholder="Designation name"]')) return;

                    requestAnimationFrame(() => {
                        const description = event.target.closest('form')?.querySelector('textarea[placeholder="Designation description"]');
                        if (description) description.value = '';
                    });
                });
            </script>

            <div x-show="deleteDepartmentOpen" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div @click.outside="closeDeleteDepartment()" class="w-full max-w-md rounded-2xl border border-border bg-white shadow-2xl" role="dialog" aria-modal="true" aria-label="Delete department">
                    <div class="flex items-start justify-between border-b border-border px-5 py-4">
                        <div><p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">Colleges and Offices</p><h2 class="mt-1 text-lg font-bold text-destructive">Delete College or Office</h2></div>
                        <button type="button" @click="closeDeleteDepartment()" class="grid h-8 w-8 place-items-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground" aria-label="Close delete confirmation"><x-icons.x class="h-4 w-4" /></button>
                    </div>
                    <p class="px-5 pt-4 text-sm leading-6 text-gray-700">Are you sure you want to delete <span class="font-semibold" x-text="deleteDepartmentName"></span>? Its configured programs and designations will also be removed.</p>
                    <form method="POST" x-bind:action="`/admin/units/${deleteDepartmentId}`" class="flex justify-end gap-2 p-5 pt-4">
                        @csrf
                        @method('DELETE')
                        <button type="button" @click="closeDeleteDepartment()" class="inline-flex h-8 items-center justify-center rounded-full border border-border bg-white px-4 text-[11px] font-semibold text-foreground hover:bg-muted">Cancel</button>
                        <button type="submit" class="inline-flex h-8 items-center justify-center rounded-full bg-destructive px-4 text-[11px] font-semibold text-white hover:bg-destructive/90">Delete</button>
                    </form>
                </div>
            </div>

            <div x-show="addDepartmentOpen" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div @click.outside="resetDepartmentForm()" class="max-h-[min(75vh,45rem)] w-full max-w-2xl overflow-y-auto rounded-2xl border border-border bg-white shadow-2xl" role="dialog" aria-modal="true" aria-label="Add college or office">
                    <div class="flex items-start justify-between border-b border-border px-5 py-4">
                        <div><p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">Colleges and Offices</p><h2 class="mt-1 text-lg font-bold text-foreground" x-text="departmentType === 'college' ? (departmentEditId ? 'Edit College' : 'Add College') : (departmentEditId ? 'Edit Office' : 'Add Office')"></h2></div>
                        <button type="button" @click="resetDepartmentForm()" class="grid h-8 w-8 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground" aria-label="Close add department form"><x-icons.x class="h-4 w-4" /></button>
                    </div>
                    <form x-bind:action="departmentEditId ? '{{ route('admin.units.update', ['unit' => '__DEPARTMENT__']) }}'.replace('__DEPARTMENT__', departmentEditId) : '{{ route('admin.units.store') }}'" method="POST" @submit="saveDepartment($event)" class="min-h-0 overflow-y-auto grid gap-4 p-5">
                        @csrf
                        <input type="hidden" name="_method" value="PUT" x-bind:disabled="!departmentEditId">
                        <input type="hidden" name="type" :value="departmentType">
                        <div>
                            <label for="settings-department-name" class="mb-1.5 block text-sm font-semibold text-foreground">Name</label>
                            <input id="settings-department-name" name="name" type="text" x-model="departmentName" @input="departmentNameError = ''" class="h-8 w-full rounded-md border border-input bg-white px-3 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring">
                            <p x-show="departmentNameError" x-text="departmentNameError" class="mt-1 text-xs text-destructive"></p>
                        </div>
                        <div>
                            <label for="settings-department-description" class="mb-1.5 block text-sm font-semibold text-foreground">Description</label>
                            <textarea id="settings-department-description" name="description" x-model="departmentDescription" rows="3" class="w-full rounded-md border border-input bg-white px-3 py-2 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring"></textarea>
                        </div>
                        <div x-show="departmentType === 'college'" class="rounded-lg border border-border">
                            <div class="border-b border-border bg-muted/50 px-3 py-2">
                                <p class="text-sm font-semibold text-foreground">Programs</p>
                                <p class="text-[11px] text-muted-foreground">Fill in a program, then choose Add program. A college needs at least one.</p>
                            </div>
                            <div class="grid gap-2 p-3">
                                <input x-ref="courseInput" type="text" x-model="courseDraft.course" placeholder="Program name" class="h-8 w-full rounded-md border border-input bg-white px-3 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring">
                                <textarea x-model="courseDraft.description" rows="2" placeholder="Program description" class="w-full rounded-md border border-input bg-white px-3 py-2 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring"></textarea>
                                <div class="flex flex-wrap items-end gap-2">
                                    <label class="grid w-28 gap-1 text-[11px] font-medium text-muted-foreground">Year levels
                                        <input type="number" min="1" max="6" step="1" x-model="courseDraft.year_level" placeholder="e.g. 4" class="h-8 w-full rounded-md border border-input bg-white px-2 text-sm text-foreground">
                                    </label>
                                    <label class="grid w-28 gap-1 text-[11px] font-medium text-muted-foreground">Blocks (optional)
                                        <input type="number" min="1" max="10" step="1" x-model="courseDraft.block" placeholder="e.g. 2" class="h-8 w-full rounded-md border border-input bg-white px-2 text-sm text-foreground">
                                    </label>
                                    <div class="ml-auto flex items-center gap-2">
                                        <button type="button" @click="courseDraft._editingCourse ? (departmentCourses.splice(courseDraft._editingIndex, 0, courseDraft._editingCourse), resetCourseDraft()) : resetCourseDraft()" class="inline-flex h-8 items-center justify-center rounded-md px-3 text-xs font-semibold text-muted-foreground transition-colors hover:bg-muted hover:text-foreground">Clear</button>
                                        <button type="button" @click="addCourse()" class="inline-flex h-8 items-center justify-center gap-1.5 rounded-md border border-primary/10 bg-primary-soft px-3 text-xs font-semibold text-primary transition-colors hover:bg-primary/15"><x-icons.plus class="h-3.5 w-3.5" /> Add program</button>
                                    </div>
                                </div>
                                <p x-show="courseDraftError" x-text="courseDraftError" class="text-xs text-destructive"></p>
                            </div>
                            <div class="grid gap-2 border-t border-border bg-muted/50 p-3">
                                <p x-show="departmentCourses.length === 0" class="py-1 text-center text-[11px] text-muted-foreground">No programs added yet.</p>
                                <template x-for="(course, index) in departmentCourses" :key="`${course.course}-${index}`">
                                    <div class="flex items-start justify-between gap-3 rounded-md border border-border bg-white px-3 py-2 text-sm">
                                        <div class="min-w-0">
                                            <input type="hidden" :name="`programs[${index}][name]`" :value="course.course">
                                            <input type="hidden" :name="`programs[${index}][year_level]`" :value="course.year_level">
                                            <input type="hidden" :name="`programs[${index}][block]`" :value="course.block">
                                            <input type="hidden" :name="`programs[${index}][description]`" :value="course.description">
                                            <div x-text="course.course" class="font-semibold"></div>
                                            <div x-show="course.description" x-text="course.description" class="text-[11px] leading-relaxed text-muted-foreground"></div>
                                            <div x-text="`${course.year_level} Year Levels${course.block ? ` · ${course.block} Blocks` : ''}`" class="text-[11px] text-muted-foreground"></div>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-1">
                                            <button type="button" @click="courseDraft = { ...course, _editingCourse: { ...course }, _editingIndex: index }; departmentCourses.splice(index, 1); $nextTick(() => $refs.courseInput?.focus())" class="grid h-7 w-7 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-primary" aria-label="Edit program" title="Edit program"><x-icons.pencil class="h-3.5 w-3.5" /></button>
                                            <button type="button" @click="removeCourse(index)" class="grid h-7 w-7 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-primary hover:text-destructive" aria-label="Remove program" title="Remove program"><x-icons.trash class="h-3.5 w-3.5" /></button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div class="rounded-lg border border-border">
                            <div class="border-b border-border bg-muted/50 px-3 py-2">
                                <p class="text-sm font-semibold text-foreground">Designations</p>
                                <p class="text-[11px] text-muted-foreground">The positions staff hold here, such as Dean or Program Chair. Fill one in, then choose Add designation.</p>
                            </div>
                            <div class="grid gap-2 p-3">
                                <input x-ref="positionInput" type="text" x-model="positionDraft" @input="positionDraftError = ''" @keydown.enter.prevent="addPosition()" placeholder="Designation name" class="h-8 w-full rounded-md border border-input bg-white px-3 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring">
                                <textarea x-model="positionDescriptionDraft" @input="positionDraftError = ''" rows="2" placeholder="Designation description" class="w-full rounded-md border border-input bg-white px-3 py-2 text-sm text-foreground outline-none focus:ring-1 focus:ring-ring"></textarea>
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" data-designation-cancel @click="positionEditingPosition ? (departmentPositions.splice(positionEditingIndex, 0, positionEditingPosition), positionEditingPosition = null, positionEditingIndex = null, positionDraft = '', positionDescriptionDraft = '', positionDraftError = '') : (positionDraft = '', positionDescriptionDraft = '', positionDraftError = '')" class="inline-flex h-8 items-center justify-center rounded-md px-3 text-xs font-semibold text-muted-foreground transition-colors hover:bg-muted hover:text-foreground">Clear</button>
                                    <button type="button" data-designation-confirm @click="addPosition()" class="inline-flex h-8 items-center justify-center gap-1.5 rounded-md border border-primary/10 bg-primary-soft px-3 text-xs font-semibold text-primary transition-colors hover:bg-primary/15"><x-icons.plus class="h-3.5 w-3.5" /> Add designation</button>
                                </div>
                                <p x-show="positionDraftError" x-text="positionDraftError" class="text-xs text-destructive"></p>
                            </div>
                            <div class="grid gap-2 border-t border-border bg-muted/50 p-3">
                                <p x-show="departmentPositions.length === 0" class="py-1 text-center text-[11px] text-muted-foreground">No designations added yet.</p>
                                <template x-for="(position, index) in departmentPositions" :key="`${position.name}-${index}`">
                                    <div data-designation-row class="flex items-start justify-between gap-3 rounded-md border border-border bg-white px-3 py-2 text-sm">
                                        <div class="min-w-0">
                                            <input type="hidden" :name="`designations[${index}][name]`" :value="position.name">
                                            <input type="hidden" :name="`designations[${index}][description]`" :value="position.description">
                                            <div x-text="position.name" class="font-semibold"></div>
                                            <div x-show="position.description" x-text="position.description" class="text-[11px] leading-relaxed text-muted-foreground"></div>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-1">
                                            <button type="button" data-designation-edit @click="positionDraft = position.name; positionDescriptionDraft = position.description || ''; positionEditingPosition = { ...position }; positionEditingIndex = index; departmentPositions.splice(index, 1); $nextTick(() => $refs.positionInput?.focus())" class="grid h-7 w-7 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-primary" aria-label="Edit designation" title="Edit designation"><x-icons.pencil class="h-3.5 w-3.5" /></button>
                                            <button type="button" @click="removePosition(index)" class="grid h-7 w-7 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-primary hover:text-destructive" aria-label="Remove designation" title="Remove designation"><x-icons.trash class="h-3.5 w-3.5" /></button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 border-t border-border pt-4"><button type="button" @click="resetDepartmentForm()" class="inline-flex h-8 items-center justify-center rounded-full border border-border bg-white px-4 text-[11px] font-semibold text-foreground hover:bg-muted">Cancel</button><button type="submit" class="inline-flex h-8 items-center justify-center rounded-full bg-primary px-5 text-[11px] font-semibold text-primary-foreground hover:bg-primary/90">Save</button></div>
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
                    <div class="grid gap-3 rounded-lg border border-border p-3">
                        <label class="flex cursor-pointer items-start gap-2.5 text-sm">
                            <input type="hidden" name="allows_hidden_identity" value="0">
                            <input type="checkbox" name="allows_hidden_identity" value="1" checked class="mt-0.5 h-4 w-4 shrink-0" style="accent-color: #7a1d2a">
                            <span><span class="font-semibold text-foreground">Allow hidden identity</span><span class="block text-[11px] leading-relaxed text-muted-foreground">Students may submit under this category without showing their name and student ID.</span></span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-2.5 text-sm">
                            <input type="hidden" name="is_sensitive" value="0">
                            <input type="checkbox" name="is_sensitive" value="1"  class="mt-0.5 h-4 w-4 shrink-0" style="accent-color: #7a1d2a">
                            <span><span class="font-semibold text-foreground">Sensitive category</span><span class="block text-[11px] leading-relaxed text-muted-foreground">For harassment and similar concerns. Tickets are handled confidentially and always accept hidden identity.</span></span>
                        </label>
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
                    <div class="grid gap-3 rounded-lg border border-border p-3">
                        <label class="flex cursor-pointer items-start gap-2.5 text-sm">
                            <input type="hidden" name="allows_hidden_identity" value="0">
                            <input type="checkbox" name="allows_hidden_identity" value="1" x-model="editCategory.allowsHiddenIdentity" x-bind:disabled="editCategory.isSensitive" class="mt-0.5 h-4 w-4 shrink-0" style="accent-color: #7a1d2a">
                            <span><span class="font-semibold text-foreground">Allow hidden identity</span><span class="block text-[11px] leading-relaxed text-muted-foreground">Students may submit under this category without showing their name and student ID.</span></span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-2.5 text-sm">
                            <input type="hidden" name="is_sensitive" value="0">
                            <input type="checkbox" name="is_sensitive" value="1" x-model="editCategory.isSensitive" class="mt-0.5 h-4 w-4 shrink-0" style="accent-color: #7a1d2a">
                            <span><span class="font-semibold text-foreground">Sensitive category</span><span class="block text-[11px] leading-relaxed text-muted-foreground">For harassment and similar concerns. Tickets are handled confidentially and always accept hidden identity.</span></span>
                        </label>
                    </div>
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

        <div x-show="suggestedRecipientsOpen" x-init="recipientOptions = @js($recipients->map(fn ($recipient) => ['id' => $recipient->id, 'name' => trim(implode(' ', array_filter([$recipient->user?->first_name ?: $recipient->user?->name ?: $recipient->user?->username, $recipient->user?->middle_name ? strtoupper(substr(trim($recipient->user->middle_name), 0, 1)) . '.' : null, $recipient->user?->last_name]))) ?: 'Recipient', 'department' => trim((string) $recipient->unit) !== '' ? $recipient->unit : 'College or office not specified', 'position' => trim((string) $recipient->designation) !== '' ? $recipient->designation : 'Designation not specified', 'designation' => trim((string) $recipient->designation) !== '' ? $recipient->designation : 'Designation not specified', 'avatar' => $recipient->user?->avatar_path ? asset('storage/' . $recipient->user->avatar_path) : null])->values())" x-cloak x-transition.opacity @click.stop class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4">
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
                    <div x-show="recipientDraftIds.length > 0" class="mt-4 flex flex-wrap gap-3 rounded-lg border border-border bg-muted/50 p-3">
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
                                    <span class="block truncate text-[10px] text-muted-foreground" x-text="`${recipient.department || 'College or office not specified'} · ${recipient.designation || recipient.position || 'Designation not specified'}`"></span>
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
            const recipientPositionData = @json($units->where('type', 'office')->mapWithKeys(fn ($department) => [$department->id => $department->designations->map(fn ($position) => ['name' => $position->name, 'description' => $position->description])->values()])->all());
            state.openEditDepartment = function (department) {
                this.departmentEditId = department.id;
                this.departmentType = department.type;
                this.departmentName = department.name;
                this.departmentDescription = department.description || '';
                this.departmentCourses = (department.courses || []).map(course => ({ course: course.course, year_level: String(course.year_level), block: course.block === null ? '' : String(course.block), description: course.description || '' }));
                this.departmentPositions = department.type === 'office' ? (recipientPositionData[department.id] || []) : (department.positions || []);
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
                    this.departmentNameError = 'Input a name';
                    return;
                }

                if (this.departmentType === 'office' && this.departmentPositions.filter(position => (typeof position === 'string' ? position : position.name || '').trim() !== '').length === 0) {
                    event.preventDefault();
                    this.positionDraftError = 'Add at least one designation';
                    return;
                }

                return originalSaveDepartment.call(this, event);
            };
        });

        // Scoped in a block: this script runs again on every in-app visit to the page.
        {
        document.querySelectorAll('.recipient-departments table').forEach((table) => {
            const header = table.querySelector('thead tr');
            if (header) header.innerHTML = '<th class="px-4 py-2.5">Designation</th><th class="px-4 py-2.5">Description</th>';
            table.querySelectorAll('tbody tr').forEach((row) => {
                const cell = row.querySelector('td');
                if (!cell || row.querySelectorAll('td').length > 1) return;
                const position = cell.textContent.trim().replace(/^•\s*/, '');
                cell.outerHTML = `<td class="px-4 py-2 text-xs font-bold text-foreground">${position}</td><td class="px-4 py-2 text-xs text-muted-foreground">No designation description provided.</td>`;
            });
        });

        const recipientPositionData = @json($units->where('type', 'office')->values()->map(fn ($department) => $department->designations->map(fn ($position) => ['name' => $position->name, 'description' => $position->description])->values())->values());
        document.querySelectorAll('.recipient-departments > ul > li').forEach((card, departmentIndex) => {
            const positions = recipientPositionData[departmentIndex] || [];
            card.querySelectorAll('tbody tr').forEach((row, positionIndex) => {
                const descriptionCell = row.querySelectorAll('td')[1];
                if (descriptionCell && positions[positionIndex]) {
                    descriptionCell.textContent = positions[positionIndex].description || 'No designation description provided.';
                }
            });
        });

        }
    </script>
</div>
</x-app-layout>
