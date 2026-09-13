(() => {
    'use strict';

    let csrfToken = '';

    const number = value => new Intl.NumberFormat('en-US').format(Number(value || 0));
    const time = value => new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit' }).format(new Date(String(value).replace(' ', 'T')));
    const shortDate = value => new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric' }).format(new Date(String(value).replace(' ', 'T')));
    const sectionArticle = heading => [...document.querySelectorAll('.admin-main article')]
        .find(article => article.querySelector('h2')?.textContent.trim() === heading);

    const applyAccount = user => {
        const displayName = user.full_name || user.username;
        const initials = `${user.first_name?.[0] || ''}${user.last_name?.[0] || ''}`.toUpperCase();
        const hour = new Date().getHours();
        const greeting = hour < 12 ? 'morning' : (hour < 18 ? 'afternoon' : 'evening');
        document.querySelector('.admin-main > header h1').textContent = `Good ${greeting}, ${user.first_name}.`;

        const account = document.querySelector('[data-account-menu]');
        const avatar = account.querySelector('summary > span:first-child');
        avatar.childNodes[0].textContent = initials;
        account.querySelector('summary strong').textContent = displayName;
        account.querySelector('summary small').textContent = user.role;
        account.querySelector('div > div strong').textContent = displayName;
        account.querySelector('div > div span').textContent = user.email || user.username;
    };

    const applyPrimaryMetrics = data => {
        const cards = document.querySelectorAll('[aria-label="Primary dashboard indicators"] > article');
        cards[0].querySelector('strong').textContent = data.stats.attendance_rate === null ? '—' : `${Number(data.stats.attendance_rate).toFixed(1)}%`;
        cards[0].querySelector('p:last-child').textContent = data.today_attendance.marked
            ? `${number(data.today_attendance.attended)} of ${number(data.today_attendance.marked)} marked records attended`
            : 'No attendance has been marked today';
        cards[1].querySelector('strong').textContent = number(data.stats.students_present);
        cards[1].querySelector('p:last-child').textContent = `of ${number(data.stats.students)} registered students`;
        cards[2].querySelector('strong').textContent = number(data.stats.upcoming_events);
        cards[2].querySelector('p:last-child').textContent = data.upcoming_events[0]
            ? `Next: ${data.upcoming_events[0].title}`
            : 'No upcoming events scheduled';

        const people = document.querySelector('[aria-label="People overview"]');
        const totals = people.querySelectorAll('strong');
        [data.stats.total_users, data.stats.students, data.stats.faculty, data.stats.sbo]
            .forEach((value, index) => { totals[index].textContent = number(value); });
    };

    const applyAttendance = data => {
        const article = sectionArticle('Attendance Overview');
        const oldBody = article.querySelector('header').nextElementSibling;
        oldBody?.remove();

        if (!data.today_attendance.marked) {
            const empty = document.createElement('div');
            empty.className = 'px-6 py-12 text-center';
            empty.innerHTML = '<strong class="block text-sm text-slate-600">No attendance recorded yet</strong><p class="mt-1 text-xs text-slate-400">Today’s distribution will appear after attendance is marked.</p>';
            article.append(empty);
            return;
        }

        const body = document.createElement('div');
        body.className = 'p-6';
        const progress = document.createElement('div');
        progress.className = 'h-3 overflow-hidden rounded-full bg-slate-100';
        progress.setAttribute('role', 'progressbar');
        const bar = document.createElement('div');
        bar.className = 'h-full rounded-full bg-[#397565]';
        bar.style.width = `${Math.min(100, data.today_attendance.rate)}%`;
        progress.append(bar);
        const grid = document.createElement('div');
        grid.className = 'mt-6 grid grid-cols-2 gap-x-6 gap-y-5 sm:grid-cols-4';
        [['present', '#C6F24E'], ['late', '#FF6B2C'], ['absent', '#FF6B2C'], ['excused', '#12101740']].forEach(([status, color]) => {
            const item = document.createElement('div');
            const stripe = document.createElement('span');
            stripe.className = 'mb-2 block h-1 w-8 rounded-full';
            stripe.style.backgroundColor = color;
            const count = document.createElement('strong');
            count.className = 'text-2xl font-extrabold text-[#121017]';
            count.textContent = number(data.today_attendance[status]);
            const label = document.createElement('p');
            label.className = 'mt-1 text-xs font-semibold text-slate-500';
            label.textContent = status[0].toUpperCase() + status.slice(1);
            item.append(stripe, count, label);
            grid.append(item);
        });
        body.append(progress, grid);
        article.append(body);
    };

    const applyTodayEvents = data => {
        const article = sectionArticle('Today’s Events');
        [...article.children].slice(1).forEach(child => child.remove());
        if (!data.today_events.length) {
            const empty = document.createElement('div');
            empty.className = 'px-6 py-10 text-center';
            empty.innerHTML = '<strong class="block text-sm text-slate-600">No events today</strong><p class="mt-1 text-xs text-slate-400"></p>';
            empty.querySelector('p').textContent = data.upcoming_events[0]
                ? `Next: ${data.upcoming_events[0].title} on ${shortDate(data.upcoming_events[0].start_at)}`
                : 'Create an event to start building the schedule.';
            article.append(empty);
            return;
        }

        data.today_events.forEach(event => {
            const row = document.createElement('div');
            row.className = 'group flex gap-4 border-b border-slate-100 px-6 py-4 last:border-b-0 hover:bg-slate-50';
            const dot = document.createElement('span');
            dot.className = 'mt-1 flex flex-col items-center';
            dot.innerHTML = '<i class="h-2.5 w-2.5 rounded-full bg-[#2F3AE0]"></i><i class="mt-1 h-full min-h-8 w-px bg-slate-200"></i>';
            const details = document.createElement('span');
            details.className = 'grid min-w-0 flex-1 gap-1';
            const title = document.createElement('strong');
            title.className = 'truncate text-sm text-slate-700';
            title.textContent = event.title;
            const schedule = document.createElement('small');
            schedule.className = 'text-xs text-slate-400';
            schedule.textContent = `${time(event.start_at)}–${time(event.end_at)} · ${event.location || 'Location not set'}`;
            const assigned = document.createElement('small');
            assigned.className = 'text-xs text-slate-400';
            assigned.textContent = `${number(event.assigned_count)} ${Number(event.assigned_count) === 1 ? 'person' : 'people'} in charge`;
            details.append(title, schedule, assigned);
            row.append(dot, details);
            article.append(row);
        });
    };

    const applyRecentAttendance = data => {
        const article = sectionArticle('Recent Attendance');
        [...article.children].slice(1).forEach(child => child.remove());
        if (!data.recent_attendance.length) {
            const empty = document.createElement('div');
            empty.className = 'px-6 py-12 text-center';
            empty.innerHTML = '<strong class="block text-sm text-slate-600">No recent attendance</strong><p class="mt-1 text-xs text-slate-400">Student attendance activity will appear here.</p>';
            article.append(empty);
            return;
        }
        const wrapper = document.createElement('div');
        wrapper.className = 'overflow-x-auto';
        const table = document.createElement('table');
        table.className = 'w-full min-w-[620px] text-left';
        table.innerHTML = '<thead class="bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-400"><tr><th class="px-6 py-3">Student</th><th class="px-4 py-3">Event</th><th class="px-4 py-3">Status</th><th class="px-6 py-3 text-right">Recorded</th></tr></thead><tbody class="divide-y divide-slate-100"></tbody>';
        const tbody = table.querySelector('tbody');
        data.recent_attendance.forEach(attendance => {
            const row = document.createElement('tr');
            ['student_name', 'event_title', 'status', 'updated_at'].forEach((field, index) => {
                const cell = document.createElement('td');
                cell.className = index === 0 ? 'px-6 py-4 text-sm font-bold text-slate-700' : (index === 3 ? 'px-6 py-4 text-right text-xs text-slate-400' : 'px-4 py-4 text-sm text-slate-500');
                cell.textContent = field === 'updated_at' ? dateTime(attendance.checked_in_at || attendance.updated_at) : attendance[field];
                row.append(cell);
            });
            tbody.append(row);
        });
        wrapper.append(table);
        article.append(wrapper);
    };

    const applyLeaderboard = data => {
        const article = sectionArticle('Team Leaderboard');
        article.querySelector('header strong').textContent = Number(data.stats.points_awarded).toLocaleString('en-US', { maximumFractionDigits: 2 });
        [...article.children].slice(1).forEach(child => child.remove());
        if (!data.leaderboard.length) {
            const empty = document.createElement('div');
            empty.className = 'px-6 py-12 text-center';
            empty.innerHTML = '<strong class="block text-sm text-slate-600">No team rankings yet</strong><p class="mt-1 text-xs text-slate-400">The leaderboard will populate after teams receive scores.</p>';
            article.append(empty);
            return;
        }
        data.leaderboard.forEach(team => {
            const row = document.createElement('div');
            row.className = 'flex items-center gap-4 border-b border-slate-100 px-6 py-4 last:border-b-0';
            const rank = document.createElement('span');
            rank.className = 'grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#C6F24E] text-xs font-extrabold';
            rank.textContent = team.rank;
            const details = document.createElement('span');
            details.className = 'grid min-w-0 flex-1 gap-1';
            const name = document.createElement('strong');
            name.className = 'truncate text-sm text-slate-700';
            name.textContent = team.name;
            const members = document.createElement('small');
            members.className = 'text-xs text-slate-400';
            members.textContent = `${number(team.members_count)} members`;
            details.append(name, members);
            const score = document.createElement('strong');
            score.className = 'text-lg font-extrabold text-[#121017]';
            score.textContent = Number(team.total_score).toLocaleString('en-US', { maximumFractionDigits: 2 });
            row.append(rank, details, score);
            article.append(row);
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
        document.addEventListener('click', event => {
            if (menu?.open && !menu.contains(event.target)) menu.removeAttribute('open');
        });
        const search = document.querySelector('[data-dashboard-search]');
        search?.addEventListener('input', () => {
            const query = search.value.trim().toLowerCase();
            document.querySelectorAll('.admin-main > section').forEach(section => section.classList.toggle('hidden', Boolean(query) && !section.innerText.toLowerCase().includes(query)));
        });
        document.querySelector('form[action="api/auth.php?action=logout"]')?.addEventListener('submit', async event => {
            event.preventDefault();
            try {
                await axios.post('api/auth.php?action=logout', {}, { headers: { 'X-CSRF-Token': csrfToken } });
            } finally {
                window.location.replace('./');
            }
        });
    };

    initializeInteractions();
    axios.get('api/auth.php?action=session')
        .then(response => {
            if (!response.data.authenticated || response.data.user?.role !== 'SBO Adviser') {
                window.location.replace('./');
                throw new Error('Unauthorized');
            }
            csrfToken = response.data.csrf_token;
            applyAccount(response.data.user);
            return axios.get('api/adviser-dashboard.php');
        })
        .then(response => {
            const data = response.data.data;
            applyPrimaryMetrics(data);
            applyAttendance(data);
            applyTodayEvents(data);
            applyRecentAttendance(data);
            applyLeaderboard(data);
        })
        .catch(error => {
            if (error.message !== 'Unauthorized') console.error('Unable to load adviser dashboard:', error);
        });
})();
