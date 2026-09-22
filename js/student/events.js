(() => {
    'use strict';

    const {initialize, formatDate, timeOnly, escapeHtml} = window.StudentPortal;

    const emptyState = (title, message) => `
        <div class="col-span-full rounded-3xl border border-dashed border-[#397565]/25 bg-white px-6 py-14 text-center">
            <h3 class="font-black">${title}</h3>
            <p class="mt-2 text-sm text-[#121017]/45">${message}</p>
        </div>`;

    const schedule = event => {
        if (!event.schedules?.length) {
            return `<p class="mt-4 text-xs font-bold text-[#121017]/40">Schedule: ${formatDate(event.start_at)} – ${formatDate(event.end_at)}</p>`;
        }
        return event.schedules.map(item => {
            const times = item.whole_day_in_time
                ? `${timeOnly(item.whole_day_in_time)} – ${timeOnly(item.whole_day_out_time)}`
                : `Morning ${timeOnly(item.morning_in_time)} – ${timeOnly(item.morning_out_time)} · Afternoon ${timeOnly(item.afternoon_in_time)} – ${timeOnly(item.afternoon_out_time)}`;
            return `
                <div class="mt-3 rounded-xl bg-[#F7F4ED] p-3">
                    <span class="block text-[9px] font-black uppercase text-[#397565]">${escapeHtml(item.mode)}</span>
                    <strong class="mt-1 block text-xs">${formatDate(item.schedule_date, false)}</strong>
                    <span class="mt-1 block text-[10px] text-[#121017]/45">${times}</span>
                </div>`;
        }).join('');
    };

    const activeCard = event => `
        <article class="overflow-hidden rounded-3xl border border-[#121017]/8 bg-white">
            ${event.poster_path
                ? `<img class="h-44 w-full object-cover" src="${escapeHtml(event.poster_path)}" alt="${escapeHtml(event.title)} poster">`
                : '<div class="h-24 bg-[#397565]"></div>'}
            <div class="p-5">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-[#C6F24E]/30 px-2.5 py-1 text-[9px] font-black uppercase text-[#397565]">${escapeHtml(event.schedule_state)}</span>
                    ${event.type ? `<span class="text-[9px] font-black uppercase text-[#121017]/35">${escapeHtml(event.type)}</span>` : ''}
                </div>
                <h3 class="mt-3 text-xl font-black">${escapeHtml(event.title)}</h3>
                <p class="mt-2 text-xs leading-5 text-[#121017]/45">${formatDate(event.start_at)}<br>${escapeHtml(event.location || 'CITE Campus')}</p>
                <p class="mt-4 text-sm leading-6 text-[#121017]/65">${escapeHtml(event.description || 'More details will be announced soon.')}</p>
                ${schedule(event)}
            </div>
        </article>`;

    const pastCard = event => `
        <article class="flex min-w-0 gap-4 rounded-2xl border border-[#121017]/8 bg-white p-4">
            ${event.poster_path
                ? `<img class="h-20 w-20 shrink-0 rounded-xl object-cover" src="${escapeHtml(event.poster_path)}" alt="">`
                : '<span class="grid h-20 w-20 shrink-0 place-items-center rounded-xl bg-[#121017]/7 text-2xl text-[#397565]">✓</span>'}
            <div class="min-w-0 flex-1">
                <span class="text-[9px] font-black uppercase tracking-wider text-[#121017]/40">Completed</span>
                <h3 class="mt-1 truncate font-black">${escapeHtml(event.title)}</h3>
                <p class="mt-1 text-xs leading-5 text-[#121017]/50">${formatDate(event.start_at, false)} · ${escapeHtml(event.location || 'CITE Campus')}</p>
                <p class="mt-2 text-[10px] font-bold text-[#397565]">Schedule and activity record retained.</p>
            </div>
        </article>`;

    const load = async () => {
        await initialize('events');
        const response = await axios.get('api/student-portal.php', {params: {page: 'events'}});
        const {active, past} = response.data.data;
        document.querySelector('[data-active-count]').textContent = `${active.length} ${active.length === 1 ? 'event' : 'events'}`;
        document.querySelector('[data-past-count]').textContent = `${past.length} ${past.length === 1 ? 'event' : 'events'}`;
        document.querySelector('[data-active-events]').innerHTML = active.length
            ? active.map(activeCard).join('')
            : emptyState('No active or upcoming events', 'Events available to you will appear here.');
        document.querySelector('[data-past-events]').innerHTML = past.length
            ? past.map(pastCard).join('')
            : emptyState('No past events', 'Completed activities will appear here.');
    };

    load().catch(error => {
        if (error.response?.status !== 401 && error.response?.status !== 403) {
            document.querySelector('[data-active-events]').innerHTML = emptyState('Events unavailable', 'Please refresh the page and try again.');
        }
    });
})();
