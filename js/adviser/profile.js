(() => {
  'use strict';
  const root = document.querySelector('[data-adviser-profile-root]');
  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  const inputClass = 'min-h-11 w-full rounded-xl border border-[#121017]/15 bg-white px-3 text-sm outline-none transition focus:border-[#397565] focus:ring-4 focus:ring-[#397565]/10';

  window.SharedNavigation.ready.then(async session => {
    const response = await axios.get('api/adviser-profile.php');
    const profile = response.data.data;
    const fullName = [profile.first_name, profile.middle_name, profile.last_name].filter(Boolean).join(' ');
    const initials = `${String(profile.first_name || 'A')[0]}${String(profile.last_name || 'A')[0]}`.toUpperCase();
    const avatar = profile.profile_photo_path
      ? `<img class="h-full w-full object-cover" src="${esc(profile.profile_photo_path)}" alt="${esc(fullName)} profile picture">`
      : `<span aria-hidden="true">${esc(initials)}</span>`;
    root.innerHTML = `<section class="adviser-profile-card">
      <div class="adviser-profile-hero"></div>
      <div class="adviser-profile-summary">
        <div class="adviser-profile-avatar" data-profile-avatar>${avatar}</div>
        <div class="min-w-0 pb-1"><h2 class="truncate text-xl font-black">${esc(fullName)}</h2><p class="text-sm font-bold text-[#397565]">SBO Adviser · @${esc(profile.username)}</p></div>
        <button class="min-h-11 rounded-xl border border-[#397565]/25 px-4 text-xs font-black text-[#397565] hover:bg-[#397565]/8 sm:ml-auto" type="button" data-change-photo>Change photo</button>
        <input class="sr-only" type="file" accept="image/jpeg,image/png,image/webp" data-photo-input aria-label="Choose a profile photo">
      </div>
    </section>
    <section class="mt-5 rounded-2xl border border-[#397565]/15 bg-white p-5 shadow-sm sm:p-7"><div class="mb-5"><h2 class="text-lg font-black">Profile details</h2><p class="mt-1 text-xs text-[#121017]/50">Your username and adviser role are managed by the system.</p></div>
      <form class="grid gap-4 sm:grid-cols-2" data-profile-form>
        <label class="grid gap-1.5 text-xs font-bold text-[#397565]">First name<input class="${inputClass}" name="first_name" maxlength="100" required autocomplete="given-name"></label>
        <label class="grid gap-1.5 text-xs font-bold text-[#397565]">Last name<input class="${inputClass}" name="last_name" maxlength="100" required autocomplete="family-name"></label>
        <label class="grid gap-1.5 text-xs font-bold text-[#397565]">Middle name<input class="${inputClass}" name="middle_name" maxlength="100" autocomplete="additional-name"></label>
        <label class="grid gap-1.5 text-xs font-bold text-[#397565]">Email address<input class="${inputClass}" name="email" type="email" required autocomplete="email"></label>
        <label class="grid gap-1.5 text-xs font-bold text-[#397565] sm:col-span-2">About me<textarea class="min-h-28 w-full resize-y rounded-xl border border-[#121017]/15 bg-white p-3 text-sm outline-none focus:border-[#397565] focus:ring-4 focus:ring-[#397565]/10" name="bio" maxlength="280" placeholder="Introduce yourself to the community"></textarea></label>
        <p class="text-xs text-[#121017]/55 sm:col-span-2" data-photo-hint>Choose a photo to crop and preview it before saving.</p>
        <div class="flex justify-end sm:col-span-2"><button class="min-h-11 rounded-xl bg-[#397565] px-6 text-sm font-black text-white disabled:cursor-wait disabled:opacity-60" type="submit" data-save-profile>Save profile</button></div>
      </form>
    </section>`;

    const form = root.querySelector('[data-profile-form]');
    for (const name of ['first_name','middle_name','last_name','email','bio']) form.elements[name].value = profile[name] || '';
    const photoInput = root.querySelector('[data-photo-input]');
    const changeButton = root.querySelector('[data-change-photo]');
    const saveButton = root.querySelector('[data-save-profile]');
    let croppedPhoto = null, previewUrl = null, cropping = false;
    changeButton.onclick = () => photoInput.click();
    photoInput.onchange = async () => {
      const file = photoInput.files?.[0];
      if (!file) return;
      cropping = true; saveButton.disabled = true;
      try {
        const cropped = await window.ProfilePhotoCrop.open(file);
        if (cropped) {
          croppedPhoto = cropped;
          if (previewUrl) URL.revokeObjectURL(previewUrl);
          previewUrl = URL.createObjectURL(cropped);
          root.querySelector('[data-profile-avatar]').innerHTML = `<img class="h-full w-full object-cover" src="${previewUrl}" alt="Cropped profile photo preview">`;
          root.querySelector('[data-photo-hint]').textContent = 'Photo ready. Save your profile to apply it.';
        }
      } catch (error) { window.Notifications?.error(error.message || 'The photo could not be prepared.'); }
      finally { photoInput.value = ''; cropping = false; saveButton.disabled = false; }
    };
    form.onsubmit = async event => {
      event.preventDefault();
      if (cropping || !form.reportValidity()) return;
      saveButton.disabled = true; saveButton.textContent = 'Saving…';
      try {
        const body = new FormData(form);
        if (croppedPhoto) body.append('photo', croppedPhoto);
        await axios.post('api/adviser-profile.php', body, {headers:{'X-CSRF-Token':session.csrfToken}});
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        window.Notifications?.flashNext('Profile updated.');
        location.reload();
      } catch (error) {
        window.Notifications?.error(error.response?.data?.message || 'The profile could not be saved.');
        saveButton.disabled = false; saveButton.textContent = 'Save profile';
      }
    };
  }).catch(error => {
    root.innerHTML = `<div class="rounded-xl bg-white p-8 text-sm font-bold text-[#c84510]">${esc(error.response?.data?.message || 'Profile could not be loaded.')}</div>`;
  });
})();
