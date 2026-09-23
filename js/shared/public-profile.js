(() => {
  'use strict';
  const initialize = () => {
  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  const root = document.querySelector('[data-public-profile-root]');
  const backLink = document.querySelector('[data-profile-back]');
  const userId = Number(new URLSearchParams(location.search).get('user_id'));
  let nextCursor = null, loading = false, profile, mediaHome = './';

  const showError = message => {
    if (!root) return;
    root.innerHTML = `<section class="rounded-2xl border border-[#397565]/15 bg-white p-8 text-center"><h1 class="text-xl font-black">Profile unavailable</h1><p class="mt-2 text-sm text-[#121017]/55">${esc(message)}</p><a class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-[#397565] px-5 text-sm font-black text-white" href="${esc(mediaHome)}">Back to media</a></section>`;
  };
  const profileMarkup = user => `<section class="flex flex-col items-center gap-4 rounded-2xl border border-[#397565]/15 bg-white p-6 text-center shadow-sm sm:flex-row sm:text-left"><span class="grid h-24 w-24 shrink-0 place-items-center overflow-hidden rounded-full bg-[#C6F24E]/35 text-2xl font-black text-[#397565]">${user.profile_photo_path ? `<img class="h-full w-full object-cover" src="${esc(user.profile_photo_path)}" alt="${esc(user.full_name)} profile photo">` : esc(user.initials)}</span><div class="min-w-0"><h1 class="text-2xl font-black tracking-tight">${esc(user.full_name)}</h1><p class="mt-1 text-sm font-bold text-[#397565]">${esc(CiteMediaPermissions.roleLabel(user.role_name))} · ${user.post_count} public post${Number(user.post_count) === 1 ? '' : 's'}</p>${user.bio ? `<p class="mt-3 whitespace-pre-wrap text-sm leading-6 text-[#121017]/60">${esc(user.bio)}</p>` : ''}</div></section><h2 class="mb-4 mt-8 text-lg font-black">Public posts</h2><div class="grid gap-5" data-profile-posts></div><button class="${nextCursor ? '' : 'hidden'} mt-5 min-h-11 w-full rounded-xl border border-[#397565]/25 bg-white px-5 text-sm font-black text-[#397565]" type="button" data-profile-more>Load more posts</button>`;
  const renderPage = async cursor => {
    if (loading) return;
    loading = true;
    const button = root.querySelector('[data-profile-more]');
    if (button) { button.disabled = true; button.textContent = 'Loading…'; }
    try {
      const data = await CiteMediaApi.author(userId, cursor);
      profile = data.profile; nextCursor = data.next_cursor;
      if (!cursor) {
        root.innerHTML = profileMarkup(profile);
        root.querySelector('[data-profile-more]').onclick = () => renderPage(nextCursor);
      }
      const cards = root.querySelector('[data-profile-posts]');
      const viewer = {id:-1};
      const permissions = {hide:false,moderate:false};
      data.posts.forEach(post => cards.append(CiteMediaPostCard.create(post, {
        viewer,
        permissions,
        action: async payload => {
          try { await CiteMediaApi.send(payload); await renderPage(null); }
          catch (error) { window.Notifications?.error(error.response?.data?.message || 'That action could not be completed.'); }
        },
        edit: () => {},
      })));
      const more = root.querySelector('[data-profile-more]');
      more.classList.toggle('hidden', !nextCursor);
      more.disabled = false; more.textContent = 'Load more posts';
    } catch (error) {
      if (!cursor) showError(error.response?.data?.message || 'This account could not be loaded.');
      else window.Notifications?.error(error.response?.data?.message || 'More posts could not be loaded.');
    } finally { loading = false; }
  };

  if (!window.SharedNavigation?.ready) { showError('Navigation could not be loaded.'); return; }
  window.SharedNavigation.ready.then(session => {
    CiteMediaApi.configure(session.csrfToken);
    mediaHome = ({Student:'pages/student/home.html','SBO Adviser':'pages/adviser/posts.html','SBO Officer':'pages/sbo/media.html',Faculty:'pages/faculty/posts.html',Admin:'pages/admin/media.html',SBO:'pages/admin/media.html'})[session.user?.role] || './';
    if (backLink) backLink.href = mediaHome;
    if (!userId) { showError('Choose a valid account from search.'); return; }
    renderPage(null);
  }).catch(error => showError(error.response?.data?.message || 'Sign in to view this profile.'));
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, {once:true});
  else initialize();
})();
