window.SharedNavigation.ready.then(async session => {
  'use strict';

  const root = document.querySelector('[data-student-profile-root]');
  const esc = value => window.SharedNavigation.escapeHtml(value ?? '');
  const notify = (type, message) => window.Notifications?.[type]?.(message);
  const formatDate = value => value ? new Intl.DateTimeFormat('en-PH', {month:'long', year:'numeric'}).format(new Date(value.replace(' ', 'T'))) : 'Not available';
  const MAX_PHOTO_BYTES = 5 * 1024 * 1024;
  const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
  const safeColor = value => /^#[0-9a-f]{6}$/i.test(value || '') ? value : '#397565';
  const teamCoverColor = profile => safeColor(profile.team_color);
  let data;
  let postForm;
  let profileRequest = 0;

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
    const coverColor = teamCoverColor(profile);
    const avatar = profile.profile_photo_path
      ? `<img src="${esc(profile.profile_photo_path)}" alt="${esc(profile.full_name)} profile picture">`
      : `<span class="text-2xl font-black text-[#121017] sm:text-3xl">${esc(profile.initials)}</span>`;
    const totalPosts = Object.values(data.post_counts).reduce((total, count) => total + Number(count || 0), 0);
    root.innerHTML = `
      <section class="overflow-hidden bg-white shadow-sm sm:mx-4 sm:mt-5 sm:rounded-2xl">
        <div class="student-profile-cover relative overflow-hidden" style="--team-cover:${coverColor}">
          <div class="student-profile-cover-pattern absolute inset-0"></div>
          <div class="absolute inset-x-0 bottom-0 h-28" style="background:linear-gradient(to top,rgba(0,0,0,.22),transparent)"></div>
        </div>
        <div class="student-profile-summary relative px-5 pb-5 sm:px-8 sm:pb-7">
          <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div class="student-profile-identity flex min-w-0 flex-col sm:flex-row sm:items-end sm:gap-5">
              <div class="student-profile-avatar relative -mt-14 grid h-28 w-28 shrink-0 place-items-center border-white bg-[#C6F24E] shadow-lg sm:-mt-20 sm:h-40 sm:w-40">${avatar}</div>
              <div class="min-w-0 pt-2 sm:pb-2"><h1 class="truncate text-2xl font-black tracking-tight sm:text-3xl">${esc(profile.full_name)}</h1><p class="mt-1 text-sm font-bold text-[#121017]/50">${esc(profile.year_level_label || 'Student')}${profile.team_name ? ` · ${esc(profile.team_name)}` : ''}</p><p class="mt-1 text-xs text-[#121017]/38">${totalPosts} post${totalPosts === 1 ? '' : 's'}</p></div>
            </div>
            <div class="student-profile-actions flex gap-2 sm:pb-2"><button class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[#397565] px-4 text-sm font-black text-white disabled:cursor-wait disabled:opacity-65" type="button" data-change-photo><svg class="h-4 w-4 fill-none stroke-current stroke-2" viewBox="0 0 24 24"><path d="M4 7h3l2-3h6l2 3h3v12H4V7Zm8 9a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"></path></svg><span data-change-photo-label>Change photo</span></button><a class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[#121017]/12 px-4 text-sm font-black" href="pages/student/home.html">Media feed</a></div>
          </div>
          <form class="hidden" data-photo-form><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-photo-input aria-label="Choose a profile picture"></form>
        </div>
      </section>
      <div class="student-profile-layout grid items-start gap-5 px-4 py-5">
        <aside class="space-y-5 lg:sticky lg:top-24">
          <section class="flex items-center justify-between gap-4 rounded-2xl border border-[#397565]/15 bg-white p-5 shadow-sm"><div class="min-w-0"><p class="text-[9px] font-black uppercase tracking-[.14em] text-[#397565]">Student profile</p><h2 class="mt-1 text-lg font-black">About me</h2></div><button class="shrink-0 rounded-xl border border-[#397565]/20 px-4 py-2.5 text-xs font-black text-[#397565] transition hover:bg-[#397565]/8" type="button" data-profile-details-open>View details</button></section>
          <section class="rounded-2xl border border-[#397565]/15 bg-white p-5 shadow-sm"><h2 class="font-black">Post overview</h2><div class="mt-4 grid grid-cols-2 gap-3"><div class="rounded-xl bg-[#397565]/7 p-3"><strong class="block text-xl font-black text-[#397565]">${data.post_counts.approved}</strong><span class="text-[10px] font-bold text-[#121017]/45">Published</span></div><div class="rounded-xl bg-[#C6F24E]/20 p-3"><strong class="block text-xl font-black text-[#397565]">${data.post_counts.pending}</strong><span class="text-[10px] font-bold text-[#121017]/45">In review</span></div></div></section>
        </aside>
        <div class="min-w-0 space-y-5"><div data-profile-post-form></div>${pendingMarkup(data.own_posts)}<section><div class="mb-3 flex items-center justify-between"><div><p class="text-[9px] font-black uppercase tracking-[.14em] text-[#397565]">Timeline</p><h2 class="mt-1 text-xl font-black">My posts</h2></div></div><div class="space-y-5" data-profile-posts></div><div class="mt-5 text-center"><button class="${data.next_cursor ? '' : 'hidden'} min-h-11 rounded-xl border border-[#397565]/25 bg-white px-6 text-sm font-black text-[#397565] disabled:opacity-60" type="button" data-more-profile-posts>Load more posts</button></div></section></div>
      </div>
      <dialog class="student-profile-dialog" data-profile-details-dialog aria-labelledby="student-profile-details-title">
        <div class="student-profile-dialog-accent" style="background-color:${coverColor}"></div>
        <header><div><p>Student profile</p><h2 id="student-profile-details-title">Profile details</h2></div><button type="button" data-profile-details-close aria-label="Close profile details">&times;</button></header>
        <div class="student-profile-dialog-body">
          <div class="student-profile-dialog-person"><span class="student-profile-dialog-avatar">${avatar}</span><div class="min-w-0"><strong>${esc(profile.full_name)}</strong><span>${esc(profile.year_level_label || 'Student')}${profile.team_name ? ` · ${esc(profile.team_name)}` : ''}</span></div></div>
          <p class="student-profile-dialog-bio">${profile.bio ? esc(profile.bio) : 'CITE student profile'}</p>
          <dl><div><dt>Student ID</dt><dd>${esc(profile.id_number)}</dd></div><div><dt>Year level</dt><dd>${esc(profile.year_level_label || 'Not set')}</dd></div><div><dt>Team</dt><dd><i style="background-color:${coverColor}"></i>${esc(profile.team_name || 'Not assigned')}</dd></div><div><dt>Joined</dt><dd>${esc(formatDate(profile.created_at))}</dd></div></dl>
        </div>
        <footer><button type="button" data-profile-details-close>Close</button></footer>
      </dialog>`;

    postForm = CiteMediaPostForm.mount(root.querySelector('[data-profile-post-form]'), {events:data.post_events,viewer:data.viewer,onSaved:load,notify});
    const posts = root.querySelector('[data-profile-posts]');
    if (data.posts.length) data.posts.forEach(post => posts.append(makeProfileCard(post)));
    else posts.innerHTML = '<div class="rounded-2xl border border-dashed border-[#397565]/25 bg-white px-6 py-14 text-center"><h3 class="font-black">No published posts yet</h3><p class="mt-1 text-sm text-[#121017]/45">Your approved posts will appear on your profile.</p></div>';
    root.querySelector('[data-more-profile-posts]').onclick = loadMorePosts;

    const photoInput = root.querySelector('[data-photo-input]');
    const photoButtons = [...root.querySelectorAll('[data-change-photo]')];
    photoButtons.forEach(button => button.onclick = () => photoInput.click());
    photoInput.onchange = async () => {
      const photo = photoInput.files?.[0];
      if (!photo) return;
      if (!PHOTO_TYPES.includes(photo.type)) {
        notify('error', 'Choose a JPG, PNG, or WebP image.');
        photoInput.value = '';
        return;
      }
      if (photo.size > MAX_PHOTO_BYTES) {
        notify('error', 'Choose a profile picture no larger than 5 MB.');
        photoInput.value = '';
        return;
      }
      const body = new FormData(); body.append('photo', photo);
      photoButtons.forEach(button => { button.disabled = true; });
      root.querySelectorAll('[data-change-photo-label]').forEach(label => { label.textContent = 'Uploading…'; });
      try {
        const response = await axios.post('api/student-profile.php', body, {headers:{'X-CSRF-Token':session.csrfToken}});
        window.Notifications?.success?.(response.data.message || 'Profile picture updated.', {title:'Profile updated'});
        const headerAvatar = document.querySelector('[data-shared-account-initials]');
        if (headerAvatar) headerAvatar.innerHTML = `<img src="${esc(response.data.profile_photo_path)}" alt="">`;
        await load();
      } catch (error) {
        notify('error', error.response?.data?.message || 'The profile picture could not be updated.');
        photoButtons.forEach(button => { button.disabled = false; });
        root.querySelectorAll('[data-change-photo-label]').forEach(label => { label.textContent = 'Change photo'; });
      } finally {
        photoInput.value = '';
      }
    };
    const detailsDialog = root.querySelector('[data-profile-details-dialog]');
    root.querySelector('[data-profile-details-open]').onclick = () => detailsDialog.showModal();
    detailsDialog.querySelectorAll('[data-profile-details-close]').forEach(button => button.onclick = () => detailsDialog.close());
    detailsDialog.onclick = event => { if (event.target === detailsDialog) detailsDialog.close(); };
    root.querySelectorAll('[data-profile-edit]').forEach(button => button.onclick = () => { const post=data.own_posts.find(item=>item.id===Number(button.dataset.profileEdit)); if(post)postForm.edit(post,button.closest('article')); });
    root.querySelectorAll('[data-profile-delete]').forEach(button => button.onclick = () => execute({action:'delete',id:button.dataset.profileDelete},{confirm:'Delete this post?'}));
  }

  function makeProfileCard(post) {
    return CiteMediaPostCard.create(post, {viewer:data.viewer,permissions:data.permissions,action:execute,edit:(item,card)=>postForm.edit(item,card)});
  }

  async function loadMorePosts() {
    const button=root.querySelector('[data-more-profile-posts]');
    if(!button || button.disabled || !data.next_cursor)return;
    const request=profileRequest,cursor=data.next_cursor;
    button.disabled=true;button.textContent='Loading posts…';
    try {
      const page=await CiteMediaApi.posts({mine:true,cursor});
      if(request!==profileRequest)return;
      const seen=new Set(data.posts.map(post=>post.id));
      page.posts.filter(post=>!seen.has(post.id)).forEach(post=>{data.posts.push(post);root.querySelector('[data-profile-posts]').append(makeProfileCard(post));});
      data.next_cursor=page.next_cursor;
      button.classList.toggle('hidden',!data.next_cursor);
    } catch(error) { notify('error',error.response?.data?.message||'Older posts could not be loaded.'); }
    finally { if(request===profileRequest){button.disabled=false;button.textContent='Load more posts';} }
  }

  async function execute(payload, options = {}) {
    if (options.confirm) {
      const accepted = await window.Notifications.confirm({title:'Delete post?',message:options.confirm,action:'Delete'});
      if (!accepted) return;
    }
    let saved=false;
    try {
      const response=await CiteMediaApi.send(payload);saved=true;notify('success',response.message);
      if(['reaction_toggle','comment_create','comment_update','comment_delete','comment_pin'].includes(payload.action)) {
        const oldCard=root.querySelector(`[data-profile-posts] [data-post-id="${Number(payload.post_id)}"]`);
        const commentsOpen=oldCard?.querySelector('[data-comment-focus]')?.getAttribute('aria-expanded')==='true';
        const fresh=await CiteMediaApi.post(Number(payload.post_id));
        const index=data.posts.findIndex(post=>post.id===fresh.id);
        if(index>=0)data.posts[index]=fresh;
        if(oldCard){const newCard=makeProfileCard(fresh);oldCard.replaceWith(newCard);if(commentsOpen)await newCard.openComments();}
      } else await load();
    }
    catch(error){ notify('error',saved?'Your action was saved, but the post could not refresh. Reload the page.':error.response?.data?.message||'The post action could not be completed.'); }
  }

  async function load() {
    const request=++profileRequest;
    try { const result=(await axios.get('api/student-profile.php')).data.data;if(request===profileRequest){data=result;render();} }
    catch(error){ root.innerHTML=`<div class="m-4 rounded-2xl border border-[#FF6B2C]/20 bg-white p-8 text-center text-sm font-bold text-[#c84510]">${esc(error.response?.data?.message||'Your profile could not be loaded.')}</div>`; }
  }

  await load();
  window.SharedNavigation.finishPageLoad();
}).catch(error => {
  const root = document.querySelector('[data-student-profile-root]');
  if (root) root.innerHTML = '<div class="m-4 rounded-2xl border border-[#FF6B2C]/20 bg-white p-8 text-center text-sm font-bold text-[#c84510]">Your profile could not start. Please sign in again and retry.</div>';
  console.error('Student profile failed to initialize.', error);
});
