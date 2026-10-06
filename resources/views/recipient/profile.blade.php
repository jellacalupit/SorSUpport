@props(['editable' => false, 'nameParts' => ['', '', ''], 'units' => []])

@php
    $profileExtensionOptions = ['' => 'None', 'Jr.' => 'Jr.', 'Sr.' => 'Sr.', 'II' => 'II', 'III' => 'III', 'IV' => 'IV', 'V' => 'V'];
    $hasAdminProfileDetails = $editable
        && filled($user->first_name)
        && filled($user->last_name)
        && filled($user->email)
        && filled($user->username)
        && ($user->role === 'sds_admin' || $user->recipient?->exists());
    $isInitialAdminSetup = $editable && $user->must_change_password && ! $hasAdminProfileDetails;
    $showAdminPasswordPrompt = $editable && $user->must_change_password && $hasAdminProfileDetails;
    $adminSetupHasNoDeptOrPosition = $editable && $user->role === 'sds_admin' && $user->must_change_password && blank($user->first_name) && blank($user->last_name) && blank($user->name);
    $savedNameParts = is_array($savedNameParts ?? null) ? ($savedNameParts ?? []) : [];
    $profileFirstName = trim((string) ($savedNameParts['first_name'] ?? ($user->first_name ?? '')));
    $profileMiddleName = trim((string) ($savedNameParts['middle_name'] ?? ($user->middle_name ?? '')));
    $profileLastName = trim((string) ($savedNameParts['last_name'] ?? ($user->last_name ?? '')));
    $profileExtension = trim((string) ($savedNameParts['extension'] ?? ''));
    $adminHasEmptyProfile = $user->role === 'sds_admin' && blank($user->first_name) && blank($user->last_name) && blank($user->name);

    $parsedNameParts = array_values(array_filter(preg_split('/\s+/', trim((string) ($user->name ?? ''))) ?: [], static fn ($part) => $part !== ''));

    if ($profileFirstName === '' && !empty($parsedNameParts)) {
        $profileFirstName = count($parsedNameParts) > 1
            ? implode(' ', array_slice($parsedNameParts, 0, -1))
            : ($parsedNameParts[0] ?? '');
    }

    if ($profileLastName === '' && !empty($parsedNameParts)) {
        $profileLastName = $parsedNameParts[count($parsedNameParts) - 1] ?? '';
    }

    if ($isInitialAdminSetup) {
        $profileFirstName = 'Administrator';
        $profileMiddleName = '';
        $profileLastName = '';
        $profileExtension = '';
    }

    if ($adminHasEmptyProfile && $profileFirstName === '') {
        $profileFirstName = 'Administrator';
    }

    $profileDisplayMiddleInitial = $profileMiddleName !== '' ? strtoupper(substr($profileMiddleName, 0, 1)) . '.' : '';
    $recipientDisplayName = $isInitialAdminSetup || $adminHasEmptyProfile
        ? $profileFirstName
        : trim(implode(' ', array_filter([
            $profileFirstName,
            $profileDisplayMiddleInitial,
            $profileLastName,
            $profileExtension,
        ], static fn ($part) => $part !== null && $part !== '')));
    $profileRole = $user->role === 'sds_admin' ? 'Administrator' : ($user->role === 'student' ? 'Student' : 'Recipient');
    $profileStatus = $user->is_active ? 'Active' : 'Inactive';
    $profileStatusClass = $user->is_active ? 'text-emerald-600' : 'text-red-600';
    $showMinimalAdminProfile = $user->role === 'sds_admin' && blank($user->first_name) && blank($user->last_name) && blank($user->recipient?->unit) && blank($user->recipient?->designation);
    $adminIsInSetupPlaceholderState = $user->role === 'sds_admin'
        && $user->must_change_password
        && blank($user->recipient?->staff_id)
        && blank($user->recipient?->unit)
        && blank($user->recipient?->designation);
    $profileId = $adminIsInSetupPlaceholderState ? '—' : ($recipient?->staff_id ?? $user->recipient?->staff_id ?? $user->username ?? 'Not assigned');
    $profileDepartment = $adminIsInSetupPlaceholderState ? '—' : ($recipient?->unit ?? $user->recipient?->unit ?? ($user->department ?? '—'));
    $profileDesignation = $adminIsInSetupPlaceholderState ? '—' : ($recipient?->designation ?? $user->recipient?->designation ?? ($user->designation ?? '—'));
    $profileFullName = $isInitialAdminSetup || $adminHasEmptyProfile ? $profileFirstName : $user->name;
    $profileEmail = filled($user->email) ? $user->email : ($isInitialAdminSetup ? 'sorsu.support@gmail.com' : '—');
    $isRecipient = $user->role === 'recipient';
    $passwordUpdateHasErrors = $errors->getBag('updatePassword')->any();
    $currentPasswordServerError = $errors->getBag('updatePassword')->first('current_password');
    $currentPasswordServerErrorIsRequired = $currentPasswordServerError && str_contains(strtolower($currentPasswordServerError), 'required');
    $profileDepartmentValue = old('unit', $isInitialAdminSetup ? '' : ($profileDepartment !== '—' ? $profileDepartment : ''));
    $profileDesignationValue = old('designation', $isInitialAdminSetup ? '' : ($profileDesignation !== '—' ? $profileDesignation : ''));
    $profileDepartmentOptions = collect($units)->map(fn ($unit) => [
        'name' => $unit->name,
        'positions' => $unit->designations->pluck('name')->values(),
    ])->values();
