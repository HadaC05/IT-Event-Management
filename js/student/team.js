(() => {
    'use strict';

    const {initialize, formatDate, escapeHtml} = window.StudentPortal;
    const page = document.querySelector('[data-team-page]');

    const safeColor = value => /^#[0-9a-f]{6}$/i.test(value || '') ? value : '#397565';

    const emptyTeam = () => `
        <div class="mx-auto max-w-xl rounded-3xl border border-dashed border-[#397565]/25 bg-white px-6 py-16 text-center">
            <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-[#397565]/10 text-2xl text-[#397565]">◎</span>
            <h1 class="mt-4 text-xl font-black">No team assigned</h1>
            <p class="mt-2 text-sm text-[#121017]/45">An adviser will assign your team for the current school year.</p>
        </div>`;

    const member = person => `
        <div class="flex items-center gap-3 rounded-2xl bg-[#F7F4ED]/60 p-3">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[#C6F24E] text-[10px] font-black">${escapeHtml(person.initials)}</span>
            <div class="min-w-0">
                <strong class="block truncate text-xs">${escapeHtml(person.full_name)}</strong>
                <span class="text-[10px] text-[#121017]/40">${escapeHtml(person.year_level || 'Student')}</span>
            </div>
        </div>`;

    const teamPage = team => {
        const color = safeColor(team.color);
        const scores = team.scores.length
            ? team.scores.map(score => `
                <div class="rounded-2xl border border-[#121017]/7 bg-[#F7F4ED]/60 p-4">
                    <span class="text-[10px] font-black uppercase text-[#121017]/40">${escapeHtml(score.category)}</span>
                    <strong class="mt-1 block text-xl text-[#397565]">${score.points.toFixed(1)} pts</strong>
                </div>`).join('')
            : '<p class="col-span-full text-sm text-[#121017]/45">No scores have been recorded yet.</p>';
        const leaders = team.leaders.length
            ? team.leaders.map(leader => `<div><strong class="block text-xs">${escapeHtml(leader.full_name)}</strong><span class="text-[10px] font-bold text-[#397565]">${escapeHtml(leader.position)}</span></div>`).join('')
            : '<p class="text-xs text-[#121017]/45">No active team officers listed.</p>';
        const activities = team.activities.length
            ? team.activities.map(activity => `<div><strong class="block text-xs">${escapeHtml(activity.title)}</strong><span class="text-[10px] text-[#121017]/40">${formatDate(activity.start_at)} · ${escapeHtml(activity.location || 'CITE Campus')}</span></div>`).join('')
            : '<p class="text-xs text-[#121017]/45">Nothing scheduled specifically for this team.</p>';

        return `
            <header class="overflow-hidden rounded-3xl border border-[#121017]/8 bg-white">
                <div class="h-3" style="background-color:${color}"></div>
                <div class="flex flex-col gap-5 p-6 sm:flex-row sm:items-center">
                    <span class="grid h-20 w-20 place-items-center rounded-3xl text-3xl font-black text-white shadow-lg" style="background-color:${color}">${escapeHtml(team.name.charAt(0).toUpperCase())}</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-black uppercase tracking-[.15em] text-[#397565]">Your team · ${escapeHtml(team.school_year)}</p>
                        <h1 class="mt-1 text-3xl font-black">${escapeHtml(team.name)}</h1>
                        <p class="mt-1 text-sm text-[#121017]/45">${team.members_count} members united for CITE activities.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="rounded-2xl bg-[#F7F4ED] p-4 text-center">
                            <span class="block text-[9px] font-black uppercase text-[#121017]/40">Rank</span>
                            <strong class="mt-1 block text-2xl">${team.rank ? `#${team.rank}` : '—'}</strong>
                        </div>
                        <div class="rounded-2xl bg-[#397565] p-4 text-center text-white">
                            <span class="block text-[9px] font-black uppercase text-white/60">Total</span>
                            <strong class="mt-1 block text-2xl">${team.total_score.toFixed(1)}</strong>
                        </div>
                    </div>
                </div>
            </header>

            <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                <div class="space-y-6">
                    <section class="rounded-3xl border border-[#121017]/8 bg-white p-5">
                        <h2 class="text-lg font-black">Activity category scores</h2>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">${scores}</div>
                    </section>
                    <section class="rounded-3xl border border-[#121017]/8 bg-white p-5">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-black">Team members</h2>
                            <span class="text-xs text-[#121017]/40">${team.members.length}</span>
                        </div>
                        <div class="mt-4 grid gap-2 sm:grid-cols-2">${team.members.map(member).join('')}</div>
                    </section>
                </div>
                <aside class="space-y-5">
                    <section class="rounded-3xl border border-[#121017]/8 bg-white p-5">
                        <h2 class="font-black">Team leaders</h2>
                        <div class="mt-3 space-y-3">${leaders}</div>
                    </section>
                    <section class="rounded-3xl border border-[#121017]/8 bg-white p-5">
                        <h2 class="font-black">Upcoming activities</h2>
                        <div class="mt-3 space-y-3">${activities}</div>
                    </section>
                </aside>
            </div>`;
    };

    const load = async () => {
        await initialize('team');
        const response = await axios.get('api/student-portal.php', {params: {page: 'team'}});
        const team = response.data.data.team;
        page.innerHTML = team ? teamPage(team) : emptyTeam();
    };

    load().catch(() => {
        page.innerHTML = '<div class="rounded-3xl bg-white p-10 text-center font-bold text-[#FF6B2C]">Team information could not be loaded.</div>';
    });
})();
