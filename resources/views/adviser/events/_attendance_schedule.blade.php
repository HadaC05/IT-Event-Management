@php
    $savedSchedules = collect(old('attendance_days', ($editing ?? false) ? $event->attendanceSchedules->map(fn ($schedule) => [
        'date' => $schedule->schedule_date->toDateString(), 'attendance_session_mode_id' => $schedule->attendance_session_mode_id,
        'morning_in' => filled($schedule->whole_day_in_time ?? $schedule->morning_in_time) ? substr((string) ($schedule->whole_day_in_time ?? $schedule->morning_in_time), 0, 5) : null,
        'morning_out' => filled($schedule->whole_day_out_time ?? $schedule->morning_out_time) ? substr((string) ($schedule->whole_day_out_time ?? $schedule->morning_out_time), 0, 5) : null,
        'afternoon_in' => filled($schedule->afternoon_in_time) ? substr((string) $schedule->afternoon_in_time, 0, 5) : null,
        'afternoon_out' => filled($schedule->afternoon_out_time) ? substr((string) $schedule->afternoon_out_time, 0, 5) : null,
    ])->values()->all() : []));
    $minimumDate = now()->toDateString();
@endphp

<div data-attendance-schedule data-initial-schedules='@json($savedSchedules)'>
    @foreach(['start_date' => 'start_at', 'start_time' => 'start_at', 'end_date' => 'end_at', 'end_time' => 'end_at'] as $name => $attribute)<input type="hidden" name="{{ $name }}" value="{{ old($name, ($editing ?? false) ? $event->{$attribute}->format(str_ends_with($name, 'date') ? 'Y-m-d' : 'H:i') : '') }}" data-event-required>@endforeach
    <div class="mb-4 flex items-center justify-between gap-3"><p class="text-xs text-slate-500">Add each event day and its attendance session.</p><button class="inline-flex min-h-10 items-center rounded-xl border border-sky-200 bg-white px-3 text-xs font-extrabold text-sky-700 transition hover:border-sky-400 hover:bg-sky-50" type="button" data-add-schedule>+ Add schedule</button></div>
    <div class="grid gap-3" data-schedules></div>
    @if($errors->has('attendance_days') || $errors->has('attendance_days.*'))<p class="mt-3 text-xs font-semibold text-red-600">{{ $errors->first('attendance_days') ?: $errors->first('attendance_days.*') }}</p>@endif
</div>