@endphp

<x-app-layout :role="$user->role === 'sds_admin' ? 'admin' : ($user->role === 'student' ? 'student' : 'recipient')" title="My Profile">
    <div class="w-full font-sans">
        <div class="{{ $isRecipient ? 'grid gap-0' : 'grid gap-5 lg:grid-cols-[440px_minmax(0,1fr)] lg:items-start' }}">
            <div class="{{ $isRecipient ? 'w-full' : 'w-full space-y-3 lg:w-[440px] lg:flex-shrink-0' }}">
                <aside class="{{ $isRecipient ? 'brand-gradient -mx-4 -mt-5 rounded-b-3xl px-6 pt-5 pb-7 text-center sm:-mx-6 sm:-mt-6 sm:py-9 lg:-mx-8 lg:-mt-8' : 'brand-gradient h-[420px] w-full overflow-hidden rounded-[28px] shadow-sm' }}">
                    <div class="flex h-full flex-col items-center justify-center {{ $isRecipient ? 'px-0 py-0' : 'px-6 py-8' }} text-center text-primary-foreground">
                        <div class="relative mx-auto {{ $isRecipient ? 'h-24 w-24 sm:h-28 sm:w-28' : 'h-40 w-40 sm:h-44 sm:w-44' }} overflow-visible rounded-full bg-primary-foreground/15 ring-2 ring-primary-foreground/30">
                            @if ($user->role === 'recipient' || $editable)
                                <form method="POST" action="{{ route('profile.photo') }}" enctype="multipart/form-data" class="absolute {{ $isRecipient ? 'right-0 bottom-0' : 'bottom-1 right-1' }} z-20 {{ $editable ? 'hidden' : '' }}" id="profile-photo-overlay">
                                    @csrf
                                    <label class="grid h-8 w-8 cursor-pointer place-items-center rounded-full border-2 border-white bg-[#5a101c] text-white shadow-md transition-colors hover:bg-[#7a1d2a]" aria-label="Upload profile photo">
                                        <x-icons.camera class="h-4 w-4 text-white" />
                                        <input id="recipient-profile-photo" name="avatar" type="file" accept="image/*" class="hidden" />
                                    </label>
                                </form>
                            @endif

                            <div class="h-full w-full overflow-hidden rounded-full">
                                @if ($user->avatar_path)
                                    <img src="{{ asset('storage/' . $user->avatar_path) }}" alt="{{ $user->name }}" class="h-full w-full object-cover">
                                @else
                                    <span class="grid h-full w-full place-items-center text-3xl font-bold sm:text-4xl">{{ $user->name_initials ?: 'A' }}</span>
                                @endif
                            </div>
                        </div>
                        <h2 class="{{ $isRecipient ? 'mt-4 text-xl sm:text-2xl' : 'mt-5 text-lg sm:text-xl' }} truncate font-bold">{{ $recipientDisplayName }}</h2>
                        @unless ($isInitialAdminSetup)
                            <p class="mt-1 w-full max-w-full whitespace-normal break-words px-2 text-xs font-normal opacity-95">ID {{ $profileId }} · {{ $profileDepartment }} · {{ $profileDesignation }}</p>
                        @endunless
                        @if ($user->role !== 'sds_admin')
                            <span class="mt-3 inline-flex rounded-full border border-primary bg-primary-foreground/15 px-4 py-1 text-xs font-normal">{{ $profileRole }}</span>
                        @endif
                    </div>
                </aside>

                @if ($editable)
                    <button type="button" id="toggle-profile-edit" @click="document.getElementById('profile-details-view')?.classList.add('hidden'); document.getElementById('profile-edit-form')?.classList.remove('hidden'); document.getElementById('profile-photo-overlay')?.classList.remove('hidden'); $el.classList.add('hidden')" class="inline-flex h-10 w-full items-center justify-center rounded-full bg-[#5a101c] px-5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-[#7a1d2a]">
                        Edit Profile
                    </button>
                @endif
            </div>

            <div class="space-y-5">
                <div>
                    <h3 class="{{ $isRecipient ? 'mt-6' : '' }} px-1 text-xs font-bold uppercase tracking-wide text-muted-foreground">Account Information</h3>
                    <div id="profile-details-card" class="surface relative z-10 mt-2 overflow-visible p-0">
                        <div id="profile-details-view">
                            @php
                                $accountInfoRows = $isInitialAdminSetup
                                    ? [
                                        ['Full Name', $profileFullName],
                                        ['Email', $profileEmail],
                                        ['Staff ID', $profileId],
                                        ['College / Office', $profileDepartment],
                                        ['Position', $profileDesignation],
                                        ['Account Type', $profileRole],
                                        ['Status', '<span class="text-[11px] font-semibold ' . $profileStatusClass . '">' . $profileStatus . '</span>'],
                                    ]
                                    : ($showMinimalAdminProfile
                                        ? [
                                            ['Full Name', $profileFullName],
                                            ['Email', $profileEmail],
                                            ['Staff ID', $profileId],
                                            ['College / Office', '—'],
                                            ['Position', '—'],
                                            ['Account Type', $profileRole],
                                            ['Status', '<span class="text-[11px] font-semibold ' . $profileStatusClass . '">' . $profileStatus . '</span>'],
                                        ]
                                        : [
                                            ['Full Name', $profileFullName],
                                            ['Email', $profileEmail],
                                            ...($user->role === 'recipient' ? [] : [
                                                ['Staff ID', $profileId],
                                                ['College / Office', $profileDepartment],
                                                ['Position', $profileDesignation],
                                            ]),
                                            ['Account Type', $profileRole],
                                            ['Status', '<span class="text-[11px] font-semibold ' . $profileStatusClass . '">' . $profileStatus . '</span>'],
                                        ]
                                    );
                            @endphp
                            @foreach($accountInfoRows as [$label, $value])
                                <div class="grid grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] gap-4 border-b border-border px-4 py-3 last:border-b-0 sm:px-5">
                                    <dt class="{{ $isRecipient ? 'text-sm' : 'text-xs' }} text-muted-foreground">{{ $label }}</dt>
                                    <dd class="flex min-w-0 items-center justify-end gap-1.5 wrap-break-word text-right {{ $isRecipient ? 'text-sm' : 'text-xs' }} font-medium">
                                        @if ($label === 'Status')
                                            {!! $value !!}
                                        @else
                                            <span>{{ $value }}</span>
                                            @if ($label === 'Email' && filled($value) && $value !== '—')
                                                <span class="grid h-4 w-4 shrink-0 place-items-center text-success" title="Verified" aria-label="Verified email">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.74 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.74Z"/><path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4"/></svg>
                                                </span>
                                            @endif
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </div>

                        @if ($editable)
                            <form id="profile-edit-form" method="POST" action="{{ route('profile.update') }}" class="relative z-20 hidden grid gap-1.5 overflow-visible p-2 sm:p-3">
                                @csrf
                                @method('PATCH')

                                <div class="grid items-start gap-1.5 md:grid-cols-[1.5fr_1.5fr_1.5fr_0.7fr]">
                                    <div class="grid gap-0.5">
                                        <label for="profile-first-name" class="text-[11px] font-medium text-muted-foreground">First Name <span class="text-destructive">*</span></label>
                                        <input id="profile-first-name" name="first_name" value="{{ old('first_name', $profileFirstName) }}" pattern="[A-Za-zÑñÁÉÍÓÚáéíóúüÇç\- ]+" title="Letters, spaces, and hyphen only" data-required="true" aria-describedby="profile-first-name-empty-error" class="h-8 w-full rounded-md border border-input bg-muted px-2 text-[12px] font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                                        <div data-error-slot class="{{ $errors->has('first_name') ? 'min-h-4' : 'hidden' }}"><p id="profile-first-name-empty-error" class="hidden text-[11px] font-medium text-destructive">This field is required.</p></div>
                                    </div>
                                    <div class="grid gap-0.5">
                                        <label for="profile-middle-name" class="text-[11px] font-medium text-muted-foreground">Middle Name</label>
                                        <input id="profile-middle-name" name="middle_name" value="{{ old('middle_name', $profileMiddleName) }}" pattern="[A-Za-zÑñÁÉÍÓÚáéíóúüÇç\- ]*" title="Letters, spaces, and hyphen only" class="h-8 w-full rounded-md border border-input bg-muted px-2 text-[12px] font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                                    </div>
                                    <div class="grid gap-0.5">
                                        <label for="profile-last-name" class="text-[11px] font-medium text-muted-foreground">Last Name <span class="text-destructive">*</span></label>
                                        <input id="profile-last-name" name="last_name" value="{{ old('last_name', $profileLastName) }}" pattern="[A-Za-zÑñÁÉÍÓÚáéíóúüÇç\- ]+" title="Letters, spaces, and hyphen only" data-required="true" aria-describedby="profile-last-name-empty-error" class="h-8 w-full rounded-md border border-input bg-muted px-2 text-[12px] font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                                        <div data-error-slot class="{{ $errors->has('last_name') ? 'min-h-4' : 'hidden' }}"><p id="profile-last-name-empty-error" class="hidden text-[11px] font-medium text-destructive">This field is required.</p></div>
                                    </div>
                                    <div class="grid gap-0.5">
                                        <label for="profile-extension" class="text-[11px] font-medium text-muted-foreground">Ext.</label>
                                        <details class="group relative" x-data="{}" x-on:click.outside="$el.removeAttribute('open')">
                                            <summary id="profile-extension" class="flex h-8 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-muted px-2 py-1 text-[12px] font-medium text-foreground [&::-webkit-details-marker]:hidden">
                                                <span id="profile-extension-display" class="truncate">{{ old('extension', $profileExtension) !== '' ? (collect($profileExtensionOptions)->get(old('extension', $profileExtension), old('extension', $profileExtension))) : 'None' }}</span>
                                                <svg class="h-3.5 w-3.5 shrink-0 opacity-60 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                                            </summary>
                                            <div class="absolute top-full z-40 mt-1 w-full overflow-y-auto rounded-lg border border-border bg-white p-1 text-foreground shadow-lg">
                                                @foreach ($profileExtensionOptions as $value => $label)
                                                    <button type="button" data-extension-value="{{ $value }}" data-extension-label="{{ $label }}" class="flex w-full items-center justify-start rounded-md px-2 py-1 text-left text-[12px] transition-colors hover:bg-[#f5dfe3]">
                                                        {{ $label }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        </details>
                                        <input type="hidden" id="profile-extension-input" name="extension" value="{{ old('extension', $profileExtension) }}" />
                                    </div>
                                </div>

                                <div class="grid items-start gap-1.5 md:grid-cols-2">
                                    <div class="grid gap-0.5">
                                        <label for="profile-email" class="text-[11px] font-medium text-muted-foreground">Email <span class="text-destructive">*</span></label>
                                        <input id="profile-email" name="email" type="email" value="{{ old('email', $user->email ?? '') }}" data-required="true" aria-describedby="profile-email-empty-error" class="h-8 w-full rounded-md border border-input bg-muted px-2 text-[12px] font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                                        <div data-error-slot class="{{ $errors->has('email') ? 'min-h-4' : 'hidden' }}">
                                            <p id="profile-email-empty-error" class="hidden text-[11px] font-medium text-destructive">This field is required.</p>
                                            @error('email') <p data-server-error class="text-[11px] text-destructive">{{ $message }}</p> @enderror
                                        </div>
                                    </div>

                                    <div class="grid gap-0.5">
                                        <label for="profile-username" class="text-[11px] font-medium text-muted-foreground">Staff ID <span class="text-destructive">*</span></label>
                                        <input id="profile-username" name="username" inputmode="numeric" pattern="[0-9]+" value="{{ old('username', $isInitialAdminSetup ? '' : ($recipient?->staff_id ?? $user->username)) }}" data-required="true" aria-describedby="profile-username-empty-error" class="h-8 w-full rounded-md border border-input bg-muted px-2 text-[12px] font-medium text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                                        <div data-error-slot class="{{ $errors->has('username') ? 'min-h-4' : 'hidden' }}">
                                            <p id="profile-username-empty-error" class="hidden text-[11px] font-medium text-destructive">This field is required.</p>
                                            @error('username') <p data-server-error class="text-[11px] text-destructive">{{ $message }}</p> @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="grid items-start gap-1.5 md:grid-cols-2">
                                    <div class="grid gap-0.5">
                                        <label for="profile-department" class="text-[11px] font-medium text-muted-foreground">College / Office</label>
                                        <details id="profile-department-menu" class="group relative" x-data="{}" x-on:click.outside="$el.removeAttribute('open')">
                                            <summary class="flex h-8 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-muted px-2 py-1 text-[12px] font-medium text-foreground [&::-webkit-details-marker]:hidden">
                                                <span id="profile-department-label" class="truncate">{{ $profileDepartmentValue !== '' ? $profileDepartmentValue : 'Select college or office' }}</span>
                                                <svg class="h-3.5 w-3.5 shrink-0 opacity-60 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                                            </summary>
                                            <div class="absolute top-full z-[100] mt-1 w-max min-w-full max-w-[calc(100vw-2rem)] overflow-visible rounded-md border border-border bg-white p-1 text-foreground shadow-lg">
                                                <button type="button" data-profile-department-option="" class="relative flex w-full items-center rounded-md px-2 py-1.5 text-left text-[12px] hover:bg-muted">Select college or office</button>
                                                @foreach ($profileDepartmentOptions as $departmentOption)
                                                    <button type="button" data-profile-department-option="{{ $departmentOption['name'] }}" class="relative flex w-full items-center rounded-md px-2 py-1.5 text-left text-[12px] hover:bg-muted">{{ $departmentOption['name'] }}</button>
                                                @endforeach
                                            </div>
                                        </details>
                                        <input type="hidden" id="profile-department" name="unit" data-profile-department value="{{ $profileDepartmentValue }}">
                                        <div data-error-slot class="{{ $errors->has('unit') ? 'min-h-4' : 'hidden' }}">@error('unit') <p data-server-error class="text-[11px] text-destructive">{{ $message }}</p> @enderror</div>
                                    </div>

                                    <div class="grid gap-0.5">
                                        <label for="profile-designation" class="text-[11px] font-medium text-muted-foreground">Position</label>
                                        <details id="profile-position-menu" class="group relative" x-data="{}" x-on:click.outside="$el.removeAttribute('open')">
                                            <summary class="flex h-8 w-full cursor-pointer list-none items-center justify-between rounded-md border border-input bg-muted px-2 py-1 text-[12px] font-medium text-foreground [&::-webkit-details-marker]:hidden">
                                                <span id="profile-position-label" class="truncate">{{ $profileDesignationValue !== '' ? $profileDesignationValue : 'Select position' }}</span>
                                                <svg class="h-3.5 w-3.5 shrink-0 opacity-60 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                                            </summary>
                                            <div id="profile-position-options" class="absolute top-full z-[100] mt-1 w-max min-w-full max-w-[calc(100vw-2rem)] overflow-visible rounded-md border border-border bg-white p-1 text-foreground shadow-lg"></div>
                                        </details>
                                        <input type="hidden" id="profile-designation" name="designation" data-profile-position value="{{ $profileDesignationValue }}">
                                        <div data-error-slot class="{{ $errors->has('designation') ? 'min-h-4' : 'hidden' }}">@error('designation') <p data-server-error class="text-[11px] text-destructive">{{ $message }}</p> @enderror</div>
                                    </div>
                                </div>

                                <input type="hidden" name="is_active" value="1" />

                                <div class="flex flex-wrap items-center gap-2 pt-2">
                                    <button type="submit" class="inline-flex h-8 items-center justify-center rounded-full bg-primary px-3 text-[11px] font-semibold text-primary-foreground transition-colors hover:bg-primary/90">Save Changes</button>
                                    <button type="button" id="cancel-profile-edit" @click="document.getElementById('profile-edit-form')?.classList.add('hidden'); document.getElementById('profile-details-view')?.classList.remove('hidden'); document.getElementById('profile-photo-overlay')?.classList.add('hidden'); document.getElementById('toggle-profile-edit')?.classList.remove('hidden')" class="inline-flex h-8 min-w-[90px] items-center justify-center rounded-full border border-border bg-white px-3 text-[11px] font-semibold text-foreground transition-colors hover:bg-muted">Cancel</button>
                                    @if (session('status') === 'profile-updated')
                                        <p class="text-[11px] text-success">Saved.</p>
                                    @endif
                                </div>
                            </form>
                            <script>
                                (() => {
                                    const departmentInput = document.querySelector('[data-profile-department]');
                                    const positionInput = document.querySelector('[data-profile-position]');
                                    const departmentLabel = document.getElementById('profile-department-label');
                                    const positionLabel = document.getElementById('profile-position-label');
                                    const departmentOptions = document.querySelectorAll('[data-profile-department-option]');
                                    const positionOptions = document.getElementById('profile-position-options');
                                    if (!departmentInput || !positionInput || !positionOptions) return;
                                    const departments = @json($profileDepartmentOptions);
                                    const selectedPosition = @js($profileDesignationValue);
                                    const renderPositions = () => {
                                        const department = departments.find((item) => item.name === departmentInput.value);
                                        positionOptions.innerHTML = '<button type="button" data-profile-position-option="" class="relative flex w-full items-center rounded-md px-2 py-1.5 text-left text-[12px] hover:bg-muted">Select position</button>';
                                        (department?.positions || []).forEach((position) => {
                                            positionOptions.insertAdjacentHTML('beforeend', `<button type="button" data-profile-position-option="${position.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;')}" class="relative flex w-full items-center rounded-md px-2 py-1.5 text-left text-[12px] hover:bg-muted">${position}</button>`);
                                        });
                                        positionOptions.querySelectorAll('[data-profile-position-option]').forEach((option) => option.addEventListener('click', () => {
                                            positionInput.value = option.dataset.profilePositionOption;
                                            positionLabel.textContent = positionInput.value || 'Select position';
                                            option.closest('details')?.removeAttribute('open');
                                        }));
                                    };
                                    departmentOptions.forEach((option) => option.addEventListener('click', () => {
                                        departmentInput.value = option.dataset.profileDepartmentOption;
                                        departmentLabel.textContent = departmentInput.value || 'Select college or office';
                                        positionInput.value = '';
                                        positionLabel.textContent = 'Select position';
                                        option.closest('details')?.removeAttribute('open');
                                        renderPositions();
                                    }));
                                    renderPositions();
                                    if (selectedPosition) {
                                        positionInput.value = selectedPosition;
                                        positionLabel.textContent = selectedPosition;
                                    }
                                })();
                            </script>
                        @endif
                    </div>
                </div>

                @if (! $isInitialAdminSetup || $showAdminPasswordPrompt)
                <div class="{{ $isRecipient ? 'mt-6' : '-mt-2' }}">
                    <h3 class="px-1 text-xs font-bold uppercase tracking-wide text-muted-foreground">Security</h3>
                    <section class="surface {{ $isRecipient ? 'mt-2' : 'mt-0.5' }} overflow-hidden p-0 {{ $showAdminPasswordPrompt || $passwordUpdateHasErrors ? 'border-2 border-red-600' : '' }}">
                        <button type="button" id="toggle-recipient-password" class="flex w-full items-center gap-3 {{ $isRecipient ? 'px-4 py-3.5 text-sm' : 'px-4 py-2.5 text-xs' }} text-left font-semibold transition-colors hover:bg-primary-soft">
                            <x-icons.key-round class="h-4 w-4 shrink-0 text-muted-foreground" />
                            Change Password
                        </button>
                        <form id="recipient-password-form" method="POST" action="{{ route('password.update') }}" class="{{ $showAdminPasswordPrompt || $passwordUpdateHasErrors ? 'grid' : 'hidden' }} gap-2 px-3 pb-3 pt-1">
                            @csrf
                            @method('PUT')
                            <div id="recipient-current-password-field" class="grid gap-0.5">
                                <label for="recipient-current-password" class="sr-only">Current password</label>
                                <div class="relative">
                                    <input id="recipient-current-password" data-password-field="current" name="current_password" type="password" autocomplete="current-password" aria-describedby="recipient-current-password-empty-error recipient-current-password-error" placeholder="Enter current password" class="h-8 w-full rounded-lg border border-input bg-muted px-2.5 pr-9 text-[12px] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                                    <button type="button" data-toggle-password="recipient-current-password" aria-label="Show password" class="absolute inset-y-0 right-2.5 grid place-items-center text-muted-foreground"><svg data-eye-icon class="hidden h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0Z"/><circle cx="12" cy="12" r="3"/></svg><svg data-eye-off-icon class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0Z"/><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" stroke-linejoin="round" d="m4 4 16 16"/></svg></button>
                                    <span data-valid-icon="recipient-current-password" class="pointer-events-none absolute inset-y-0 right-2.5 hidden place-items-center text-green-600" aria-hidden="true"><x-icons.check class="h-3.5 w-3.5" stroke-width="3" /></span>
                                </div>
                                <p id="recipient-current-password-empty-error" data-password-error="current-empty" class="{{ $currentPasswordServerErrorIsRequired ? '' : 'hidden' }} text-[11px] font-medium text-destructive">This field is required.</p>
                                <p id="recipient-current-password-error" data-password-error="current-mismatch" class="{{ $currentPasswordServerError && ! $currentPasswordServerErrorIsRequired ? '' : 'hidden' }} text-[11px] font-medium text-destructive">Input does not match current password</p>
                            </div>
                            <div id="recipient-password-field" class="grid gap-0.5">
                                <label for="recipient-password" class="sr-only">New password</label>
                                <div class="relative">
                                    <input id="recipient-password" data-password-field="new" name="password" autocomplete="new-password" type="password" aria-describedby="recipient-password-empty-error recipient-password-mismatch-error" placeholder="Enter new password" class="h-8 w-full rounded-lg border border-input bg-muted px-2.5 pr-9 text-[12px] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                                        <button type="button" data-toggle-password="recipient-password" aria-label="Show password" class="absolute inset-y-0 right-2.5 grid place-items-center text-muted-foreground"><svg data-eye-icon class="hidden h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0Z"/><circle cx="12" cy="12" r="3"/></svg><svg data-eye-off-icon class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0Z"/><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" stroke-linejoin="round" d="m4 4 16 16"/></svg></button>
                                    <span data-valid-icon="recipient-password" class="pointer-events-none absolute inset-y-0 right-2.5 hidden place-items-center text-green-600" aria-hidden="true"><x-icons.check class="h-3.5 w-3.5" stroke-width="3" /></span>
                                </div>
                                <p id="recipient-password-empty-error" data-password-error="new-empty" class="hidden text-[11px] font-medium text-destructive">This field is required.</p>
                            </div>
                            <div id="recipient-password-confirmation-field" class="grid gap-0.5">
                                <label for="recipient-password-confirmation" class="sr-only">Confirm new password</label>
                                <div class="relative">
                                    <input id="recipient-password-confirmation" data-password-field="confirmation" name="password_confirmation" autocomplete="new-password" type="password" disabled aria-describedby="recipient-password-confirmation-empty-error recipient-password-mismatch-error" placeholder="Re-enter new password" class="h-8 w-full rounded-lg border border-input bg-muted px-2.5 pr-9 text-[12px] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-60" />
                                        <button type="button" data-toggle-password="recipient-password-confirmation" aria-label="Show password" class="absolute inset-y-0 right-2.5 grid place-items-center text-muted-foreground"><svg data-eye-icon class="hidden h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0Z"/><circle cx="12" cy="12" r="3"/></svg><svg data-eye-off-icon class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0Z"/><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" stroke-linejoin="round" d="m4 4 16 16"/></svg></button>
                                    <span data-valid-icon="recipient-password-confirmation" class="pointer-events-none absolute inset-y-0 right-2.5 hidden place-items-center text-green-600" aria-hidden="true"><x-icons.check class="h-3.5 w-3.5" stroke-width="3" /></span>
                                </div>
                                <p id="recipient-password-confirmation-empty-error" data-password-error="confirmation-empty" class="hidden text-[11px] font-medium text-destructive">This field is required.</p>
                                <p id="recipient-password-mismatch-error" data-password-error="confirmation-mismatch" class="hidden text-[11px] font-medium text-destructive">New passwords don't match.</p>
                            </div>
                            <ul data-password-rules-list class="grid gap-1 text-[11px] text-muted-foreground">
                                <li data-rule="length" class="flex items-center gap-2"><span class="hidden shrink-0 text-sm font-bold leading-none text-success">✓</span>At least 8 characters</li>
                                <li data-rule="uppercase" class="flex items-center gap-2"><span class="hidden shrink-0 text-sm font-bold leading-none text-success">✓</span>One uppercase letter</li>
                                <li data-rule="number" class="flex items-center gap-2"><span class="hidden shrink-0 text-sm font-bold leading-none text-success">✓</span>One number or special character</li>
                            </ul>
                            <div class="mt-1 flex flex-wrap gap-2">
                                <button type="submit" class="inline-flex h-8 items-center justify-center rounded-full bg-primary px-3 text-[11px] font-semibold text-primary-foreground">Save Password</button>
                                <button type="button" id="cancel-recipient-password" class="inline-flex h-8 items-center justify-center rounded-full border border-border px-3 text-[11px] font-semibold transition-colors hover:bg-accent hover:text-accent-foreground">Cancel</button>
                            </div>
                        </form>
                    </section>
                </div>
                @endif

                @if ($user->role === 'recipient')
                    <div class="mt-5">
                        <a href="{{ route('logout.get') }}" class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-full border border-destructive px-4 text-sm font-semibold text-destructive transition-colors hover:bg-destructive/5">
                            <x-icons.log-out class="h-4 w-4 shrink-0" />
                            Log Out
                        </a>
                    </div>
                @endif
            </div>
        </div>

        @if ($user->role === 'recipient' || $editable)
            <x-avatar-cropper input="recipient-profile-photo" />
        @endif
        <x-password-form-script form="recipient-password-form" toggle="toggle-recipient-password" cancel="cancel-recipient-password" />
    </div>
</x-app-layout>

<script>
    const toggleRecipientPassword = document.getElementById('toggle-recipient-password');
    const recipientPasswordForm = document.getElementById('recipient-password-form');
    const cancelRecipientPassword = document.getElementById('cancel-recipient-password');
    const toggleProfileEdit = document.getElementById('toggle-profile-edit');
    const cancelProfileEdit = document.getElementById('cancel-profile-edit');
    const profileDetailsView = document.getElementById('profile-details-view');
    const profileEditForm = document.getElementById('profile-edit-form');
    const profileDetailsCard = document.getElementById('profile-details-card');
    const profilePhotoOverlay = document.getElementById('profile-photo-overlay');
    const extensionInput = document.getElementById('profile-extension-input');
    const extensionDisplay = document.getElementById('profile-extension-display');
    const sanitizeNameInput = (event) => {
        const allowedPattern = /[^A-Za-zÑñÁÉÍÓÚáéíóúüÇç\- ]/g;
        event.target.value = event.target.value.replace(allowedPattern, '');
    };
    const setProfileEditState = (isEditing) => {
        if (toggleProfileEdit) toggleProfileEdit.style.display = isEditing ? 'none' : '';
        profileDetailsView?.classList.toggle('hidden', isEditing);
        profileEditForm?.classList.toggle('hidden', !isEditing);
        profilePhotoOverlay?.classList.toggle('hidden', !isEditing);
    };
    const profileRequiredFields = [
        { input: document.getElementById('profile-first-name'), error: document.getElementById('profile-first-name-empty-error') },
        { input: document.getElementById('profile-last-name'), error: document.getElementById('profile-last-name-empty-error') },
        { input: document.getElementById('profile-email'), error: document.getElementById('profile-email-empty-error') },
        { input: document.getElementById('profile-username'), error: document.getElementById('profile-username-empty-error') },
    ];
    const setProfileRequiredFieldState = (input, error, isEmpty) => {
        input?.classList.toggle('border-destructive', isEmpty);
        input?.classList.toggle('!border-destructive', isEmpty);
        error?.classList.toggle('hidden', !isEmpty);
        const errorSlot = error?.closest('[data-error-slot]');
        const hasServerError = errorSlot?.querySelector('[data-server-error]');
        errorSlot?.classList.toggle('hidden', !isEmpty && !hasServerError);
        errorSlot?.classList.toggle('min-h-4', isEmpty || !!hasServerError);
    };
    profileRequiredFields.forEach(({ input, error }) => {
        input?.addEventListener('input', () => setProfileRequiredFieldState(input, error, !input.value.trim()));
    });
    ['profile-first-name', 'profile-middle-name', 'profile-last-name'].forEach((id) => {
        const field = document.getElementById(id);
        field?.addEventListener('input', sanitizeNameInput);
        field?.addEventListener('paste', (event) => {
            event.preventDefault();
            const value = (event.clipboardData || window.clipboardData).getData('text');
            const sanitized = value.replace(/[^A-Za-zÑñÁÉÍÓÚáéíóúüÇç\- ]/g, '');
            document.execCommand('insertText', false, sanitized);
        });
    });
    toggleProfileEdit?.addEventListener('click', () => setProfileEditState(true));
    cancelProfileEdit?.addEventListener('click', () => {
        if (profileEditForm) profileEditForm.reset();
        profileRequiredFields.forEach(({ input, error }) => setProfileRequiredFieldState(input, error, false));
        setProfileEditState(false);
    });
    profileEditForm?.addEventListener('submit', (event) => {
        let hasEmptyRequiredField = false;
        profileRequiredFields.forEach(({ input, error }) => {
            const isEmpty = !input?.value || !input.value.trim();
            setProfileRequiredFieldState(input, error, isEmpty);
            if (isEmpty) hasEmptyRequiredField = true;
        });

        if (hasEmptyRequiredField) {
            event.preventDefault();
        }
    });
    document.querySelectorAll('[data-extension-value]').forEach((button) => {
        button.addEventListener('click', () => {
            const value = button.dataset.extensionValue ?? '';
            const label = button.dataset.extensionLabel ?? 'None';
            if (extensionInput) extensionInput.value = value;
            if (extensionDisplay) extensionDisplay.textContent = label;
            const details = button.closest('details');
            if (details) details.removeAttribute('open');
        });
    });
</script>
