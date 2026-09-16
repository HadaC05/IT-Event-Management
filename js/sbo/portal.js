(() => {
  'use strict';
  let csrfToken = '';
  const esc = value => window.SharedNavigation?.escapeHtml(value) ?? String(value ?? '');
  const icon = path => `<svg class="h-5 w-5 shrink-0 fill-none stroke-current stroke-2" viewBox="0 0 24 24">${path}</svg>`;
  const passwordGate = user => {
    if (!user.must_change_password) return;
    document.body.insertAdjacentHTML('beforeend',`<dialog class="m-auto w-[min(560px,calc(100%_-_2rem))] rounded-2xl border-0 bg-white p-0 shadow-2xl backdrop:bg-[#121017]/70" data-required-password><form class="p-6" data-required-password-form><p class="text-xs font-black uppercase tracking-wider text-[#397565]">Required security step</p><h2 class="mt-1 text-2xl font-black">Create your SBO password</h2><p class="mt-2 text-sm leading-6 text-[#121017]/55">Replace the temporary password. After saving, sign in again with your new password.</p><div class="mt-6 grid gap-4">${['password','password_confirmation'].map((name,index)=>`<label class="grid gap-2"><span class="text-sm font-bold">${index?'Confirm new password':'New SBO password'}</span><span class="relative"><input class="h-12 w-full rounded-xl border border-[#121017]/12 px-3 pr-12 outline-none focus:border-[#397565]" name="${name}" type="password" minlength="8" required autocomplete="new-password"><button class="absolute inset-y-0 right-0 grid w-12 place-items-center text-[#397565]" type="button" data-password-eye aria-label="Show password">${icon('<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/>')}</button></span></label>`).join('')}</div><button class="mt-6 min-h-12 w-full rounded-xl bg-[#397565] px-5 font-black text-white" type="submit">Save password and sign in again</button></form></dialog>`);
    const dialog=document.querySelector('[data-required-password]'); dialog.showModal();
    dialog.querySelectorAll('[data-password-eye]').forEach(button=>button.addEventListener('click',()=>{const input=button.parentElement.querySelector('input');input.type=input.type==='password'?'text':'password';button.setAttribute('aria-label',input.type==='password'?'Show password':'Hide password');}));
    dialog.querySelector('form').addEventListener('submit',async event=>{event.preventDefault();const data=Object.fromEntries(new FormData(event.currentTarget));if(data.password!==data.password_confirmation){window.Notifications?.error('The password confirmation does not match.');return;}try{const response=await axios.post('api/auth.php?action=change_password',data,{headers:{'X-CSRF-Token':csrfToken}});location.replace(response.data.redirect_url||'./?login=password-changed');}catch(error){window.Notifications?.error(error.response?.data?.message||'Password change failed.');}});
  };
  const initialize = async () => {
    const session = await window.SharedNavigation.ready;
    if (!session || session.role !== 'sbo') throw new Error('SBO Officer navigation is unavailable.');
    csrfToken = session.csrfToken; passwordGate(session.user);
    const notificationRegion=document.querySelector('[data-notification-region]'); if(notificationRegion) notificationRegion.style.zIndex='100';
    return {user:session.user,csrfToken};
  };
  window.SboPortal={initialize,escapeHtml:esc,get csrfToken(){return csrfToken;}};
})();
