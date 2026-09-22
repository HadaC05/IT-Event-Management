(() => {
    'use strict';

    const API = 'api/sbo-students.php';
    const form = document.querySelector('[data-filters]');
    const results = document.querySelector('[data-student-results]');
    const pagination = document.querySelector('[data-student-pagination]');
    const assignmentSelect = document.querySelector('[data-assignment]');
    const upcomingNotice = document.querySelector('[data-upcoming-assignment-notice]');
    const desktop = matchMedia('(min-width: 1024px)');
    let assignmentsLoaded = false;
    let currentPage = 1;
    let latestData = null;
    let requestSequence = 0;
    let searchTimer;

    const escapeHtml = value => SboPortal.escapeHtml(value ?? '');
    const visibleEmail = student => /@pending\.invalid$/i.test(String(student?.email || '')) ? '' : String(student?.email || '');
    const initials = name => { const words=String(name||'').trim().split(/\s+/).filter(Boolean); return (words.length>1?`${words[0][0]}${words[words.length-1][0]}`:(words[0]||'CU').slice(0,2)).toUpperCase(); };
    const statusBadge = student => { const status=String(student.attendance_status||'not_recorded'); return `<span class="sbo-student-status ${status === 'not_recorded' ? 'sbo-student-status--waiting' : 'sbo-student-status--recorded'}">${escapeHtml(status.replace('_', ' '))}</span>`; };

    const tableRow = student => `
        <tr class="sbo-student-row">
            <td class="p-4 text-sm font-bold">${escapeHtml(student.id_number)}</td>
            <td class="p-4"><div class="sbo-student-person"><span class="sbo-student-avatar" aria-hidden="true">${escapeHtml(initials(student.full_name))}</span><span><strong class="text-sm">${escapeHtml(student.full_name)}</strong>${visibleEmail(student) ? `<small>${escapeHtml(visibleEmail(student))}</small>` : ''}</span></div></td>
            <td class="p-4 text-sm">CITE · ${escapeHtml(student.year_level || 'Not specified')}</td>
            <td class="p-4 text-sm font-bold text-[#397565]">${escapeHtml(student.team_name)}</td>
            <td class="p-4">${statusBadge(student)}</td>
        </tr>`;

    const mobileCard = student => `
        <article class="sbo-student-card min-w-0 rounded-2xl border border-[#121017]/10 bg-white p-4 shadow-sm">
            <div class="flex min-w-0 items-start justify-between gap-3">
                <div class="sbo-student-person min-w-0"><span class="sbo-student-avatar" aria-hidden="true">${escapeHtml(initials(student.full_name))}</span><span class="min-w-0"><strong class="block truncate text-sm">${escapeHtml(student.full_name)}</strong><small>${escapeHtml(student.id_number)}</small></span></div>
                ${statusBadge(student)}
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-[#121017]/8 pt-3 text-xs">
                <div><dt class="text-[9px] font-black uppercase tracking-wider text-[#121017]/40">Course & year</dt><dd class="mt-1 font-bold">CITE · ${escapeHtml(student.year_level || 'Not specified')}</dd></div>
                <div><dt class="text-[9px] font-black uppercase tracking-wider text-[#121017]/40">Team</dt><dd class="mt-1 font-bold text-[#397565]">${escapeHtml(student.team_name)}</dd></div>
                ${visibleEmail(student) ? `<div class="col-span-2 min-w-0"><dt class="text-[9px] font-black uppercase tracking-wider text-[#121017]/40">Email</dt><dd class="mt-1 truncate">${escapeHtml(visibleEmail(student))}</dd></div>` : ''}
            </dl>
        </article>`;

    const emptyMessage = data => data.assignments.length
        ? 'No students match these filters.'
        : 'You currently have no current or upcoming event assignment.';

    const renderStudents = data => {
        const students = data.students;
        if (!students.length) {
            results.innerHTML = `<div class="sbo-workspace-empty"><span aria-hidden="true">◎</span><strong>No students to show</strong><p>${emptyMessage(data)}</p></div>`;
            return;
        }
        results.innerHTML = desktop.matches
            ? `<div class="overflow-x-auto"><table class="sbo-student-table w-full min-w-[760px] text-left"><thead class="text-xs uppercase tracking-wider"><tr><th class="p-4">Student ID</th><th class="p-4">Name</th><th class="p-4">Course & year</th><th class="p-4">Team</th><th class="p-4">Attendance</th></tr></thead><tbody class="divide-y divide-[#121017]/8">${students.map(tableRow).join('')}</tbody></table></div>`
            : `<div class="sbo-student-cards grid gap-3 p-4">${students.map(mobileCard).join('')}</div>`;
    };

    const renderPagination = page => {
        const visible = page.total > 0;
        pagination.classList.toggle('hidden', !visible);
        pagination.classList.toggle('flex', visible);
        pagination.replaceChildren();
        if (!visible) return;
        const count = document.createElement('span');
        count.textContent = `Showing ${page.from.toLocaleString()}–${page.to.toLocaleString()} of ${page.total.toLocaleString()} students`;
        const controls = document.createElement('span');
        controls.className = 'flex items-center gap-2';
        const position = document.createElement('span');
        position.className = 'px-2';
        position.textContent = `${page.current_page} / ${page.last_page}`;
        const control = (label, target, disabled) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'min-h-9 rounded-lg border border-[#121017]/12 px-3 font-bold disabled:opacity-30';
            button.textContent = label;
            button.disabled = disabled;
            button.addEventListener('click', () => load(target));
            return button;
        };
        controls.append(
            control('Previous', page.current_page - 1, page.current_page === 1),
            position,
            control('Next', page.current_page + 1, page.current_page === page.last_page),
        );
        pagination.append(count, controls);
    };

    const render = data => {
        latestData = data;
        document.querySelector('[data-student-total]').textContent = Number(data.pagination?.total||0).toLocaleString('en-PH');
        document.querySelector('[data-student-assignment-title]').textContent = data.selected ? `${data.selected.event_name} · ${data.selected.team_name}` : 'No current assignment';
        upcomingNotice.classList.toggle('hidden', data.selected?.assignment_state !== 'upcoming');
        renderStudents(data);
        renderPagination(data.pagination);
    };

    const load = async (targetPage = currentPage) => {
        const request = ++requestSequence;
        try {
            const params = Object.fromEntries(new FormData(form));
            params.page = targetPage;
            const {data} = (await axios.get(API, {params})).data;
            if (request !== requestSequence) return;
            if (!assignmentsLoaded) {
                assignmentSelect.innerHTML = data.assignments.length
                    ? data.assignments.map(assignment => `<option value="${assignment.id}">${assignment.assignment_state === 'upcoming' ? 'Upcoming assignment · ' : ''}${escapeHtml(assignment.event_name)} · ${escapeHtml(assignment.team_name)} · ${escapeHtml(assignment.activity_name)}</option>`).join('')
                    : '<option value="">No active assignment</option>';
                if (data.selected) assignmentSelect.value = data.selected.id;
                assignmentsLoaded = true;
            }
            currentPage = data.pagination.current_page;
            render(data);
        } catch (error) {
            if (request !== requestSequence) return;
            window.Notifications?.error(error.response?.data?.message || 'Unable to load students.');
        }
    };

    form.elements.search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => load(1), 300);
    });
    form.elements.status.addEventListener('change', () => load(1));
    assignmentSelect.addEventListener('change', () => load(1));
    desktop.addEventListener('change', () => latestData && renderStudents(latestData));
    SboPortal.initialize('students').then(() => load(1));
})();
