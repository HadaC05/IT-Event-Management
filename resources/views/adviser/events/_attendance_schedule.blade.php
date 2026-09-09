@php
    $savedAttendanceDays = collect(old('attendance_days', ($editing ?? false) ? $event->attendanceSchedules->map(fn ($day) => [
        'date' => $day->schedule_date->toDateString(), 'mode' => $day->session_mode,
        'morning_in' => $day->morning_in_time ? substr($day->morning_in_time, 0, 5) : '',
        'morning_out' => $day->morning_out_time ? substr($day->morning_out_time, 0, 5) : '',
        'afternoon_in' => $day->afternoon_in_time ? substr($day->afternoon_in_time, 0, 5) : '',
        'afternoon_out' => $day->afternoon_out_time ? substr($day->afternoon_out_time, 0, 5) : '',
    ])->values()->all() : []));
@endphp

<section class="overflow-hidden rounded-2xl border border-[#397565]/15 bg-white shadow-sm" data-attendance-schedule data-initial-schedules='@json($savedAttendanceDays)'>
    @foreach(['start_date' => 'start_at', 'start_time' => 'start_at', 'end_date' => 'end_at', 'end_time' => 'end_at'] as $name => $attribute)<input type="hidden" name="{{ $name }}" value="{{ old($name, ($editing ?? false) ? $event->{$attribute}->format(str_ends_with($name, 'date') ? 'Y-m-d' : 'H:i') : '') }}" data-event-required>@endforeach
    <header class="border-b border-[#121017]/7 px-5 py-5 sm:px-6"><div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><p class="text-[9px] font-black uppercase tracking-[.15em] text-[#397565]">When</p><h2 class="mt-1 text-lg font-extrabold text-[#121017]">Event days and attendance</h2><p class="mt-1 max-w-xl text-xs leading-5 text-slate-500">Choose each date separately. Times belong to that day’s attendance schedule.</p></div><button class="min-h-10 shrink-0 rounded-xl bg-[#397565] px-4 text-xs font-extrabold text-white disabled:opacity-40" type="button" data-add-event-day>+ Add another day</button></div></header>
    <div class="p-4 sm:p-6"><div class="grid gap-4" data-attendance-days></div><div class="mt-4 flex flex-wrap items-center justify-between gap-3"><p class="text-[10px] text-slate-400">Add up to 31 individual event days.</p><button class="hidden min-h-9 rounded-xl border border-[#397565]/20 bg-[#397565]/7 px-3 text-[10px] font-extrabold text-[#397565]" type="button" data-copy-first-day>Copy Day 1 schedule to all</button></div>@if($errors->has('attendance_days') || $errors->has('attendance_days.*'))<p class="mt-3 rounded-xl bg-red-50 px-4 py-3 text-xs font-semibold text-red-600">{{ $errors->first('attendance_days') ?: $errors->first('attendance_days.*') }}</p>@endif</div>
</section>

