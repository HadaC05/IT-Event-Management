(() => {
    'use strict';

    const {initialize, formatDate, escapeHtml} = window.StudentPortal;
    const page = document.querySelector('[data-team-page]');
    const safeColor = value => /^#[0-9a-f]{6}$/i.test(String(value || '')) ? value : '#397565';
    const points = value => (Number(value) || 0).toFixed(1);
    let directory = {members: [], search: '', year: '', sort: 'name', page: 1, loaded: false, years: [], pagination: {current_page: 1, last_page: 1, total: 0}};
    let directoryRequest = 0;
    let directorySearchTimer;
    let showZeroScores = false;

    const emptyTeam = () => `
        <div class="student-stage">
            <p class="student-stage__eyebrow">Your people / Team assignment</p>
            <h1 class="student-stage__title">Your team is taking shape.</h1>
            <p class="student-stage__copy">No team assigned yet. An adviser will assign yours for the current school year. You can still explore events while you wait.</p>
            <div class="student-stage__actions"><a class="student-stage__action student-stage__action--primary" href="pages/student/events.html">Explore events <span aria-hidden="true">→</span></a></div>
            <span class="student-stage__number" aria-hidden="true">05</span>
        </div>`;

    const member = person => `
        <li class="student-team-member-compact">
            <span class="student-team-member__avatar" aria-hidden="true">${escapeHtml(person.initials || '?')}</span>
            <span class="student-team-member-compact__text"><strong>${escapeHtml(person.full_name)}</strong><small>${escapeHtml(person.year_level || 'Student')}</small></span>
        </li>`;

    const scoreRow = (score, maxScore) => {
        const value = Number(score.points) || 0;
        const width = Math.max(0, Math.min(100, value / maxScore * 100));
        return `<li class="student-team-score-item">
            <span><strong>${escapeHtml(score.category)}</strong><b>${points(value)} pts</b></span>
            <i class="student-score-track"><i style="width:${width}%"></i></i>
        </li>`;
    };

    const renderScores = team => {
        const allScores = [...team.scores].sort((a, b) => Number(b.points) - Number(a.points) || String(a.category).localeCompare(String(b.category)));
        const visible = showZeroScores ? allScores : allScores.filter(score => Number(score.points) !== 0);
        const maxScore = Math.max(1, ...allScores.map(score => Number(score.points) || 0));
        const list = page.querySelector('[data-all-scores]');
        const toggle = page.querySelector('[data-score-toggle]');
        if (!list || !toggle) return;
        list.innerHTML = visible.length ? visible.map(score => scoreRow(score, maxScore)).join('')
            : '<li class="student-team-empty">No non-zero scores to show yet.</li>';
        const zeroCount = allScores.filter(score => Number(score.points) === 0).length;
        toggle.hidden = zeroCount === 0;
        toggle.textContent = showZeroScores ? `Hide zero-score items (${zeroCount})` : `Show all including ${zeroCount} zero-score items`;
    };

    const renderDirectory = () => {
        const list = page.querySelector('[data-member-list]');
        const count = page.querySelector('[data-member-count]');
        const label = page.querySelector('[data-member-page-label]');
        const previous = page.querySelector('[data-member-previous]');
        const next = page.querySelector('[data-member-next]');
        const year = page.querySelector('[data-member-year]');
        if (!list || !count || !label || !previous || !next || !year) return;
        const pagination = directory.pagination;
        list.innerHTML = directory.members.length ? directory.members.map(member).join('')
            : '<li class="student-team-empty">No teammate matches these filters.</li>';
        count.textContent = `${pagination.total} ${directory.search || directory.year ? 'matching ' : ''}teammate${pagination.total === 1 ? '' : 's'}`;
        label.textContent = `Page ${pagination.current_page} of ${pagination.last_page}`;
        previous.disabled = pagination.current_page <= 1;
        next.disabled = pagination.current_page >= pagination.last_page;
        year.innerHTML = '<option value="">All year levels</option>' + directory.years.map(item => `<option value="${item.id}">${escapeHtml(item.label)}</option>`).join('');
        year.value = directory.year;
    };

    const loadDirectory = async (targetPage = directory.page) => {
        const request = ++directoryRequest;
        const count = page.querySelector('[data-member-count]');
        if (count) count.textContent = 'Loading teammates…';
        try {
            const response = await axios.get('api/student-portal.php', {params: {
                page: 'team_members', search: directory.search, year_level_id: directory.year,
                sort: directory.sort, page_number: targetPage,
            }});
            if (request !== directoryRequest) return;
            directory.members = response.data.data.members;
            directory.years = response.data.data.year_levels || [];
            directory.pagination = response.data.data.pagination;
            directory.page = directory.pagination.current_page;
            directory.loaded = true;
            renderDirectory();
        } catch {
            if (request !== directoryRequest) return;
            const list = page.querySelector('[data-member-list]');
            if (list) list.innerHTML = '<li class="student-team-empty">The team directory could not be loaded. Try another filter.</li>';
            if (count) count.textContent = 'Could not load teammates';
        }
    };

    const activateTab = id => {
        page.querySelectorAll('[data-team-tab]').forEach(button => {
            const active = button.dataset.teamTab === id;
            button.setAttribute('aria-selected', String(active));
            button.tabIndex = active ? 0 : -1;
        });
        page.querySelectorAll('[data-team-panel]').forEach(panel => { panel.hidden = panel.dataset.teamPanel !== id; });
        if (id === 'members' && !directory.loaded) loadDirectory(1);
    };

    const installControls = team => {
        page.querySelectorAll('[data-team-tab]').forEach(button => button.addEventListener('click', () => activateTab(button.dataset.teamTab)));
        page.querySelector('[data-team-tabs]')?.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            const tabs = [...page.querySelectorAll('[data-team-tab]')];
            const index = tabs.indexOf(document.activeElement);
            if (index < 0) return;
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1
                : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
            tabs[next].focus();
            activateTab(tabs[next].dataset.teamTab);
        });
        page.querySelectorAll('[data-go-tab]').forEach(button => button.addEventListener('click', () => {
            const tab = page.querySelector('[data-team-tab="' + button.dataset.goTab + '"]');
            tab?.click();
            tab?.focus();
        }));
        page.querySelector('[data-member-search]')?.addEventListener('input', event => {
            directory.search = event.target.value;
            directory.page = 1;
            clearTimeout(directorySearchTimer);
            directorySearchTimer = setTimeout(() => loadDirectory(1), 300);
        });
        page.querySelector('[data-member-year]')?.addEventListener('change', event => {
            directory.year = event.target.value;
            directory.page = 1;
            loadDirectory(1);
        });
        page.querySelector('[data-member-sort]')?.addEventListener('change', event => {
            directory.sort = event.target.value;
            directory.page = 1;
            loadDirectory(1);
        });
        page.querySelector('[data-member-previous]')?.addEventListener('click', () => { if (directory.page > 1) loadDirectory(directory.page - 1); });
        page.querySelector('[data-member-next]')?.addEventListener('click', () => { if (directory.page < directory.pagination.last_page) loadDirectory(directory.page + 1); });
        page.querySelector('[data-score-toggle]')?.addEventListener('click', () => { showZeroScores = !showZeroScores; renderScores(team); });
    };

    const teamPage = team => {
        const current = team.current_member;
        const scores = team.leaderboard_visible ? team.scores : [];
        const nonZero = [...scores].filter(score => Number(score.points) !== 0).sort((a,b) => Number(b.points) - Number(a.points));
        const maxScore = Math.max(1, ...scores.map(score => Number(score.points) || 0));
        const leaders = team.leaders || [];
        const activities = team.activities || [];
        directory = {members: [], search: '', year: '', sort: 'name', page: 1, loaded: false, years: [], pagination: {current_page: 1, last_page: 1, total: Math.max(0, team.members_count - 1)}};
        return `
            <header class="student-team-hero student-team-hero--compact ${team.image_path ? 'student-team-hero--has-image' : ''}" data-initial="${escapeHtml(team.name.charAt(0).toUpperCase())}">
                ${team.image_path ? `<img class="student-team-hero__image" src="${escapeHtml(team.image_path)}" alt="${escapeHtml(team.name)} tribe image">` : ''}
                <div><span class="student-team-hero__badge" aria-label="${escapeHtml(team.name)} tribe logo">${team.image_path ? `<img src="${escapeHtml(team.image_path)}" alt="">` : escapeHtml(team.name.charAt(0).toUpperCase())}</span><p class="student-stage__eyebrow">Your people / ${escapeHtml(team.school_year)}</p><h1>${escapeHtml(team.name)}</h1><p class="student-team-hero__color"><span aria-hidden="true"></span>Your team color</p></div>
                <dl class="student-team-hero__metrics"><div><dt>Members</dt><dd>${team.members_count}</dd></div>${team.leaderboard_visible ? `<div><dt>Standing</dt><dd>${team.rank ? `#${team.rank}` : '—'}</dd></div><div><dt>Finalized score</dt><dd>${points(team.total_score)} <small>pts</small></dd></div>` : '<div><dt>Standings</dt><dd class="text-sm">Awaiting reveal</dd></div>'}</dl>
            </header>
            <div class="student-team-tabs" role="tablist" aria-label="Team information" data-team-tabs>
                ${[['overview','Overview'],['members','Members'],['scores','Scores'],['leaders','Leaders']].map(([id,label]) => `<button type="button" id="team-tab-${id}" role="tab" aria-controls="team-panel-${id}" aria-selected="${id === 'overview'}" tabindex="${id === 'overview' ? '0' : '-1'}" data-team-tab="${id}">${label}</button>`).join('')}
            </div>
            <section class="student-team-panel" id="team-panel-overview" role="tabpanel" aria-labelledby="team-tab-overview" tabindex="0" data-team-panel="overview">
                ${current ? `<div class="student-team-you"><span class="student-team-member__avatar" aria-hidden="true">${escapeHtml(current.initials || '?')}</span><span><strong>You belong here</strong><small>${escapeHtml(current.full_name)} · ${escapeHtml(current.year_level || 'Student')}</small></span></div>` : ''}
                <div class="student-team-overview-grid">
                    <div class="student-team-overview-column">
                    <section class="student-team-card"><div class="student-team-card__heading"><div><p class="student-section-kicker">People</p><h2>Your teammates</h2></div><button type="button" data-go-tab="members">Browse ${team.members_count} members →</button></div><p>Search by name, filter by year, and browse 24 teammates per page.</p></section>
                    <section class="student-team-card"><div class="student-team-card__heading"><div><p class="student-section-kicker">The crew</p><h2>Team leaders</h2></div><button type="button" data-go-tab="leaders">View all →</button></div>${leaders.length ? `<ul class="student-team-mini-list">${leaders.slice(0,3).map(leader => `<li><strong>${escapeHtml(leader.full_name)}</strong><small>${escapeHtml(leader.position)}</small></li>`).join('')}</ul>` : '<p>No team leaders listed yet.</p>'}</section>
                    </div>
                    <div class="student-team-overview-column">
                    <section class="student-team-card"><div class="student-team-card__heading"><div><p class="student-section-kicker">On the board</p><h2>Top category scores</h2></div><button type="button" data-go-tab="scores">All scores →</button></div>${team.leaderboard_visible ? (nonZero.length ? `<ul class="student-team-score-list">${nonZero.slice(0,5).map(score => scoreRow(score,maxScore)).join('')}</ul>` : '<p>No finalized category scores yet.</p>') : '<p>Scores will appear after the leaderboard reveal.</p>'}</section>
                    ${activities.length ? `<section class="student-team-card"><div class="student-team-card__heading"><div><p class="student-section-kicker">Coming up</p><h2>Team activities</h2></div></div><ul class="student-team-mini-list">${activities.slice(0,3).map(activity => `<li><strong>${escapeHtml(activity.title)}</strong><small>${formatDate(activity.start_at)} · ${escapeHtml(activity.location || 'CITE Campus')}</small></li>`).join('')}</ul></section>` : ''}
                    </div>
                </div>
            </section>
            <section class="student-team-panel" id="team-panel-members" role="tabpanel" aria-labelledby="team-tab-members" tabindex="0" data-team-panel="members" hidden>
                <div class="student-team-panel__heading"><div><p class="student-section-kicker">The people beside you</p><h2>Members</h2><small data-member-count>Open this tab to load teammates</small></div></div>
                <div class="student-team-directory" data-team-directory>
                    <div class="student-team-directory__filters">
                        <label><span>Find a teammate</span><input type="search" maxlength="100" placeholder="Search by name…" data-member-search></label>
                        <label><span>Year level</span><select data-member-year><option value="">All year levels</option></select></label>
                        <label><span>Sort</span><select data-member-sort><option value="name">Last name A–Z</option><option value="year">Year level</option></select></label>
                    </div>
                    <ul class="student-team-member-list" data-member-list></ul>
                    <nav class="student-team-pagination" aria-label="Team directory pages"><button type="button" data-member-previous>Previous</button><span data-member-page-label>Page 1 of 1</span><button type="button" data-member-next>Next</button></nav>
                </div>
            </section>
            <section class="student-team-panel" id="team-panel-scores" role="tabpanel" aria-labelledby="team-tab-scores" tabindex="0" data-team-panel="scores" hidden>
                <div class="student-team-panel__heading"><div><p class="student-section-kicker">Finalized results</p><h2>Category scores</h2><small>Highest scores first. Zero-score items are hidden by default.</small></div></div>
                ${team.leaderboard_visible ? `<ul class="student-team-score-list" data-all-scores></ul><button class="student-team-show-all" type="button" data-score-toggle hidden></button>` : '<div class="student-team-empty">Scores will appear after the leaderboard reveal.</div>'}
            </section>
            <section class="student-team-panel" id="team-panel-leaders" role="tabpanel" aria-labelledby="team-tab-leaders" tabindex="0" data-team-panel="leaders" hidden>
                <div class="student-team-panel__heading"><div><p class="student-section-kicker">The crew</p><h2>Team leaders</h2></div></div>
                ${leaders.length ? `<ul class="student-team-leader-list">${leaders.map(leader => `<li><strong>${escapeHtml(leader.full_name)}</strong><span>${escapeHtml(leader.position)}</span></li>`).join('')}</ul>` : '<div class="student-team-empty">No team leaders listed yet.</div>'}
            </section>`;
    };

    const load = async () => {
        await initialize('team');
        const response = await axios.get('api/student-portal.php', {params: {page: 'team'}});
        const team = response.data.data.team;
        page.style.setProperty('--team-accent', team ? safeColor(team.color) : '#C6F24E');
        page.innerHTML = team ? teamPage(team) : emptyTeam();
        if (team) { installControls(team); renderScores(team); }
    };

    load().catch(() => {
        page.innerHTML = '<div class="rounded-3xl bg-white p-10 text-center font-bold text-[#FF6B2C]">Team information could not be loaded.</div>';
    });
})();
