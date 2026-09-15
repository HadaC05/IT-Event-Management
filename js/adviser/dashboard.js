(() => {
    'use strict';

    let csrfToken = '';
    const number = value => new Intl.NumberFormat('en-US').format(Number(value || 0));
    const parseDate = value => value instanceof Date ? value : new Date(String(value).replace(' ', 'T'));
    const format = (value, options) => new Intl.DateTimeFormat('en-US', options).format(parseDate(value));
    const time = value => format(value, { hour: 'numeric', minute: '2-digit' });
    const shortDate = value => format(value, { month: 'short', day: 'numeric' });
    const dateTime = value => value ? format(value, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '—';

    const emptyState = (title, message, href = '', action = '') => {
        const element = document.createElement('div');
        element.className = 'px-6 py-10 text-center';
        element.innerHTML = '<span class="mx-auto grid h-11 w-11 place-items-center rounded-full bg-emerald-50 text-emerald-700" aria-hidden="true"><svg class="h-5 w-5 fill-none stroke-current stroke-2" viewBox="0 0 24 24"><path d="M5 12.5 9 16l10-10"/></svg></span><strong class="mt-4 block text-sm text-slate-700"></strong><p class="mt-1 text-xs leading-5 text-slate-400"></p>';
        element.querySelector('strong').textContent = title;
        element.querySelector('p').textContent = message;
        if (href && action) {
            const link = document.createElement('a');
            link.className = 'mt-4 inline-flex min-h-9 items-center rounded-xl border border-emerald-200 px-4 text-xs font-extrabold text-emerald-700 transition hover:bg-emerald-50';
            link.href = href;
            link.textContent = action;
            element.append(link);
        }
        return element;
    };

    const applyAccount = user => {
        const displayName = user.full_name || user.username;
        const initials = `${user.first_name?.[0] || ''}${user.last_name?.[0] || ''}`.toUpperCase();
        const hour = new Date().getHours();
        document.querySelector('.admin-main > header h1').textContent = `Good ${hour < 12 ? 'morning' : hour < 18 ? 'afternoon' : 'evening'}, ${user.first_name}.`;
        document.querySelector('[data-current-date]').textContent = format(new Date(), { weekday: 'long', month: 'long', day: 'numeric' });
        const account = document.querySelector('[data-account-menu]');
        account.querySelector('summary > span:first-child').childNodes[0].textContent = initials;
        account.querySelector('summary strong').textContent = displayName;
        account.querySelector('summary small').textContent = user.role;
        account.querySelector('div > div strong').textContent = displayName;
        account.querySelector('div > div span').textContent = user.email || user.username;
    };

    const renderOverview = data => {
        const values = { students: data.stats.students, teams: data.stats.active_teams, officers: data.stats.sbo, upcoming: data.stats.upcoming_events };
        Object.entries(values).forEach(([key, value]) => document.querySelector(`[data-overview="${key}"]`).textContent = number(value));
        if (Number(data.attention.pending_posts)) {
            const review = document.querySelector('[data-review-posts]');
            review.textContent = `Review posts · ${number(data.attention.pending_posts)}`;
            review.classList.remove('hidden');
            review.classList.add('inline-flex');
        }
    };

    const renderToday = data => {
        const count = document.querySelector('[data-today-count]');
        const content = document.querySelector('[data-today-content]');
        content.replaceChildren();
        if (!data.today_events.length) {
            count.textContent = 'Clear today';
            const next = data.upcoming_events[0];
            content.append(emptyState(
                'No event today',
                next ? `Next: ${next.title} · ${shortDate(next.start_at)} at ${time(next.start_at)}` : 'There are no upcoming events on the schedule.',
                next ? 'pages/adviser/events.html' : 'pages/adviser/events.html?create=1',
                next ? 'View schedule' : 'Create an event'
            ));
            return;
        }
        count.textContent = `${number(data.today_events.length)} today`;
        data.today_events.forEach(event => {
            const row = document.createElement('a');
            row.className = 'group grid gap-4 border-b border-slate-100 px-6 py-5 transition last:border-b-0 hover:bg-emerald-50 sm:flex sm:items-center sm:justify-between';
            row.href = `pages/adviser/attendance.html?event_id=${event.id}`;
            const details = document.createElement('div');
            details.className = 'min-w-0';
            details.innerHTML = '<div class="flex flex-wrap items-center gap-2"><strong class="truncate text-base font-black text-[#121017]"></strong><span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[9px] font-black uppercase tracking-wider text-emerald-700">Today</span></div><p class="mt-2 text-xs text-slate-500"></p><p class="mt-1 text-xs text-slate-400"></p>';
            details.querySelector('strong').textContent = event.title;
            details.querySelectorAll('p')[0].textContent = `${time(event.start_at)}–${time(event.end_at)} · ${event.location || 'Location not set'}`;
            details.querySelectorAll('p')[1].textContent = event.expected_count ? `${number(event.attendances_count)} of ${number(event.expected_count)} attendance records completed` : 'No students are currently expected';
            const rate = event.expected_count ? Math.min(100, Number(event.attendances_count) / Number(event.expected_count) * 100) : 0;
            const progress = document.createElement('div');
            progress.className = 'w-48';
            progress.innerHTML = '<div class="flex items-center justify-between gap-4"><span class="text-xs font-bold text-slate-500">Attendance</span><strong class="text-sm font-black text-[#397565]"></strong></div><div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100"><i class="block h-full rounded-full bg-[#397565]"></i></div>';
            progress.querySelector('strong').textContent = `${Math.round(rate)}%`;
            progress.querySelector('i').style.width = `${rate}%`;
            row.append(details, progress);
            content.append(row);
        });
    };

    const renderAttention = data => {
        const items = data.attention.items || [];
        const list = document.querySelector('[data-attention-list]');
        document.querySelector('[data-attention-total]').textContent = number(items.reduce((sum, item) => sum + Number(item.count), 0));
        list.replaceChildren();
        if (!items.length) {
            list.append(emptyState('All caught up', 'There is nothing requiring your attention right now.'));
            return;
        }
        items.forEach(item => {
            const link = document.createElement('a');
            link.className = 'group flex items-center gap-4 border-b border-slate-100 px-6 py-4 transition last:border-b-0 hover:bg-amber-50';
            link.href = item.href;
            link.innerHTML = '<span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-amber-50 text-sm font-black text-amber-700"></span><span class="min-w-0 flex-1"><strong class="block text-sm text-slate-700"></strong><small class="mt-1 block truncate text-xs text-slate-400"></small></span><span class="text-lg text-slate-300 transition group-hover:translate-x-1 group-hover:text-emerald-700">→</span>';
            link.querySelector('span').textContent = number(item.count);
            link.querySelector('strong').textContent = item.label;
            link.querySelector('small').textContent = item.description;
            list.append(link);
        });
    };

    const renderActivity = data => {
        const list = document.querySelector('[data-activity-list]');
        list.replaceChildren();
        if (!data.recent_activity.length) {
            list.append(emptyState('No recent activity', 'Updates will appear here as your team uses the system.'));
            return;
        }
        data.recent_activity.forEach(activity => {
            const row = document.createElement('div');
            row.className = 'flex gap-4 border-b border-slate-100 px-6 py-4 last:border-b-0';
            row.innerHTML = '<span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-300 ring-4 ring-emerald-100"></span><span class="min-w-0 flex-1"><strong class="block text-sm font-bold leading-5 text-slate-700"></strong><small class="mt-1 block text-xs text-slate-400"></small></span><time class="shrink-0 text-xs font-semibold text-slate-400"></time>';
            row.querySelector('strong').textContent = activity.description || activity.action;
            row.querySelector('small').textContent = [activity.actor_name, activity.event_title].filter(Boolean).join(' · ') || 'System activity';
            row.querySelector('time').textContent = dateTime(activity.created_at);
            list.append(row);
        });
    };

    const renderStandings = data => {
        const list = document.querySelector('[data-standings-list]');
        list.replaceChildren();
        if (!data.leaderboard.length) {
            list.append(emptyState('No rankings yet', 'Standings will appear after a tribe receives its first score.', 'pages/adviser/scores.html', 'Manage scores'));
            return;
        }
        data.leaderboard.forEach(team => {
            const row = document.createElement('div');
            row.className = 'flex items-center gap-4 border-b border-slate-100 px-6 py-4 last:border-b-0';
            const rank = document.createElement('span');
            rank.className = team.rank === 1 ? 'grid h-9 w-9 shrink-0 place-items-center rounded-full bg-emerald-300 text-xs font-black' : 'grid h-9 w-9 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-black text-slate-500';
            rank.textContent = team.rank;
            const details = document.createElement('span');
            details.className = 'min-w-0 flex-1';
            details.innerHTML = '<strong class="block truncate text-sm text-slate-700"></strong><small class="mt-1 block text-xs text-slate-400"></small>';
            details.querySelector('strong').textContent = team.name;
            details.querySelector('small').textContent = `${number(team.members_count)} members`;
            const score = document.createElement('strong');
            score.className = 'text-sm font-black text-[#121017]';
            score.textContent = `${Number(team.total_score).toLocaleString('en-US', { maximumFractionDigits: 2 })} pts`;
            row.append(rank, details, score);
            list.append(row);
        });
    };

    const initializeInteractions = () => {
        const sidebar = document.querySelector('#sidebar');
        const scrim = document.querySelector('[data-sidebar-scrim]');
        const trigger = document.querySelector('[aria-controls="sidebar"]');
        document.querySelectorAll('[data-sidebar-toggle]').forEach(button => button.addEventListener('click', () => {
            const opening = sidebar.classList.contains('-translate-x-full');
            sidebar.classList.toggle('-translate-x-full', !opening);
            sidebar.classList.toggle('translate-x-0', opening);
            scrim.classList.toggle('hidden', !opening);
            trigger?.setAttribute('aria-expanded', String(opening));
        }));
        const menu = document.querySelector('[data-account-menu]');
        document.addEventListener('click', event => { if (menu?.open && !menu.contains(event.target)) menu.removeAttribute('open'); });
        document.querySelector('form[action="api/auth.php?action=logout"]')?.addEventListener('submit', async event => {
            event.preventDefault();
            try { await axios.post('api/auth.php?action=logout', {}, { headers: { 'X-CSRF-Token': csrfToken } }); }
            finally { window.location.replace('./'); }
        });
    };

    initializeInteractions();
    axios.get('api/auth.php?action=session').then(response => {
        if (!response.data.authenticated || response.data.user?.role !== 'SBO Adviser') {
            window.location.replace('./');
            throw new Error('Unauthorized');
        }
        csrfToken = response.data.csrf_token;
        applyAccount(response.data.user);
        return axios.get('api/adviser-dashboard.php');
    }).then(response => {
        const data = response.data.data;
        renderOverview(data);
        renderToday(data);
        renderAttention(data);
        renderActivity(data);
        renderStandings(data);
    }).catch(error => {
        if (error.message === 'Unauthorized') return;
        console.error('Unable to load adviser dashboard:', error);
        document.querySelector('[data-today-content]')?.replaceChildren(emptyState('Dashboard unavailable', 'Refresh the page to try loading the latest data again.'));
    });
})();