@push('scripts')
<script>
(() => {
    const section = document.querySelector('[data-attendance-schedule]'); if (!section) return;
    const form = section.closest('form'), list = section.querySelector('[data-schedules]'), addButton = section.querySelector('[data-add-schedule]');
    const schedules = JSON.parse(section.dataset.initialSchedules || '[]');
    const modes = @json($attendanceSessionModes->map(fn ($mode) => ['id' => $mode->id, 'code' => $mode->code, 'name' => $mode->name])->values());
    const defaultMode = modes.find((mode) => mode.code === 'whole_day') || modes[0], keys = ['morning_in', 'morning_out', 'afternoon_in', 'afternoon_out'];
    const rows = () => [...list.querySelectorAll('[data-schedule-row]')];
    const selectedMode = (row) => modes.find((mode) => String(mode.id) === row.querySelector('[data-session-mode]').value) || modes[0];
    const values = () => rows().map((row) => ({ date: row.querySelector('[data-schedule-date]').value, attendance_session_mode_id: row.querySelector('[data-session-mode]').value, ...Object.fromEntries(keys.map((key) => [key, row.querySelector(`[data-checkpoint="${key}"]`).value])) }));
    const sync = () => {
        rows().forEach((row, index) => { row.querySelector('[data-schedule-number]').textContent = `Schedule ${index + 1}`; row.querySelector('[data-remove-schedule]').hidden = rows().length === 1; row.querySelectorAll('[name]').forEach((input) => input.name = input.name.replace(/attendance_days\[\d+\]/, `attendance_days[${index}]`)); });
        const days = values().filter((day) => day.date).sort((a, b) => a.date.localeCompare(b.date)), first = days[0], last = days.at(-1), firstMode = first && modes.find((mode) => String(mode.id) === first.attendance_session_mode_id), lastMode = last && modes.find((mode) => String(mode.id) === last.attendance_session_mode_id);
        const globals = { start_date: first?.date || '', start_time: first ? (firstMode?.code === 'none' ? '00:00' : first.morning_in) : '', end_date: last?.date || '', end_time: last ? (lastMode?.code === 'two_sessions' ? last.afternoon_out : (lastMode?.code === 'whole_day' ? last.morning_out : '23:59')) : '' };
        Object.entries(globals).forEach(([name, value]) => { const input = form.elements[name]; if (input.value !== value) { input.value = value || ''; input.dispatchEvent(new Event('change', { bubbles: true })); } });
    };
    const applyMode = (row, defaults = false) => {
        const mode = selectedMode(row), enabled = mode.code !== 'none', split = mode.code === 'two_sessions', checkpoint = (key) => row.querySelector(`[data-checkpoint="${key}"]`);
        row.querySelectorAll('[data-primary]').forEach((field) => { field.hidden = !enabled; field.querySelector('input').required = enabled; field.querySelector('input').disabled = !enabled; }); row.querySelectorAll('[data-secondary]').forEach((field) => { field.hidden = !split; field.querySelector('input').required = split; field.querySelector('input').disabled = !split; if (!split) field.querySelector('input').value = ''; });
        if (defaults && mode.code === 'whole_day') { checkpoint('morning_in').value = '08:00'; checkpoint('morning_out').value = '18:00'; }
        if (defaults && mode.code === 'two_sessions') { checkpoint('morning_in').value = '08:00'; checkpoint('morning_out').value = '11:00'; checkpoint('afternoon_in').value = '13:00'; checkpoint('afternoon_out').value = '18:00'; }
        row.querySelector('[data-in-label]').innerHTML = `${split ? 'Morning time in' : 'Time in'} <i class="font-normal text-rose-500">*</i>`; row.querySelector('[data-out-label]').innerHTML = `${split ? 'Morning time out' : 'Time out'} <i class="font-normal text-rose-500">*</i>`; sync();
    };
    const options = modes.map((mode) => `<option value="${mode.id}">${mode.name}</option>`).join('');
    const addSchedule = (day = {}, defaults = false) => {
        const index = rows().length, row = document.createElement('div'); row.dataset.scheduleRow = ''; row.className = 'rounded-xl border border-slate-200 bg-white p-4';
        row.innerHTML = `<div class="mb-3 flex items-center justify-between"><strong class="text-xs font-extrabold text-slate-600" data-schedule-number>Schedule ${index + 1}</strong><button class="text-xs font-bold text-rose-600 hover:text-rose-700" type="button" data-remove-schedule>Remove</button></div><div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><label class="grid min-w-0 gap-2"><span class="text-xs font-bold text-slate-700">Date <i class="font-normal text-rose-500">*</i></span><input class="h-11 w-full min-w-0 rounded-xl border border-slate-200 px-3 text-sm" type="date" min="{{ $minimumDate }}" name="attendance_days[${index}][date]" value="${day.date || ''}" required data-schedule-date></label><label class="grid min-w-0 gap-2"><span class="text-xs font-bold text-slate-700">Session <i class="font-normal text-rose-500">*</i></span><select class="h-11 w-full min-w-0 max-w-full rounded-xl border border-slate-200 bg-white px-3 text-sm" name="attendance_days[${index}][attendance_session_mode_id]" data-session-mode>${options}</select></label><label class="grid min-w-0 gap-2" data-primary><span class="text-xs font-bold text-slate-700" data-in-label>Time in <i class="font-normal text-rose-500">*</i></span><input class="h-11 w-full min-w-0 rounded-xl border border-slate-200 px-3 text-sm" type="time" name="attendance_days[${index}][morning_in]" value="${day.morning_in || ''}" data-checkpoint="morning_in"></label><label class="grid min-w-0 gap-2" data-primary><span class="text-xs font-bold text-slate-700" data-out-label>Time out <i class="font-normal text-rose-500">*</i></span><input class="h-11 w-full min-w-0 rounded-xl border border-slate-200 px-3 text-sm" type="time" name="attendance_days[${index}][morning_out]" value="${day.morning_out || ''}" data-checkpoint="morning_out"></label><label class="grid min-w-0 gap-2" data-secondary><span class="text-xs font-bold text-slate-700">Afternoon time in <i class="font-normal text-rose-500">*</i></span><input class="h-11 w-full min-w-0 rounded-xl border border-slate-200 px-3 text-sm" type="time" name="attendance_days[${index}][afternoon_in]" value="${day.afternoon_in || ''}" data-checkpoint="afternoon_in"></label><label class="grid min-w-0 gap-2" data-secondary><span class="text-xs font-bold text-slate-700">Afternoon time out <i class="font-normal text-rose-500">*</i></span><input class="h-11 w-full min-w-0 rounded-xl border border-slate-200 px-3 text-sm" type="time" name="attendance_days[${index}][afternoon_out]" value="${day.afternoon_out || ''}" data-checkpoint="afternoon_out"></label></div>`;
        list.appendChild(row); row.querySelector('[data-session-mode]').value = day.attendance_session_mode_id || defaultMode?.id || ''; applyMode(row, defaults);
    };
    const nextDate = () => { const date = values().map((day) => day.date).filter(Boolean).sort().at(-1); if (!date) return ''; const next = new Date(`${date}T12:00:00`); next.setDate(next.getDate() + 1); return `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}-${String(next.getDate()).padStart(2, '0')}`; };
    addButton.addEventListener('click', () => { const previous = values().at(-1) || {}; addSchedule({ ...previous, date: nextDate() }, !previous.attendance_session_mode_id); });
    section.addEventListener('input', sync); section.addEventListener('change', (event) => event.target.matches('[data-session-mode]') ? applyMode(event.target.closest('[data-schedule-row]'), true) : sync()); section.addEventListener('click', (event) => { const remove = event.target.closest('[data-remove-schedule]'); if (remove) { remove.closest('[data-schedule-row]').remove(); sync(); } });
    (schedules.length ? schedules : [{}]).forEach((schedule) => addSchedule(schedule, !schedule.attendance_session_mode_id)); sync();
})();
</script>
@endpush