@push('scripts')
<script>
(() => {
    const section = document.querySelector('[data-attendance-schedule]');
    if (!section) return;
    const form = section.closest('form'), container = section.querySelector('[data-attendance-days]');
    const addButton = section.querySelector('[data-add-event-day]'), copyButton = section.querySelector('[data-copy-first-day]');
    const initial = JSON.parse(section.dataset.initialSchedules || '[]');
    const keys = ['morning_in', 'morning_out', 'afternoon_in', 'afternoon_out'];
    const cards = () => [...container.querySelectorAll('[data-day-card]')];
    const values = () => cards().map(card => ({ date: card.querySelector('[data-day-date]').value, mode: card.querySelector('[data-session-mode]').value, ...Object.fromEntries(keys.map(key => [key, card.querySelector(`[data-checkpoint="${key}"]`).value])) }));
    const sync = () => {
        cards().forEach((card, index) => { card.querySelector('[data-day-number]').textContent = `Day ${index + 1}`; card.querySelector('[data-remove-day]').classList.toggle('hidden', cards().length === 1); card.querySelectorAll('[name]').forEach(input => input.name = input.name.replace(/attendance_days\[\d+\]/, `attendance_days[${index}]`)); });
        copyButton.classList.toggle('hidden', cards().length < 2); addButton.disabled = cards().length >= 31;
        const days = values().filter(day => day.date).sort((a, b) => a.date.localeCompare(b.date)), first = days[0], last = days.at(-1);
        const globals = { start_date: first?.date || '', start_time: first ? (first.mode === 'none' ? '00:00' : first.morning_in) : '', end_date: last?.date || '', end_time: last ? (last.mode === 'split' ? last.afternoon_out : (last.mode === 'single' ? last.morning_out : '23:59')) : '' };
        Object.entries(globals).forEach(([name, value]) => { const input = form.elements[name]; if (input.value !== value) { input.value = value || ''; input.dispatchEvent(new Event('change', { bubbles: true })); } });
    };
    const applyMode = card => {
        const mode = card.querySelector('[data-session-mode]').value, enabled = mode !== 'none', split = mode === 'split';
        card.querySelector('[data-primary]').classList.toggle('hidden', !enabled); card.querySelector('[data-secondary]').classList.toggle('hidden', !split);
        card.querySelector('[data-in-label]').textContent = split ? 'Morning time in' : 'Time in'; card.querySelector('[data-out-label]').textContent = split ? 'Morning time out' : 'Time out';
        card.querySelectorAll('[data-primary] input').forEach(input => { input.required = enabled; input.disabled = !enabled; });
        card.querySelectorAll('[data-secondary] input').forEach(input => { input.required = split; input.disabled = !split; if (!split) input.value = ''; }); sync();
    };
    const addDay = (day = {}) => {
        const index = cards().length, card = document.createElement('article'); card.dataset.dayCard = ''; card.className = 'overflow-hidden rounded-2xl border border-slate-200 bg-slate-50/45';
        card.innerHTML = `<header class="grid gap-3 border-b border-slate-200 bg-white p-4 sm:grid-cols-[1fr_1.4fr_auto] sm:items-end"><label class="grid gap-1.5"><span class="text-[9px] font-black uppercase tracking-[.14em] text-[#397565]" data-day-number>Day ${index + 1}</span><input class="h-11 rounded-xl border border-slate-200 px-3 text-sm font-bold outline-none focus:border-emerald-500" type="date" name="attendance_days[${index}][date]" value="${day.date || ''}" required data-day-date></label><label class="grid gap-1.5"><span class="text-[9px] font-black uppercase tracking-[.14em] text-slate-400">Attendance pattern</span><select class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold" name="attendance_days[${index}][mode]" data-session-mode><option value="single">Whole day · one in and out</option><option value="split">Morning + afternoon · two in and out</option><option value="none">No attendance scanning</option></select></label><button class="min-h-10 rounded-xl px-3 text-xs font-bold text-red-500 hover:bg-red-50" type="button" data-remove-day>Remove</button></header><div class="grid gap-3 p-4 sm:grid-cols-2" data-primary><label class="grid gap-1.5"><span class="text-[10px] font-bold text-slate-500" data-in-label>Time in</span><input class="h-11 rounded-xl border border-slate-200 bg-white px-3" type="time" name="attendance_days[${index}][morning_in]" value="${day.morning_in || ''}" data-checkpoint="morning_in"></label><label class="grid gap-1.5"><span class="text-[10px] font-bold text-slate-500" data-out-label>Time out</span><input class="h-11 rounded-xl border border-slate-200 bg-white px-3" type="time" name="attendance_days[${index}][morning_out]" value="${day.morning_out || ''}" data-checkpoint="morning_out"></label></div><div class="grid gap-3 border-t border-slate-200 p-4 sm:grid-cols-2" data-secondary><label class="grid gap-1.5"><span class="text-[10px] font-bold text-slate-500">Afternoon time in</span><input class="h-11 rounded-xl border border-slate-200 bg-white px-3" type="time" name="attendance_days[${index}][afternoon_in]" value="${day.afternoon_in || ''}" data-checkpoint="afternoon_in"></label><label class="grid gap-1.5"><span class="text-[10px] font-bold text-slate-500">Afternoon time out</span><input class="h-11 rounded-xl border border-slate-200 bg-white px-3" type="time" name="attendance_days[${index}][afternoon_out]" value="${day.afternoon_out || ''}" data-checkpoint="afternoon_out"></label></div>`;
        container.appendChild(card); card.querySelector('[data-session-mode]').value = day.mode || 'single'; applyMode(card);
    };
    const nextDate = () => { const dates = values().map(day => day.date).filter(Boolean).sort(); if (!dates.length) return ''; const date = new Date(`${dates.at(-1)}T12:00:00`); date.setDate(date.getDate() + 1); return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`; };
    section.addEventListener('input', sync); section.addEventListener('change', event => event.target.matches('[data-session-mode]') ? applyMode(event.target.closest('[data-day-card]')) : sync());
    section.addEventListener('click', event => { const remove = event.target.closest('[data-remove-day]'); if (remove) { remove.closest('[data-day-card]').remove(); sync(); } });
    addButton.addEventListener('click', () => addDay({ date: nextDate(), mode: 'single' }));
    copyButton.addEventListener('click', () => { const source = values()[0]; cards().slice(1).forEach(card => { card.querySelector('[data-session-mode]').value = source.mode; keys.forEach(key => card.querySelector(`[data-checkpoint="${key}"]`).value = source[key]); applyMode(card); }); });
    (initial.length ? initial : [{ date: '', mode: 'single' }]).forEach(addDay); sync();
})();
</script>
@endpush
