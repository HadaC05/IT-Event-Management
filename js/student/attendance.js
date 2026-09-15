(() => {
    'use strict';

    const {initialize, formatDate, timeOnly, escapeHtml} = window.StudentPortal;

    const summaryCard = (label, value, tone) => `
        <div class="rounded-2xl border border-[#121017]/8 bg-white p-4">
            <span class="text-[9px] font-black uppercase text-[#121017]/40">${label}</span>
            <strong class="mt-1 block text-2xl ${tone}">${value}</strong>
        </div>`;

    const currentEvent = event => event ? `
        <p class="text-[9px] font-black uppercase tracking-wider text-[#397565]">Next assigned event</p>
        <h2 class="mt-2 text-xl font-black">${escapeHtml(event.title)}</h2>
        <p class="mt-2 text-xs leading-5 text-[#121017]/50">${formatDate(event.start_at)}<br>${escapeHtml(event.location || 'CITE Campus')}</p>
        <span class="mt-4 inline-flex rounded-full bg-[#C6F24E]/30 px-3 py-1 text-[9px] font-black uppercase text-[#397565]">${escapeHtml(event.schedule_state)}</span>` : `
        <div class="py-8 text-center">
            <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-[#397565]/10 text-xl text-[#397565]">✓</span>
            <h2 class="mt-4 font-black">No active attendance session</h2>
            <p class="mt-2 text-sm leading-6 text-[#121017]/45">Your next assigned event will appear here.</p>
        </div>`;

    const statusTone = status => ({
        present: 'bg-[#397565]/10 text-[#397565]',
        late: 'bg-[#C6F24E]/30 text-[#397565]',
        absent: 'bg-[#FF6B2C]/12 text-[#c84510]',
        excused: 'bg-[#2F3AE0]/10 text-[#2F3AE0]',
    })[status] || 'bg-[#121017]/7 text-[#121017]/50';

    const record = item => `
        <article class="p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="font-black">${escapeHtml(item.event_title)}</h3>
                    <p class="mt-1 text-xs text-[#121017]/45">${formatDate(item.attendance_date || item.start_at, false)}</p>
                </div>
                <span class="rounded-full px-3 py-1 text-[9px] font-black uppercase ${statusTone(item.status)}">${escapeHtml(item.status)}</span>
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                ${(item.attendance_mode === 'whole_day' ? [
                    ['Time in', item.morning_in_at || item.checked_in_at],
                    ['Time out', item.morning_out_at],
                ] : [
                    ['Morning in', item.morning_in_at],
                    ['Morning out', item.morning_out_at],
                    ['Afternoon in', item.afternoon_in_at],
                    ['Afternoon out', item.afternoon_out_at],
                ]).map(([label, value]) => `
                    <div class="rounded-xl bg-[#F7F4ED] p-3">
                        <dt class="text-[9px] font-black uppercase text-[#121017]/35">${label}</dt>
                        <dd class="mt-1 text-xs font-black">${timeOnly(value)}</dd>
                    </div>`).join('')}
            </dl>
            ${item.notes ? `<p class="mt-3 text-xs text-[#121017]/50"><strong>Note:</strong> ${escapeHtml(item.notes)}</p>` : ''}
        </article>`;

    const refreshHistory = async () => {
        const response = await axios.get('api/student-portal.php', {params: {page: 'attendance'}});
        const {summary, current_event: event, records} = response.data.data;

        document.querySelector('[data-attendance-summary]').innerHTML = [
            summaryCard('Present', summary.present, 'text-[#397565]'),
            summaryCard('Late', summary.late, 'text-[#D88722]'),
            summaryCard('Absent', summary.absent, 'text-[#FF6B2C]'),
            summaryCard('Excused', summary.excused, 'text-[#2F3AE0]'),
        ].join('');
        if (summary.total > 0) {
            document.querySelector('[data-attendance-rate-card]').classList.remove('hidden');
            document.querySelector('[data-attendance-rate]').textContent = `${summary.rate}%`;
        }
        document.querySelector('[data-current-event]').innerHTML = currentEvent(event);
        document.querySelector('[data-record-count]').textContent = `${records.length} ${records.length === 1 ? 'record' : 'records'}`;
        document.querySelector('[data-attendance-records]').innerHTML = records.length
            ? records.map(record).join('')
            : '<div class="px-6 py-16 text-center"><h3 class="font-black">No attendance records yet</h3><p class="mt-2 text-sm text-[#121017]/45">Recorded attendance will appear here.</p></div>';
    };

    const loadQr = async () => {
        const host = document.querySelector('[data-student-qr]');
        try {
            const response = await axios.post('api/student-attendance-qr.php', {}, {headers: {'X-CSRF-Token': StudentPortal.csrfToken}});
            const data = response.data.data;
            if (!data.token) {
                host.textContent = 'No active attendance session is available for your account.';
                return;
            }
            const module = await import(new URL('js/vendor/qrcode-generator/qrcode.mjs', document.baseURI));
            const qr = module.default(0, 'M');
            qr.addData(data.token);
            qr.make();
            host.innerHTML = `<p class="text-sm font-bold">${escapeHtml(data.event_name)} · ${escapeHtml(data.session.replace('_', ' '))}</p>${qr.createSvgTag({cellSize:5,margin:20,scalable:true})}`;
            const svg = host.querySelector('svg');
            svg?.setAttribute('aria-label', 'Attendance QR token');
            if (svg) svg.style.maxWidth = '240px';
        } catch (error) {
            host.textContent = error.response?.data?.message || 'Attendance QR is unavailable.';
        }
    };
    document.querySelector('[data-refresh-qr]').addEventListener('click', loadQr);

    const load = async () => {
        await initialize('attendance');
        loadQr();
        await refreshHistory();
    };

    load().catch(() => {
        document.querySelector('[data-attendance-records]').innerHTML = '<div class="p-8 text-center font-bold text-[#FF6B2C]">Attendance could not be loaded.</div>';
    });
})();
