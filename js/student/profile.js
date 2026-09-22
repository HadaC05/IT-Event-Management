window.SharedNavigation.ready.then(async session => {
  'use strict';

  const root = document.querySelector('[data-student-profile-root]');
  const esc = value => window.SharedNavigation.escapeHtml(value ?? '');
  const notify = (type, message) => window.Notifications?.[type]?.(message) || (type === 'error' ? alert(message) : null);
  const formatDate = value => value ? new Intl.DateTimeFormat('en-PH', {month:'long', year:'numeric'}).format(new Date(value.replace(' ', 'T'))) : 'Not available';
  let data;
  let postForm;

  CiteMediaApi.configure(session.csrfToken);

  const statusTone = status => ({pending:'bg-[#C6F24E]/35 text-[#397565]',rejected:'bg-[#FF6B2C]/12 text-[#c84510]',hidden:'bg-[#121017]/10 text-[#121017]/55'}[status] || 'bg-[#397565]/10 text-[#397565]');

  const reviewPostMarkup = post => {
    const actions = post.status === 'hidden' ? '' : `<div class="flex gap-2"><button class="text-[10px] font-black text-[#397565]" type="button" data-profile-edit="${post.id}">Edit</button><button class="text-[10px] font-black text-[#D64A12]" type="button" data-profile-delete="${post.id}">Delete</button></div>`;
    return `<article class="rounded-xl border border-[#121017]/8 bg-[#F7F4ED]/55 p-4"><div class="flex items-start justify-between gap-3"><span class="rounded-full px-2.5 py-1 text-[9px] font-black uppercase ${statusTone(post.status)}">${esc(post.status)}</span>${actions}</div><p class="mt-3 whitespace-pre-line text-sm leading-6">${esc(post.content)}</p>${post.rejection_reason ? `<p class="mt-3 rounded-lg bg-[#FF6B2C]/8 p-3 text-xs leading-5 text-[#a33b0e]"><strong>Review note:</strong> ${esc(post.rejection_reason)}</p>` : ''}</article>`;
  };

  const pendingMarkup = posts => posts.length ? `
    <section class="rounded-2xl border border-[#397565]/15 bg-white p-4 shadow-sm sm:p-5">
      <div class="flex items-center justify-between gap-3"><div><p class="text-[9px] font-black uppercase tracking-[.14em] text-[#397565]">Only visible to you</p><h2 class="mt-1 text-lg font-black">Posts in review</h2></div><span class="rounded-full bg-[#F3F0E9] px-3 py-1 text-xs font-black">${posts.length}</span></div>
      <div class="mt-4 grid gap-3">${posts.map(reviewPostMarkup).join('')}</div>
    </section>` : '';

  function render() {
    const profile = data.profile;
    const avatar = profile.profile_photo_path
      ? `<img class="h-full w-full rounded-full object-cover" src="${esc(profile.profile_photo_path)}" alt="${esc(profile.full_name)} profile picture">`
      : `<span class="text-3xl font-black text-[#121017]">${esc(profile.initials)}</span>`;
    const totalPosts = Object.values(data.post_counts).reduce((total, count) => total + Number(count || 0), 0);
    root.innerHTML = `
      <section class="overflow-hidden bg-white shadow-sm sm:mx-4 sm:mt-5 sm:rounded-2xl">
        <div class="student-profile-cover relative overflow-hidden bg-[#397565]">
          <div class="absolute inset-0 opacity-90" style="background:radial-gradient(circle at 18% 25%,#C6F24E 0 8%,transparent 8.5%),radial-gradient(circle at 82% 30%,#2F3AE0 0 12%,transparent 12.5%),linear-gradient(135deg,#121017 0%,#397565 58%,#C6F24E 140%)"></div>
          <div class="absolute inset-x-0 bottom-0 h-28" style="background:linear-gradient(to top,rgba(0,0,0,.3),transparent)"></div>
        </div>
        <div class="relative px-5 pb-5 sm:px-8 sm:pb-7">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex min-w-0 flex-col sm:flex-row sm:items-end sm:gap-5">
              <div class="student-profile-avatar -mt-16 grid h-32 w-32 shrink-0 place-items-center overflow-hidden rounded-full border-white bg-[#C6F24E] shadow-lg sm:-mt-20 sm:h-40 sm:w-40">${avatar}</div>
              <div class="min-w-0 pt-2 sm:pb-2"><h1 class="truncate text-2xl font-black tracking-tight sm:text-3xl">${esc(profile.full_name)}</h1><p class="mt-1 text-sm font-bold text-[#121017]/50">${esc(profile.year_level_label || 'Student')}${profile.team_name ? ` · ${esc(profile.team_name)}` : ''}</p><p class="mt-1 text-xs text-[#121017]/38">${totalPosts} post${totalPosts === 1 ? '' : 's'}</p></div>
            </div>
            <div class="flex flex-wrap gap-2 sm:pb-2"><button class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-[#397565] px-4 text-sm font-black text-white" type="button" data-change-photo><svg class="h-4 w-4 fill-none stroke-current stroke-2" viewBox="0 0 24 24"><path d="M4 7h3l2-3h6l2 3h3v12H4V7Zm8 9a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"></path></svg>Change profile picture</button><a class="inline-flex min-h-11 items-center rounded-xl border border-[#121017]/12 px-4 text-sm font-black" href="pages/student/home.html">Media feed</a></div>
          </div>
          <form class="hidden" data-photo-form><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-photo-input></form>
        </div>
      </section>
      <div class="student-profile-layout grid items-start gap-5 px-4 py-5">
        <aside class="space-y-5 lg:sticky lg:top-24">
          <section class="rounded-2xl border border-[#397565]/15 bg-white p-5 shadow-sm"><h2 class="text-lg font-black">Intro</h2>${profile.bio ? `<p class="mt-3 whitespace-pre-line text-sm leading-6 text-[#121017]/65">${esc(profile.bio)}</p>` : '<p class="mt-3 text-sm text-[#121017]/45">CITE student profile</p>'}<dl class="mt-4 grid gap-3 border-t border-[#121017]/8 pt-4 text-sm"><div class="flex gap-3"><dt class="w-24 shrink-0 text-[#121017]/40">Student ID</dt><dd class="font-bold">${esc(profile.id_number)}</dd></div><div class="flex gap-3"><dt class="w-24 shrink-0 text-[#121017]/40">Year level</dt><dd class="font-bold">${esc(profile.year_level_label || 'Not set')}</dd></div><div class="flex gap-3"><dt class="w-24 shrink-0 text-[#121017]/40">Team</dt><dd class="font-bold">${esc(profile.team_name || 'Not assigned')}</dd></div><div class="flex gap-3"><dt class="w-24 shrink-0 text-[#121017]/40">Joined</dt><dd class="font-bold">${esc(formatDate(profile.created_at))}</dd></div></dl></section>
          <section class="rounded-2xl border border-[#397565]/15 bg-white p-5 shadow-sm"><h2 class="font-black">Post overview</h2><div class="mt-4 grid grid-cols-2 gap-3"><div class="rounded-xl bg-[#397565]/7 p-3"><strong class="block text-xl font-black text-[#397565]">${data.post_counts.approved}</strong><span class="text-[10px] font-bold text-[#121017]/45">Published</span></div><div class="rounded-xl bg-[#C6F24E]/20 p-3"><strong class="block text-xl font-black text-[#397565]">${data.post_counts.pending}</strong><span class="text-[10px] font-bold text-[#121017]/45">In review</span></div></div></section>
        </aside>
        <div class="min-w-0 space-y-5"><div data-profile-post-form></div>${pendingMarkup(data.own_posts)}<section><div class="mb-3 flex items-center justify-between"><div><p class="text-[9px] font-black uppercase tracking-[.14em] text-[#397565]">Timeline</p><h2 class="mt-1 text-xl font-black">My posts</h2></div></div><div class="space-y-5" data-profile-posts></div></section></div>
      </div>`;

    postForm = CiteMediaPostForm.mount(root.querySelector('[data-profile-post-form]'), {events:data.post_events,viewer:data.viewer,onSaved:load,notify});
    const posts = root.querySelector('[data-profile-posts]');
    if (data.posts.length) data.posts.forEach(post => posts.append(CiteMediaPostCard.create(post, {viewer:data.viewer,permissions:data.permissions,action:execute,edit:item=>postForm.edit(item)})));
    else posts.innerHTML = '<div class="rounded-2xl border border-dashed border-[#397565]/25 bg-white px-6 py-14 text-center"><h3 class="font-black">No published posts yet</h3><p class="mt-1 text-sm text-[#121017]/45">Your approved posts will appear on your profile.</p></div>';

    const photoInput = root.querySelector('[data-photo-input]');
    root.querySelector('[data-change-photo]').onclick = () => photoInput.click();
    photoInput.onchange = async () => {
      if (!photoInput.files?.[0]) return;
      const body = new FormData(); body.append('photo', photoInput.files[0]);
      try {
        const response = await axios.post('api/student-profile.php', body, {headers:{'X-CSRF-Token':session.csrfToken}});
        notify('success', response.data.message);
        const headerAvatar = document.querySelector('[data-shared-account-initials]');
        if (headerAvatar) headerAvatar.innerHTML = `<img class="h-full w-full rounded-full object-cover" src="${esc(response.data.profile_photo_path)}" alt="">`;
        await load();
      } catch (error) { notify('error', error.response?.data?.message || 'The profile picture could not be updated.'); }
    };
    root.querySelectorAll('[data-profile-edit]').forEach(button => button.onclick = () => { const post=data.own_posts.find(item=>item.id===Number(button.dataset.profileEdit)); if(post)postForm.edit(post); });
    root.querySelectorAll('[data-profile-delete]').forEach(button => button.onclick = () => execute({action:'delete',id:button.dataset.profileDelete},{confirm:'Delete this post?'}));
  }

  async function execute(payload, options = {}) {
    if (options.confirm) {
      const accepted = window.Notifications?.confirm ? await window.Notifications.confirm({title:'Delete post?',message:options.confirm,action:'Delete'}) : confirm(options.confirm);
      if (!accepted) return;
    }
    try { const response=await CiteMediaApi.send(payload); notify('success',response.message); await load(); }
    catch(error){ notify('error',error.response?.data?.message||'The post action could not be completed.'); }
  }

  async function load() {
    try { data=(await axios.get('api/student-profile.php')).data.data; render(); }
    catch(error){ root.innerHTML=`<div class="m-4 rounded-2xl border border-[#FF6B2C]/20 bg-white p-8 text-center text-sm font-bold text-[#c84510]">${esc(error.response?.data?.message||'Your profile could not be loaded.')}</div>`; }
  }

  await load();
  window.SharedNavigation.finishPageLoad();
}).catch(error => {
  const root = document.querySelector('[data-student-profile-root]');
  if (root) root.innerHTML = '<div class="m-4 rounded-2xl border border-[#FF6B2C]/20 bg-white p-8 text-center text-sm font-bold text-[#c84510]">Your profile could not start. Please sign in again and retry.</div>';
  console.error('Student profile failed to initialize.', error);
});
