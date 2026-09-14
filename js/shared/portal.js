(() => {
    'use strict';

    let csrfToken = '';
    const passwordDialog = document.querySelector('[data-required-password-dialog]');
    const passwordForm = document.querySelector('[data-required-password-form]');
    const passwordError = document.querySelector('[data-password-error]');

    const logout = async button => {
        button.disabled = true;
        const originalText = button.textContent;
        button.textContent = 'Signing out…';
        try {
            await axios.post('api/auth.php?action=logout', {}, { headers: { 'X-CSRF-Token': csrfToken } });
        } finally {
            button.textContent = originalText;
            window.location.replace('./');
        }
    };

    axios.get('api/auth.php?action=session')
        .then(response => {
            if (!response.data.authenticated || !response.data.user) {
                window.location.replace('./');
                return;
            }

            csrfToken = response.data.csrf_token;
            document.querySelector('[data-name]').textContent = response.data.user.first_name || response.data.user.username;
            document.querySelector('[data-role]').textContent = response.data.user.role || 'Account';
            if (response.data.user.role === 'SBO Officer' && response.data.user.must_change_password) {
                passwordDialog.showModal();
                passwordForm.elements.password.focus();
            }
        })
        .catch(() => window.location.replace('./'));

    document.querySelector('[data-logout]').addEventListener('click', event => logout(event.currentTarget));
    document.querySelector('[data-force-logout]').addEventListener('click', event => logout(event.currentTarget));

    passwordDialog.addEventListener('cancel', event => event.preventDefault());
    passwordDialog.querySelectorAll('[data-password-toggle]').forEach(toggle => toggle.addEventListener('click', () => {
        const input = toggle.parentElement.querySelector('input');
        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        const icon = toggle.querySelector('svg');
        icon.querySelector('[data-eye-slash]')?.remove();
        if (!showing) {
            const slash = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            slash.setAttribute('d', 'M4 4 20 20');
            slash.dataset.eyeSlash = '';
            icon.append(slash);
        }
        toggle.setAttribute('aria-pressed', String(!showing));
        toggle.setAttribute('aria-label', `${showing ? 'Show' : 'Hide'} ${input.name === 'password_confirmation' ? 'password confirmation' : 'new password'}`);
    }));

    const validateConfirmation = () => {
        const matches = passwordForm.elements.password.value === passwordForm.elements.password_confirmation.value;
        passwordForm.elements.password_confirmation.setCustomValidity(matches ? '' : 'Passwords do not match.');
    };
    passwordForm.elements.password.addEventListener('input', validateConfirmation);
    passwordForm.elements.password_confirmation.addEventListener('input', validateConfirmation);

    passwordForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (!passwordForm.reportValidity()) return;
        const submit = event.submitter;
        submit.disabled = true;
        submit.textContent = 'Saving…';
        passwordError.classList.add('hidden');
        try {
            const data = Object.fromEntries(new FormData(passwordForm));
            const response = await axios.post('api/auth.php?action=change_password', data, {
                headers: { 'X-CSRF-Token': csrfToken },
            });
            passwordDialog.close();
            document.querySelector('main p.text-base').textContent = response.data.message;
        } catch (error) {
            passwordError.textContent = error.response?.data?.message || 'Unable to change your password.';
            passwordError.classList.remove('hidden');
        } finally {
            submit.disabled = false;
            submit.textContent = 'Save New Password';
        }
    });
})();
