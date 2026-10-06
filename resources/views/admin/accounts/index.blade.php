<x-app-layout :role="'admin'" title="Manage Accounts">
    @php
        $selectedAccountCategory = request('category_filter', 'students');
    @endphp
    <div class="-mt-1 sm:-mt-2" x-data="{
            tab: '{{ $selectedAccountCategory }}',
            accountModalOpen: false,
            recipientModalOpen: false,
            editStudentModalOpen: false,
            editRecipientModalOpen: false,
            department: '',
            courseOptions: @js($studentDepartments->mapWithKeys(fn ($department) => [$department->name => $department->courses->pluck('course')->unique()->values()])->all()),
            studentCourseDetails: @js($studentDepartments->mapWithKeys(fn ($department) => [
                $department->name => $department->courses->groupBy('course')->mapWithKeys(fn ($courses, $course) => [$course => [
                    'years' => collect(($maxYear = (int) $courses->max('year_level')) > 0 ? range(1, $maxYear) : [])->map(fn ($year) => (string) $year)->values(),
                    'blocks' => collect(($maxBlock = (int) $courses->max('block')) > 0 ? range(1, $maxBlock) : [])->map(fn ($block) => (string) $block)->values(),
                ]])->all(),
            ])->all()),
            recipientDepartmentOptions: @js($recipientDepartments->mapWithKeys(fn ($department) => [$department->name => $department->positions->pluck('name')->values()])->all()),
            selectedCourse: '',
            yearValue: '',
            blockValue: '',
            first_name: '',
            middle_name: '',
            last_name: '',
            recipientFirstName: '',
            recipientMiddleName: '',
            recipientLastName: '',
            recipientStaffId: '',
            recipientEmail: '',
            recipientDepartment: '',
            recipientDesignation: '',
            editStudentUserId: null,
            editStudentFirstName: '',
            editStudentMiddleName: '',
            editStudentLastName: '',
            editStudentEmail: '',
            editStudentId: '',
            editStudentDepartment: '',
            editStudentCourse: '',
            editStudentYearLevel: '',
            editStudentBlock: '',
            editRecipientUserId: null,
            editRecipientFirstName: '',
            editRecipientMiddleName: '',
            editRecipientLastName: '',
            editRecipientEmail: '',
            editRecipientStaffId: '',
            editRecipientDepartment: '',
            editRecipientDesignation: '',
            splitFullName(name, user = null) {
                if (user && [user.first_name, user.middle_name, user.last_name].some((part) => (part ?? '').trim() !== '')) {
                    return {
                        first: user.first_name ?? '',
                        middle: user.middle_name ?? '',
                        last: user.last_name ?? '',
                    };
                }
                const parts = (name || '').trim().split(/\s+/).filter(Boolean);
                if (!parts.length) {
                    return { first: '', middle: '', last: '' };
                }
                if (parts.length === 1) {
                    return { first: parts[0], middle: '', last: '' };
                }
                if (parts.length === 2) {
                    return { first: parts[0], middle: '', last: parts[1] };
                }
                if (parts.length === 3) {
                    return { first: parts[0], middle: parts[1], last: parts[2] };
                }
                return {
                    first: `${parts[0]} ${parts[1]}`,
                    middle: parts.slice(2, -1).join(' '),
                    last: parts[parts.length - 1],
                };
            },
            clearAccountValidationErrors() {
                const errorSelectors = [
                    '#new-student-first-name-empty-error', '#new-student-last-name-empty-error', '#new-student-id-empty-error', '#new-student-email-empty-error',
                    '#new-student-department-empty-error', '#new-student-course-empty-error', '#new-student-year-empty-error',
                    '#new-recipient-first-name-empty-error', '#new-recipient-last-name-empty-error', '#new-recipient-staff-id-empty-error', '#new-recipient-email-empty-error',
                    '#new-recipient-department-empty-error', '#new-recipient-designation-empty-error',
                    '#edit-student-first-name-empty-error', '#edit-student-last-name-empty-error', '#edit-student-id-empty-error', '#edit-student-email-empty-error',
                    '#edit-student-department-empty-error', '#edit-student-course-empty-error', '#edit-student-year-empty-error',
                    '#edit-recipient-first-name-empty-error', '#edit-recipient-last-name-empty-error', '#edit-recipient-staff-id-empty-error', '#edit-recipient-email-empty-error',
                    '#edit-recipient-department-empty-error', '#edit-recipient-designation-empty-error'
                ];

                errorSelectors.forEach((selector) => {
                    const error = document.querySelector(selector);
                    if (error) {
                        error.classList.add('invisible', 'opacity-0');
                        error.classList.remove('opacity-100');
                    }
                });

                document.querySelectorAll('input, summary').forEach((element) => {
                    element.classList.remove('border-destructive', '!border-destructive', 'password-error-border');
                });
            },
            resetStudentForm() {
                this.department = '';
                this.selectedCourse = '';
                this.yearValue = '';
                this.blockValue = '';
                this.first_name = '';
                this.middle_name = '';
                this.last_name = '';
                this.accountModalOpen = false;
                this.clearAccountValidationErrors();
            },
            resetRecipientForm() {
                this.recipientFirstName = '';
                this.recipientMiddleName = '';
                this.recipientLastName = '';
                this.recipientStaffId = '';
                this.recipientEmail = '';
                this.recipientDepartment = '';
                this.recipientDesignation = '';
                this.recipientModalOpen = false;
                document.getElementById('new-recipient-department-field')?.removeAttribute('open');
                document.getElementById('new-recipient-designation-field')?.removeAttribute('open');
                const newPositionInput = document.getElementById('new-recipient-designation');
                const newPositionLabel = document.getElementById('new-recipient-designation-field')?.querySelector('[data-position-label]');
                if (newPositionInput) newPositionInput.value = '';
                if (newPositionLabel) newPositionLabel.textContent = 'Select position';
                document.getElementById('admin-recipient-account-form')?.reset();
                this.clearAccountValidationErrors();
            },
            openRecipientForm() {
                this.resetRecipientForm();
                this.recipientModalOpen = true;
            },
            closeEditStudentForm() {
                this.editStudentModalOpen = false;
                this.editStudentUserId = null;
                this.editStudentFirstName = '';
                this.editStudentMiddleName = '';
                this.editStudentLastName = '';
                this.editStudentEmail = '';
                this.editStudentId = '';
                this.editStudentDepartment = '';
                this.editStudentCourse = '';
                this.editStudentYearLevel = '';
                this.editStudentBlock = '';
                this.clearAccountValidationErrors();
            },
            closeEditRecipientForm() {
                this.editRecipientModalOpen = false;
                this.editRecipientUserId = null;
                this.editRecipientFirstName = '';
                this.editRecipientMiddleName = '';
                this.editRecipientLastName = '';
                this.editRecipientEmail = '';
                this.editRecipientStaffId = '';
                this.editRecipientDepartment = '';
                this.editRecipientDesignation = '';
                document.getElementById('edit-recipient-department-field')?.removeAttribute('open');
                document.getElementById('edit-recipient-designation-field')?.removeAttribute('open');
                this.clearAccountValidationErrors();
            },
            openEditStudent(user) {
                const studentProfile = user.student ?? {};
                const nameParts = this.splitFullName(user.name ?? '', user);
                this.editStudentUserId = user.id;
                this.editStudentFirstName = nameParts.first;
                this.editStudentMiddleName = nameParts.middle;
                this.editStudentLastName = nameParts.last;
                this.editStudentEmail = user.email ?? '';
                this.editStudentId = user.student_id ?? studentProfile.student_id ?? '';
                this.editStudentDepartment = user.department ?? studentProfile.department ?? '';
                this.editStudentCourse = user.course ?? studentProfile.course ?? '';
                this.editStudentYearLevel = user.year_level ?? studentProfile.year_level ?? '';
                this.editStudentBlock = user.block ?? studentProfile.block ?? '';
                this.editStudentModalOpen = true;
            },
            openEditRecipient(user) {
                const recipientProfile = user.recipient ?? {};
                const nameParts = this.splitFullName(user.name ?? '', user);
                this.editRecipientUserId = user.id;
                this.editRecipientFirstName = nameParts.first;
                this.editRecipientMiddleName = nameParts.middle;
                this.editRecipientLastName = nameParts.last;
                this.editRecipientEmail = user.email ?? '';
                this.editRecipientStaffId = user.staff_id ?? recipientProfile.staff_id ?? '';
                this.editRecipientDepartment = user.department ?? recipientProfile.department ?? '';
                this.editRecipientDesignation = user.designation ?? recipientProfile.designation ?? '';
                this.editRecipientModalOpen = true;
            }
        }"
         x-init="if (!courseOptions[department]) { department = ''; selectedCourse = ''; }">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="inline-flex items-center gap-6 border-b border-border">
                <button type="button"
                    data-account-tab="students"
                    @click="tab = 'students'; document.getElementById('account-search').value = ''"
                    :aria-selected="tab === 'students'"
                    class="inline-flex items-center border-b-2 px-1 pb-2 text-sm transition-colors"
                    :class="tab === 'students' ? 'border-[#7a1d2a] font-semibold text-[#7a1d2a]' : 'border-transparent font-medium text-muted-foreground hover:text-foreground'">
                    Students
                </button>

                <button type="button"
                    data-account-tab="recipients"
                    @click="tab = 'recipients'; document.getElementById('account-search').value = ''"
                    :aria-selected="tab === 'recipients'"
                    class="inline-flex items-center border-b-2 px-1 pb-2 text-sm transition-colors"
                    :class="tab === 'recipients' ? 'border-[#7a1d2a] font-semibold text-[#7a1d2a]' : 'border-transparent font-medium text-muted-foreground hover:text-foreground'">
                    Recipients
                </button>
            </div>

            <div class="flex flex-wrap gap-2">
                <form id="bulk-upload-form" action="{{ route('admin.accounts.upload.store') }}" method="POST" enctype="multipart/form-data" class="hidden">
                    @csrf
                    <input id="bulk-upload-type" type="hidden" name="account_type" value="" />
                    <input id="bulk-upload-input" type="file" name="file" accept=".csv,.xlsx,.xls" />
                </form>

                <button type="button" id="bulk-upload-trigger" class="inline-flex h-9 items-center gap-1.5 rounded-md border border-border bg-background px-3 text-sm font-semibold transition-colors hover:bg-muted">
                    <x-icons.upload class="h-4 w-4" /> Bulk Upload
                </button>
                <button type="button" @click="tab === 'students' ? accountModalOpen = true : openRecipientForm()" class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90">
                    <x-icons.plus class="h-4 w-4" /> Add Account
                </button>
            </div>
        </div>

        @php
            $accountCategories = ['students' => 'Students', 'recipients' => 'Recipients'];
            $statusOptions = ['' => 'All Status', 'active' => 'Active', 'inactive' => 'Inactive'];
            $sortOptions = ['asc' => 'Ascending', 'desc' => 'Descending'];
            $selectedCategory = request('category_filter', 'students');
            $configuredDepartmentOptions = ($selectedCategory === 'recipients' ? $recipientDepartments : $studentDepartments)->pluck('name')->mapWithKeys(fn ($name) => [$name => $name])->all();
            $departmentOptions = ['' => 'All Department'] + $configuredDepartmentOptions;
            $selectedCourse = request('course_filter', '');
            $selectedStatus = request('status_filter', '');
            $selectedSort = request('sort_id', 'asc');
            $courseDepartmentMap = $studentDepartments
                ->flatMap(fn ($department) => $department->courses
                    ->pluck('course')
                    ->unique()
                    ->mapWithKeys(fn ($course) => [$course => $department->name]))
                ->all();
            $selectedDepartment = request('department_filter', '') ?: ($courseDepartmentMap[$selectedCourse] ?? '');
            $selectedStudentDepartment = $studentDepartments->firstWhere('name', $selectedDepartment);
            $selectedStudentCourses = $selectedStudentDepartment?->courses ?? $studentDepartments->flatMap(fn ($department) => $department->courses);
            $selectedCourseRows = $selectedStudentCourses->when($selectedCourse !== '', fn ($courses) => $courses->where('course', $selectedCourse));
            $configuredYears = collect(($maxConfiguredYear = (int) $selectedCourseRows->max('year_level')) > 0 ? range(1, $maxConfiguredYear) : [])->map(fn ($year) => (string) $year)->values();
            $configuredBlocks = collect(($maxConfiguredBlock = (int) $selectedCourseRows->max('block')) > 0 ? range(1, $maxConfiguredBlock) : [])->map(fn ($block) => (string) $block)->values();
            $yearOptions = ['' => 'All Year'] + $configuredYears->mapWithKeys(fn ($year) => [$year => $year])->all();
            $blockOptions = ['' => 'All Block'] + $configuredBlocks->mapWithKeys(fn ($block) => [$block => $block])->all();
            $configuredCourses = $selectedDepartment !== ''
                ? ($studentDepartments->firstWhere('name', $selectedDepartment)?->courses->pluck('course')->unique()->values() ?? collect())
                : $studentDepartments->flatMap(fn ($department) => $department->courses->pluck('course'))->unique()->sort()->values();
            $courseOptions = ['' => 'All Course'] + $configuredCourses->mapWithKeys(fn ($course) => [$course => $course])->all();
            $selectedYear = request('year_filter', '');
            $selectedBlock = request('block_filter', '');
            $splitAccountName = function ($name, $user = null): array {
                return [
                    'last' => trim((string) ($user?->last_name ?? '')),
                    'first' => trim((string) ($user?->first_name ?? '')),
                    'middle' => trim((string) ($user?->middle_name ?? '')),
                ];
            };
        @endphp

        <form method="GET" action="{{ route('admin.accounts.index') }}" class="relative z-20 mb-4 w-full" data-account-filter-form>
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <div class="w-full sm:w-auto sm:min-w-[280px] sm:flex-1">
                    <div class="relative min-w-0">
                        <x-icons.search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <label for="account-search" class="sr-only">Search accounts</label>
                        <input id="account-search" name="search" value="{{ request('search') }}" autocomplete="off" placeholder="Search id, name or email" class="h-9 w-full rounded-md border border-input bg-transparent pl-9 pr-3 text-sm shadow-sm outline-none placeholder:text-xs placeholder:text-muted-foreground focus:ring-1 focus:ring-ring" />
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-2 sm:flex sm:flex-wrap sm:items-center">
                <div class="flex w-full min-w-0 items-center gap-2 sm:w-auto">
                    <span class="hidden whitespace-nowrap text-xs font-semibold text-foreground sm:inline">Sort by ID</span>
                    <details x-data="{}" class="group relative w-full min-w-0 sm:w-[160px] sm:shrink-0" x-on:click.outside="$el.removeAttribute('open')">
                        <summary class="flex h-9 w-full cursor-pointer list-none items-center justify-between whitespace-nowrap rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden">
                            <span class="truncate"><span class="sm:hidden">ID · </span>{{ $sortOptions[$selectedSort] ?? 'Ascending' }}</span>
                            <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                        </summary>
                        <div class="absolute top-full left-0 z-50 mt-1 w-full min-w-32 rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                            @foreach ($sortOptions as $value => $label)
                                <a href="{{ route('admin.accounts.index', array_filter(['search' => request('search'), 'sort_id' => $value, 'department_filter' => request('department_filter'), 'course_filter' => request('course_filter'), 'year_filter' => request('year_filter'), 'block_filter' => request('block_filter'), 'status_filter' => request('status_filter'), 'category_filter' => $selectedCategory])) }}" class="relative flex w-full items-center rounded-sm py-1.5 pl-2 pr-8 text-xs {{ $selectedSort === $value ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">
                                    @if ($selectedSort === $value)
                                        <x-icons.check class="absolute right-2 h-4 w-4" />
                                    @endif
                                    {{ $label }}
                                </a>
                            @endforeach
                        </div>
                    </details>
                </div>

                <details x-data="{}" class="group relative w-full min-w-0 {{ $selectedCategory === 'recipients' ? 'sm:w-[240px]' : 'sm:w-[150px]' }} sm:shrink-0" x-on:click.outside="$el.removeAttribute('open')">
                    <summary class="flex h-9 w-full cursor-pointer list-none items-center justify-between whitespace-nowrap rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden">
                        <span class="truncate">{{ $departmentOptions[$selectedDepartment] ?? 'All Department' }}</span>
                        <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                    </summary>
                    <div class="absolute top-full left-0 z-50 mt-1 w-full min-w-44 rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                        @foreach ($departmentOptions as $value => $label)
                            <a href="{{ route('admin.accounts.index', array_filter(['search' => request('search'), 'sort_id' => request('sort_id'), 'department_filter' => $value, 'course_filter' => '', 'year_filter' => request('year_filter'), 'block_filter' => request('block_filter'), 'status_filter' => request('status_filter'), 'category_filter' => $selectedCategory])) }}" class="relative flex w-full items-center rounded-sm py-1.5 pl-2 pr-8 text-xs {{ $selectedDepartment === $value ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">
                                @if ($selectedDepartment === $value)
                                    <x-icons.check class="absolute right-2 h-4 w-4" />
                                @endif
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </details>

                <details x-data="{}" x-show="tab === 'students'" x-cloak class="group relative w-full min-w-0 sm:w-[120px] sm:shrink-0" x-on:click.outside="$el.removeAttribute('open')">
                    <summary class="flex h-9 w-full cursor-pointer list-none items-center justify-between whitespace-nowrap rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden">
                        <span class="truncate">{{ $courseOptions[$selectedCourse] ?? 'All Course' }}</span>
                        <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                    </summary>
                    <div class="absolute top-full left-0 z-50 mt-1 w-full min-w-28 rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                        @foreach ($courseOptions as $value => $label)
                            <a href="{{ route('admin.accounts.index', array_filter(['search' => request('search'), 'sort_id' => request('sort_id'), 'department_filter' => $courseDepartmentMap[$value] ?? request('department_filter'), 'course_filter' => $value, 'year_filter' => request('year_filter'), 'block_filter' => request('block_filter'), 'status_filter' => request('status_filter'), 'category_filter' => $selectedCategory])) }}" class="relative flex w-full items-center rounded-sm py-1.5 pl-2 pr-8 text-xs {{ $selectedCourse === $value ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">
                                @if ($selectedCourse === $value)
                                    <x-icons.check class="absolute right-2 h-4 w-4" />
                                @endif
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </details>

                <details x-data="{}" x-show="tab === 'students'" x-cloak class="group relative w-full min-w-0 sm:w-[120px] sm:shrink-0" x-on:click.outside="$el.removeAttribute('open')">
                    <summary class="flex h-9 w-full cursor-pointer list-none items-center justify-between whitespace-nowrap rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden">
                        <span class="truncate">{{ $yearOptions[$selectedYear] ?? 'All Year' }}</span>
                        <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                    </summary>
                    <div class="absolute top-full left-0 z-50 mt-1 w-full min-w-24 rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                        @foreach ($yearOptions as $value => $label)
                            <a href="{{ route('admin.accounts.index', array_filter(['search' => request('search'), 'sort_id' => request('sort_id'), 'department_filter' => request('department_filter'), 'course_filter' => request('course_filter'), 'year_filter' => $value, 'block_filter' => request('block_filter'), 'status_filter' => request('status_filter'), 'category_filter' => $selectedCategory])) }}" class="relative flex w-full items-center rounded-sm py-1.5 pl-2 pr-8 text-xs {{ $selectedYear === $value ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">
                                @if ($selectedYear === $value)
                                    <x-icons.check class="absolute right-2 h-4 w-4" />
                                @endif
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </details>

                <details x-data="{}" x-show="tab === 'students'" x-cloak class="group relative w-full min-w-0 sm:w-[120px] sm:shrink-0" x-on:click.outside="$el.removeAttribute('open')">
                    <summary class="flex h-9 w-full cursor-pointer list-none items-center justify-between whitespace-nowrap rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden">
                        <span class="truncate">{{ $blockOptions[$selectedBlock] ?? 'All Block' }}</span>
                        <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                    </summary>
                    <div class="absolute top-full left-0 z-50 mt-1 w-full min-w-24 rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                        @foreach ($blockOptions as $value => $label)
                            <a href="{{ route('admin.accounts.index', array_filter(['search' => request('search'), 'sort_id' => request('sort_id'), 'department_filter' => request('department_filter'), 'course_filter' => request('course_filter'), 'year_filter' => request('year_filter'), 'block_filter' => $value, 'status_filter' => request('status_filter'), 'category_filter' => $selectedCategory])) }}" class="relative flex w-full items-center rounded-sm py-1.5 pl-2 pr-8 text-xs {{ $selectedBlock === $value ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">
                                @if ($selectedBlock === $value)
                                    <x-icons.check class="absolute right-2 h-4 w-4" />
                                @endif
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </details>

                <details x-data="{}" class="group relative w-full min-w-0 sm:w-[120px] sm:shrink-0" x-on:click.outside="$el.removeAttribute('open')">
                    <summary class="flex h-9 w-full cursor-pointer list-none items-center justify-between whitespace-nowrap rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted [&::-webkit-details-marker]:hidden">
                        <span class="truncate">{{ $statusOptions[$selectedStatus] ?? 'All Status' }}</span>
                        <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                    </summary>
                    <div class="absolute top-full right-0 z-50 mt-1 w-full min-w-28 rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                        @foreach ($statusOptions as $value => $label)
                            <a href="{{ route('admin.accounts.index', array_filter(['search' => request('search'), 'sort_id' => request('sort_id'), 'department_filter' => request('department_filter'), 'course_filter' => request('course_filter'), 'year_filter' => request('year_filter'), 'block_filter' => request('block_filter'), 'status_filter' => $value, 'category_filter' => $selectedCategory])) }}" class="relative flex w-full items-center rounded-sm py-1.5 pl-2 pr-8 text-xs {{ $selectedStatus === $value ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground' }}">
                                @if ($selectedStatus === $value)
                                    <x-icons.check class="absolute right-2 h-4 w-4" />
                                @endif
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </details>
            </div>
            </div>
        </form>

        <div data-account-table="students" x-show="tab === 'students'" x-cloak class="mx-auto mt-4 w-full overflow-x-auto rounded-lg border">
            <table class="w-full min-w-max whitespace-nowrap text-[13px]">
                <thead class="border-b bg-primary text-white">
                    <tr class="text-left">
                        <th class="whitespace-nowrap rounded-tl-lg py-2 pl-4 pr-2 text-[13px] font-semibold">Student ID</th>
                        <th class="px-2 py-2 text-[13px] font-semibold">Last Name</th>
                        <th class="px-2 py-2 text-[13px] font-semibold">First Name</th>
                        <th class="px-2 py-2 text-[13px] font-semibold">Middle Name</th>
                        <th class="px-2 py-2 text-[13px] font-semibold">Email</th>
                        <th class="px-2 py-2 text-[13px] font-semibold">Department</th>
                        <th class="px-2 py-2 text-[13px] font-semibold">Course</th>
                        <th class="px-2 py-2 text-[13px] font-semibold">Year</th>
                        <th class="px-2 py-2 text-[13px] font-semibold">Block</th>
                        <th class="px-2 py-2 text-[13px] font-semibold">Status</th>
                        <th class="w-24 min-w-24 whitespace-nowrap rounded-tr-lg py-2 pl-2 pr-4 text-left text-[13px] font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($studentUsers as $user)
                        @php($accountName = $splitAccountName($user->name, $user))
                        <tr class="transition-colors hover:bg-primary-soft">
                            <td class="whitespace-nowrap py-1.5 pl-4 pr-2 font-mono text-[12px] font-semibold text-primary">{{ $user->student?->student_id ?? '—' }}</td>
                            <td class="px-2 py-1.5 font-normal">{{ $accountName['last'] ?: '—' }}</td>
                            <td class="px-2 py-1.5 font-normal">{{ $accountName['first'] ?: '—' }}</td>
                            <td class="px-2 py-1.5">{{ $accountName['middle'] ?: '—' }}</td>
                            <td class="px-2 py-1.5 text-[13px] text-foreground">{{ $user->email ?? '—' }}</td>
                            <td class="px-2 py-1.5 text-[13px] text-foreground">{{ $user->student?->department ?? '—' }}</td>
                            <td class="px-2 py-1.5 text-[13px] text-foreground">{{ $user->student?->course ?? '—' }}</td>
                            <td class="px-2 py-1.5 text-[13px] text-foreground">{{ $user->student?->year_level ?? '—' }}</td>
                            <td class="px-2 py-1.5 text-[13px] text-foreground">{{ $user->student?->block ?? '—' }}</td>
                            <td class="px-2 py-1.5">
                                @if ($user->is_active)
                                    <span class="text-[12px] font-semibold text-green-600">Active</span>
                                @else
                                    <span class="text-[12px] font-semibold text-red-600">Inactive</span>
                                @endif
                            </td>
                            <td class="w-24 min-w-24 whitespace-nowrap py-1.5 pl-2 pr-4 text-left">
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="openEditStudent({ id: {{ $user->id }}, name: @js($user->name), first_name: @js($user->first_name), middle_name: @js($user->middle_name), last_name: @js($user->last_name), email: @js($user->email), student_id: @js($user->student?->student_id ?? ''), department: @js($user->student?->department ?? ''), course: @js($user->student?->course ?? ''), year_level: @js($user->student?->year_level ?? ''), block: @js($user->student?->block ?? ''), student: { student_id: @js($user->student?->student_id ?? ''), department: @js($user->student?->department ?? ''), course: @js($user->student?->course ?? ''), year_level: @js($user->student?->year_level ?? ''), block: @js($user->student?->block ?? '') } })" class="inline-flex h-6 items-center justify-center rounded-md bg-[#7a1d2a] px-2 text-[11px] font-semibold text-white transition-colors hover:bg-[#651923]" aria-label="Edit account">
                                        Edit
                                    </button>
                                    @include('admin.accounts.partials.status-action', ['user' => $user])
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="py-10 text-center text-muted-foreground">No student accounts found.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if ($studentUsers->hasPages())
                <nav class="mt-5 flex justify-end" aria-label="Student accounts pagination">
                    <div class="flex items-center gap-1 rounded-md border border-border bg-card p-1 shadow-sm">
                        @if ($studentUsers->onFirstPage())
                            <span class="inline-flex h-8 min-w-8 items-center justify-center rounded px-2 text-xs text-muted-foreground/50" aria-disabled="true">Previous</span>
                        @else
                            <a href="{{ $studentUsers->previousPageUrl() }}" class="inline-flex h-8 min-w-8 items-center justify-center rounded px-2 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground">Previous</a>
                        @endif

                        @foreach ($studentUsers->getUrlRange(1, $studentUsers->lastPage()) as $page => $url)
                            @if ($page === $studentUsers->currentPage())
                                <span class="inline-flex h-8 min-w-8 items-center justify-center rounded bg-primary px-2 text-xs font-semibold text-primary-foreground" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="inline-flex h-8 min-w-8 items-center justify-center rounded border border-transparent px-2 text-xs text-muted-foreground transition-colors hover:border-border hover:bg-muted hover:text-foreground">{{ $page }}</a>
                            @endif
                        @endforeach

                        @if ($studentUsers->hasMorePages())
                            <a href="{{ $studentUsers->nextPageUrl() }}" class="inline-flex h-8 min-w-8 items-center justify-center rounded px-2 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground">Next</a>
                        @else
                            <span class="inline-flex h-8 min-w-8 items-center justify-center rounded px-2 text-xs text-muted-foreground/50" aria-disabled="true">Next</span>
                        @endif
                    </div>
                </nav>
            @endif
        </div>

        <div x-show="accountModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div @click.outside="accountModalOpen = false" class="w-full max-w-2xl rounded-2xl border border-border bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-border px-5 py-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">Students</p>
                        <h2 class="mt-1 text-lg font-bold text-foreground">Add Student Account</h2>
                    </div>
                    <button type="button" @click="resetStudentForm()" class="grid h-8 w-8 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground" aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="h-4 w-4"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>

                <form id="admin-student-account-form" action="{{ route('admin.accounts.store') }}" method="POST" novalidate class="space-y-3 p-5">
                    @csrf
                    <input type="hidden" name="role" value="student">
                    <input type="hidden" name="name" x-bind:value="`${first_name} ${middle_name ? middle_name + ' ' : ''}${last_name}`.trim()">

                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="grid min-h-[74px] gap-0.5">
                            <label for="new-student-first-name" class="text-[11px] font-medium text-muted-foreground">First Name <span class="text-destructive">*</span></label>
                            <input id="new-student-first-name" x-model="first_name" name="first_name" aria-describedby="new-student-first-name-empty-error" class="h-8 w-full rounded-md border border-input bg-muted px-2 text-[12px] font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                            <p id="new-student-first-name-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">This field is required.</p>
                        </div>
                        <div class="grid min-h-[74px] gap-0.5">
                            <label for="new-student-middle-name" class="text-[11px] font-medium text-muted-foreground">Middle Name</label>
                            <input id="new-student-middle-name" x-model="middle_name" name="middle_name" class="h-8 w-full rounded-md border border-input bg-muted px-2 text-[12px] font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                            <p class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">&nbsp;</p>
                        </div>
                        <div class="grid min-h-[74px] gap-0.5">
                            <label for="new-student-last-name" class="text-[11px] font-medium text-muted-foreground">Last Name <span class="text-destructive">*</span></label>
                            <input id="new-student-last-name" x-model="last_name" name="last_name" aria-describedby="new-student-last-name-empty-error" class="h-8 w-full rounded-md border border-input bg-muted px-2 text-[12px] font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                            <p id="new-student-last-name-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">This field is required.</p>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="grid min-h-[74px] gap-0.5">
                            <label for="new-student-id" class="text-[11px] font-medium text-muted-foreground">Student ID <span class="text-destructive">*</span></label>
                            <input id="new-student-id" name="student_id" type="text" inputmode="numeric" minlength="8" maxlength="8" pattern="[0-9]{8}" aria-describedby="new-student-id-empty-error" class="h-8 w-full rounded-md border border-input bg-muted px-2 text-[12px] font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                            <p id="new-student-id-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">This field is required.</p>
                        </div>
                        <div class="grid min-h-[74px] gap-0.5">
                            <label for="new-student-email" class="text-[11px] font-medium text-muted-foreground">Email <span class="text-destructive">*</span></label>
                            <input id="new-student-email" name="email" type="email" aria-describedby="new-student-email-empty-error" class="h-8 w-full rounded-md border border-input bg-muted px-2 text-[12px] font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                            <p id="new-student-email-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">This field is required.</p>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                            <div class="grid min-h-[74px] gap-0.5">
                            <label class="text-[11px] font-medium text-muted-foreground">Department <span class="text-destructive">*</span></label>
                            <input type="hidden" name="department" x-model="department">
                            <details id="new-student-department-field" x-data="{}" class="group relative w-full" x-on:click.outside="$el.removeAttribute('open')">
                                <summary class="flex h-9 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-muted px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted/80 [&::-webkit-details-marker]:hidden">
                                    <span x-text="department || 'Select'" :class="department ? '' : 'text-muted-foreground'"></span>
                                    <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                                </summary>
                                <div class="absolute top-full z-50 mt-1 w-full rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                    <template x-for="option in Object.keys(courseOptions)" :key="option">
                                        <button type="button" @click="department = option; selectedCourse = ''; yearValue = ''; blockValue = ''; $event.target.closest('details').removeAttribute('open')" class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-xs hover:bg-accent hover:text-accent-foreground" x-text="option"></button>
                                    </template>
                                </div>
                            </details>
                            <p id="new-student-department-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">Please select an option.</p>
                        </div>
                        <div class="grid min-h-[74px] gap-0.5">
                            <label class="text-[11px] font-medium text-muted-foreground">Course <span class="text-destructive">*</span></label>
                            <input type="hidden" name="course" x-model="selectedCourse">
                            <details id="new-student-course-field" x-data="{}" class="group relative w-full" x-on:click.outside="$el.removeAttribute('open')">
                                <summary class="flex h-9 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-muted px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted/80 [&::-webkit-details-marker]:hidden">
                                    <span x-text="selectedCourse || 'Select'" :class="selectedCourse ? '' : 'text-muted-foreground'"></span>
                                    <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                                </summary>
                                <div class="absolute top-full z-50 mt-1 max-h-56 w-full overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                    <template x-if="department">
                                        <template x-for="option in courseOptions[department]" :key="option">
                                            <button type="button" @click="selectedCourse = option; yearValue = ''; blockValue = ''; $event.target.closest('details').removeAttribute('open')" class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-xs hover:bg-accent hover:text-accent-foreground" x-text="option"></button>
                                        </template>
                                    </template>
                                </div>
                            </details>
                            <p id="new-student-course-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">Please select an option.</p>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="grid min-h-[74px] gap-0.5">
                            <label class="text-[11px] font-medium text-muted-foreground">Year <span class="text-destructive">*</span></label>
                            <input type="hidden" name="year_level" x-model="yearValue">
                            <details id="new-student-year-field" x-data="{}" class="group relative w-full" x-on:click.outside="$el.removeAttribute('open')">
                                <summary class="flex h-9 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-muted px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted/80 [&::-webkit-details-marker]:hidden">
                                    <span x-text="yearValue || 'Select'" :class="yearValue ? '' : 'text-muted-foreground'"></span>
                                    <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                                </summary>
                                <div class="absolute top-full z-50 mt-1 w-full rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                    <template x-for="year in (studentCourseDetails[department]?.[selectedCourse]?.years || [])" :key="year">
                                        <button type="button" @click="yearValue = year; $event.target.closest('details').removeAttribute('open')" class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-xs hover:bg-accent hover:text-accent-foreground" x-text="year"></button>
                                    </template>
                                </div>
                            </details>
                            <p id="new-student-year-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">Please select an option.</p>
                        </div>
                        <div class="grid min-h-[74px] gap-0.5">
                            <label class="text-[11px] font-medium text-muted-foreground">Block</label>
                            <input type="hidden" name="block" x-model="blockValue">
                            <details x-data="{}" class="group relative w-full" x-on:click.outside="$el.removeAttribute('open')">
                                <summary class="flex h-9 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-muted px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted/80 [&::-webkit-details-marker]:hidden">
                                    <span x-text="blockValue || 'Select'" :class="blockValue ? '' : 'text-muted-foreground'"></span>
                                    <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                                </summary>
                                <div class="absolute top-full z-50 mt-1 w-full rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                    <template x-for="block in (studentCourseDetails[department]?.[selectedCourse]?.blocks || [])" :key="block">
                                        <button type="button" @click="blockValue = block; $event.target.closest('details').removeAttribute('open')" class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-xs hover:bg-accent hover:text-accent-foreground" x-text="block"></button>
                                    </template>
                                </div>
                            </details>
                            <p class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">&nbsp;</p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="resetStudentForm()" class="inline-flex h-8 items-center justify-center rounded-full border border-border bg-white px-4 text-[11px] font-semibold text-foreground transition-colors hover:bg-muted">Cancel</button>
                        <button type="submit" class="inline-flex h-8 items-center justify-center rounded-full bg-primary px-4 text-[11px] font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Create</button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="editStudentModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div @click.outside="closeEditStudentForm()" class="w-full max-w-2xl rounded-2xl border border-border bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-border px-5 py-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">Students</p>
                        <h2 class="mt-1 text-lg font-bold text-foreground">Edit Student Account</h2>
                    </div>
                    <button type="button" @click="closeEditStudentForm()" class="grid h-8 w-8 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground" aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="h-4 w-4"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>

                <form id="edit-student-account-form" method="POST" :action="`{{ url('/admin/accounts') }}/${editStudentUserId}`" novalidate class="space-y-3 p-5">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="role" value="student">
                    <input type="hidden" name="name" x-bind:value="`${editStudentFirstName} ${editStudentMiddleName ? editStudentMiddleName + ' ' : ''}${editStudentLastName}`.trim()">

                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="grid min-h-[74px] gap-0.5">
                            <label for="edit-student-first-name" class="text-[11px] font-medium text-muted-foreground">First Name <span class="text-destructive">*</span></label>
                            <input id="edit-student-first-name" x-model="editStudentFirstName" name="first_name" aria-describedby="edit-student-first-name-empty-error" class="h-8 w-full rounded-md border border-input bg-muted px-2 text-[12px] font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                            <p id="edit-student-first-name-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">This field is required.</p>
                        </div>
                        <div class="grid min-h-[74px] gap-0.5">
                            <label for="edit-student-middle-name" class="text-[11px] font-medium text-muted-foreground">Middle Name</label>
                            <input id="edit-student-middle-name" x-model="editStudentMiddleName" name="middle_name" class="h-8 w-full rounded-md border border-input bg-muted px-2 text-[12px] font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                            <p class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">&nbsp;</p>
                        </div>
                        <div class="grid min-h-[74px] gap-0.5">
                            <label for="edit-student-last-name" class="text-[11px] font-medium text-muted-foreground">Last Name <span class="text-destructive">*</span></label>
                            <input id="edit-student-last-name" x-model="editStudentLastName" name="last_name" aria-describedby="edit-student-last-name-empty-error" class="h-8 w-full rounded-md border border-input bg-muted px-2 text-[12px] font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                            <p id="edit-student-last-name-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">This field is required.</p>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="grid min-h-[74px] gap-0.5">
                            <label for="edit-student-email" class="text-[11px] font-medium text-muted-foreground">Email <span class="text-destructive">*</span></label>
                            <input id="edit-student-email" x-model="editStudentEmail" name="email" type="email" aria-describedby="edit-student-email-empty-error" class="h-8 w-full rounded-md border border-input bg-muted px-2 text-[12px] font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                            <p id="edit-student-email-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">This field is required.</p>
                        </div>
                        <div class="grid min-h-[74px] gap-0.5">
                            <label for="edit-student-id" class="text-[11px] font-medium text-muted-foreground">Student ID <span class="text-destructive">*</span></label>
                            <input id="edit-student-id" x-model="editStudentId" name="student_id" aria-describedby="edit-student-id-empty-error" class="h-8 w-full rounded-md border border-input bg-muted px-2 text-[12px] font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                            <p id="edit-student-id-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">This field is required.</p>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="grid min-h-[74px] gap-0.5">
                            <label class="text-[11px] font-medium text-muted-foreground">Department <span class="text-destructive">*</span></label>
                            <input type="hidden" name="department" x-model="editStudentDepartment">
                            <details id="edit-student-department-field" x-data="{}" class="group relative w-full" x-on:click.outside="$el.removeAttribute('open')">
                                <summary class="flex h-9 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-muted px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted/80 [&::-webkit-details-marker]:hidden">
                                    <span x-text="editStudentDepartment || 'Select'" :class="editStudentDepartment ? '' : 'text-muted-foreground'"></span>
                                    <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                                </summary>
                                <div class="absolute top-full z-50 mt-1 max-h-56 w-full overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                    <button type="button" @click="editStudentDepartment = ''; editStudentCourse = ''; editStudentYearLevel = ''; editStudentBlock = ''; $event.target.closest('details').removeAttribute('open')" class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-xs" :class="editStudentDepartment === '' ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground'">Select</button>
                                    <template x-for="option in Object.keys(courseOptions)" :key="`edit-${option}`">
                                        <button type="button" @click="editStudentDepartment = option; editStudentCourse = ''; editStudentYearLevel = ''; editStudentBlock = ''; $event.target.closest('details').removeAttribute('open')" class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-xs hover:bg-accent hover:text-accent-foreground" x-text="option"></button>
                                    </template>
                                </div>
                            </details>
                            <p id="edit-student-department-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">Please select an option.</p>
                        </div>
                        <div class="grid min-h-[74px] gap-0.5">
                            <label class="text-[11px] font-medium text-muted-foreground">Course <span class="text-destructive">*</span></label>
                            <input type="hidden" name="course" x-model="editStudentCourse">
                            <details id="edit-student-course-field" x-data="{}" class="group relative w-full" x-on:click.outside="$el.removeAttribute('open')">
                                <summary class="flex h-9 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-muted px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted/80 [&::-webkit-details-marker]:hidden">
                                    <span x-text="editStudentCourse || 'Select'" :class="editStudentCourse ? '' : 'text-muted-foreground'"></span>
                                    <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                                </summary>
                                <div class="absolute top-full z-50 mt-1 max-h-56 w-full overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                    <template x-if="editStudentDepartment">
                                        <template x-for="option in (courseOptions[editStudentDepartment] || [])" :key="option">
                                            <button type="button" @click="editStudentCourse = option; editStudentYearLevel = ''; editStudentBlock = ''; $event.target.closest('details').removeAttribute('open')" class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-xs hover:bg-accent hover:text-accent-foreground" x-text="option"></button>
                                        </template>
                                    </template>
                                </div>
                            </details>
                            <p id="edit-student-course-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">Please select an option.</p>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="grid min-h-[74px] gap-0.5">
                            <label class="text-[11px] font-medium text-muted-foreground">Year <span class="text-destructive">*</span></label>
                            <input type="hidden" name="year_level" x-model="editStudentYearLevel">
                            <details id="edit-student-year-field" x-data="{}" class="group relative w-full" x-on:click.outside="$el.removeAttribute('open')">
                                <summary class="flex h-9 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-muted px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted/80 [&::-webkit-details-marker]:hidden">
                                    <span x-text="editStudentYearLevel || 'Select'" :class="editStudentYearLevel ? '' : 'text-muted-foreground'"></span>
                                    <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                                </summary>
                                <div class="absolute top-full z-50 mt-1 w-full rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                    <template x-for="year in (studentCourseDetails[editStudentDepartment]?.[editStudentCourse]?.years || [])" :key="year">
                                        <button type="button" @click="editStudentYearLevel = year; $event.target.closest('details').removeAttribute('open')" class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-xs hover:bg-accent hover:text-accent-foreground" x-text="year"></button>
                                    </template>
                                </div>
                            </details>
                            <p id="edit-student-year-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">Please select an option.</p>
                        </div>
                        <div class="grid min-h-[74px] gap-0.5">
                            <label class="text-[11px] font-medium text-muted-foreground">Block</label>
                            <input type="hidden" name="block" x-model="editStudentBlock">
                            <details x-data="{}" class="group relative w-full" x-on:click.outside="$el.removeAttribute('open')">
                                <summary class="flex h-9 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-muted px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted/80 [&::-webkit-details-marker]:hidden">
                                    <span x-text="editStudentBlock || 'Select'" :class="editStudentBlock ? '' : 'text-muted-foreground'"></span>
                                    <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                                </summary>
                                <div class="absolute top-full z-50 mt-1 w-full rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                    <template x-for="block in (studentCourseDetails[editStudentDepartment]?.[editStudentCourse]?.blocks || [])" :key="block">
                                        <button type="button" @click="editStudentBlock = block; $event.target.closest('details').removeAttribute('open')" class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-xs hover:bg-accent hover:text-accent-foreground" x-text="block"></button>
                                    </template>
                                </div>
                            </details>
                            <p class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">&nbsp;</p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="closeEditStudentForm()" class="inline-flex h-8 items-center justify-center rounded-full border border-border bg-white px-4 text-[11px] font-semibold text-foreground transition-colors hover:bg-muted">Cancel</button>
                        <button type="submit" class="inline-flex h-8 items-center justify-center rounded-full bg-primary px-4 text-[11px] font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Update</button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="editRecipientModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div @click.outside="closeEditRecipientForm()" class="w-full max-w-2xl rounded-2xl border border-border bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-border px-5 py-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">Recipients</p>
                        <h2 class="mt-1 text-lg font-bold text-foreground">Edit Recipient Account</h2>
                    </div>
                    <button type="button" @click="closeEditRecipientForm()" class="grid h-8 w-8 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground" aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="h-4 w-4"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>

                <form id="edit-recipient-account-form" method="POST" :action="`{{ url('/admin/accounts') }}/${editRecipientUserId}`" novalidate class="space-y-3 p-5">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="role" value="recipient">
                    <input type="hidden" name="name" x-bind:value="`${editRecipientFirstName} ${editRecipientMiddleName ? editRecipientMiddleName + ' ' : ''}${editRecipientLastName}`.trim()">

                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="grid min-h-[74px] gap-0.5">
                            <label for="edit-recipient-first-name" class="text-[11px] font-medium text-muted-foreground">First Name <span class="text-destructive">*</span></label>
                            <input id="edit-recipient-first-name" x-model="editRecipientFirstName" name="first_name" aria-describedby="edit-recipient-first-name-empty-error" class="h-8 w-full rounded-md border border-input bg-muted px-2 text-[12px] font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                            <p id="edit-recipient-first-name-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">This field is required.</p>
                        </div>
                        <div class="grid min-h-[74px] gap-0.5">
                            <label for="edit-recipient-middle-name" class="text-[11px] font-medium text-muted-foreground">Middle Name</label>
                            <input id="edit-recipient-middle-name" x-model="editRecipientMiddleName" name="middle_name" class="h-8 w-full rounded-md border border-input bg-muted px-2 text-[12px] font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                            <p class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">&nbsp;</p>
                        </div>
                        <div class="grid min-h-[74px] gap-0.5">
                            <label for="edit-recipient-last-name" class="text-[11px] font-medium text-muted-foreground">Last Name <span class="text-destructive">*</span></label>
                            <input id="edit-recipient-last-name" x-model="editRecipientLastName" name="last_name" aria-describedby="edit-recipient-last-name-empty-error" class="h-8 w-full rounded-md border border-input bg-muted px-2 text-[12px] font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                            <p id="edit-recipient-last-name-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">This field is required.</p>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="grid min-h-[74px] gap-0.5">
                            <label for="edit-recipient-email" class="text-[11px] font-medium text-muted-foreground">Email <span class="text-destructive">*</span></label>
                            <input id="edit-recipient-email" x-model="editRecipientEmail" name="email" type="email" aria-describedby="edit-recipient-email-empty-error" class="h-8 w-full rounded-md border border-input bg-muted px-2 text-[12px] font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                            <p id="edit-recipient-email-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">This field is required.</p>
                        </div>
                        <div class="grid min-h-[74px] gap-0.5">
                            <label for="edit-recipient-staff-id" class="text-[11px] font-medium text-muted-foreground">Staff ID <span class="text-destructive">*</span></label>
                            <input id="edit-recipient-staff-id" x-model="editRecipientStaffId" name="staff_id" aria-describedby="edit-recipient-staff-id-empty-error" class="h-8 w-full rounded-md border border-input bg-muted px-2 text-[12px] font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                            <p id="edit-recipient-staff-id-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">This field is required.</p>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="grid min-h-[74px] gap-0.5">
                            <label class="text-[11px] font-medium text-muted-foreground">Department <span class="text-destructive">*</span></label>
                            <input type="hidden" name="recipient_department" x-model="editRecipientDepartment">
                            <details id="edit-recipient-department-field" x-data="{}" class="group relative w-full" x-on:click.outside="$el.removeAttribute('open')">
                                <summary class="flex h-9 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-muted px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted/80 [&::-webkit-details-marker]:hidden">
                                    <span x-text="editRecipientDepartment || 'Select department'" :class="editRecipientDepartment ? '' : 'text-muted-foreground'"></span>
                                    <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                                </summary>
                                <div class="absolute top-full z-50 mt-1 max-h-56 w-full overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                    <button type="button" @click="editRecipientDepartment = ''; $event.target.closest('details').removeAttribute('open')" class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-xs" :class="editRecipientDepartment === '' ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground'">Select department</button>
                                    @foreach ($recipientDepartments as $department)
                                        <button type="button" @click="editRecipientDepartment = @js($department->name); editRecipientDesignation = ''; $event.target.closest('details').removeAttribute('open')" class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-xs" :class="editRecipientDepartment === @js($department->name) ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground'">{{ $department->name }}</button>
                                    @endforeach
                                </div>
                            </details>
                            <p id="edit-recipient-department-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">Please select an option.</p>
                        </div>
                        <div class="grid min-h-[74px] gap-0.5">
                            <label for="edit-recipient-designation" class="text-[11px] font-medium text-muted-foreground">Position / Designation <span class="text-destructive">*</span></label>
                            <input id="edit-recipient-designation" type="hidden" name="designation" x-model="editRecipientDesignation">
                            <details id="edit-recipient-designation-field" x-data="{}" class="group relative w-full" x-on:click.outside="$el.removeAttribute('open')">
                                <summary class="flex h-9 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-muted px-3 py-2 text-xs shadow-sm outline-none [&::-webkit-details-marker]:hidden"><span class="truncate" x-text="editRecipientDesignation || 'Select position'" :class="editRecipientDesignation ? '' : 'text-muted-foreground'"></span><svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6" /></svg></summary>
                                <div class="absolute top-full z-50 mt-1 max-h-56 w-full overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md"><button type="button" @click="editRecipientDesignation = ''; $event.target.closest('details').removeAttribute('open')" class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-xs hover:bg-accent hover:text-accent-foreground">Select position</button><template x-for="position in (recipientDepartmentOptions[editRecipientDepartment] || [])" :key="position"><button type="button" @click="editRecipientDesignation = position; $event.target.closest('details').removeAttribute('open')" class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-xs hover:bg-accent hover:text-accent-foreground" x-text="position"></button></template></div>
                            </details>
                            <p id="edit-recipient-designation-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">This field is required.</p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="closeEditRecipientForm()" class="inline-flex h-8 items-center justify-center rounded-full border border-border bg-white px-4 text-[11px] font-semibold text-foreground transition-colors hover:bg-muted">Cancel</button>
                        <button type="submit" class="inline-flex h-8 items-center justify-center rounded-full bg-primary px-4 text-[11px] font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Update</button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="recipientModalOpen" x-cloak @click.self="resetRecipientForm()" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div @click.outside="recipientModalOpen = false" class="w-full max-w-2xl rounded-2xl border border-border bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-border px-5 py-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">Recipients</p>
                        <h2 class="mt-1 text-lg font-bold text-foreground">Add Recipient Account</h2>
                    </div>
                    <button type="button" @click="resetRecipientForm()" class="grid h-8 w-8 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground" aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="h-4 w-4"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>

                <form id="admin-recipient-account-form" action="{{ route('admin.accounts.store') }}" method="POST" novalidate class="space-y-3 p-5">
                    @csrf
                    <input type="hidden" name="role" value="recipient">
                    <input type="hidden" name="name" x-bind:value="`${recipientFirstName} ${recipientMiddleName ? recipientMiddleName + ' ' : ''}${recipientLastName}`.trim()">

                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="grid min-h-[74px] gap-0.5">
                            <label for="new-recipient-first-name" class="text-[11px] font-medium text-muted-foreground">First Name <span class="text-destructive">*</span></label>
                            <input id="new-recipient-first-name" x-model="recipientFirstName" name="first_name" aria-describedby="new-recipient-first-name-empty-error" class="h-8 w-full rounded-md border border-input bg-muted px-2 text-[12px] font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                            <p id="new-recipient-first-name-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">This field is required.</p>
                        </div>
                        <div class="grid min-h-[74px] gap-0.5">
                            <label for="new-recipient-middle-name" class="text-[11px] font-medium text-muted-foreground">Middle Name</label>
                            <input id="new-recipient-middle-name" x-model="recipientMiddleName" name="middle_name" class="h-8 w-full rounded-md border border-input bg-muted px-2 text-[12px] font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                            <p class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">&nbsp;</p>
                        </div>
                        <div class="grid min-h-[74px] gap-0.5">
                            <label for="new-recipient-last-name" class="text-[11px] font-medium text-muted-foreground">Last Name <span class="text-destructive">*</span></label>
                            <input id="new-recipient-last-name" x-model="recipientLastName" name="last_name" aria-describedby="new-recipient-last-name-empty-error" class="h-8 w-full rounded-md border border-input bg-muted px-2 text-[12px] font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                            <p id="new-recipient-last-name-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">This field is required.</p>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="grid min-h-[74px] gap-0.5">
                            <label for="new-recipient-staff-id" class="text-[11px] font-medium text-muted-foreground">Staff ID <span class="text-destructive">*</span></label>
                            <input id="new-recipient-staff-id" x-model="recipientStaffId" name="staff_id" aria-describedby="new-recipient-staff-id-empty-error" class="h-8 w-full rounded-md border border-input bg-muted px-2 text-[12px] font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                            <p id="new-recipient-staff-id-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">This field is required.</p>
                        </div>
                        <div class="grid min-h-[74px] gap-0.5">
                            <label for="new-recipient-email" class="text-[11px] font-medium text-muted-foreground">Email <span class="text-destructive">*</span></label>
                            <input id="new-recipient-email" x-model="recipientEmail" name="email" type="email" aria-describedby="new-recipient-email-empty-error" class="h-8 w-full rounded-md border border-input bg-muted px-2 text-[12px] font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                            <p id="new-recipient-email-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">This field is required.</p>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="grid min-h-[74px] gap-0.5">
                            <label for="new-recipient-department" class="text-[11px] font-medium text-muted-foreground">Department <span class="text-destructive">*</span></label>
                            <div class="relative">
                                <input type="hidden" name="recipient_department" x-model="recipientDepartment" />
                                <details id="new-recipient-department-field" x-data="{}" class="group relative w-full" x-on:click.outside="$el.removeAttribute('open')">
                                    <summary class="flex h-9 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-muted px-3 py-2 text-xs shadow-sm outline-none transition-colors hover:bg-muted/80 [&::-webkit-details-marker]:hidden">
                                        <span class="truncate" x-text="recipientDepartment || 'Select department'" :class="recipientDepartment ? '' : 'text-muted-foreground'"></span>
                                        <svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                                    </summary>
                                    <div class="absolute top-full z-50 mt-1 max-h-56 w-full overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                                        <button type="button" @click="recipientDepartment = ''; $event.target.closest('details').removeAttribute('open')" class="relative flex w-full items-center rounded-sm px-2 py-1.5 text-left text-xs" :class="!recipientDepartment ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground'">
                                            <svg x-show="!recipientDepartment" class="absolute right-2 h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 10 3 3 7-7" /></svg>
                                            <span>Select department</span>
                                        </button>
                                        @foreach ($recipientDepartments as $department)
                                            <button type="button" @click="recipientDepartment = @js($department->name); recipientDesignation = ''; $event.target.closest('details').removeAttribute('open')" class="relative flex w-full items-center rounded-sm px-2 py-1.5 text-left text-xs" :class="recipientDepartment === @js($department->name) ? 'bg-primary-soft text-primary' : 'hover:bg-accent hover:text-accent-foreground'">
                                                <svg x-show="recipientDepartment === @js($department->name)" class="absolute right-2 h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 10 3 3 7-7" /></svg>
                                                {{ $department->name }}
                                            </button>
                                        @endforeach
                                    </div>
                                </details>
                            </div>
                            <p id="new-recipient-department-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">Please select an option.</p>
                        </div>
                        <div class="grid min-h-[74px] gap-0.5">
                            <label for="new-recipient-designation" class="text-[11px] font-medium text-muted-foreground">Position / Designation <span class="text-destructive">*</span></label>
                            <input id="new-recipient-designation" type="hidden" name="designation" x-model="recipientDesignation">
                            <details id="new-recipient-designation-field" x-data="{}" class="group relative w-full" x-on:click.outside="$el.removeAttribute('open')">
                                <summary class="flex h-9 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-muted px-3 py-2 text-xs shadow-sm outline-none [&::-webkit-details-marker]:hidden"><span data-position-label class="truncate" x-text="recipientDesignation || 'Select position'" :class="recipientDesignation ? '' : 'text-muted-foreground'"></span><svg class="h-4 w-4 shrink-0 opacity-50 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6" /></svg></summary>
                                <div class="absolute top-full z-50 mt-1 max-h-56 w-full overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md"><button type="button" @click="recipientDesignation = ''; $event.target.closest('details').removeAttribute('open')" class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-xs hover:bg-accent hover:text-accent-foreground">Select position</button><template x-for="position in (recipientDepartmentOptions[recipientDepartment] || [])" :key="position"><button type="button" @click="recipientDesignation = position; $event.target.closest('details').removeAttribute('open')" class="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-xs hover:bg-accent hover:text-accent-foreground" x-text="position"></button></template></div>
                            </details>
                            <p id="new-recipient-designation-empty-error" class="invisible min-h-[14px] text-[11px] font-medium text-destructive opacity-0">This field is required.</p>
                        </div>
                    </div>

                    <datalist id="configured-position-options">
                        @foreach ($recipientDepartments as $department)
                            @foreach ($department->positions as $position)
                                <option value="{{ $position->name }}">{{ $department->name }}</option>
                            @endforeach
                        @endforeach
                    </datalist>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="resetRecipientForm()" class="inline-flex h-8 items-center justify-center rounded-full border border-border bg-white px-4 text-[11px] font-semibold text-foreground transition-colors hover:bg-muted">Cancel</button>
                        <button type="submit" class="inline-flex h-8 items-center justify-center rounded-full bg-primary px-4 text-[11px] font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Create</button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="tab === 'recipients'" x-cloak class="mx-auto mt-4 w-full">
            <div data-account-table="recipients" class="w-full overflow-x-auto rounded-lg border">
            <table class="w-full min-w-max whitespace-nowrap text-[13px]">
                <thead class="border-b bg-primary text-white">
                    <tr class="text-left">
                        <th class="whitespace-nowrap rounded-tl-lg py-2 pl-4 pr-2 text-[13px] font-semibold">Staff ID</th>
                        <th class="px-2 py-2 text-[13px] font-semibold">Last Name</th>
                        <th class="px-2 py-2 text-[13px] font-semibold">First Name</th>
                        <th class="px-2 py-2 text-[13px] font-semibold">Middle Name</th>
                        <th class="px-2 py-2 text-[13px] font-semibold">Email</th>
                        <th class="px-2 py-2 text-[13px] font-semibold">Department</th>
                        <th class="px-2 py-2 text-[13px] font-semibold">Position</th>
                        <th class="px-2 py-2 text-[13px] font-semibold">Status</th>
                        <th class="w-24 min-w-24 whitespace-nowrap rounded-tr-lg py-2 pl-2 pr-4 text-left text-[13px] font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recipientUsers as $user)
                        @php($accountName = $splitAccountName($user->name, $user))
                        <tr class="transition-colors hover:bg-primary-soft">
                            <td class="whitespace-nowrap py-1.5 pl-4 pr-2 font-mono text-[12px] font-semibold text-primary">{{ $user->recipient?->staff_id ?? '—' }}</td>
                            <td class="px-2 py-1.5 font-normal">{{ $accountName['last'] ?: '—' }}</td>
                            <td class="px-2 py-1.5 font-normal">{{ $accountName['first'] ?: '—' }}</td>
                            <td class="px-2 py-1.5">{{ $accountName['middle'] ?: '—' }}</td>
                            <td class="px-2 py-1.5 text-[13px] text-foreground">{{ $user->email ?? '—' }}</td>
                            <td class="px-2 py-1.5 text-[13px] text-foreground">{{ $user->recipient?->department ?? '—' }}</td>
                            <td class="px-2 py-1.5 text-[13px] text-foreground">{{ $user->recipient?->designation ?? '—' }}</td>
                            <td class="px-2 py-1.5">
                                @if ($user->is_active)
                                    <span class="text-[12px] font-semibold text-green-600">Active</span>
                                @else
                                    <span class="text-[12px] font-semibold text-red-600">Inactive</span>
                                @endif
                            </td>
                            <td class="w-24 min-w-24 whitespace-nowrap py-1.5 pl-2 pr-4 text-left">
                                <div class="flex items-center gap-2">
                                    @if ($user->role === \App\Models\User::ROLE_SDS_ADMIN)
                                        <span class="text-[11px] font-semibold text-muted-foreground">Administrator</span>
                                    @else
                                        <button type="button" @click="openEditRecipient({ id: {{ $user->id }}, name: @js($user->name), first_name: @js($user->first_name), middle_name: @js($user->middle_name), last_name: @js($user->last_name), email: @js($user->email), staff_id: @js($user->recipient?->staff_id ?? ''), department: @js($user->recipient?->department ?? ''), designation: @js($user->recipient?->designation ?? ''), recipient: { staff_id: @js($user->recipient?->staff_id ?? ''), department: @js($user->recipient?->department ?? ''), designation: @js($user->recipient?->designation ?? '') } })" class="inline-flex h-6 items-center justify-center rounded-md bg-[#7a1d2a] px-2 text-[11px] font-semibold text-white transition-colors hover:bg-[#651923]" aria-label="Edit account">
                                            Edit
                                        </button>
                                        @include('admin.accounts.partials.status-action', ['user' => $user])
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="py-10 text-center text-muted-foreground">No recipient accounts found.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            @if ($recipientUsers->hasPages())
                <nav class="mt-5 flex justify-end" aria-label="Recipient accounts pagination">
                    <div class="flex items-center gap-1 rounded-md border border-border bg-card p-1 shadow-sm">
                        @if ($recipientUsers->onFirstPage())
                            <span class="inline-flex h-8 min-w-8 items-center justify-center rounded px-2 text-xs text-muted-foreground/50" aria-disabled="true">Previous</span>
                        @else
                            <a href="{{ $recipientUsers->previousPageUrl() }}" class="inline-flex h-8 min-w-8 items-center justify-center rounded px-2 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground">Previous</a>
                        @endif

                        @foreach ($recipientUsers->getUrlRange(1, $recipientUsers->lastPage()) as $page => $url)
                            @if ($page === $recipientUsers->currentPage())
                                <span class="inline-flex h-8 min-w-8 items-center justify-center rounded bg-primary px-2 text-xs font-semibold text-primary-foreground" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="inline-flex h-8 min-w-8 items-center justify-center rounded border border-transparent px-2 text-xs text-muted-foreground transition-colors hover:border-border hover:bg-muted hover:text-foreground">{{ $page }}</a>
                            @endif
                        @endforeach

                        @if ($recipientUsers->hasMorePages())
                            <a href="{{ $recipientUsers->nextPageUrl() }}" class="inline-flex h-8 min-w-8 items-center justify-center rounded px-2 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground">Next</a>
                        @else
                            <span class="inline-flex h-8 min-w-8 items-center justify-center rounded px-2 text-xs text-muted-foreground/50" aria-disabled="true">Next</span>
                        @endif
                    </div>
                </nav>
            @endif
        </div>
    </div>
