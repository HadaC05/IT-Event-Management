(() => {
    'use strict';

    const {initialize, escapeHtml} = window.StudentPortal;
    let data = null;
    let selectedCategory = 'overall';

    const safeColor = value => /^#[0-9a-f]{6}$/i.test(value || '') ? value : '#397565';

    const scoreFor = team => {
        if (selectedCategory === 'overall') return team.total_score;
        return Number(team.category_scores[selectedCategory] || 0);
    };

    const renderRows = () => {
        const ranked = [...data.teams].sort((left, right) => {
            const difference = scoreFor(right) - scoreFor(left);
            return difference || left.name.localeCompare(right.name);
        });
        const hasAnyScore = ranked.some(team => scoreFor(team) > 0);
        let previousScore = null;
        let previousRank = null;

        const rows = ranked.map((team, index) => {
            const score = scoreFor(team);
            const rank = !hasAnyScore || score <= 0
                ? null
                : (previousScore === score ? previousRank : index + 1);
            if (rank !== null) {
                previousScore = score;
                previousRank = rank;
            }
            return `
                <div class="grid min-h-16 grid-cols-[55px_minmax(0,1fr)_80px] items-center border-b border-[#121017]/7 px-4 last:border-0 sm:grid-cols-[70px_minmax(0,1fr)_120px]">
                    <strong class="text-lg">${rank ? `#${rank}` : '—'}</strong>
                    <div class="flex min-w-0 items-center gap-3">
                        <i class="h-3 w-3 shrink-0 rounded-full" style="background-color:${safeColor(team.color)}"></i>
                        <div class="min-w-0">
                            <strong class="block truncate text-sm">${escapeHtml(team.name)}</strong>
                            <span class="text-[9px] text-[#121017]/35">${team.members_count} members</span>
                        </div>
                    </div>
                    <strong class="text-right text-sm text-[#397565]">${score.toFixed(1)}</strong>
                </div>`;
        }).join('');

        document.querySelector('[data-leaderboard]').innerHTML = `
            <div class="grid grid-cols-[55px_minmax(0,1fr)_80px] border-b border-[#121017]/7 bg-[#F7F4ED] px-4 py-3 text-[9px] font-black uppercase tracking-wider text-[#121017]/40 sm:grid-cols-[70px_minmax(0,1fr)_120px]">
                <span>Rank</span>
                <span>Team</span>
                <span class="text-right">Total score</span>
            </div>
            <div>${rows}</div>`;
    };

    const renderTabs = () => {
        const categories = [{id: 'overall', name: 'Overall ranking'}, ...data.categories];
        const tabs = document.querySelector('[data-category-tabs]');
        tabs.innerHTML = categories.map(category => {
            const id = String(category.id);
            const active = id === selectedCategory;
            return `
                <button
                    class="min-h-11 shrink-0 rounded-xl px-4 text-xs font-black ${active ? 'bg-[#397565] text-white' : 'bg-white text-[#121017]/55 ring-1 ring-[#121017]/8'}"
                    type="button"
                    data-category="${escapeHtml(id)}"
                    aria-selected="${active}"
                >${escapeHtml(category.name)}</button>`;
        }).join('');
    };

    const render = () => {
        if (!data.visible) {
            document.querySelector('[data-category-tabs]').classList.add('hidden');
            document.querySelector('[data-leaderboard-subtitle]').textContent = 'Standings will be revealed by the SBO Adviser.';
            document.querySelector('[data-leaderboard]').innerHTML = `
                <div class="px-6 py-16 text-center">
                    <span class="text-4xl" aria-hidden="true">🏆</span>
                    <h2 class="mt-4 text-lg font-black">Leaderboard is under wraps</h2>
                    <p class="mx-auto mt-2 max-w-md text-sm text-[#121017]/50">Check back when the SBO Adviser reveals the results.</p>
                </div>`;
            return;
        }
        if (!data.event) {
            document.querySelector('[data-category-tabs]').classList.add('hidden');
            document.querySelector('[data-leaderboard]').innerHTML = `
                <div class="py-16 text-center">
                    <h2 class="font-black">No active leaderboard</h2>
                    <p class="mt-2 text-sm text-[#121017]/45">Rankings will appear when a current or upcoming activity is available.</p>
                </div>`;
            return;
        }
        document.querySelector('[data-leaderboard-subtitle]').textContent = `${data.event.title} · current activity totals`;
        document.querySelector('[data-category-tabs]').classList.remove('hidden');
        renderTabs();
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
        document.querySelector('[data-category-tabs]').addEventListener('click', event => {
            const button = event.target.closest('[data-category]');
            if (!button) return;
            selectedCategory = button.dataset.category;
            renderTabs();
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
        document.querySelector('[data-leaderboard]').innerHTML = '<div class="p-10 text-center font-bold text-[#FF6B2C]">Leaderboard could not be loaded.</div>';
    });
})();
