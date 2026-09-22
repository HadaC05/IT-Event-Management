(() => {
  'use strict';

  const ROLE_LABELS = {admin: 'Admin', student: 'Student', sbo: 'SBO Officer', adviser: 'SBO Adviser', faculty: 'Faculty'};
  const ROLE_SESSION_ROLES = {admin: ['Admin', 'SBO'], student: ['Student'], sbo: ['SBO Officer'], adviser: ['SBO Adviser'], faculty: ['Faculty']};
  const ROLE_HOME = {admin: 'pages/admin/media.html', student: 'pages/student/home.html', sbo: 'pages/sbo/attendance.html', adviser: 'pages/adviser/dashboard.html', faculty: 'pages/faculty/students.html'};
  const SESSION_ROLE_PORTAL = {'Admin': 'admin', 'SBO': 'admin', 'Student': 'student', 'SBO Officer': 'sbo', 'SBO Adviser': 'adviser', 'Faculty': 'faculty'};
  const ROLE_PAGES = {
    admin: [
      ['media','Media Feed','pages/admin/media.html','M4 5h16v14H4V5Zm3 10 3-3 2 2 3-4 3 5M8 9h.01'],
    ],
    faculty: [
      ['students','Students','pages/faculty/students.html','M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8'],
      ['leaderboard','Leaderboard','pages/faculty/leaderboard.html','M8 21h8M12 17v4M7 4h10v4a5 5 0 0 1-10 0V4Z'],
      ['team','Assigned Team','pages/faculty/team.html','M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8'],
      ['attendance','Team Attendance','pages/faculty/attendance.html','M5 4h14v16H5V4Zm4 4h6m-6 4h6'],
      ['posts','Media Feed','pages/faculty/posts.html','M4 5h16v12H8l-4 4V5Zm4 4h8m-8 4h5'],
      ['profile','Profile','pages/faculty/profile.html','M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 21a8 8 0 0 1 16 0'],
    ],
    student: [
      ['home','Home','pages/student/home.html','M3 11 12 3l9 8M5 10v10h14V10M9 20v-6h6v6'],
      ['events','Events','pages/student/events.html','M6 3v3m12-3v3M4 9h16M5 5h14a2 2 0 0 1 2 2v13H3V7a2 2 0 0 1 2-2Z'],
      ['attendance','Attendance','pages/student/attendance.html','M5 4h14v16H5V4Zm4 4h6m-6 4h6m-6 4h4'],
      ['team','Team','pages/student/team.html','M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87'],
      ['leaderboard','Leaderboard','pages/student/leaderboard.html','M8 21h8M12 17v4M7 4h10v4a5 5 0 0 1-10 0V4Zm0 2H4v2a4 4 0 0 0 4 4m9-6h3v2a4 4 0 0 1-4 4'],
    ],
    sbo: [
      ['attendance','Attendance','pages/sbo/attendance.html','M9 11l2 2 4-4m6 3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
      ['students','Students','pages/sbo/students.html','M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87'],
      ['scores','Scores','pages/sbo/scores.html','M8 21h8M12 17v4M7 4h10v4a5 5 0 0 1-10 0V4Zm0 2H4v1a4 4 0 0 0 4 4m9-5h3v1a4 4 0 0 1-4 4'],
      ['media','Media Feed','pages/sbo/media.html','M4 5h16v14H4V5Zm3 10 3-3 2 2 3-4 3 5M8 9h.01'],
    ],
    adviser: [
      ['dashboard','Dashboard','pages/adviser/dashboard.html','M4 13h6V4H4v9Zm0 7h6v-4H4v4Zm10 0h6v-9h-6v9Zm0-16v4h6V4h-6Z'],
      ['users','Users','pages/adviser/users.html','M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8'],
      ['officers','SBO Officers','pages/adviser/officers.html','M12 3 4 7v5c0 4.6 3.2 7.8 8 9 4.8-1.2 8-4.4 8-9V7l-8-4Zm-3 9 2 2 4-5'],
      ['teams','Team Management','pages/adviser/teams.html','M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm8 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM2 20v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2'],
      ['events','Events','pages/adviser/events.html','M6 2v4m12-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z'],
      ['activities','Activities','pages/adviser/activities.html','M4 5h16v14H4V5Zm4 4h8m-8 3h8m-8 3h5'],
      ['locations','Locations','pages/adviser/locations.html','M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0ZM12 10h.01'],
      ['attendance','Attendance','pages/adviser/attendance.html','M9 11l2 2 4-4m6 3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
      ['scores','Scores','pages/adviser/scores.html','M8 21h8M12 17v4M7 4h10v4a5 5 0 0 1-10 0V4Z'],
      ['leaderboard','Leaderboard','pages/adviser/leaderboard.html','M4 20V10h4v10H4Zm6 0V4h4v16h-4Zm6 0v-7h4v7h-4Z'],
      ['announcements','Announcements','pages/adviser/announcements.html','M4 13V9l11-5v14L4 13Zm0 0v5h4v-3'],
      ['reports','Reports','pages/adviser/reports.html','M5 3h10l4 4v14H5V3Zm10 0v5h4M8 12h8M8 16h8'],
      ['posts','Media Feed','pages/adviser/posts.html','M4 5h16v12H8l-4 4V5Zm4 4h8m-8 4h5'],
    ],
  };
  const ADVISER_NAVIGATION_SECTIONS = [
    {items: ['dashboard']},
    {title: 'User Management', items: ['users', 'officers']},
    {title: 'Event Management', items: ['teams', 'events', 'activities', 'locations', 'attendance', 'scores', 'leaderboard', 'announcements', 'reports', 'posts']},
  ];

  const esc = value => String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
  const parseLocalDate = value => value ? new Date(String(value).replace(' ', 'T')) : null;
  const notificationTime = value => {
    const date = parseLocalDate(value);
    if (!date || Number.isNaN(date.getTime())) return '';
    const elapsed = Math.max(0, Date.now() - date.getTime());
    const minutes = Math.floor(elapsed / 60000);
    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes} min${minutes === 1 ? '' : 's'} ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h${minutes % 60 ? ` ${minutes % 60}m` : ''} ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d${hours % 24 ? ` ${hours % 24}h` : ''} ago`;
    if (days < 30) return `${Math.floor(days / 7)}w ago`;
    return new Intl.DateTimeFormat('en-PH', {month:'short',day:'numeric',year:date.getFullYear() === new Date().getFullYear() ? undefined : 'numeric'}).format(date);
  };
  const notificationDateTitle = value => {
    const date = parseLocalDate(value);
    return date && !Number.isNaN(date.getTime()) ? new Intl.DateTimeFormat('en-PH', {dateStyle:'medium',timeStyle:'short'}).format(date) : '';
  };
  const portalForSession = sessionRole => ROLE_HOME[SESSION_ROLE_PORTAL[sessionRole]] || './';
  const icon = (path, mobile = false) => `<svg class="${mobile ? 'h-5 w-5' : 'h-6 w-6'} shrink-0 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="${path}"></path></svg>`;
  const activePage = role => {
    const file = location.pathname.split('/').pop().replace('.html','') || ROLE_PAGES[role][0][0];
    if (role === 'adviser' && ['event-edit','event-details'].includes(file)) return 'events';
    if (role === 'adviser' && file === 'attendance-roster') return 'attendance';
    if (role === 'adviser' && file === 'scoreboard') return 'leaderboard';
    return file;
  };
  const ensureStyles = () => {
    if (document.querySelector('link[href^="css/navigation.css"]')) return;
    const link = document.createElement('link'); link.rel = 'stylesheet'; link.href = 'css/navigation.css?v=20260922-profile-avatar-1'; document.head.append(link);
  };
  const clone = (documentFragment, selector) => documentFragment.querySelector(selector).content.firstElementChild.cloneNode(true);
  const linkMarkup = (item, active, mobile = false) => {
    const [key,label,href,path] = item, selected = key === active;
    if (mobile) return `<a class="flex min-w-0 flex-col items-center justify-center gap-1 rounded-xl text-[10px] font-black ${selected ? 'bg-[#397565] text-[#C6F24E]' : 'text-white/55'}" href="${href}" aria-label="${label}" aria-current="${selected ? 'page' : 'false'}">${icon(path,true)}<span class="max-w-full truncate">${key === 'leaderboard' ? 'Ranks' : label}</span></a>`;
    return `<a class="group flex min-h-14 items-center gap-4 overflow-hidden rounded-2xl border px-3 text-sm font-black transition ${selected ? 'border-[#C6F24E]/30 bg-[#397565]/55 text-[#C6F24E] shadow-lg shadow-black/20' : 'border-white/5 bg-white/[.06] text-[#F3F0E9]/75 hover:border-[#C6F24E]/20 hover:bg-white/[.1] hover:text-[#C6F24E]'}" href="${href}" aria-label="${label}" aria-current="${selected ? 'page' : 'false'}">${icon(path)}<span class="hidden whitespace-nowrap" data-shared-sidebar-label>${label}</span></a>`;
  };
  const sidebarLinksMarkup = (role, pages, active) => {
    if (role !== 'adviser') return pages.map(item => linkMarkup(item, active)).join('');
    const itemsByKey = new Map(pages.map(item => [item[0], item]));
    return ADVISER_NAVIGATION_SECTIONS.map(section => {
      const links = section.items.map(key => linkMarkup(itemsByKey.get(key), active)).join('');
      if (!section.title) return `<div class="shared-navigation-link-group">${links}</div>`;
      return `<section class="shared-navigation-link-group" aria-label="${section.title}"><h2 class="shared-navigation-section-title hidden" data-shared-sidebar-label>${section.title}</h2>${links}</section>`;
    }).join('');
  };

  const studentNotifications = async (header, csrf) => {
    const slot = header.querySelector('[data-shared-notification-slot]');
    slot.innerHTML = `<details class="group relative" data-notification-menu><summary class="relative grid h-11 w-11 cursor-pointer list-none place-items-center rounded-full bg-[#121017]/8 text-[#121017]" aria-label="Notifications"><svg class="h-5 w-5 fill-current" viewBox="0 0 24 24"><path d="M12 22a2.5 2.5 0 0 0 2.35-1.65h-4.7A2.5 2.5 0 0 0 12 22Zm7-6.5-1.5-2V9a5.5 5.5 0 0 0-4.25-5.35V3a1.25 1.25 0 0 0-2.5 0v.65A5.5 5.5 0 0 0 6.5 9v4.5l-1.5 2V18h14v-2.5Z"></path></svg><span class="absolute -right-1 -top-1 hidden min-h-5 min-w-5 place-items-center rounded-full border-2 border-white bg-[#FF4D4F] px-1 text-[9px] font-black text-white" data-notification-count>0</span></summary><div class="absolute right-0 top-[calc(100%+.65rem)] w-[min(22rem,calc(100vw-1rem))] overflow-hidden rounded-2xl border border-[#121017]/10 bg-white shadow-2xl"><div class="flex items-center justify-between border-b px-4 py-3"><div><strong class="block text-sm font-black">Notifications</strong><span class="text-[10px] text-[#121017]/45" data-notification-summary>No unread notifications</span></div><button class="hidden text-[10px] font-black text-[#397565]" type="button" data-mark-all-notifications>Mark all read</button></div><div class="max-h-80 overflow-y-auto" data-notification-list></div></div></details>`;
    const refreshTimes = () => slot.querySelectorAll('[data-notification-time]').forEach(node => { node.textContent = notificationTime(node.dataset.notificationTime); });
    const load = async () => {
      const response = await axios.get('api/student-home.php', {params: {action: 'notifications'}}), data = response.data.data, count = Number(data.unread_notifications || 0);
      const badge = slot.querySelector('[data-notification-count]'); badge.textContent = count > 9 ? '9+' : count; badge.classList.toggle('hidden', !count); badge.classList.toggle('grid', !!count);
      slot.querySelector('[data-notification-summary]').textContent = count ? `${count} unread notification${count === 1 ? '' : 's'}` : 'No unread notifications';
      slot.querySelector('[data-mark-all-notifications]').classList.toggle('hidden', !count);
      slot.querySelector('[data-notification-list]').innerHTML = data.notifications?.length ? data.notifications.map(item => `<article class="grid grid-cols-[minmax(0,1fr)_auto] gap-x-3 gap-y-1 border-b px-4 py-3 ${item.is_read ? '' : 'bg-[#C6F24E]/10'}"><p class="min-w-0 text-xs font-bold leading-5">${esc(item.message)}</p>${item.is_read ? '' : `<button class="row-span-2 shrink-0 self-center text-[10px] font-black text-[#397565]" data-mark-notification="${esc(item.id)}">Mark read</button>`}<time class="text-[10px] font-semibold text-[#121017]/40" datetime="${esc(item.created_at)}" title="${esc(notificationDateTitle(item.created_at))}" data-notification-time="${esc(item.created_at)}">${esc(notificationTime(item.created_at))}</time></article>`).join('') : '<p class="px-6 py-10 text-center text-xs text-[#121017]/45">No notifications yet.</p>';
      refreshTimes();
    };
    const mark = async id => { await axios.post('api/student-home.php',{action:'mark_notifications_read',...(id ? {notification_id:id} : {})},{headers:{'X-CSRF-Token':csrf}}); await load(); };
    slot.querySelector('[data-mark-all-notifications]').onclick = () => mark();
    slot.querySelector('[data-notification-list]').onclick = event => { const button = event.target.closest('[data-mark-notification]'); if (button) mark(button.dataset.markNotification); };
    const menu = slot.querySelector('[data-notification-menu]');
    document.addEventListener('click', event => { if (menu.open && !menu.contains(event.target)) menu.removeAttribute('open'); });
    await load();
    window.setInterval(refreshTimes, 60000);
  };

  async function mount() {
    const role = document.body.dataset.navigationRole || (document.body.hasAttribute('data-admin-shell') ? 'adviser' : '');
    if (role) document.body.dataset.navigationRole = role;
    if (!ROLE_PAGES[role]) return null;
    ensureStyles();
    const [session, fragmentResponse] = await Promise.all([axios.get('api/auth.php?action=session'), axios.get('pages/shared/navigation.html?v=20260922-student-profile-1',{responseType:'text'})]);
    const expected = ROLE_LABELS[role];
    if (!session.data.authenticated || !ROLE_SESSION_ROLES[role].includes(session.data.user?.role)) {
      // Do not strand authenticated users on the public homepage when they open
      // a page that belongs to another role (for example, an SBO Officer opening
      // the adviser-only SBO Officers or Events pages).
      location.href = session.data.authenticated ? portalForSession(session.data.user?.role) : './';
      throw new Error(`${expected} authentication required.`);
    }
    const user = session.data.user, csrf = session.data.csrf_token, name = user.full_name || `${user.first_name || ''} ${user.last_name || ''}`.trim(), initials = `${user.first_name?.[0] || ''}${user.last_name?.[0] || ''}`.toUpperCase() || expected.slice(0,2).toUpperCase();
    await window.RequiredPasswordGate.open(user, csrf);
    const fragment = new DOMParser().parseFromString(fragmentResponse.data,'text/html');
    const sidebar = clone(fragment,'[data-shared-navigation-sidebar]'), header = clone(fragment,'[data-shared-navigation-header]'), active = activePage(role), pages = ROLE_PAGES[role];
    sidebar.querySelector('[data-shared-brand-label]').textContent = role === 'adviser' ? 'CITE ADVISER' : role === 'sbo' ? 'CITE SBO' : role === 'faculty' ? 'CITE FACULTY' : 'STUDENT MENU';
    sidebar.querySelector('[data-shared-navigation-links]').innerHTML = sidebarLinksMarkup(role, pages, active);
    sidebar.querySelector('[data-shared-sidebar-name]').textContent = name;
    sidebar.querySelector('[data-shared-sidebar-account]').textContent = user.username ? `@${user.username}` : user.email || '';
    if (role === 'adviser') sidebar.querySelector('[data-shared-sidebar-name]')?.closest('[data-shared-sidebar-label]')?.remove();
    header.querySelector('[data-shared-home-link]').href = ROLE_HOME[role];
    const accountAvatar = header.querySelector('[data-shared-account-initials]');
    if (user.profile_photo_path) accountAvatar.innerHTML = `<img src="${esc(user.profile_photo_path)}" alt="${esc(name)} profile picture">`;
    else accountAvatar.textContent = initials;
    header.querySelector('[data-shared-account-name]').textContent = name;
    header.querySelector('[data-shared-account-role]').textContent = expected;
    header.querySelector('[data-shared-account-heading]').textContent = `${expected} account`;
    header.querySelector('[data-shared-account-menu-name]').textContent = name;
    const visibleEmail = /@pending\.invalid$/i.test(String(user.email || '')) ? '' : String(user.email || '');
    header.querySelector('[data-shared-account-detail]').textContent = visibleEmail || (user.username ? `Login: ${user.username}` : '');
    const profileLink = header.querySelector('[data-shared-profile-link]');
    if (role === 'student') {
      profileLink.classList.remove('hidden');
      profileLink.classList.add('flex');
    }

    document.querySelectorAll('[data-student-sidebar-host],[data-student-header-host],[data-sbo-sidebar-host],[data-sbo-header-host],[data-shared-navigation-host]').forEach(node => node.remove());
    document.body.querySelector(':scope > aside')?.remove();
    document.body.querySelector(':scope > nav[aria-label="Student pages"]')?.remove();
    const adviserShell = role === 'adviser' ? document.body.querySelector(':scope > div.min-h-screen') : null;
    if (adviserShell) adviserShell.querySelector(':scope > header')?.remove();
    else document.body.querySelector(':scope > header[data-student-sidebar-content]')?.remove();
    document.querySelectorAll('[data-sidebar-scrim]').forEach(node => node.remove());
    const content = adviserShell || document.querySelector('main');
    content?.classList.remove('lg:ml-64'); content?.classList.add('lg:ml-20'); content?.setAttribute('data-shared-content','');
    document.body.prepend(header); document.body.prepend(sidebar);
    if (role === 'sbo') header.querySelector('[data-shared-account-menu]').setAttribute('data-sbo-account-menu','');

    const labels = sidebar.querySelectorAll('[data-shared-sidebar-label]'), toggle = sidebar.querySelector('[data-shared-sidebar-toggle]');
    const sidebarStateKey = `cite-navigation-${role}-expanded`;
    const setDesktopExpanded = (expanded,persist = true) => {
      sidebar.classList.toggle('is-expanded',expanded); header.classList.toggle('is-expanded',expanded); content?.classList.toggle('is-expanded',expanded);
      labels.forEach(label => label.classList.toggle('hidden',!expanded)); sidebar.querySelector('[data-shared-brand]')?.classList.toggle('flex',expanded);
      toggle.setAttribute('aria-expanded',String(expanded)); toggle.setAttribute('aria-label',expanded ? 'Collapse navigation' : 'Expand navigation');
      if (persist) localStorage.setItem(sidebarStateKey,expanded ? '1' : '0');
    };
    setDesktopExpanded(
      document.body.hasAttribute('data-navigation-collapsed')
        ? false
        : localStorage.getItem(sidebarStateKey) === '1',
      false,
    );
    if (role === 'admin' || role === 'adviser' || role === 'faculty') {
      const mobileToggle = header.querySelector('[data-shared-mobile-toggle]'), scrim = clone(fragment,'[data-shared-navigation-scrim]');
      mobileToggle.classList.remove('hidden'); mobileToggle.classList.add('grid'); document.body.append(scrim);
      const setMobileOpen = open => {
        sidebar.classList.toggle('is-open',open); scrim.classList.toggle('hidden',!open); mobileToggle.setAttribute('aria-expanded',String(open)); document.body.classList.toggle('overflow-hidden',open);
        if (innerWidth < 1024) { labels.forEach(label => label.classList.toggle('hidden',!open)); sidebar.querySelector('[data-shared-brand]')?.classList.toggle('flex',open); }
      };
      mobileToggle.onclick = () => setMobileOpen(true); scrim.onclick = () => setMobileOpen(false);
      toggle.onclick = () => innerWidth < 1024 ? setMobileOpen(false) : setDesktopExpanded(toggle.getAttribute('aria-expanded') !== 'true');
      addEventListener('resize',() => { if (innerWidth >= 1024 && sidebar.classList.contains('is-open')) setMobileOpen(false); });
      if (innerWidth >= 1024) setMobileOpen(false);
    } else {
      sidebar.classList.add('hidden','lg:flex');
      toggle.onclick = () => setDesktopExpanded(toggle.getAttribute('aria-expanded') !== 'true');
      const mobile = clone(fragment,'[data-shared-navigation-mobile]'), grid = mobile.querySelector('[data-shared-mobile-links]');
      grid.style.gridTemplateColumns = `repeat(${pages.length},minmax(0,1fr))`; grid.innerHTML = pages.map(item => linkMarkup(item,active,true)).join(''); document.body.append(mobile);
    }
    const account = header.querySelector('[data-shared-account-menu]'); document.addEventListener('click',event => { if (account.open && !account.contains(event.target)) account.removeAttribute('open'); });
    header.querySelector('[data-shared-logout-form]').onsubmit = async event => {
      event.preventDefault();
      try { await axios.post('api/auth.php?action=logout',{}, {headers:{'X-CSRF-Token':csrf}}); }
      finally { location.href='./'; }
    };
    document.querySelectorAll('[data-shared-navigation-links] a,[data-shared-mobile-links] a').forEach(link => link.addEventListener('click',event => {
      if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      const destination = new URL(link.href,location.href), current = new URL(location.href);
      if (destination.pathname === current.pathname) event.preventDefault();
    }));
    if (role === 'student') {
      try { await studentNotifications(header,csrf); }
      catch (error) { console.warn('Student notifications could not be loaded.', error); }
    }
    return {user,csrfToken:csrf,role};
  }

  const finishPageLoad = () => document.querySelectorAll('[data-page-loading-cloak]').forEach(node => node.remove());
  const ready = mount().catch(error => {
    document.querySelectorAll('[data-shared-navigation-host]').forEach(node => node.remove());
    finishPageLoad();
    console.error('Shared navigation failed to initialize.',error);
    throw error;
  });
  window.SharedNavigation = {ready, escapeHtml: esc, finishPageLoad};
})();
