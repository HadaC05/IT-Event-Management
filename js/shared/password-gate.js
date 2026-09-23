(() => {
  'use strict';

  let activeGate = null;

  const setBusy = (button, busy) => {
    if (!button) return;
    button.disabled = busy;
    if (!button.dataset.originalText) button.dataset.originalText = button.textContent;
    button.textContent = busy ? 'Saving…' : button.dataset.originalText;
  };

  const open = (user, csrfToken) => {
    if (!user?.must_change_password) return Promise.resolve();
    if (activeGate) return activeGate;

    document.body.insertAdjacentHTML('beforeend', `
      <dialog class="m-auto w-[min(540px,calc(100%_-_1rem))] rounded-2xl border-0 bg-white p-0 text-[#121017] shadow-2xl backdrop:bg-[#121017]/70 backdrop:backdrop-blur-[2px]" style="max-height:calc(100dvh - 1rem);overflow-y:auto" data-required-password-gate data-modal-size="medium" data-modal-kind="form" aria-labelledby="required-password-title" aria-describedby="required-password-description">
        <form data-required-password-form>
          <header class="border-b border-[#121017]/8 border-t-4 border-t-[#397565] px-6 py-5">
            <p class="text-[10px] font-black uppercase tracking-[.15em] text-[#397565]">Step 2 of 2 · First sign-in</p>
            <h2 class="mt-1 text-2xl font-black tracking-[-.03em]" id="required-password-title">Create your own password</h2>
            <p class="mt-2 text-sm leading-6 text-[#121017]/55" id="required-password-description">You signed in with a temporary password. Enter a new private password twice. Then sign in again with your same ID or username and the new password.</p>
          </header>
          <div class="grid gap-4 p-6">
            <div class="rounded-xl bg-[#397565]/7 px-4 py-3">
              <span class="block text-[10px] font-black uppercase tracking-wider text-[#397565]">Signed in as</span>
              <strong class="mt-1 block text-sm" data-password-gate-name></strong>
              <span class="mt-0.5 block text-xs text-[#121017]/50" data-password-gate-role></span>
            </div>
            <label class="grid gap-2"><span class="text-sm font-bold">New password</span><span class="relative"><input class="h-12 w-full rounded-xl border border-[#121017]/12 px-3 pr-12 text-sm outline-none focus:border-[#397565] focus:ring-4 focus:ring-[#397565]/10" name="password" type="password" minlength="8" required autocomplete="new-password"><button class="absolute inset-y-0 right-0 grid w-12 place-items-center text-[#397565]" type="button" data-password-eye aria-label="Show new password" aria-pressed="false"><svg class="h-5 w-5 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg></button></span><small class="text-xs leading-5 text-[#121017]/45">Use at least 8 characters. Do not use your ID plus surname again.</small></label>
            <label class="grid gap-2"><span class="text-sm font-bold">Confirm new password</span><span class="relative"><input class="h-12 w-full rounded-xl border border-[#121017]/12 px-3 pr-12 text-sm outline-none focus:border-[#397565] focus:ring-4 focus:ring-[#397565]/10" name="password_confirmation" type="password" minlength="8" required autocomplete="new-password"><button class="absolute inset-y-0 right-0 grid w-12 place-items-center text-[#397565]" type="button" data-password-eye aria-label="Show password confirmation" aria-pressed="false"><svg class="h-5 w-5 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg></button></span></label>
            <p class="hidden rounded-xl bg-[#FF6B2C]/9 px-4 py-3 text-xs font-bold leading-5 text-[#D64A12]" data-password-gate-error role="alert"></p>
          </div>
          <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-[#121017]/8 bg-white px-6 py-4">
            <button class="min-h-11 px-2 text-sm font-bold text-[#121017]/50" type="button" data-password-gate-logout>Sign out</button>
            <button class="min-h-11 rounded-xl bg-[#397565] px-5 text-sm font-black text-white" type="submit">Save and sign in again</button>
          </footer>
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
    const validateConfirmation = () => {
      const matches = form.elements.password.value === form.elements.password_confirmation.value;
      form.elements.password_confirmation.setCustomValidity(matches ? '' : 'Passwords do not match.');
      return matches;
    };

    dialog.addEventListener('cancel', event => event.preventDefault());
    dialog.querySelectorAll('[data-password-eye]').forEach(button => button.addEventListener('click', () => {
      const input = button.parentElement.querySelector('input');
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      button.setAttribute('aria-pressed', String(show));
      button.setAttribute('aria-label', `${show ? 'Hide' : 'Show'} ${input.name === 'password_confirmation' ? 'password confirmation' : 'new password'}`);
    }));
    form.elements.password.addEventListener('input', validateConfirmation);
    form.elements.password_confirmation.addEventListener('input', validateConfirmation);

    form.addEventListener('submit', async event => {
      event.preventDefault();
      error.classList.add('hidden');
      validateConfirmation();
      if (!form.reportValidity()) return;
      const submit = event.submitter;
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
      setBusy(event.currentTarget, true);
      try {
        await axios.post('api/auth.php?action=logout', {}, {headers: {'X-CSRF-Token': csrfToken}});
      } finally {
        location.replace('./');
      }
    });

    dialog.showModal();
    requestAnimationFrame(() => form.elements.password.focus({preventScroll: true}));
    activeGate = new Promise(() => {});
    return activeGate;
  };

  window.RequiredPasswordGate = {open};
})();