</x-app-layout>

<script>
    const bulkUploadInput = document.getElementById('bulk-upload-input');
    const bulkUploadForm = document.getElementById('bulk-upload-form');
    const bulkUploadTrigger = document.getElementById('bulk-upload-trigger');
    const bulkUploadType = document.getElementById('bulk-upload-type');

    if (bulkUploadTrigger && bulkUploadInput && bulkUploadForm) {
        bulkUploadTrigger.addEventListener('click', () => {
            const type = document.querySelector('[data-account-tab][aria-selected="true"]')?.dataset.accountTab ?? 'students';
            bulkUploadType.value = type === 'recipients' ? 'recipient' : 'student';
            bulkUploadInput.click();
        });
        bulkUploadInput.addEventListener('change', () => {
            if (bulkUploadInput.files && bulkUploadInput.files.length > 0) {
                bulkUploadForm.submit();
            }
        });
    }

    const adminAccountForms = [
        {
            form: document.getElementById('admin-student-account-form'),
            requiredFields: [
                { input: document.getElementById('new-student-first-name'), error: document.getElementById('new-student-first-name-empty-error'), isDropdown: false },
                { input: document.getElementById('new-student-last-name'), error: document.getElementById('new-student-last-name-empty-error'), isDropdown: false },
                { input: document.getElementById('new-student-id'), error: document.getElementById('new-student-id-empty-error'), isDropdown: false },
                { input: document.getElementById('new-student-email'), error: document.getElementById('new-student-email-empty-error'), isDropdown: false },
                { value: () => document.getElementById('admin-student-account-form')?.querySelector('input[name="department"]')?.value ?? '', error: document.getElementById('new-student-department-empty-error'), field: document.getElementById('new-student-department-field'), isDropdown: true },
                { value: () => document.getElementById('admin-student-account-form')?.querySelector('input[name="course"]')?.value ?? '', error: document.getElementById('new-student-course-empty-error'), field: document.getElementById('new-student-course-field'), isDropdown: true },
                { value: () => document.getElementById('admin-student-account-form')?.querySelector('input[name="year_level"]')?.value ?? '', error: document.getElementById('new-student-year-empty-error'), field: document.getElementById('new-student-year-field'), isDropdown: true },
            ]
        },
        {
            form: document.getElementById('admin-recipient-account-form'),
            requiredFields: [
                { input: document.getElementById('new-recipient-first-name'), error: document.getElementById('new-recipient-first-name-empty-error'), isDropdown: false },
                { input: document.getElementById('new-recipient-last-name'), error: document.getElementById('new-recipient-last-name-empty-error'), isDropdown: false },
                { input: document.getElementById('new-recipient-staff-id'), error: document.getElementById('new-recipient-staff-id-empty-error'), isDropdown: false },
                { input: document.getElementById('new-recipient-email'), error: document.getElementById('new-recipient-email-empty-error'), isDropdown: false },
                { value: () => document.getElementById('admin-recipient-account-form')?.querySelector('input[name="recipient_department"]')?.value ?? '', error: document.getElementById('new-recipient-department-empty-error'), field: document.getElementById('new-recipient-department-field'), isDropdown: true },
                { input: document.getElementById('new-recipient-designation'), error: document.getElementById('new-recipient-designation-empty-error'), isDropdown: false },
            ]
        },
        {
            form: document.getElementById('edit-student-account-form'),
            requiredFields: [
                { input: document.getElementById('edit-student-first-name'), error: document.getElementById('edit-student-first-name-empty-error'), isDropdown: false },
                { input: document.getElementById('edit-student-last-name'), error: document.getElementById('edit-student-last-name-empty-error'), isDropdown: false },
                { input: document.getElementById('edit-student-email'), error: document.getElementById('edit-student-email-empty-error'), isDropdown: false },
                { input: document.getElementById('edit-student-id'), error: document.getElementById('edit-student-id-empty-error'), isDropdown: false },
                { value: () => document.getElementById('edit-student-account-form')?.querySelector('input[name="department"]')?.value ?? '', error: document.getElementById('edit-student-department-empty-error'), field: document.getElementById('edit-student-department-field'), isDropdown: true },
                { value: () => document.getElementById('edit-student-account-form')?.querySelector('input[name="course"]')?.value ?? '', error: document.getElementById('edit-student-course-empty-error'), field: document.getElementById('edit-student-course-field'), isDropdown: true },
                { value: () => document.getElementById('edit-student-account-form')?.querySelector('input[name="year_level"]')?.value ?? '', error: document.getElementById('edit-student-year-empty-error'), field: document.getElementById('edit-student-year-field'), isDropdown: true },
            ]
        },
        {
            form: document.getElementById('edit-recipient-account-form'),
            requiredFields: [
                { input: document.getElementById('edit-recipient-first-name'), error: document.getElementById('edit-recipient-first-name-empty-error'), isDropdown: false },
                { input: document.getElementById('edit-recipient-last-name'), error: document.getElementById('edit-recipient-last-name-empty-error'), isDropdown: false },
                { input: document.getElementById('edit-recipient-email'), error: document.getElementById('edit-recipient-email-empty-error'), isDropdown: false },
                { input: document.getElementById('edit-recipient-staff-id'), error: document.getElementById('edit-recipient-staff-id-empty-error'), isDropdown: false },
                { value: () => document.getElementById('edit-recipient-account-form')?.querySelector('input[name="recipient_department"]')?.value ?? '', error: document.getElementById('edit-recipient-department-empty-error'), field: document.getElementById('edit-recipient-department-field'), isDropdown: true },
                { input: document.getElementById('edit-recipient-designation'), error: document.getElementById('edit-recipient-designation-empty-error'), isDropdown: false },
            ]
        }
    ];

    const setAdminAccountRequiredState = (input, error, isEmpty, field, isDropdown = false) => {
        if (input) {
            input.classList.toggle('border-destructive', isEmpty);
            input.classList.toggle('!border-destructive', isEmpty);
            input.classList.toggle('password-error-border', isEmpty);
        }

        if (field) {
            field.querySelector('summary')?.classList.toggle('border-destructive', isEmpty);
            field.querySelector('summary')?.classList.toggle('!border-destructive', isEmpty);
            field.querySelector('summary')?.classList.toggle('password-error-border', isEmpty);
        }

        if (error) {
            error.textContent = isDropdown ? 'Please select an option.' : 'This field is required.';
            error.classList.toggle('invisible', !isEmpty);
            error.classList.toggle('opacity-0', !isEmpty);
            error.classList.toggle('opacity-100', isEmpty);
        }
    };

    adminAccountForms.forEach(({ form, requiredFields }) => {
        if (!form) return;

        form.addEventListener('submit', (event) => {
            let hasEmptyRequired = false;

            requiredFields.forEach(({ input, error, field, value, isDropdown }) => {
                const isEmpty = input ? !input.value.trim() : !value?.().trim();
                setAdminAccountRequiredState(input, error, isEmpty, field, isDropdown);
                if (isEmpty) hasEmptyRequired = true;
            });

            if (hasEmptyRequired) {
                event.preventDefault();
            }
        });
    });

    (() => {
        const filterFormSelector = '[data-account-filter-form]';
        const tableSelector = '[data-account-table]';
        let searchTimer;
        let searchRequest = 0;
        let abortController;

        const replaceAccountFragments = (html) => {
            const nextDocument = new DOMParser().parseFromString(html, 'text/html');
            const currentFilterForm = document.querySelector(filterFormSelector);
            const nextFilterForm = nextDocument.querySelector(filterFormSelector);
            const showStudentFields = document.querySelector('[data-account-table="students"]')?.style.display !== 'none';
            const activeElement = document.activeElement;
            const activeInputId = activeElement?.matches(`${filterFormSelector} input[name="search"]`) ? activeElement.id : null;
            const activeInputValue = activeInputId ? activeElement.value : null;
            const selectionStart = activeInputId ? activeElement.selectionStart : null;
            const selectionEnd = activeInputId ? activeElement.selectionEnd : null;

            if (currentFilterForm && nextFilterForm) {
                nextFilterForm.querySelectorAll('[x-cloak]').forEach((element) => element.removeAttribute('x-cloak'));
                nextFilterForm.querySelectorAll('[x-show="tab === \'students\'"]').forEach((element) => {
                    element.style.display = showStudentFields ? '' : 'none';
                });
                currentFilterForm.replaceWith(nextFilterForm);
                window.Alpine?.initTree(nextFilterForm);

                if (activeInputId) {
                    const nextInput = document.getElementById(activeInputId);
                    if (nextInput && activeInputValue !== null) nextInput.value = activeInputValue;
                    nextInput?.focus({ preventScroll: true });
                    if (selectionStart !== null && selectionEnd !== null) {
                        nextInput?.setSelectionRange(selectionStart, selectionEnd);
                    }
                }
            }

            document.querySelectorAll(tableSelector).forEach((currentTable) => {
                const nextTable = nextDocument.querySelector(`${tableSelector}[data-account-table="${currentTable.dataset.accountTable}"]`);
                if (nextTable) {
                    currentTable.innerHTML = nextTable.innerHTML;
                    window.Alpine?.initTree(currentTable);
                }
            });
        };

        const loadAccountResults = async (url, pushState = true) => {
            const requestId = ++searchRequest;
            abortController?.abort();
            abortController = new AbortController();
            document.querySelectorAll(tableSelector).forEach((table) => table.classList.add('pointer-events-none'));

            try {
                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: abortController.signal,
                });
                if (!response.ok) throw new Error(`Account filter request failed: ${response.status}`);
                const html = await response.text();
                if (requestId !== searchRequest) return;
                replaceAccountFragments(html);
                if (pushState) window.history.pushState({}, '', url);
            } catch (error) {
                if (error.name !== 'AbortError') window.location.assign(url);
            } finally {
                if (requestId === searchRequest) {
                    document.querySelectorAll(tableSelector).forEach((table) => table.classList.remove('pointer-events-none'));
                }
            }
        };

        const submitAccountFilter = (form) => {
            if (!form) return;

            const url = new URL(window.location.href);
            const search = form.querySelector('input[name="search"]')?.value.trim() ?? '';
            if (search) {
                url.searchParams.set('search', search);
            } else {
                url.searchParams.delete('search');
            }
            url.searchParams.delete('students_page');
            url.searchParams.delete('recipients_page');
            loadAccountResults(url.toString());
        };

        document.addEventListener('submit', (event) => {
            const form = event.target.closest(filterFormSelector);
            if (!form) return;

            event.preventDefault();
            submitAccountFilter(form);
        });

        document.addEventListener('input', (event) => {
            const input = event.target.closest(`${filterFormSelector} input[name="search"]`);
            if (!input) return;

            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => submitAccountFilter(input.form), 350);
        });

        document.addEventListener('click', (event) => {
            const accountTab = event.target.closest('[data-account-tab]');
            if (accountTab) {
                const searchInput = document.querySelector(`${filterFormSelector} input[name="search"]`);
                if (searchInput) searchInput.value = '';

                const url = new URL(window.location.href);
                url.searchParams.set('category_filter', accountTab.dataset.accountTab);
                url.searchParams.delete('search');
                url.searchParams.delete('students_page');
                url.searchParams.delete('recipients_page');
                loadAccountResults(url.toString());
                return;
            }

            const link = event.target.closest(`${filterFormSelector} a[href], ${tableSelector} a[href]`);
            if (!link) return;

            const url = new URL(link.href, window.location.href);
            if (url.origin !== window.location.origin) return;

            event.preventDefault();
            loadAccountResults(url.toString());
        });

        window.addEventListener('popstate', () => loadAccountResults(window.location.href, false));
    })();
</script>
