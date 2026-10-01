<x-app-layout :role="'student'" title="My Profile">
    @php
        $passwordUpdateHasErrors = $errors->getBag('updatePassword')->any();
        $firstName = trim((string) ($user->first_name ?? ''));
        $middleName = trim((string) ($user->middle_name ?? ''));
        $lastName = trim((string) ($user->last_name ?? ''));

        if ($firstName === '' && $lastName === '') {
            $nameParts = preg_split('/\s+/', trim($user->name)) ?: [];
            $lastName = array_pop($nameParts) ?? '';
            $middleName = count($nameParts) > 1 ? array_pop($nameParts) : '';
            $firstName = implode(' ', $nameParts);
        }

        $displayName = trim(implode(' ', array_filter([
            $firstName,
            $middleName !== '' ? strtoupper(substr($middleName, 0, 1)) . '.' : '',
            $lastName,
        ])));
        $department = strtoupper(trim($student?->department ?? ''));
        $department = str_contains($department, 'INFORMATION') || $department === 'CICT'
            ? 'CICT'
            : (str_contains($department, 'BUSINESS') || $department === 'CBME' ? 'CBME' : ($department ?: 'Student'));
        $course = trim($student?->course ?? '');
        $courseAcronym = preg_match('/^[A-Za-z]+$/', $course)
            ? strtoupper($course)
            : collect(preg_split('/\s+/', $course) ?: [])
                ->reject(fn ($word) => in_array(strtolower($word), ['of', 'in', 'and', 'the']))
                ->map(fn ($word) => preg_replace('/[^A-Za-z]/', '', $word))
                ->filter()
                ->map(fn ($word) => strtoupper(substr($word, 0, 1)))
                ->join('');
        $year = preg_replace('/\D/', '', (string) ($student?->year_level ?? ''));
        $block = preg_replace('/\D/', '', (string) ($student?->block ?? ''));
        $studentId = trim((string) ($student?->student_id ?? ''));
        $studentSubtitle = trim('ID ' . ($studentId ?: 'N/A') . ' · ' . $department . ' · ' . ($courseAcronym ?: 'Student') . ' ' . $year . ($block !== '' ? "-{$block}" : ''));
    @endphp
    <div class="w-full font-sans">
        <section class="brand-gradient -mx-4 -mt-5 rounded-b-3xl px-6 pt-5 pb-7 text-center text-primary-foreground sm:-mx-6 sm:-mt-6 sm:py-9 lg:-mx-8 lg:-mt-8">
            <div class="relative mx-auto h-24 w-24 sm:h-28 sm:w-28">
                <span id="student-profile-avatar" class="grid h-full w-full place-items-center overflow-hidden rounded-full bg-primary-foreground/15 text-4xl font-bold ring-2 ring-primary-foreground/30 sm:h-28 sm:w-28">
                @if ($user->avatar_path)
                    <img src="{{ asset('storage/' . $user->avatar_path) }}" alt="{{ $user->name }}" class="h-full w-full object-cover">
                @else
                    {{ $user->name_initials ?: 'A' }}
                @endif
                </span>
                <form method="POST" action="{{ route('profile.photo') }}" enctype="multipart/form-data">
                    @csrf
                    <label class="absolute right-0 bottom-0 grid h-8 w-8 cursor-pointer place-items-center rounded-full border-2 border-white bg-[#5a101c] text-white shadow-md transition-colors hover:bg-[#7a1d2a]" aria-label="Upload profile photo">
                        <x-icons.camera class="h-4 w-4 text-white" />
                        <input id="student-profile-photo" name="avatar" type="file" accept="image/*" class="hidden" onchange="this.form.submit()" />
                    </label>
                </form>
            </div>
            <h2 class="mt-4 truncate text-xl font-bold sm:text-2xl">{{ $displayName }}</h2>
            <p class="mt-1 truncate text-sm font-normal opacity-95">{{ $studentSubtitle }}</p>
            <span class="mt-3 inline-flex rounded-full border border-primary bg-primary-foreground/15 px-4 py-1 text-xs font-normal">Student</span>
        </section>

        <h3 class="mt-6 px-1 text-xs font-bold uppercase tracking-wide text-muted-foreground">Account Information</h3>
        <div class="surface mt-2 overflow-hidden p-0">
            @foreach([
                ['Full Name', $user->name],
                ['Email', $user->email],
                ['Account Type', 'Student'],
                ['Status', $user->is_active ? 'Active' : 'Inactive'],
            ] as [$label, $value])
                <div class="grid grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] gap-4 border-b border-border px-4 py-3 last:border-b-0 sm:px-5">
                    <dt class="text-sm text-muted-foreground">{{ $label }}</dt>
                    <dd class="flex min-w-0 items-center justify-end gap-1.5 wrap-break-word text-right text-sm font-medium">
                        <span class="{{ $label === 'Status' ? ($value === 'Active' ? 'text-success' : 'text-destructive') : '' }}">{{ $value }}</span>
                        @if ($label === 'Email' && $user->hasVerifiedEmail())
                            <span class="grid h-4 w-4 shrink-0 place-items-center text-success" title="Verified" aria-label="Verified email">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.74 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.74Z"/><path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4"/></svg>
                            </span>
                        @endif
                    </dd>
                </div>
            @endforeach
        </div>

        <h3 class="mt-6 px-1 text-xs font-bold uppercase tracking-wide text-muted-foreground">Security</h3>
        <section class="surface mt-2 overflow-hidden p-0">
            <button type="button" id="toggle-student-password" class="flex w-full items-center gap-3 px-4 py-3.5 text-left text-sm font-semibold transition-colors hover:bg-primary-soft">
                <x-icons.key-round class="h-4 w-4 shrink-0 text-muted-foreground" />
                Change Password
            </button>
            <form id="student-password-form" method="POST" action="{{ route('password.update') }}" class="{{ $passwordUpdateHasErrors ? 'grid' : 'hidden' }} gap-4 p-4">
                @csrf
                @method('PUT')
                <div id="current-password-field" class="grid gap-1.5">
                    <label for="current_password" class="text-sm font-semibold">Old Password</label>
                    <div class="relative">
                        <input id="current_password" name="current_password" type="password" aria-describedby="current-password-empty-error" placeholder="Enter current password" class="h-11 w-full rounded-xl border border-input bg-muted px-3 pr-11 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                        <button type="button" data-toggle-password="current_password" aria-label="Show password" class="absolute inset-y-0 right-3 grid place-items-center text-muted-foreground">
                               <svg data-eye-icon class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0Z"/><circle cx="12" cy="12" r="3"/></svg>
                               <svg data-eye-off-icon class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0Z"/><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" stroke-linejoin="round" d="m4 4 16 16"/></svg>
                        </button>
                    </div>
                    <p id="current-password-empty-error" class="hidden text-xs font-medium text-destructive">This field is required.</p>
                    <p id="current-password-error" class="hidden text-xs font-medium text-destructive">Incorrect password. Try again.</p>
                    @error('current_password', 'updatePassword')
                        <p class="text-xs font-medium text-destructive">{{ $message }}</p>
                    @enderror
                </div>
                <div id="profile-password-field" class="grid gap-1.5">
                    <label for="profile-password" class="text-sm font-semibold">New Password</label>
                    <div class="relative">
                            <input id="profile-password" data-password-rules name="password" type="password" aria-describedby="profile-password-empty-error profile-password-mismatch-error" placeholder="Enter new password" class="h-11 w-full rounded-xl border border-input bg-muted px-3 pr-11 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                            <button type="button" data-toggle-password="profile-password" aria-label="Show password" class="absolute inset-y-0 right-3 grid place-items-center text-muted-foreground">
                                <svg data-eye-icon class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0Z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg data-eye-off-icon class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0Z"/><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" stroke-linejoin="round" d="m4 4 16 16"/></svg>
                            </button>
                    </div>
                    <p id="profile-password-empty-error" class="hidden text-xs font-medium text-destructive">This field is required.</p>
                </div>
                <div id="password-confirmation-field" class="grid gap-1.5">
                    <label for="password_confirmation" class="text-sm font-semibold">Confirm Password</label>
                    <div class="relative">
                        <input id="password_confirmation" name="password_confirmation" type="password" disabled aria-describedby="profile-password-confirmation-empty-error profile-password-mismatch-error" placeholder="Re-enter new password" class="h-11 w-full rounded-xl border border-input bg-muted px-3 pr-11 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-60" />
                        <button type="button" data-toggle-password="password_confirmation" aria-label="Show password" class="absolute inset-y-0 right-3 grid place-items-center text-muted-foreground">
                            <svg data-eye-icon class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0Z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg data-eye-off-icon class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0Z"/><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" stroke-linejoin="round" d="m4 4 16 16"/></svg>
                        </button>
                    </div>
                    <p id="profile-password-confirmation-empty-error" class="hidden text-xs font-medium text-destructive">This field is required.</p>
                    <p id="profile-password-mismatch-error" class="hidden text-xs font-medium text-destructive">New passwords don't match.</p>
                </div>
                <ul data-password-rules-list class="grid gap-1 text-xs text-muted-foreground">
                    <li data-rule="length" class="flex items-center gap-2"><span class="hidden shrink-0 text-sm font-bold leading-none text-success">✓</span>At least 8 characters</li>
                    <li data-rule="uppercase" class="flex items-center gap-2"><span class="hidden shrink-0 text-sm font-bold leading-none text-success">✓</span>One uppercase letter</li>
                    <li data-rule="number" class="flex items-center gap-2"><span class="hidden shrink-0 text-sm font-bold leading-none text-success">✓</span>One number or special character</li>
                </ul>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-full bg-primary px-4 text-sm font-semibold text-primary-foreground">Save Password</button>
                    <button type="button" id="cancel-student-password" class="inline-flex h-10 items-center justify-center rounded-full border border-border px-4 text-sm font-semibold transition-colors hover:bg-accent hover:text-accent-foreground">Cancel</button>
                </div>
            </form>
        </section>

        <div class="mt-5">
            <a href="{{ route('logout.get') }}" class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-full border border-destructive px-4 text-sm font-semibold text-destructive transition-colors hover:bg-destructive/5">
                <x-icons.log-out class="h-4 w-4 shrink-0" />
                Log Out
            </a>
        </div>
    </div>
