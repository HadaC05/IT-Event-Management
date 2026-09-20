(() => {
    'use strict';

    const API = 'api/sbo-assignments.php';
    let csrf = '';
    let data = { officers: [], events: [], teams: [], tasks: [] };
    const esc = value => String(value ?? '')
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;');

    const headerButton = document.querySelector('[data-dialog-open="assign-officer-dialog"]');
    headerButton?.insertAdjacentHTML('beforebegin', '<button class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[#397565]/25 bg-white px-5 text-sm font-black text-[#397565]" type="button" data-task-open>Event Responsibilities</button>');
    document.body.insertAdjacentHTML('beforeend', `
        <dialog class="m-auto max-h-[calc(100vh_-_2rem)] w-[min(880px,calc(100%_-_2rem))] overflow-y-auto rounded-2xl border-0 bg-white p-0 shadow-2xl backdrop:bg-[#121017]/60" data-task-dialog>
            <header class="sticky top-0 z-10 flex items-center justify-between border-b border-[#121017]/8 border-t-4 border-t-[#397565] bg-white p-5">
                <div><p class="text-xs font-black uppercase tracking-wider text-[#397565]">Backend-enforced access</p><h2 class="text-2xl font-black">Event Responsibilities</h2></div>
                <button class="grid h-10 w-10 place-items-center rounded-xl bg-[#F3F0E9] text-xl" type="button" data-task-close aria-label="Close event responsibilities">×</button>
            </header>
            <form class="grid gap-4 p-5 sm:grid-cols-2" data-task-form>
                <div class="hidden rounded-xl bg-[#397565]/7 p-4 sm:col-span-2" data-task-officer-empty>
                    <strong class="block text-sm font-black text-[#397565]">Create Officer access first</strong>
                    <p class="mt-1 text-xs leading-5 text-[#121017]/55">No active Officer assignment exists. Changing a user role is not enough—the student needs a separate Officer sign-in and assignment.</p>
                    <button class="mt-3 min-h-10 rounded-xl bg-[#397565] px-4 text-xs font-black text-white" type="button" data-task-create-officer>Create Officer Access</button>
                </div>
                <label class="grid gap-2"><span class="text-sm font-bold">SBO Officer</span><select class="h-12 rounded-xl border border-[#121017]/12 px-3" name="officer_assignment_id" required data-task-officer></select></label>
                <label class="grid gap-2"><span class="text-sm font-bold">Event day</span><select class="h-12 rounded-xl border border-[#121017]/12 px-3" name="event_schedule_id" required data-task-event></select></label>
                <label class="grid gap-2"><span class="text-sm font-bold">Session</span><select class="h-12 rounded-xl border border-[#121017]/12 px-3" name="session_code" required data-task-session></select></label>
                <label class="grid gap-2"><span class="text-sm font-bold">Activity</span><input class="h-12 rounded-xl border border-[#121017]/12 px-3" name="activity_name" maxlength="120" placeholder="e.g. Basketball" required></label>
                <label class="grid gap-2"><span class="text-sm font-bold">Team / tribe</span><select class="h-12 rounded-xl border border-[#121017]/12 px-3" name="team_id" required data-task-team></select></label>
                <label class="grid gap-2"><span class="text-sm font-bold">Responsibility</span><select class="h-12 rounded-xl border border-[#121017]/12 px-3" name="responsibility" required><option value="attendance">Attendance</option><option value="scoring">Scoring</option><option value="media">Media</option></select></label>
                <div class="flex justify-end sm:col-span-2"><button class="min-h-11 rounded-xl bg-[#397565] px-5 text-sm font-black text-white" type="submit" data-task-submit>Assign responsibility</button></div>
            </form>
            <section class="border-t border-[#121017]/8 p-5"><h3 class="mb-3 font-black">Active responsibilities</h3><div class="grid gap-2" data-task-list></div></section>
        </dialog>`);

    const dialog = document.querySelector('[data-task-dialog]');
    const form = document.querySelector('[data-task-form]');
    const officerSelect = document.querySelector('[data-task-officer]');
    const eventSelect = document.querySelector('[data-task-event]');
    const sessionSelect = document.querySelector('[data-task-session]');
    const submit = document.querySelector('[data-task-submit]');
    const emptyOfficer = document.querySelector('[data-task-officer-empty]');

    const sessionOptions = () => {
        const event = data.events.find(item => item.schedule_id === Number(eventSelect.value));
        const options = event?.session_mode === 'two_sessions'
            ? [['morning', 'Morning'], ['afternoon', 'Afternoon']]
            : event?.session_mode === 'whole_day' ? [['whole_day', 'Whole day']] : [];
        sessionSelect.innerHTML = options.length
            ? options.map(option => `<option value="${option[0]}">${option[1]}</option>`).join('')
            : '<option value="">No attendance session</option>';
    };

    const teamOptions = () => {
        const selectedEvent = data.events.find(item => item.schedule_id === Number(eventSelect.value));
        const allowed = new Set((selectedEvent?.allowed_team_ids || []).map(Number));
        const teams = data.teams.filter(team => allowed.has(Number(team.id)));
        const select = document.querySelector('[data-task-team]');
        select.innerHTML = teams.length
            ? teams.map(team => `<option value="${team.id}">${esc(team.name)}</option>`).join('')
            : '<option value="">No teams in this event period and audience</option>';
        select.disabled = !teams.length;
        submit.disabled = !data.officers.length || !teams.length;
    };

    const render = () => {
        const hasOfficers = data.officers.length > 0;
        officerSelect.innerHTML = hasOfficers
            ? data.officers.map(officer => `<option value="${officer.id}">${esc(officer.full_name)} · ${esc(officer.username)}</option>`).join('')
            : '<option value="">No active Officer access</option>';
        officerSelect.disabled = !hasOfficers;
        submit.disabled = !hasOfficers;
        emptyOfficer.classList.toggle('hidden', hasOfficers);
        eventSelect.innerHTML = data.events.length
            ? data.events.map(event => `<option value="${event.schedule_id}">${esc(event.title)} · ${esc(event.schedule_date)} · ${esc(event.school_year_label)} / ${esc(event.term_name)}</option>`).join('')
            : '<option value="">No event days available</option>';
        sessionOptions();
        teamOptions();
        document.querySelector('[data-task-list]').innerHTML = data.tasks.length
            ? data.tasks.map(task => `<article class="flex flex-wrap items-center gap-3 rounded-xl border border-[#121017]/9 bg-[#F3F0E9]/35 p-3"><div class="min-w-0 flex-1"><strong class="block text-sm">${esc(task.officer_name)} · ${esc(task.responsibility)}</strong><span class="block text-xs text-[#121017]/50">${esc(task.event_name)} · ${esc(task.schedule_date)} · ${esc(task.session_code)} · ${esc(task.activity_name)} · ${esc(task.team_name)}</span></div><button class="rounded-lg border border-[#FF6B2C]/25 px-3 py-2 text-xs font-black text-[#d9470a]" type="button" data-task-end="${task.id}">End</button></article>`).join('')
            : '<p class="py-6 text-center text-sm text-[#121017]/45">No event responsibilities assigned.</p>';
    };

    const load = async () => {
        const session = await axios.get('api/auth.php?action=session');
        csrf = session.data.csrf_token;
        data = (await axios.get(API)).data.data;
        render();
    };

    eventSelect.addEventListener('change', () => { sessionOptions(); teamOptions(); });
    document.querySelector('[data-task-open]')?.addEventListener('click', async () => {
        try {
            await load();
            dialog.showModal();
        } catch (error) {
            window.Notifications?.error(error.response?.data?.message || 'Unable to load responsibilities.');
        }
    });
    document.querySelector('[data-task-close]').addEventListener('click', () => dialog.close());
    document.querySelector('[data-task-create-officer]').addEventListener('click', () => {
        dialog.close();
        document.querySelector('[data-dialog-open="assign-officer-dialog"]')?.click();
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!data.officers.length) return;
        try {
            const payload = { action: 'assign', ...Object.fromEntries(new FormData(form)) };
            const response = await axios.post(API, payload, { headers: { 'X-CSRF-Token': csrf } });
            window.Notifications?.success(response.data.message);
            data = (await axios.get(API)).data.data;
            render();
        } catch (error) {
            window.Notifications?.error(error.response?.data?.message || 'Assignment failed.');
        }
    });
    document.querySelector('[data-task-list]').addEventListener('click', async event => {
        const button = event.target.closest('[data-task-end]');
        if (!button) return;
        const confirmed = await (window.Notifications?.confirm?.({
            title: 'End responsibility?',
            message: 'The officer will immediately lose this assigned access.',
            action: 'End responsibility',
        }) ?? Promise.resolve(confirm('End this responsibility?')));
        if (!confirmed) return;
        try {
            const response = await axios.post(API, { action: 'end', id: button.dataset.taskEnd }, { headers: { 'X-CSRF-Token': csrf } });
            window.Notifications?.success(response.data.message);
            data = (await axios.get(API)).data.data;
            render();
        } catch (error) {
            window.Notifications?.error(error.response?.data?.message || 'Unable to end responsibility.');
        }
    });
})();
