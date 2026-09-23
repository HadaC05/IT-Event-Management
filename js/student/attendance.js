(() => {
    'use strict';

    const {initialize, formatDate, timeOnly, escapeHtml} = window.StudentPortal;
    const state = {event: null, cards: null, qrError: ''};

    const summaryItem = (label, value, tone, icon) => `
        <div class="flex items-center gap-3 px-2 first:pl-0 sm:border-l sm:border-[#121017]/10 sm:px-5 sm:first:border-l-0 sm:first:pl-0">
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#121017]/5 text-sm ${tone}" aria-hidden="true">${icon}</span>
            <span><strong class="block text-xl leading-none ${tone}">${value}</strong><small class="mt-1 block text-[9px] font-black uppercase tracking-wider text-[#121017]/40">${label}</small></span>
        </div>`;

    const statusTone = status => ({
        present: 'bg-[#397565]/10 text-[#397565]',
        absent: 'bg-[#FF6B2C]/12 text-[#c84510]',
    })[status] || 'bg-[#121017]/7 text-[#121017]/50';

    const attendanceStatus = status => ['present', 'late'].includes(String(status || '').toLowerCase()) ? 'present' : 'absent';

    const record = item => {
        const status = attendanceStatus(item.status);
        return `
        <article class="p-5 sm:px-7 sm:py-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="font-black">${escapeHtml(item.event_title)}</h3>
                    <p class="mt-1 text-xs text-[#121017]/45">${formatDate(item.attendance_date || item.start_at, false)}${item.manual_status ? ' · Adviser corrected' : ''}</p>
                </div>
                <span class="rounded-full px-3 py-1 text-[9px] font-black uppercase ${statusTone(status)}">${escapeHtml(status)}</span>
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
    };

    const nextEvent = event => event ? `
        <div class="mt-6 border-t border-[#121017]/8 pt-5">
            <p class="text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/40">${event.schedule_state === 'ongoing' ? 'Current event' : 'Your next event'}</p>
            <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div><h3 class="text-lg font-black">${escapeHtml(event.title)}</h3><p class="mt-1 text-sm text-[#121017]/50">${formatDate(event.start_at)} · ${escapeHtml(event.location || 'CITE Campus')}</p></div>
                <span class="w-fit rounded-full bg-[#C6F24E]/25 px-3 py-1 text-[9px] font-black uppercase text-[#397565]">${escapeHtml(event.schedule_state)}</span>
            </div>
        </div>` : '';

    const setActionBadge = (label, classes) => {
        const badge = document.querySelector('[data-attendance-state-badge]');
        badge.textContent = label;
        badge.className = `rounded-full px-3 py-1.5 text-[9px] font-black uppercase ${classes}`;
    };

    const displayTime = value => value ? new Intl.DateTimeFormat('en-PH', {
        hour: 'numeric', minute: '2-digit', timeZone: 'Asia/Manila',
    }).format(new Date(value.replace(' ', 'T') + '+08:00')) : '—';

    const qrStatus = card => {
        if (card.state === 'already_out') return card.in_at
            ? `Already timed out at ${displayTime(card.out_at)}.`
            : `Timed out at ${displayTime(card.out_at)}. Your Time In is still missing; ask the adviser if it needs correction.`;
        if (card.state === 'waiting_out') return `Already timed in at ${displayTime(card.in_at)}. Time Out opens at ${displayTime(card.out_opens_at)}.`;
        if (card.state === 'out_open') return `Already timed in at ${displayTime(card.in_at)}. Time Out is open now.`;
        if (card.state === 'out_open_without_in') return 'Time Out is open. Show this QR even if you missed Time In. Your Time In will remain missing.';
        if (card.state === 'in_open') return 'Time In is open now. Show this QR to your assigned officer.';
        if (card.state === 'waiting_in') return `Time In opens at ${displayTime(card.in_opens_at)}.`;
        if (card.state === 'time_in_closed') return `Time In is closed. Time Out opens at ${displayTime(card.out_opens_at)}; you can still use a Time Out QR then.`;
        if (card.state === 'already_in') return `Already timed in at ${displayTime(card.in_at)}.`;
        return 'No attendance QR is scannable now.';
    };

    const renderAction = () => {
        const host = document.querySelector('[data-attendance-action]');
        if (state.qrError) {
            setActionBadge('Unavailable', 'bg-[#FF6B2C]/12 text-[#c84510]');
            host.innerHTML = `<div class="flex items-start gap-4"><span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-[#FF6B2C]/12 font-black text-[#c84510]">!</span><div><h3 class="font-black">Attendance status unavailable</h3><p class="mt-1 text-sm leading-6 text-[#121017]/50">${escapeHtml(state.qrError)}</p></div></div>${nextEvent(state.event)}`;
            return;
        }
        if (state.cards === null) {
            setActionBadge('Checking', 'bg-[#121017]/6 text-[#121017]/45');
            host.innerHTML = '<p class="text-sm text-[#121017]/50">Checking today’s attendance sessions…</p>';
            return;
        }
        if (!state.cards.length) {
            setActionBadge('No session now', 'bg-[#397565]/8 text-[#397565]');
            host.innerHTML = `<div class="flex items-start gap-4"><span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-[#397565]/10 text-lg font-black text-[#397565]">✓</span><div><h3 class="font-black">No attendance action right now</h3><p class="mt-1 text-sm leading-6 text-[#121017]/50">There is no attendance session scheduled for you today.</p></div></div>${nextEvent(state.event)}`;
            return;
        }

        const open = state.cards.some(card => card.token);
        setActionBadge(open ? 'Attendance open' : 'Today scheduled', open ? 'bg-[#C6F24E]/35 text-[#397565]' : 'bg-[#397565]/8 text-[#397565]');
        const intro = open
            ? '<h3 class="text-xl font-black">Your attendance QR is ready</h3><p class="mt-1 text-sm text-[#121017]/50">Show the displayed code to your assigned officer. Time In and Time Out use different one-time codes.</p>'
            : '<h3 class="text-xl font-black">Today’s attendance</h3><p class="mt-1 text-sm text-[#121017]/50">This status updates automatically when a scan window opens.</p>';
        const sessions = state.cards.map((card, index) => {
            const label = card.phase === 'out' ? 'Time Out QR' : card.phase === 'in' ? 'Time In QR' : 'Attendance status';
            return `<article class="${index ? 'mt-5 border-t border-[#121017]/8 pt-5' : 'mt-5'}" data-qr-phase="${card.phase || 'none'}">
                <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-[9px] font-black uppercase tracking-wider text-[#397565]">${escapeHtml(card.event_name)} · ${escapeHtml(card.session.replace('_', ' '))}</p><h4 class="mt-1 font-black">${label}</h4></div>${card.token ? '<span class="inline-flex items-center gap-1.5 rounded-full bg-[#C6F24E]/25 px-3 py-1 text-[9px] font-black uppercase text-[#397565]"><i class="h-1.5 w-1.5 animate-pulse rounded-full bg-[#397565]"></i> Live</span>' : ''}</div>
                <p class="mt-2 text-sm leading-6 text-[#121017]/60" aria-live="polite">${escapeHtml(qrStatus(card))}</p>
                ${card._qrSvg ? `<div class="mx-auto mt-4 grid max-w-[280px] place-items-center rounded-2xl border border-[#397565]/12 bg-white p-3">${card._qrSvg}</div><p class="mt-3 text-center text-xs text-[#121017]/45">Refreshes automatically and can be used only once.</p>` : ''}
            </article>`;
        }).join('');
        host.innerHTML = intro + sessions;
        host.querySelectorAll('svg').forEach(svg => {
            svg.style.maxWidth = '240px';
            svg.style.width = '100%';
            svg.setAttribute('aria-label', 'Short-lived attendance QR');
        });
    };

    const refreshHistory = async () => {
        const response = await axios.get('api/student-portal.php', {params: {page: 'attendance'}});
        const {summary, current_event: event, records} = response.data.data;

        const present = Number(summary.present || 0) + Number(summary.late || 0);
        const absent = Number(summary.absent || 0) + Number(summary.excused || 0);
        document.querySelector('[data-attendance-summary]').innerHTML = [
            summaryItem('Present', present, 'text-[#397565]', '✓'),
            summaryItem('Absent', absent, 'text-[#c84510]', '×'),
        ].join('');
        const rate = document.querySelector('[data-attendance-rate]');
        if (summary.total > 0) {
            rate.classList.remove('hidden');
            rate.textContent = `${summary.rate}% attendance rate`;
        } else {
            rate.classList.add('hidden');
        }
        state.event = event;
        renderAction();
        document.querySelector('[data-record-count]').textContent = `${records.length} ${records.length === 1 ? 'record' : 'records'}`;
        document.querySelector('[data-attendance-records]').innerHTML = records.length
            ? records.map(record).join('')
            : '<div class="px-6 py-10 text-center"><span class="mx-auto grid h-10 w-10 place-items-center rounded-full bg-[#397565]/8 text-[#397565]">✓</span><h3 class="mt-3 font-black">No attendance recorded yet</h3><p class="mt-1 text-sm text-[#121017]/45">Your completed sessions will appear here.</p></div>';
    };

    let qrBusy = false;
    let lastQrFingerprint = '';
    let lastRecordedFingerprint = '';
    let qrReady = null;

    const loadQr = async () => {
        if (qrBusy || document.hidden) return;
        qrBusy = true;
        try {
            const response = await axios.post('api/student-attendance-qr.php', {}, {headers: {'X-CSRF-Token': StudentPortal.csrfToken}});
            const cards = response.data.data.sessions || [];
            const fingerprint = JSON.stringify(cards.map(card => [card.event_name, card.session, card.state, card.phase, card.token, card.in_at, card.out_at, card.in_opens_at, card.out_opens_at]));
            if (fingerprint === lastQrFingerprint && !state.qrError) return;
            const recordedFingerprint = JSON.stringify(cards.map(card => [card.in_at, card.out_at]));
            const recordChanged = lastRecordedFingerprint && lastRecordedFingerprint !== recordedFingerprint;
            lastRecordedFingerprint = recordedFingerprint;
            lastQrFingerprint = fingerprint;
            if (cards.some(card => card.token)) qrReady ||= import(new URL('js/vendor/qrcode-generator/qrcode.mjs', document.baseURI));
            const factory = qrReady ? (await qrReady).default : null;
            state.cards = cards.map(card => {
                if (!card.token || !factory) return card;
                const qr = factory(0, 'M');
                qr.addData(card.token);
                qr.make();
                return {...card, _qrSvg: qr.createSvgTag({cellSize: 5, margin: 20, scalable: true})};
            });
            state.qrError = '';
            renderAction();
            if (recordChanged) refreshHistory().catch(() => {});
        } catch (error) {
            state.qrError = error.response?.data?.message || 'Attendance QR is unavailable.';
            renderAction();
        } finally {
            qrBusy = false;
        }
    };

    document.querySelector('[data-refresh-qr]').addEventListener('click', loadQr);

    const load = async () => {
        const user = await initialize('attendance');
        const hour = Number(new Intl.DateTimeFormat('en-PH', {hour: '2-digit', hourCycle: 'h23', timeZone: 'Asia/Manila'}).format(new Date()));
        const greeting = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
        document.querySelector('[data-attendance-greeting]').textContent = `${greeting}, ${user.first_name || 'Student'}.`;
        document.querySelector('[data-attendance-date]').textContent = new Intl.DateTimeFormat('en-PH', {
            weekday: 'long', month: 'long', day: 'numeric', timeZone: 'Asia/Manila',
        }).format(new Date());
        loadQr();
        await refreshHistory();
        setInterval(loadQr, 5000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) loadQr(); });
    };

    load().catch(() => {
        state.qrError = 'Attendance could not be loaded. Refresh the page and try again.';
        renderAction();
        document.querySelector('[data-attendance-records]').innerHTML = '<div class="p-8 text-center font-bold text-[#c84510]">Attendance history could not be loaded.</div>';
    });
})();