</x-app-layout>

<script>
    const toggleStudentPassword = document.getElementById('toggle-student-password');
    const studentPasswordForm = document.getElementById('student-password-form');
    const cancelStudentPassword = document.getElementById('cancel-student-password');
    toggleStudentPassword?.addEventListener('click', () => {
        if (!studentPasswordForm?.classList.contains('hidden')) {
            studentPasswordForm.reset();
            if (profileConfirmation) profileConfirmation.disabled = true;
            setProfileMismatchState(false);
            setProfileEmptyState(currentPassword, currentPasswordEmptyError, false);
            setProfileEmptyState(profilePassword, profilePasswordEmptyError, false);
            setProfileEmptyState(profileConfirmation, profileConfirmationEmptyError, false);
            renderRules({ length: false, uppercase: false, number: false });
        }
        studentPasswordForm?.classList.toggle('hidden');
        studentPasswordForm?.classList.toggle('grid');
    });
    cancelStudentPassword?.addEventListener('click', () => {
        studentPasswordForm?.reset();
        if (profileConfirmation) profileConfirmation.disabled = true;
        setProfileMismatchState(false);
        setProfileEmptyState(currentPassword, currentPasswordEmptyError, false);
        setProfileEmptyState(profilePassword, profilePasswordEmptyError, false);
        setProfileEmptyState(profileConfirmation, profileConfirmationEmptyError, false);
        renderRules({ length: false, uppercase: false, number: false });
        studentPasswordForm?.classList.add('hidden');
        studentPasswordForm?.classList.remove('grid');
    });

    document.querySelectorAll('[data-toggle-password]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.togglePassword);
            const eye = button.querySelector('[data-eye-icon]');
            const eyeOff = button.querySelector('[data-eye-off-icon]');
            if (!input || !eye || !eyeOff) return;
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            eye.classList.toggle('hidden', !isHidden);
            eyeOff.classList.toggle('hidden', isHidden);
        });
    });

    const profilePassword = document.querySelector('[data-password-rules]');
    const currentPassword = document.getElementById('current_password');
    const profileConfirmation = document.getElementById('password_confirmation');
    const profileRules = document.querySelector('[data-password-rules-list]');
    const profilePasswordForm = document.getElementById('student-password-form');
    const currentPasswordEmptyError = document.getElementById('current-password-empty-error');
    const currentPasswordError = document.getElementById('current-password-error');
    const profilePasswordEmptyError = document.getElementById('profile-password-empty-error');
    const profileConfirmationEmptyError = document.getElementById('profile-password-confirmation-empty-error');
    const profilePasswordMismatchError = document.getElementById('profile-password-mismatch-error');
    const checksFor = (value) => ({ length: value.length >= 8, uppercase: /[A-Z]/.test(value), number: /[\d\W_]/.test(value) });
    const renderRules = (checks) => Object.entries(checks).forEach(([name, passed]) => {
        const rule = profileRules?.querySelector(`[data-rule="${name}"]`);
        if (!rule) return;
        rule.classList.toggle('text-primary', passed);
        rule.classList.toggle('text-muted-foreground', !passed);
        const marker = rule.querySelector('span');
        marker?.classList.toggle('text-success', passed);
        marker?.classList.toggle('hidden', !passed);
        if (marker) marker.textContent = passed ? '✓' : '';
    });
    const setProfileMismatchState = (hasMismatch) => {
        profileConfirmation?.classList.toggle('border-destructive', hasMismatch);
        profileConfirmation?.classList.toggle('!border-destructive', hasMismatch);
        profileConfirmation?.classList.toggle('password-error-border', hasMismatch);
        profilePasswordMismatchError?.classList.toggle('hidden', !hasMismatch);
    };
    const setProfileEmptyState = (input, error, isEmpty) => {
        input?.classList.toggle('border-destructive', isEmpty);
        input?.classList.toggle('!border-destructive', isEmpty);
        input?.classList.toggle('password-error-border', isEmpty);
        error?.classList.toggle('hidden', !isEmpty);
    };
    const updateProfilePasswordMatchState = () => {
        const hasMismatch = Boolean(profilePassword?.value && profileConfirmation?.value && profilePassword.value !== profileConfirmation.value);
        setProfileMismatchState(hasMismatch);
    };
    profilePassword?.addEventListener('input', () => {
        const checks = checksFor(profilePassword.value);
        renderRules(checks);
        if (profileConfirmation) profileConfirmation.disabled = !Object.values(checks).every(Boolean);
        setProfileEmptyState(profilePassword, profilePasswordEmptyError, profilePassword.value.length === 0);
        updateProfilePasswordMatchState();
    });
    currentPassword?.addEventListener('input', () => {
        setProfileEmptyState(currentPassword, currentPasswordEmptyError, currentPassword.value.length === 0);
    });
    profileConfirmation?.addEventListener('input', () => {
        setProfileEmptyState(profileConfirmation, profileConfirmationEmptyError, profileConfirmation.value.length === 0);
        updateProfilePasswordMatchState();
    });
    profilePasswordForm?.addEventListener('submit', (event) => {
        if (profilePasswordForm.dataset.passwordVerified === 'true') {
            delete profilePasswordForm.dataset.passwordVerified;
            return;
        }

        const currentPasswordIsEmpty = !currentPassword?.value;
        const passwordIsEmpty = !profilePassword?.value;
        const confirmationIsEmpty = !profileConfirmation?.value;
        setProfileMismatchState(false);
        setProfileEmptyState(currentPassword, currentPasswordEmptyError, currentPasswordIsEmpty);
        setProfileEmptyState(profilePassword, profilePasswordEmptyError, passwordIsEmpty);
        setProfileEmptyState(profileConfirmation, profileConfirmationEmptyError, confirmationIsEmpty);
        if (currentPasswordIsEmpty || passwordIsEmpty || confirmationIsEmpty) {
            event.preventDefault();
            return;
        }
        if (profilePassword.value !== profileConfirmation.value) {
            event.preventDefault();
            setProfileMismatchState(true);
            return;
        }

        event.preventDefault();
        fetch('{{ route('password.check-current') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'Accept': 'application/json',
            },
            body: new URLSearchParams({ current_password: currentPassword.value }),
        }).then(async (response) => {
            if (! response.ok) {
                currentPasswordError?.classList.remove('hidden');
                setProfileEmptyState(currentPassword, currentPasswordEmptyError, false);
                currentPassword?.classList.add('border-destructive', '!border-destructive', 'password-error-border');
                return;
            }

            profilePasswordForm.dataset.passwordVerified = 'true';
            profilePasswordForm.submit();
        });

    });
</script>
