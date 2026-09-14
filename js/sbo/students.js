(() => {
    'use strict';

    const API = 'api/sbo-students.php';
    const form = document.querySelector('[data-filters]');
    const tableBody = document.querySelector('[data-students]');
    const cards = document.querySelector('[data-student-cards]');
    const assignmentSelect = document.querySelector('[data-assignment]');
    let assignmentsLoaded = false;

    const escapeHtml = value => SboPortal.escapeHtml(value);
    const statusBadge = student => `<span class="shrink-0 rounded-full px-3 py-1 text-[10px] font-black uppercase ${student.attendance_status === 'not_recorded' ? 'bg-[#121017]/7 text-[#121017]/45' : 'bg-[#C6F24E]/35 text-[#397565]'}">${escapeHtml(student.attendance_status.replace('_', ' '))}</span>`;

    const tableRow = student => `
        <tr>
            <td class="p-4 text-sm font-bold">${escapeHtml(student.id_number)}</td>
            <td class="p-4"><strong class="text-sm">${escapeHtml(student.full_name)}</strong><span class="block text-xs text-[#121017]/45">${escapeHtml(student.email)}</span></td>
            <td class="p-4 text-sm">CITE · ${escapeHtml(student.year_level || 'Not specified')}</td>
            <td class="p-4 text-sm font-bold text-[#397565]">${escapeHtml(student.team_name)}</td>
            <td class="p-4">${statusBadge(student)}</td>
        </tr>`;

    const mobileCard = student => `
        <article class="min-w-0 rounded-2xl border border-[#121017]/10 bg-white p-4 shadow-sm">
            <div class="flex min-w-0 items-start justify-between gap-3">
                <div class="min-w-0"><strong class="block truncate text-sm">${escapeHtml(student.full_name)}</strong><span class="mt-1 block text-xs font-bold text-[#397565]">${escapeHtml(student.id_number)}</span></div>
                ${statusBadge(student)}
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-[#121017]/8 pt-3 text-xs">
                <div><dt class="text-[9px] font-black uppercase tracking-wider text-[#121017]/40">Course & year</dt><dd class="mt-1 font-bold">CITE · ${escapeHtml(student.year_level || 'Not specified')}</dd></div>
                <div><dt class="text-[9px] font-black uppercase tracking-wider text-[#121017]/40">Team</dt><dd class="mt-1 font-bold text-[#397565]">${escapeHtml(student.team_name)}</dd></div>
                <div class="col-span-2 min-w-0"><dt class="text-[9px] font-black uppercase tracking-wider text-[#121017]/40">Email</dt><dd class="mt-1 truncate">${escapeHtml(student.email)}</dd></div>
            </dl>
        </article>`;

    const load = async () => {
        try {
            const params = Object.fromEntries(new FormData(form));
            const {data} = (await axios.get(API, {params})).data;
            if (!assignmentsLoaded) {
                assignmentSelect.innerHTML = data.assignments.length
                    ? data.assignments.map(assignment => `<option value="${assignment.id}">${escapeHtml(assignment.event_name)} · ${escapeHtml(assignment.team_name)} · ${escapeHtml(assignment.activity_name)}</option>`).join('')
                    : '<option value="">No active assignment</option>';
                if (data.selected) assignmentSelect.value = data.selected.id;
                assignmentsLoaded = true;
            }

            const emptyMessage = data.assignments.length
                ? 'No students match these filters.'
                : 'You currently have no active event assignment.';
            tableBody.innerHTML = data.students.length
                ? data.students.map(tableRow).join('')
                : `<tr><td colspan="5" class="p-12 text-center text-sm text-[#121017]/45">${emptyMessage}</td></tr>`;
            cards.innerHTML = data.students.length
                ? data.students.map(mobileCard).join('')
                : `<div class="rounded-2xl border border-dashed border-[#121017]/15 px-5 py-10 text-center text-sm leading-6 text-[#121017]/45">${emptyMessage}</div>`;
        } catch (error) {
            window.Notifications?.error(error.response?.data?.message || 'Unable to load students.');
        }
    };

    let searchTimer;
    form.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(load, 250);
    });
    form.addEventListener('change', load);
    SboPortal.initialize('students').then(load);
})();
