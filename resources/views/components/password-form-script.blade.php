@props(['form', 'toggle', 'cancel'])

{{--
    Behaviour for a change-password form. Inside the form it expects:
    - inputs marked data-password-field="current|new|confirmation"
    - messages marked data-password-error="current-empty|current-mismatch|new-empty|confirmation-empty|confirmation-mismatch"
    - a [data-password-rules-list] with [data-rule="length|uppercase|number"] items
    - per input: a [data-toggle-password="<input id>"] button and a [data-valid-icon="<input id>"] check mark
--}}
<script>
    (() => {
        const form = document.getElementById(@js($form));
        const toggleButton = document.getElementById(@js($toggle));
        const cancelButton = document.getElementById(@js($cancel));
        const field = (name) => form?.querySelector(`[data-password-field="${name}"]`);
        const message = (name) => form?.querySelector(`[data-password-error="${name}"]`);
        const currentPassword = field('current');
        const newPassword = field('new');
        const confirmation = field('confirmation');
        const rulesList = form?.querySelector('[data-password-rules-list]');
        const currentEmptyError = message('current-empty');
        const currentMismatchError = message('current-mismatch');
        const newEmptyError = message('new-empty');
        const confirmationEmptyError = message('confirmation-empty');
        const confirmationMismatchError = message('confirmation-mismatch');
        if (!form || !currentPassword || !newPassword || !confirmation) return;

        let currentVerified = false;
        let currentCheckId = 0;
        let currentCheckTimer;

        const checksFor = (value) => ({ length: value.length >= 8, uppercase: /[A-Z]/.test(value), number: /[\d\W_]/.test(value) });
        const passesRules = (value) => Object.values(checksFor(value)).every(Boolean);
        const isShown = (error) => Boolean(error && !error.classList.contains('hidden'));

        const renderRules = (checks) => Object.entries(checks).forEach(([name, passed]) => {
            const rule = rulesList?.querySelector(`[data-rule="${name}"]`);
            if (!rule) return;
            rule.classList.toggle('text-primary', passed);
            rule.classList.toggle('text-muted-foreground', !passed);
            rule.querySelector('span')?.classList.toggle('hidden', !passed);
        });

        const setErrorState = (error, hasError) => error?.classList.toggle('hidden', !hasError);

        const syncErrorBorders = () => {
            currentPassword.classList.toggle('password-error-border', isShown(currentEmptyError) || isShown(currentMismatchError));
            newPassword.classList.toggle('password-error-border', isShown(newEmptyError));
            confirmation.classList.toggle('password-error-border', isShown(confirmationEmptyError) || isShown(confirmationMismatchError));
        };

        // Swap the show/hide toggle for a green check once the field is confirmed valid.
        const setValid = (input, isValid) => {
            const toggle = form.querySelector(`[data-toggle-password="${input.id}"]`);
            const check = form.querySelector(`[data-valid-icon="${input.id}"]`);
            toggle?.classList.toggle('hidden', isValid);
            toggle?.classList.toggle('grid', !isValid);
            check?.classList.toggle('hidden', !isValid);
            check?.classList.toggle('grid', isValid);
        };

        const setVisibility = (input, visible) => {
            const toggle = form.querySelector(`[data-toggle-password="${input.id}"]`);
            input.type = visible ? 'text' : 'password';
            toggle?.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
            toggle?.querySelector('[data-eye-icon]')?.classList.toggle('hidden', !visible);
            toggle?.querySelector('[data-eye-off-icon]')?.classList.toggle('hidden', visible);
        };

        const updateMatchState = () => {
            const password = newPassword.value;
            const confirmed = confirmation.value;
            const matched = passesRules(password) && confirmed !== '' && password === confirmed;
            setErrorState(confirmationMismatchError, password !== '' && confirmed !== '' && password !== confirmed);
            setValid(newPassword, matched);
            setValid(confirmation, matched);
            rulesList?.classList.toggle('hidden', matched);
            syncErrorBorders();
        };

        const verifyCurrentPassword = async () => {
            const checkId = ++currentCheckId;
            let response;

            try {
                response = await fetch('{{ route('password.check-current') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'Accept': 'application/json',
                    },
                    body: new URLSearchParams({ current_password: currentPassword.value }),
                });
            } catch {
                return false;
            }

            // Ignore answers for a value the user has already changed.
            if (checkId !== currentCheckId) return false;

            currentVerified = response.ok;
            setValid(currentPassword, response.ok);
            setErrorState(currentMismatchError, response.status === 422);
            syncErrorBorders();

            return response.ok;
        };

        const resetForm = () => {
            clearTimeout(currentCheckTimer);
            currentCheckId++;
            currentVerified = false;
            form.reset();
            confirmation.disabled = true;
            [currentPassword, newPassword, confirmation].forEach((input) => {
                setVisibility(input, false);
                setValid(input, false);
            });
            [currentEmptyError, currentMismatchError, newEmptyError, confirmationEmptyError, confirmationMismatchError].forEach((error) => setErrorState(error, false));
            syncErrorBorders();
            renderRules(checksFor(''));
            rulesList?.classList.remove('hidden');
        };

        const closeForm = () => {
            resetForm();
            form.classList.add('hidden');
            form.classList.remove('grid');
        };

        toggleButton?.addEventListener('click', () => {
            if (!form.classList.contains('hidden')) {
                closeForm();
                return;
            }

            resetForm();
            form.classList.remove('hidden');
            form.classList.add('grid');
        });

        cancelButton?.addEventListener('click', closeForm);

        form.querySelectorAll('[data-toggle-password]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.togglePassword);
                if (input) setVisibility(input, input.type === 'password');
            });
        });

        currentPassword.addEventListener('input', () => {
            clearTimeout(currentCheckTimer);
            currentCheckId++;
            currentVerified = false;
            setValid(currentPassword, false);
            setErrorState(currentMismatchError, false);
            setErrorState(currentEmptyError, currentPassword.value === '');
            syncErrorBorders();
            if (currentPassword.value !== '') currentCheckTimer = setTimeout(verifyCurrentPassword, 400);
        });

        newPassword.addEventListener('input', () => {
            const checks = checksFor(newPassword.value);
            renderRules(checks);
            confirmation.disabled = !Object.values(checks).every(Boolean);
            setErrorState(newEmptyError, newPassword.value === '');
            updateMatchState();
        });

        confirmation.addEventListener('input', () => {
            setErrorState(confirmationEmptyError, confirmation.value === '');
            updateMatchState();
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const currentIsEmpty = currentPassword.value === '';
            const newIsEmpty = newPassword.value === '';
            const confirmationIsEmpty = confirmation.value === '';
            setErrorState(currentEmptyError, currentIsEmpty);
            setErrorState(newEmptyError, newIsEmpty);
            setErrorState(confirmationEmptyError, confirmationIsEmpty);
            updateMatchState();
            if (currentIsEmpty || newIsEmpty || confirmationIsEmpty) return;
            if (!passesRules(newPassword.value) || newPassword.value !== confirmation.value) return;

            clearTimeout(currentCheckTimer);
            if (currentVerified || await verifyCurrentPassword()) form.submit();
        });

        syncErrorBorders();
    })();
</script>
