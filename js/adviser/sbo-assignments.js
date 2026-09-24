(() => {
    'use strict';

    const API = 'api/sbo-assignments.php';
    let csrf = '';
    let editingId = null;
    let data = { officers: [], events: [], tasks: [], assignment_groups: [] };

    const esc = value => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const form = document.querySelector('[data-task-form]');
    const officer = document.querySelector('[data-task-officer]');
    const eventDays = document.querySelector('[data-task-events]');
    const scannerMode = form.elements.scanner_mode;
    const submit = document.querySelector('[data-task-submit]');
    const empty = document.querySelector('[data-task-officer-empty]');
    const dialog = document.querySelector('#event-responsibilities-dialog');
    const cancelEdit = document.querySelector('[data-task-cancel-edit]');
    const title = document.querySelector('[data-task-dialog-title]');
    const scannerNote = document.querySelector('[data-task-scanner-general-note]');
    const tabs = [...document.querySelectorAll('[data-officer-tab]')];
    const assignmentFilters = document.querySelector('[data-assignment-filters]');
    const assignmentSearch = assignmentFilters.elements.search;
    const assignmentEvent = assignmentFilters.elements.event;
    const assignmentDay = assignmentFilters.elements.event_day;
    const assignmentScannerMode = assignmentFilters.elements.scanner_mode;
    const assignmentCount = document.querySelector('[data-task-result-count]');
    const assignmentPagination = document.querySelector('[data-assignment-pagination]');
    const ASSIGNMENTS_PER_PAGE = 15;
    let assignmentPage = 1;
    let assignmentSearchTimer;

    const updateScannerNote = () => {
        scannerNote.textContent = scannerMode.value === 'general'
            ? 'General access can scan any student eligible for the event.'
            : scannerMode.value === 'specific'
                ? "Specific access automatically uses the officer's own tribe."
                : 'This event has mixed access. Choose General or Specific to apply it to the selected schedules.';
    };

    const removeMixedScannerOption = () => scannerMode.querySelector('option[value=""]')?.remove();

    const renderAssignmentDayOptions = groups => {
        const selected = assignmentDay.value;
        const days = [...new Map(groups.flatMap(group => group.schedules.map(schedule => [String(schedule.event_schedule_id), {
            id: schedule.event_schedule_id,
            label: `${schedule.schedule_date} — ${group.event_name}`,
        }]))).values()].sort((left, right) => right.label.localeCompare(left.label));
        assignmentDay.replaceChildren(new Option('All event days', ''));
        days.forEach(day => assignmentDay.add(new Option(day.label, day.id)));
        assignmentDay.value = [...assignmentDay.options].some(option => option.value === selected) ? selected : '';
    };

    const renderAssignmentEventOptions = groups => {
        const selected = assignmentEvent.value;
        const events = [...new Map(groups.map(group => [String(group.event_id), {
            id: group.event_id,
            name: group.event_name,
        }])).values()].sort((left, right) => left.name.localeCompare(right.name));
        assignmentEvent.replaceChildren(new Option('All events', ''));
        events.forEach(event => assignmentEvent.add(new Option(event.name, event.id)));
        assignmentEvent.value = [...assignmentEvent.options].some(option => option.value === selected) ? selected : '';
    };

    const renderAssignmentPagination = (total, lastPage) => {
        assignmentPagination.classList.toggle('hidden', lastPage <= 1);
        assignmentPagination.replaceChildren();
        if (lastPage <= 1) return;

        const nav = document.createElement('nav');
        nav.className = 'flex flex-col items-center justify-between gap-3 text-xs sm:flex-row';
        nav.setAttribute('aria-label', 'Assignment pages');

        const first = (assignmentPage - 1) * ASSIGNMENTS_PER_PAGE + 1;
        const last = Math.min(assignmentPage * ASSIGNMENTS_PER_PAGE, total);
        const count = document.createElement('small');
        count.className = 'text-[#121017]/40';
        count.textContent = `Showing ${first}–${last} of ${total}`;

        const controls = document.createElement('div');
        controls.className = 'flex items-center gap-2';
        const pageButton = (label, target, disabled) => {
            const control = document.createElement('button');
            control.type = 'button';
            control.disabled = disabled;
            control.className = 'min-h-9 rounded-lg border border-[#121017]/12 px-3 font-bold text-[#121017]/65 hover:bg-[#F3F0E9]/50 disabled:bg-[#F3F0E9]/50 disabled:text-[#121017]/25 disabled:hover:bg-[#F3F0E9]/50';
            control.textContent = label;
            control.addEventListener('click', () => {
                assignmentPage = target;
                renderFilteredAssignmentGroups();
                document.querySelector('#officer-assignments-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
            return control;
        };
        const position = document.createElement('span');
        position.className = 'px-2 text-[#121017]/40';
        position.textContent = `${assignmentPage} / ${lastPage}`;
        controls.append(
            pageButton('Previous', assignmentPage - 1, assignmentPage === 1),
            position,
            pageButton('Next', assignmentPage + 1, assignmentPage === lastPage),
        );
        nav.append(count, controls);
        assignmentPagination.append(nav);
    };

    const renderFilteredAssignmentGroups = () => {
        const allGroups = data.assignment_groups || [];
        const query = assignmentSearch.value.trim().toLocaleLowerCase();
        const eventId = assignmentEvent.value;
        const eventDay = assignmentDay.value;
        const scannerMode = assignmentScannerMode.value;
        const groups = allGroups.filter(group => {
            const searchable = [group.officer_name, group.event_name, ...group.schedules.flatMap(schedule => [schedule.team_name, schedule.schedule_date])]
                .join(' ').toLocaleLowerCase();
            return (!query || searchable.includes(query))
                && (!eventId || String(group.event_id) === eventId)
                && (!eventDay || group.schedules.some(schedule => String(schedule.event_schedule_id) === eventDay))
                && (!scannerMode || group.scanner_mode === scannerMode || (group.scanner_mode === 'mixed' && group.schedules.some(schedule => schedule.scanner_mode === scannerMode)));
        });
        const lastPage = Math.max(1, Math.ceil(groups.length / ASSIGNMENTS_PER_PAGE));
        assignmentPage = Math.min(assignmentPage, lastPage);
        const start = (assignmentPage - 1) * ASSIGNMENTS_PER_PAGE;
        const visibleGroups = groups.slice(start, start + ASSIGNMENTS_PER_PAGE);
        assignmentCount.textContent = `${groups.length} ${groups.length === 1 ? 'event access' : 'event accesses'}${groups.length !== allGroups.length ? ` of ${allGroups.length}` : ''}`;
        const taskList = document.querySelector('[data-task-list]');
        taskList.innerHTML = groups.length ? visibleGroups.map(group => {
            const specificTeams = [...new Set(group.schedules.filter(schedule => schedule.scanner_mode === 'specific').map(schedule => schedule.team_name).filter(Boolean))];
            const scannerLabel = group.scanner_mode === 'mixed'
                ? `<strong class="block text-[#9a570d]">Mixed access</strong><span class="mt-1 block text-xs">${group.general_count} General · ${group.specific_count} Specific</span>`
                : group.scanner_mode === 'general'
                    ? '<strong class="text-[#397565]">General</strong><span class="mt-1 block text-xs">All eligible tribes/teams</span>'
                    : `<strong class="text-[#397565]">Specific</strong><span class="mt-1 block text-xs">${esc(specificTeams.join(', ') || "Officer's tribe")}</span>`;
            const schedules = group.schedules.map(schedule => `<span class="inline-flex min-h-8 items-center rounded-lg border border-[#121017]/10 bg-[#F3F0E9]/45 px-2.5 py-1 text-xs font-bold text-[#121017]/65">${esc(schedule.schedule_date)}<small class="ml-1.5 font-black ${schedule.scanner_mode === 'general' ? 'text-[#397565]' : 'text-[#121017]/40'}">${schedule.scanner_mode === 'general' ? 'General' : 'Specific'}</small></span>`).join('');
            return `<tr>
                <td data-label="Officer" class="whitespace-nowrap px-5 py-4 font-black">${esc(group.officer_name)}</td>
                <td data-label="Event" class="px-4 py-4 font-bold">${esc(group.event_name)}</td>
                <td data-label="Event schedules" class="px-4 py-4 text-[#121017]/65"><div class="flex flex-wrap gap-1.5">${schedules}</div></td>
                <td data-label="Attendance scanner access" class="px-4 py-4 text-[#121017]/65">${scannerLabel}</td>
                <td data-label="Action" class="px-5 py-4 text-right"><div class="flex flex-nowrap justify-end gap-2 whitespace-nowrap"><button class="min-h-9 rounded-lg border border-[#397565]/25 px-3 text-xs font-black text-[#397565]" type="button" data-task-edit="${esc(group.key)}">Edit</button><button class="min-h-9 rounded-lg border border-[#FF6B2C]/25 px-3 text-xs font-black text-[#d9470a]" type="button" data-task-end-event data-officer-assignment-id="${group.officer_assignment_id}" data-event-id="${group.event_id}">End</button></div></td>
            </tr>`;
        }).join('') : `<tr><td class="px-5 py-8 text-center text-sm text-[#121017]/45" colspan="5">${allGroups.length ? 'No event access matches these filters.' : 'No event access assigned.'}</td></tr>`;
        renderAssignmentPagination(groups.length, lastPage);
    };

    const selectedEventDays = () => [...eventDays.querySelectorAll('input[type="checkbox"]:checked')].map(input => Number(input.value));

    const syncSubmit = () => {
        submit.disabled = !data.officers.length || !data.events.length || !selectedEventDays().length || !scannerMode.value;
    };

    const resetEdit = () => {
        editingId = null;
        removeMixedScannerOption();
        form.reset();
        eventDays.querySelectorAll('input[type="checkbox"]').forEach(input => { input.checked = false; });
        officer.disabled = !data.officers.length;
        title.textContent = 'Event Responsibilities';
        submit.textContent = 'Assign event access';
        cancelEdit.classList.add('hidden');
        updateScannerNote();
        syncSubmit();
    };

    const selectTab = name => tabs.forEach(tab => {
        const active = tab.dataset.officerTab === name;
        tab.setAttribute('aria-selected', String(active));
        tab.classList.toggle('bg-white', active);
        tab.classList.toggle('text-[#397565]', active);
        tab.classList.toggle('shadow-sm', active);
        tab.classList.toggle('text-[#121017]/55', !active);
        document.querySelector(`[data-officer-tab-panel="${tab.dataset.officerTab}"]`)?.classList.toggle('hidden', !active);
    });

    const render = () => {
        const hasOfficers = data.officers.length > 0;
        const hasEvents = data.events.length > 0;
        officer.innerHTML = hasOfficers
            ? data.officers.map(item => `<option value="${item.id}">${esc(item.full_name)}</option>`).join('')
            : '<option value="">No active Officer access</option>';
        eventDays.innerHTML = hasEvents
            ? data.events.map(item => `<label class="task-event-day"><input type="checkbox" name="event_schedule_ids[]" value="${item.schedule_id}"><span><strong>${esc(item.title)}</strong><small>${esc(item.schedule_date)}</small></span></label>`).join('')
            : '<p class="px-3 py-5 text-center text-sm text-[#121017]/45">No event days available.</p>';
        officer.disabled = !hasOfficers;
        syncSubmit();
        empty.classList.toggle('hidden', hasOfficers);
        renderAssignmentEventOptions(data.assignment_groups || []);
        renderAssignmentDayOptions(data.assignment_groups || []);
        renderFilteredAssignmentGroups();
    };

    const load = async () => {
        csrf = (await axios.get('api/auth.php?action=session')).data.csrf_token;
        data = (await axios.get(API)).data.data;
        render();
    };

    tabs.forEach(tab => tab.addEventListener('click', () => selectTab(tab.dataset.officerTab)));
    assignmentFilters.addEventListener('submit', event => event.preventDefault());
    assignmentSearch.addEventListener('input', () => {
        clearTimeout(assignmentSearchTimer);
        assignmentSearchTimer = setTimeout(() => {
            assignmentPage = 1;
            renderFilteredAssignmentGroups();
        }, 180);
    });
    [assignmentDay, assignmentEvent, assignmentScannerMode].forEach(filter => filter.addEventListener('change', () => {
        assignmentPage = 1;
        renderFilteredAssignmentGroups();
    }));
    eventDays.addEventListener('change', syncSubmit);
    scannerMode.addEventListener('change', () => {
        updateScannerNote();
        syncSubmit();
    });
    document.querySelector('[data-officer-tab="assignments"]')?.addEventListener('click', () => load().catch(error => window.Notifications?.error(error.response?.data?.message || 'Unable to load event access.')));
    document.querySelector('[data-dialog-open="event-responsibilities-dialog"]')?.addEventListener('click', async () => {
        try {
            await load();
            resetEdit();
            dialog.showModal();
            officer.focus();
        } catch (error) {
            window.Notifications?.error(error.response?.data?.message || 'Unable to load event access.');
        }
    });
    dialog.querySelectorAll('[data-dialog-close]').forEach(close => close.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', resetEdit);
    cancelEdit.addEventListener('click', resetEdit);
    document.querySelector('[data-task-create-officer]').addEventListener('click', () => {
        dialog.close();
        document.querySelector('[data-dialog-open="assign-officer-dialog"]')?.click();
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const wasEditing = editingId !== null;
        const scheduleIds = selectedEventDays();
        if (!scheduleIds.length) {
            window.Notifications?.error('Select at least one event day.');
            return;
        }
        const payload = {
            action: 'assign',
            officer_assignment_id: officer.value,
            event_schedule_ids: scheduleIds,
            scanner_mode: scannerMode.value,
        };
        submit.disabled = true;
        try {
            const response = await axios.post(API, payload, { headers: { 'X-CSRF-Token': csrf } });
            window.Notifications?.success(response.data.message);
            await load();
            resetEdit();
            if (wasEditing && dialog.open) dialog.close();
        } catch (error) {
            submit.disabled = false;
            window.Notifications?.error(error.response?.data?.message || 'Event access could not be saved.');
        }
    });

    document.querySelector('[data-task-list]').addEventListener('click', async event => {
        const edit = event.target.closest('[data-task-edit]');
        if (edit) {
            try {
                await load();
                const group = (data.assignment_groups || []).find(item => item.key === edit.dataset.taskEdit);
                if (!group) throw new Error('That event access is no longer active.');
                editingId = group.key;
                title.textContent = `Edit ${group.event_name}`;
                submit.textContent = 'Save selected schedules';
                cancelEdit.classList.remove('hidden');
                officer.value = String(group.officer_assignment_id);
                officer.disabled = true;
                const scheduleIds = new Set(group.schedules.map(schedule => schedule.event_schedule_id));
                eventDays.querySelectorAll('input[type="checkbox"]').forEach(input => { input.checked = scheduleIds.has(Number(input.value)); });
                removeMixedScannerOption();
                if (group.scanner_mode === 'mixed') {
                    const mixedOption = new Option('Mixed — choose General or Specific', '', true, true);
                    mixedOption.disabled = true;
                    scannerMode.insertBefore(mixedOption, scannerMode.firstChild);
                } else {
                    scannerMode.value = group.scanner_mode || 'specific';
                }
                updateScannerNote();
                syncSubmit();
                if (!dialog.open) dialog.showModal();
                scannerMode.focus();
            } catch (error) {
                window.Notifications?.error(error.message || 'Unable to edit event access.');
            }
            return;
        }

        const button = event.target.closest('[data-task-end-event]');
        if (!button) return;
        const yes = await (window.Notifications?.confirm?.({
            title: 'End event access?',
            message: 'The officer will immediately lose attendance, scoring, and media access for every schedule in this event.',
            action: 'End access',
        }) ?? Promise.resolve(false));
        if (!yes) return;
        try {
            const response = await axios.post(API, {
                action: 'end_event',
                officer_assignment_id: button.dataset.officerAssignmentId,
                event_id: button.dataset.eventId,
            }, { headers: { 'X-CSRF-Token': csrf } });
            window.Notifications?.success(response.data.message);
            await load();
        } catch (error) {
            window.Notifications?.error(error.response?.data?.message || 'Unable to end event access.');
        }
    });

    window.SboOfficerAssignments = {
        activate: async id => {
            selectTab('assignments');
            await load();
            resetEdit();
            if (id) officer.value = String(id);
            dialog.showModal();
            officer.focus();
        },
    };
})();
