(() => {
  'use strict';

  let activeGate = null;

  if (!document.querySelector('link[data-password-gate-styles]')) {
    const styles = document.createElement('link');
    styles.rel = 'stylesheet';
    styles.href = 'css/password-gate.css?v=20260925-1';
    styles.dataset.passwordGateStyles = '';
    document.head.append(styles);
  }

  const setBusy = (button, busy, label = 'Saving…') => {
    if (!button) return;
    button.disabled = busy;
    if (!button.dataset.originalText) button.dataset.originalText = button.textContent;
    button.textContent = busy ? label : button.dataset.originalText;
  };

  const open = (user, csrfToken) => {
    if (!user?.must_change_password) return Promise.resolve();
    if (activeGate) return activeGate;

    document.body.insertAdjacentHTML('beforeend', `
      <dialog class="required-password-dialog" data-required-password-gate data-modal-size="medium" data-modal-kind="form" data-focus-self tabindex="-1" aria-labelledby="required-password-title">
        <form class="required-password-form" data-required-password-form>
          <header class="required-password-heading">
            <p class="required-password-eyebrow">First sign-in</p>
            <h2 id="required-password-title">Create your password</h2>
            <p class="required-password-identity">Signed in as <strong data-password-gate-name></strong> <span aria-hidden="true">·</span> <span data-password-gate-role></span></p>
          </header>
          <div class="required-password-fields">
            <label class="required-password-field">
              <span class="sr-only">New password</span>
              <input name="password" type="password" minlength="8" required autocomplete="new-password" placeholder="New password">
              <button type="button" data-password-eye aria-label="Show new password" aria-pressed="false"><svg class="h-5 w-5 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg></button>
            </label>
            <label class="required-password-field">
              <span class="sr-only">Confirm password</span>
              <input name="password_confirmation" type="password" minlength="8" required autocomplete="new-password" placeholder="Confirm password">
              <button type="button" data-password-eye aria-label="Show password confirmation" aria-pressed="false"><svg class="h-5 w-5 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg></button>
            </label>
          </div>
          <div class="required-password-checks" aria-live="polite">
            <span class="required-password-check" data-password-length-check data-valid="false"><span class="required-password-check-icon" aria-hidden="true"></span>8+ characters</span>
            <span class="required-password-check" data-password-match-check data-valid="false"><span class="required-password-check-icon" aria-hidden="true"></span>Passwords match</span>
          </div>
          <p class="required-password-error hidden" data-password-gate-error role="alert"></p>
          <button class="required-password-submit" type="submit">Save password</button>
          <button class="required-password-logout" type="button" data-password-gate-logout>Sign out</button>
        </form>
      </dialog>`);

    const dialog = document.querySelector('[data-required-password-gate]');
    const form = dialog.querySelector('[data-required-password-form]');
    const error = dialog.querySelector('[data-password-gate-error]');
    const fullName = user.full_name || [user.first_name, user.middle_name, user.last_name].filter(Boolean).join(' ') || user.username || 'User';
    dialog.querySelector('[data-password-gate-name]').textContent = fullName;
    dialog.querySelector('[data-password-gate-role]').textContent = user.role || 'Account';

    const showError = message => {
      error.textContent = message;
      error.classList.remove('hidden');
    };
    const updateRequirements = () => {
      const password = form.elements.password.value;
      const confirmation = form.elements.password_confirmation.value;
      const longEnough = password.length >= 8;
      const matches = confirmation !== '' && password === confirmation;
      dialog.querySelector('[data-password-length-check]').dataset.valid = String(longEnough);
      dialog.querySelector('[data-password-match-check]').dataset.valid = String(matches);
      form.elements.password_confirmation.setCustomValidity(confirmation && !matches ? 'Passwords do not match.' : '');
      error.classList.add('hidden');
    };

    dialog.addEventListener('cancel', event => event.preventDefault());
    dialog.querySelectorAll('[data-password-eye]').forEach(button => button.addEventListener('click', () => {
      const input = button.closest('.required-password-field').querySelector('input');
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      button.setAttribute('aria-pressed', String(show));
      button.setAttribute('aria-label', `${show ? 'Hide' : 'Show'} ${input.name === 'password_confirmation' ? 'password confirmation' : 'new password'}`);
    }));
    form.elements.password.addEventListener('input', updateRequirements);
    form.elements.password_confirmation.addEventListener('input', updateRequirements);

    form.addEventListener('submit', async event => {
      event.preventDefault();
      error.classList.add('hidden');
      updateRequirements();
      if (!form.reportValidity()) return;
      const submit = event.submitter || form.querySelector('[type="submit"]');
      setBusy(submit, true);
      try {
        const response = await axios.post('api/auth.php?action=change_password', Object.fromEntries(new FormData(form)), {
          headers: {'X-CSRF-Token': csrfToken},
        });
        location.replace(response.data.redirect_url || './?login=password-changed');
      } catch (requestError) {
        showError(requestError.response?.data?.message || 'Unable to change your password. Please try again.');
        setBusy(submit, false);
      }
    });

    dialog.querySelector('[data-password-gate-logout]').addEventListener('click', async event => {
      setBusy(event.currentTarget, true, 'Signing out…');
      try {
        await axios.post('api/auth.php?action=logout', {}, {headers: {'X-CSRF-Token': csrfToken}});
      } finally {
        location.replace('./');
      }
    });

    dialog.showModal();
    requestAnimationFrame(() => dialog.focus({preventScroll: true}));
    activeGate = new Promise(() => {});
    return activeGate;
  };

  window.RequiredPasswordGate = {open};
})();
