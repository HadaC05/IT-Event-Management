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

    const updateScannerNote = () => {
        scannerNote.textContent = scannerMode.value === 'general'
            ? 'General access can scan any student eligible for the event.'
            : "Specific access automatically uses the officer's own tribe.";
    };

    const selectedEventDays = () => [...eventDays.querySelectorAll('input[type="checkbox"]:checked')].map(input => Number(input.value));

    const syncSubmit = () => {
        submit.disabled = !data.officers.length || !data.events.length || !selectedEventDays().length;
    };

    const resetEdit = () => {
        editingId = null;
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

        const groups = data.assignment_groups || [];
        const taskList = document.querySelector('[data-task-list]');
        taskList.innerHTML = groups.length ? groups.map(group => {
            const scannerLabel = group.scanner_mode === 'general'
                ? 'General — all eligible tribes/teams'
                : `Specific — ${esc(group.team_name || "officer's tribe")}`;
            return `<tr>
                <td data-label="Officer" class="whitespace-nowrap px-5 py-4 font-black">${esc(group.officer_name)}</td>
                <td data-label="Event day" class="px-4 py-4"><strong class="block font-bold">${esc(group.event_name)}</strong><span class="mt-1 block whitespace-nowrap text-xs text-[#121017]/50">${esc(group.schedule_date)}</span></td>
                <td data-label="Attendance scanner access" class="px-4 py-4 text-[#121017]/65">${scannerLabel}</td>
                <td data-label="Action" class="px-5 py-4 text-right"><div class="flex flex-wrap justify-end gap-2"><button class="min-h-9 rounded-lg border border-[#397565]/25 px-3 text-xs font-black text-[#397565]" type="button" data-task-edit="${group.id}">Edit</button><button class="min-h-9 rounded-lg border border-[#FF6B2C]/25 px-3 text-xs font-black text-[#d9470a]" type="button" data-task-end="${group.id}">End</button></div></td>
            </tr>`;
        }).join('') : '<tr><td class="px-5 py-8 text-center text-sm text-[#121017]/45" colspan="4">No event access assigned.</td></tr>';
    };

    const load = async () => {
        csrf = (await axios.get('api/auth.php?action=session')).data.csrf_token;
        data = (await axios.get(API)).data.data;
        render();
    };

    tabs.forEach(tab => tab.addEventListener('click', () => selectTab(tab.dataset.officerTab)));
    eventDays.addEventListener('change', syncSubmit);
    scannerMode.addEventListener('change', updateScannerNote);
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
                const group = (data.assignment_groups || []).find(item => item.id === Number(edit.dataset.taskEdit));
                if (!group) throw new Error('That event access is no longer active.');
                editingId = group.id;
                title.textContent = 'Edit or add event days';
                submit.textContent = 'Save selected days';
                cancelEdit.classList.remove('hidden');
                officer.value = String(group.officer_assignment_id);
                officer.disabled = true;
                eventDays.querySelectorAll('input[type="checkbox"]').forEach(input => { input.checked = Number(input.value) === group.event_schedule_id; });
                scannerMode.value = group.scanner_mode || 'specific';
                updateScannerNote();
                syncSubmit();
                if (!dialog.open) dialog.showModal();
                scannerMode.focus();
            } catch (error) {
                window.Notifications?.error(error.message || 'Unable to edit event access.');
            }
            return;
        }

        const button = event.target.closest('[data-task-end]');
        if (!button) return;
        const yes = await (window.Notifications?.confirm?.({
            title: 'End event access?',
            message: 'The officer will immediately lose attendance, scoring, and media access for this event day.',
            action: 'End access',
        }) ?? Promise.resolve(false));
        if (!yes) return;
        try {
            const response = await axios.post(API, { action: 'end', id: button.dataset.taskEnd }, { headers: { 'X-CSRF-Token': csrf } });
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
