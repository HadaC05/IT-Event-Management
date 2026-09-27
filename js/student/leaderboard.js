(() => {
    'use strict';

    const {initialize, escapeHtml} = window.StudentPortal;
    const filter = document.querySelector('[data-category-filter]');
    const select = document.querySelector('[data-category-select]');
    const podium = document.querySelector('[data-student-podium]');
    const standings = document.querySelector('[data-leaderboard]');
    const subtitle = document.querySelector('[data-leaderboard-subtitle]');
    let data = null;
    let selectedCategory = 'overall';

    const safeColor = value => /^#[0-9a-f]{6}$/i.test(String(value || '')) ? value : '#397565';
    const scoreFor = team => {
        const value = selectedCategory === 'overall' ? team.total_score : team.category_scores?.[selectedCategory];
        const score = Number(value);
        return Number.isFinite(score) ? score : 0;
    };
    const points = value => Number(value).toFixed(1).replace(/\.0$/, '');

    const rankedTeams = () => {
        const teams = [...data.teams].sort((left, right) => {
            const difference = scoreFor(right) - scoreFor(left);
            return difference || String(left.name || '').localeCompare(String(right.name || ''));
        });
        let lastScore = null;
        let lastRank = null;
        return teams.map((team, index) => {
            const score = scoreFor(team);
            const rank = score <= 0 ? null : (score === lastScore ? lastRank : index + 1);
            if (rank !== null) { lastScore = score; lastRank = rank; }
            return {team, score, rank};
        });
    };

    const renderPodium = ranked => {
        const winners = ranked.filter(entry => entry.rank !== null).slice(0, 3);
        podium.hidden = winners.length === 0;
        if (!winners.length) { podium.innerHTML = ''; return; }
        const order = winners.length === 3 ? [winners[1], winners[0], winners[2]] : winners;
        const medals = {1: '🥇', 2: '🥈', 3: '🥉'};
        podium.innerHTML = `<div class="student-leaderboard-podium__heading"><p>THE LEADERS</p><h2>Top tribes</h2></div><div class="student-leaderboard-podium__cards">${order.map(({team, score, rank}) => `
            <article class="student-winner ${rank === 1 ? 'student-winner--first' : ''}" style="--team-color:${safeColor(team.color)}">
                <span class="student-winner__medal" aria-hidden="true">${medals[rank] || `#${rank}`}</span>
                <span class="student-winner__place">${rank === 1 ? 'CHAMPION' : `RANK #${rank}`}</span>
                <span class="student-winner__crest" aria-hidden="true">${escapeHtml(String(team.name || '?').trim().charAt(0).toUpperCase())}</span>
                <h3>${escapeHtml(team.name || 'Unnamed tribe')}</h3>
                <p>${Number(team.members_count || 0)} members</p>
                <strong>${points(score)} <small>PTS</small></strong>
            </article>`).join('')}</div>`;
    };

    const renderRows = () => {
        const ranked = rankedTeams();
        renderPodium(ranked);
        const medals = {1: '🥇', 2: '🥈', 3: '🥉'};
        const rows = ranked.map(({team, score, rank}) => `
            <li class="student-ranking-row ${rank && rank <= 3 ? 'student-ranking-row--top' : ''}">
                <span class="student-ranking-row__rank" aria-label="${rank ? `Rank ${rank}` : 'Unranked'}">${rank ? medals[rank] || `#${rank}` : '—'}</span>
                <span class="student-ranking-row__team"><i style="background:${safeColor(team.color)}" aria-hidden="true"></i><span><strong>${escapeHtml(team.name || 'Unnamed tribe')}</strong><small>${Number(team.members_count || 0)} members</small></span></span>
                <strong class="student-ranking-row__score">${points(score)}<small> pts</small></strong>
            </li>`).join('');
        standings.innerHTML = `<div class="student-ranking-header"><span>Rank</span><span>Tribe</span><span>Points</span></div><ol class="student-ranking-list">${rows}</ol>`;
    };

    const renderSelect = () => {
        const categories = Array.isArray(data.categories) ? data.categories : [];
        const group = (name, entries) => entries.length ? `<optgroup label="${name}">${entries.map(category => `<option value="${escapeHtml(String(category.id))}">${escapeHtml(category.name)}</option>`).join('')}</optgroup>` : '';
        select.innerHTML = '<option value="overall">Overall ranking</option>'
            + group('Score categories', categories.filter(category => String(category.id).startsWith('category-')))
            + group('Activities', categories.filter(category => String(category.id).startsWith('activity-')));
        if (![...select.options].some(option => option.value === selectedCategory)) selectedCategory = 'overall';
        select.value = selectedCategory;
    };

    const render = () => {
        if (!data.visible || !data.event) {
            filter.hidden = true;
            podium.hidden = true;
            podium.innerHTML = '';
            if (!data.visible) {
                subtitle.textContent = 'Standings will be revealed by the SBO Adviser.';
                standings.innerHTML = '<div class="px-6 py-16 text-center"><span class="text-4xl" aria-hidden="true">🏆</span><h2 class="mt-4 text-lg font-black">Leaderboard is under wraps</h2><p class="mx-auto mt-2 max-w-md text-sm text-[#121017]/50">Check back when the SBO Adviser reveals the results.</p></div>';
            } else {
                subtitle.textContent = 'Rankings will appear when an event is available.';
                standings.innerHTML = '<div class="py-16 text-center"><h2 class="font-black">No active leaderboard</h2><p class="mt-2 text-sm text-[#121017]/45">Rankings will appear when a current or upcoming activity is available.</p></div>';
            }
            return;
        }
        subtitle.textContent = `${data.event.title} · current activity totals`;
        filter.hidden = false;
        renderSelect();
        renderRows();
    };

    let refreshing = false;
    const refresh = async () => {
        if (refreshing) return;
        refreshing = true;
        try {
            const response = await axios.get('api/student-portal.php', {params: {page: 'leaderboard'}});
            data = response.data.data;
            if (!data.visible) selectedCategory = 'overall';
            render();
        } finally {
            refreshing = false;
        }
    };

    const load = async () => {
        await initialize('leaderboard');
        await refresh();
        select.addEventListener('change', () => {
            selectedCategory = select.value;
            renderRows();
        });
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) refresh().catch(() => {});
        });
        window.setInterval(() => {
            if (!document.hidden) refresh().catch(() => {});
        }, 20000);
    };

    load().catch(() => {
        standings.innerHTML = '<div class="p-10 text-center font-bold text-[#FF6B2C]">Leaderboard could not be loaded.</div>';
    });
})();
