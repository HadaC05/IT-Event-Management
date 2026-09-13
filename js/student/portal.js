(() => {
    'use strict';

    const SESSION_URL = 'api/auth.php?action=session';
    const LOGOUT_URL = 'api/auth.php?action=logout';
    const STUDENT_HOME_URL = 'api/student-home.php';

    const pages = [
        {
            key: 'home',
            label: 'Home',
            href: 'pages/student/home.html',
            icon: '<path d="M3 11 12 3l9 8M5 10v10h14V10M9 20v-6h6v6"></path>',
        },
        {
            key: 'events',
            label: 'Events',
            href: 'pages/student/events.html',
            icon: '<path d="M6 3v3m12-3v3M4 9h16M5 5h14a2 2 0 0 1 2 2v13H3V7a2 2 0 0 1 2-2Z"></path>',
        },
        {
            key: 'attendance',
            label: 'Attendance',
            href: 'pages/student/attendance.html',
            icon: '<path d="M5 4h14v16H5V4Zm4 4h6m-6 4h6m-6 4h4"></path>',
        },
        {
            key: 'team',
            label: 'Team',
            href: 'pages/student/team.html',
            icon: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87m-2-11.96a4 4 0 0 1 0 7.75"></path>',
        },
        {
            key: 'leaderboard',
            label: 'Leaderboard',
            href: 'pages/student/leaderboard.html',
            icon: '<path d="M8 21h8M12 17v4M7 4h10v4a5 5 0 0 1-10 0V4Zm0 2H4v2a4 4 0 0 0 4 4m9-6h3v2a4 4 0 0 1-4 4"></path>',
        },
    ];

    const navIcon = item => `
        <svg class="h-6 w-6 shrink-0 fill-none stroke-current stroke-[2.1]" viewBox="0 0 24 24" aria-hidden="true">
            ${item.icon}
        </svg>`;

    const desktopLink = (item, activePage) => {
        const active = item.key === activePage;
        return `
            <a
                class="group flex min-h-14 items-center gap-4 overflow-hidden rounded-2xl border px-3 text-sm font-black transition ${active ? 'border-[#C6F24E]/30 bg-[#397565]/55 text-[#C6F24E] shadow-lg shadow-black/20' : 'border-white/5 bg-white/[.06] text-[#F3F0E9]/75 hover:border-[#C6F24E]/20 hover:bg-white/[.1] hover:text-[#C6F24E]'}"
                href="${item.href}"
                aria-current="${active ? 'page' : 'false'}"
                title="${item.label}"
            >
                ${navIcon(item)}
                <span class="hidden whitespace-nowrap" data-sidebar-label>${item.label}</span>
            </a>`;
    };

    const mobileLink = (item, activePage) => {
        const active = item.key === activePage;
        const icon = navIcon(item).replace('h-6 w-6', 'h-5 w-5');
        return `
            <a
                class="flex min-h-11 flex-col items-center justify-center gap-1 rounded-xl text-[10px] font-black ${active ? 'bg-[#397565]/55 text-[#C6F24E] ring-1 ring-[#C6F24E]/20' : 'text-[#F3F0E9]/65'}"
                href="${item.href}"
                aria-current="${active ? 'page' : 'false'}"
            >
                ${icon}
                <span class="max-w-full truncate">${item.label}</span>
            </a>`;
    };

    const renderShell = (activePage, user) => {
        const initials = `${user.first_name?.[0] || ''}${user.last_name?.[0] || ''}`.toUpperCase() || 'ST';
        const name = user.full_name || `${user.first_name || ''} ${user.last_name || ''}`.trim();

        document.querySelector('[data-student-sidebar-host]').outerHTML = `
            <aside
                class="fixed inset-y-0 left-0 z-50 hidden w-20 flex-col border-r border-[#397565]/35 bg-[#121017] text-[#F3F0E9] shadow-[8px_0_30px_rgba(18,16,23,.16)] transition-[width] duration-200 lg:flex"
                data-student-sidebar
                aria-label="Desktop student navigation"
            >
                <div class="flex h-[68px] shrink-0 items-center gap-3 border-b border-white/10 px-3" data-sidebar-header>
                    <span class="hidden flex-1 whitespace-nowrap text-center text-xs font-black uppercase tracking-[.12em] text-[#C6F24E]" data-sidebar-label>Student Menu</span>
                    <button
                        class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-[#C6F24E]/25 bg-[#C6F24E]/12 text-[#C6F24E] shadow-sm transition hover:bg-[#C6F24E]/20 focus-visible:ring-2 focus-visible:ring-[#C6F24E]"
                        type="button"
                        data-student-sidebar-toggle
                        aria-controls="student-desktop-nav"
                        aria-label="Expand navigation"
                        aria-expanded="false"
                    >
                        <svg class="h-6 w-6 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
                <nav class="grid gap-2 px-3 py-5" id="student-desktop-nav" aria-label="Main navigation">
                    ${pages.map(item => desktopLink(item, activePage)).join('')}
                </nav>
                <div class="mx-3 mb-5 mt-auto hidden rounded-2xl border border-white/10 bg-white/[.06] p-3" data-sidebar-label>
                    <span class="block text-[9px] font-black uppercase tracking-wider text-[#C6F24E]">Signed in as</span>
                    <strong class="mt-1 block truncate text-xs text-[#F3F0E9]">${escapeHtml(name)}</strong>
                </div>
            </aside>`;

        document.querySelector('[data-student-header-host]').outerHTML = `
            <header
                class="sticky top-0 z-40 border-b border-[#121017]/8 bg-white/90 backdrop-blur-xl transition-[margin] duration-200 lg:ml-20"
                data-student-header
                data-student-sidebar-content
            >
                <nav class="flex h-[68px] w-full items-center pl-3 pr-2 sm:pl-4 sm:pr-3 lg:px-4" aria-label="Student header">
                    <a class="flex items-center gap-2.5 text-lg font-black tracking-tight" href="pages/student/home.html">
                        <img class="h-9 w-9 rounded-full object-contain" src="assets/images/cite-logo.png" alt="CITE logo">
                        <span>CITE<span class="text-[#397565]">.</span></span>
                    </a>

                    <details class="group relative ml-auto" data-notification-menu>
                        <summary class="relative grid h-11 w-11 cursor-pointer list-none place-items-center rounded-full bg-[#121017]/8 text-[#121017] transition hover:bg-[#397565]/15 hover:text-[#397565]" aria-label="Notifications">
                            <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 22a2.5 2.5 0 0 0 2.35-1.65h-4.7A2.5 2.5 0 0 0 12 22Zm7-6.5-1.5-2V9a5.5 5.5 0 0 0-4.25-5.35V3a1.25 1.25 0 0 0-2.5 0v.65A5.5 5.5 0 0 0 6.5 9v4.5l-1.5 2V18h14v-2.5Z"></path>
                            </svg>
                            <span class="absolute -right-1 -top-1 hidden min-h-5 min-w-5 place-items-center rounded-full border-2 border-white bg-[#FF4D4F] px-1 text-[9px] font-black leading-none text-white" data-notification-count>0</span>
                        </summary>
                        <div class="absolute right-0 top-[calc(100%+.65rem)] w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-[#121017]/10 bg-white shadow-[0_22px_60px_rgba(18,16,23,.18)]">
                            <div class="flex items-center justify-between gap-3 border-b border-[#121017]/8 px-4 py-3">
                                <div>
                                    <strong class="block text-sm font-black">Notifications</strong>
                                    <span class="text-[10px] text-[#121017]/45" data-notification-summary>No unread notifications</span>
                                </div>
                                <button class="hidden shrink-0 text-[10px] font-black text-[#397565] hover:underline" type="button" data-mark-all-notifications>Mark all as read</button>
                            </div>
                            <div class="max-h-80 overflow-y-auto" data-notification-list></div>
                        </div>
                    </details>

                    <details class="group relative ml-1" data-account-menu>
                        <summary class="flex min-h-11 cursor-pointer list-none items-center gap-1 rounded-full p-1 transition hover:bg-[#397565]/7">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#C6F24E] text-xs font-black text-[#121017] ring-2 ring-[#397565]/10">${escapeHtml(initials)}</span>
                            <svg class="h-4 w-4 fill-none stroke-current stroke-2 transition group-open:rotate-180" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="m7 10 5 5 5-5"></path>
                            </svg>
                        </summary>
                        <div class="absolute right-0 top-[calc(100%+.6rem)] w-64 rounded-2xl border border-[#121017]/10 bg-white p-2 shadow-[0_22px_60px_rgba(18,16,23,.16)]">
                            <div class="border-b border-[#121017]/7 px-3 py-3">
                                <strong class="block truncate text-sm">${escapeHtml(name)}</strong>
                                <span class="block truncate text-xs text-[#121017]/45">${escapeHtml(user.email || '')}</span>
                            </div>
                            <button class="flex min-h-11 w-full items-center rounded-xl px-3 text-sm font-bold text-[#FF6B2C] hover:bg-[#FF6B2C]/8" type="button" data-student-logout>
                                Logout
                            </button>
                        </div>
                    </details>
                </nav>
            </header>`;

        document.body.insertAdjacentHTML('beforeend', `
            <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-[#397565]/35 bg-[#121017]/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-12px_35px_rgba(18,16,23,.18)] backdrop-blur-xl lg:hidden" aria-label="Student pages">
                <div class="mx-auto grid h-[76px] max-w-3xl grid-cols-5 gap-1 px-1.5 py-1.5">
                    ${pages.map(item => mobileLink(item, activePage)).join('')}
                </div>
            </nav>`);
    };

    const notificationEmptyState = () => `
        <div class="px-6 py-10 text-center">
            <span class="mx-auto grid h-11 w-11 place-items-center rounded-full bg-[#121017]/7 text-[#121017]/45">
                <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 22a2.5 2.5 0 0 0 2.35-1.65h-4.7A2.5 2.5 0 0 0 12 22Zm7-6.5-1.5-2V9a5.5 5.5 0 0 0-4.25-5.35V3a1.25 1.25 0 0 0-2.5 0v.65A5.5 5.5 0 0 0 6.5 9v4.5l-1.5 2V18h14v-2.5Z"></path>
                </svg>
            </span>
            <strong class="mt-3 block text-sm">No notifications yet</strong>
            <span class="mt-1 block text-[11px] text-[#121017]/45">Review updates will appear here.</span>
        </div>`;

    const timeAgo = value => {
        if (!value) return 'Recently';
        const difference = Date.now() - new Date(value.replace(' ', 'T')).getTime();
        const minutes = Math.max(1, Math.floor(difference / 60000));
        if (minutes < 60) return `${minutes}m ago`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours}h ago`;
        return `${Math.floor(hours / 24)}d ago`;
    };

    const renderNotifications = data => {
        const count = Number(data.unread_notifications || 0);
        const badge = document.querySelector('[data-notification-count]');
        const summary = document.querySelector('[data-notification-summary]');
        const markAll = document.querySelector('[data-mark-all-notifications]');
        const list = document.querySelector('[data-notification-list]');
        badge.textContent = count > 9 ? '9+' : String(count);
        badge.classList.toggle('hidden', count === 0);
        badge.classList.toggle('grid', count > 0);
        markAll.classList.toggle('hidden', count === 0);
        summary.textContent = count === 0
            ? 'No unread notifications'
            : `${count} unread ${count === 1 ? 'notification' : 'notifications'}`;

        list.innerHTML = data.notifications.length
            ? data.notifications.map(notification => `
                <article class="flex gap-3 border-b border-[#121017]/8 px-4 py-3 last:border-b-0 ${notification.is_read ? 'bg-white' : 'bg-[#C6F24E]/10'}">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full ${notification.is_read ? 'bg-[#121017]/15' : 'bg-[#397565]'}"></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold leading-5">${escapeHtml(notification.message)}</p>
                        <time class="mt-1 block text-[10px] text-[#121017]/40">${timeAgo(notification.created_at)}</time>
                    </div>
                    ${notification.is_read ? '' : `<button class="shrink-0 self-center text-[10px] font-black text-[#397565] hover:underline" type="button" data-mark-notification="${escapeHtml(notification.id)}">Mark read</button>`}
                </article>`).join('')
            : notificationEmptyState();
    };

    const loadNotifications = async () => {
        const response = await axios.get(STUDENT_HOME_URL);
        renderNotifications(response.data.data);
    };

    const initializeInteractions = csrfToken => {
        const sidebar = document.querySelector('[data-student-sidebar]');
        const toggle = document.querySelector('[data-student-sidebar-toggle]');
        const content = document.querySelectorAll('[data-student-sidebar-content]');

        const setExpanded = expanded => {
            sidebar.classList.toggle('w-64', expanded);
            sidebar.classList.toggle('w-20', !expanded);
            document.querySelector('[data-sidebar-header]')?.classList.toggle('justify-between', expanded);
            document.querySelectorAll('[data-sidebar-label]').forEach(label => label.classList.toggle('hidden', !expanded));
            content.forEach(element => {
                element.classList.toggle('lg:ml-64', expanded);
                element.classList.toggle('lg:ml-20', !expanded);
            });
            toggle.setAttribute('aria-expanded', String(expanded));
            toggle.setAttribute('aria-label', expanded ? 'Collapse navigation' : 'Expand navigation');
        };

        setExpanded(false);
        toggle?.addEventListener('click', () => {
            setExpanded(toggle.getAttribute('aria-expanded') !== 'true');
        });

        const menus = document.querySelectorAll('[data-notification-menu], [data-account-menu]');
        menus.forEach(menu => menu.addEventListener('toggle', () => {
            if (!menu.open) return;
            menus.forEach(other => {
                if (other !== menu) other.removeAttribute('open');
            });
        }));
        document.addEventListener('click', event => {
            menus.forEach(menu => {
                if (menu.open && !menu.contains(event.target)) menu.removeAttribute('open');
            });
        });

        const markNotificationsRead = async notificationId => {
            await axios.post(STUDENT_HOME_URL, {
                action: 'mark_notifications_read',
                ...(notificationId ? {notification_id: notificationId} : {}),
            }, {headers: {'X-CSRF-Token': csrfToken}});
            await loadNotifications();
        };

        document.querySelector('[data-mark-all-notifications]')?.addEventListener('click', () => markNotificationsRead());
        document.querySelector('[data-notification-list]')?.addEventListener('click', event => {
            const button = event.target.closest('[data-mark-notification]');
            if (button) markNotificationsRead(button.dataset.markNotification);
        });

        document.querySelector('[data-student-logout]')?.addEventListener('click', async () => {
            try {
                await axios.post(LOGOUT_URL, {}, {headers: {'X-CSRF-Token': csrfToken}});
            } finally {
                window.location.href = './';
            }
        });
    };

    const initialize = async activePage => {
        const response = await axios.get(SESSION_URL);
        if (!response.data.authenticated || response.data.user?.role !== 'Student') {
            window.location.href = './';
            throw new Error('Student authentication required.');
        }
        renderShell(activePage, response.data.user);
        initializeInteractions(response.data.csrf_token);
        await loadNotifications();
        return response.data.user;
    };

    const formatDate = (value, includeTime = true) => {
        if (!value) return 'To be announced';
        const options = {month: 'short', day: 'numeric', year: 'numeric'};
        if (includeTime) Object.assign(options, {hour: 'numeric', minute: '2-digit'});
        return new Intl.DateTimeFormat('en-PH', options).format(new Date(value.replace(' ', 'T')));
    };

    const timeOnly = value => {
        if (!value) return '—';
        const normalized = value.includes(' ') ? value.replace(' ', 'T') : `2000-01-01T${value}`;
        return new Intl.DateTimeFormat('en-PH', {hour: 'numeric', minute: '2-digit'}).format(new Date(normalized));
    };

    const escapeHtml = value => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    window.StudentPortal = {initialize, formatDate, timeOnly, escapeHtml};
})();
