(() => {
    'use strict';

    const {initialize, formatDate, escapeHtml} = window.StudentPortal;
    const page = document.querySelector('[data-team-page]');
    let directoryState = {members: [], search: '', page: 1, pagination: {current_page: 1, last_page: 1, total: 0}};
    let directoryRequest = 0;
    let directorySearchTimer;

    const safeColor = value => /^#[0-9a-f]{6}$/i.test(value || '') ? value : '#397565';

    const emptyTeam = () => `
        <div class="student-stage">
            <p class="student-stage__eyebrow">Your people / Team assignment</p>
            <h1 class="student-stage__title">Your team is taking shape.</h1>
            <p class="student-stage__copy">No team assigned yet. An adviser will assign yours for the current school year. You can still explore events while you wait.</p>
            <div class="student-stage__actions"><a class="student-stage__action student-stage__action--primary" href="pages/student/events.html">Explore events <span aria-hidden="true">→</span></a></div>
            <span class="student-stage__number" aria-hidden="true">05</span>
        </div>`;

    const member = (person, isCurrent = false) => `
        <div class="student-team-member ${isCurrent ? 'student-team-member--current' : ''} flex items-center gap-3 rounded-2xl p-3">
            <span class="student-team-member__avatar grid h-10 w-10 shrink-0 place-items-center rounded-full text-[10px] font-black">${escapeHtml(person.initials)}</span>
            <div class="min-w-0 flex-1">
                <span class="flex min-w-0 items-center gap-2">
                    <strong class="block min-w-0 truncate text-xs">${escapeHtml(person.full_name)}</strong>
                    ${isCurrent ? '<em class="shrink-0 rounded-full bg-[#397565] px-2 py-0.5 text-[9px] font-black not-italic text-white">You</em>' : ''}
                </span>
                <span class="text-[10px] text-[#121017]/40">${escapeHtml(person.year_level || 'Student')}</span>
            </div>
        </div>`;

    const renderDirectory = () => {
        const list = page.querySelector('[data-member-list]');
        const count = page.querySelector('[data-member-count]');
        const pageLabel = page.querySelector('[data-member-page-label]');
        const previous = page.querySelector('[data-member-previous]');
        const next = page.querySelector('[data-member-next]');
        if (!list || !count || !pageLabel || !previous || !next) return;

        const pagination = directoryState.pagination;
        list.innerHTML = directoryState.members.length
            ? directoryState.members.map(person => member(person)).join('')
            : '<div class="col-span-full rounded-2xl border border-dashed border-[#121017]/12 px-5 py-10 text-center text-sm text-[#121017]/45">No teammate matches that search.</div>';
        count.textContent = directoryState.search
            ? `${pagination.total} match${pagination.total === 1 ? '' : 'es'}`
            : `${pagination.total} teammate${pagination.total === 1 ? '' : 's'}`;
        pageLabel.textContent = `Page ${pagination.current_page} of ${pagination.last_page}`;
        previous.disabled = pagination.current_page <= 1;
        next.disabled = pagination.current_page >= pagination.last_page;
    };

    const loadDirectory = async (targetPage = directoryState.page) => {
        const request = ++directoryRequest;
        const count = page.querySelector('[data-member-count]');
        if (count) count.textContent = 'Loading teammates…';
        try {
            const response = await axios.get('api/student-portal.php', {params: {page: 'team_members', search: directoryState.search, page_number: targetPage}});
            if (request !== directoryRequest) return;
            directoryState.members = response.data.data.members;
            directoryState.pagination = response.data.data.pagination;
            directoryState.page = response.data.data.pagination.current_page;
            renderDirectory();
        } catch (error) {
            if (request !== directoryRequest) return;
            const list = page.querySelector('[data-member-list]');
            if (list) list.innerHTML = '<div class="col-span-full rounded-2xl border border-dashed border-[#FF6B2C]/20 px-5 py-10 text-center text-sm text-[#FF6B2C]">The team directory could not be loaded.</div>';
        }
    };

    const installDirectory = () => {
        const search = page.querySelector('[data-member-search]');
        search?.addEventListener('input', event => {
            directoryState.search = event.target.value;
            directoryState.page = 1;
            clearTimeout(directorySearchTimer);
            directorySearchTimer = setTimeout(() => loadDirectory(1), 300);
        });
        page.querySelector('[data-member-previous]')?.addEventListener('click', () => {
            if (directoryState.page <= 1) return;
            loadDirectory(directoryState.page - 1);
            page.querySelector('[data-team-directory]')?.scrollIntoView({behavior: 'smooth', block: 'start'});
        });
        page.querySelector('[data-member-next]')?.addEventListener('click', () => {
            if (directoryState.page >= directoryState.pagination.last_page) return;
            loadDirectory(directoryState.page + 1);
            page.querySelector('[data-team-directory]')?.scrollIntoView({behavior: 'smooth', block: 'start'});
        });
        loadDirectory(1);
    };

    const teamPage = team => {
        const currentMember = team.current_member;
        directoryState = {
            members: [],
            search: '',
            page: 1,
            pagination: {current_page: 1, last_page: 1, total: Math.max(0, team.members_count - 1)},
        };
        const maxScore = Math.max(1, ...team.scores.map(score => Number(score.points) || 0));
        const scores = team.scores.map(score => `
            <div class="student-score-row">
                <div class="flex items-center justify-between gap-3"><span class="text-sm font-bold">${escapeHtml(score.category)}</span><strong class="text-sm text-[#397565]">${(Number(score.points) || 0).toFixed(1)} pts</strong></div>
                <div class="student-score-track"><span style="width:${Math.max(0, Math.min(100, (Number(score.points) || 0) / maxScore * 100))}%"></span></div>
            </div>`).join('');
        const leaders = team.leaders.map(leader => `<div class="border-b border-[#121017]/10 py-2 last:border-0"><strong class="block text-sm">${escapeHtml(leader.full_name)}</strong><span class="text-xs font-bold text-[#397565]">${escapeHtml(leader.position)}</span></div>`).join('');
        const activities = team.activities.map(activity => `<div class="border-b border-[#121017]/10 py-2 last:border-0"><strong class="block text-sm">${escapeHtml(activity.title)}</strong><span class="text-xs text-[#121017]/55">${formatDate(activity.start_at)} · ${escapeHtml(activity.location || 'CITE Campus')}</span></div>`).join('');
        const hasUpdates = team.scores.length || team.leaders.length || team.activities.length;

        return `
            <header class="student-team-hero ${team.image_path ? 'student-team-hero--has-image' : ''}" data-initial="${escapeHtml(team.name.charAt(0).toUpperCase())}">
                ${team.image_path ? `<img class="student-team-hero__image" src="${escapeHtml(team.image_path)}" alt="${escapeHtml(team.name)} tribe image">` : ''}
                <div><p class="student-stage__eyebrow">Your people / ${escapeHtml(team.school_year)}</p><h1>${escapeHtml(team.name)}</h1><p class="mt-4 text-sm leading-6 text-white/75">${team.members_count} members. One team. Every moment counts.</p><p class="student-team-hero__color"><span aria-hidden="true"></span>Your team color</p></div>
                <dl class="student-team-hero__metrics"><div><dt>Members</dt><dd>${team.members_count}</dd></div>${team.leaderboard_visible ? `<div><dt>Standing</dt><dd>${team.rank ? `#${team.rank}` : '—'}</dd></div><div><dt>Finalized score</dt><dd>${team.total_score.toFixed(1)} <small>pts</small></dd></div>` : '<div><dt>Standings</dt><dd class="text-sm">Awaiting reveal</dd></div>'}</dl>
            </header>
            ${currentMember ? `<div class="student-team-note"><span class="student-team-note__icon" aria-hidden="true">✓</span><div><p class="student-section-kicker">You belong here</p><p class="text-sm leading-6 text-[#121017]/70">${escapeHtml(currentMember.full_name)} · ${escapeHtml(currentMember.year_level || 'Student')}</p></div></div>` : ''}
            <section class="student-team-directory" data-team-directory id="student-team-directory">
                        <div class="student-section-heading mb-5"><div><p class="student-section-kicker">The people beside you</p><h2>Meet your teammates</h2><span class="mt-1 block text-xs text-[#121017]/50" data-member-count></span></div></div>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <label class="relative block w-full sm:max-w-md">
                                <span class="sr-only">Find a teammate</span>
                                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-[#121017]/35 stroke-2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                                <input class="h-11 w-full rounded-xl border border-[#121017]/15 bg-white pl-10 pr-3 text-sm outline-none placeholder:text-[#121017]/40 focus:border-[#397565] focus:ring-4 focus:ring-[#397565]/10" type="search" placeholder="Find a teammate…" data-member-search>
                            </label>
                        </div>
                        <div class="mt-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-3" data-member-list></div>
                        <nav class="mt-5 flex items-center justify-between gap-3 border-t border-[#121017]/7 pt-4" aria-label="Team directory pages">
                            <button class="min-h-10 rounded-xl border border-[#121017]/10 px-4 text-xs font-black text-[#397565] disabled:cursor-not-allowed disabled:opacity-40" type="button" data-member-previous>Previous</button>
                            <span class="text-xs font-bold text-[#121017]/45" data-member-page-label></span>
                            <button class="min-h-10 rounded-xl border border-[#121017]/10 px-4 text-xs font-black text-[#397565] disabled:cursor-not-allowed disabled:opacity-40" type="button" data-member-next>Next</button>
                        </nav>
            </section>
            ${hasUpdates ? `<div class="mt-9 grid gap-8 md:grid-cols-2">
                ${team.scores.length ? `<section><div class="student-section-heading"><div><p class="student-section-kicker">On the board</p><h2>Category scores</h2></div></div><div class="student-section-rule"></div><div class="space-y-5">${scores}</div></section>` : ''}
                ${team.activities.length ? `<section><div class="student-section-heading"><div><p class="student-section-kicker">Coming up</p><h2>Team activities</h2></div></div><div class="student-section-rule"></div><div>${activities}</div></section>` : ''}
                ${team.leaders.length ? `<section><div class="student-section-heading"><div><p class="student-section-kicker">The crew</p><h2>Team leaders</h2></div></div><div class="student-section-rule"></div><div>${leaders}</div></section>` : ''}
            </div>` : `<section class="student-team-note mt-6"><span class="student-team-note__icon" aria-hidden="true">✦</span><div><p class="student-section-kicker">The season starts here</p><h2 class="text-lg font-black">More to come from ${escapeHtml(team.name)}</h2><p class="mt-1 text-sm leading-6 text-[#121017]/60">Finalized scores, team leaders, and activities will appear as they’re announced.</p></div></section>`}`;
    };

    const load = async () => {
        await initialize('team');
        const response = await axios.get('api/student-portal.php', {params: {page: 'team'}});
        const team = response.data.data.team;
        page.style.setProperty('--team-accent', team ? safeColor(team.color) : '#C6F24E');
        page.innerHTML = team ? teamPage(team) : emptyTeam();
        if (team) installDirectory();
    };

    load().catch(() => {
        page.innerHTML = '<div class="rounded-3xl bg-white p-10 text-center font-bold text-[#FF6B2C]">Team information could not be loaded.</div>';
    });
})();
