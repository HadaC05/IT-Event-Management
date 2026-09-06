@extends('layouts.adviser')

@section('title', $event->title.' Attendance')

@section('content')
    <div class="mb-5"><a class="text-xs font-extrabold text-slate-500 transition hover:text-emerald-700" href="{{ route('adviser.attendance.index') }}">← Back to attendance</a></div>
    <header class="mb-7 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"><div><p class="mb-2 text-xs font-extrabold uppercase tracking-[.14em] text-emerald-700">Event Roster</p><h1 class="text-3xl font-extrabold tracking-tight text-[#141e46] sm:text-4xl">{{ $event->title }}</h1><p class="mt-2 text-sm text-slate-500">{{ $event->start_at->format('l, F j, Y · g:i A') }}–{{ $event->end_at->format('g:i A') }} · {{ $event->location ?: 'No location' }}</p></div><label class="grid gap-1.5"><span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Switch event</span><select class="h-11 min-w-64 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-600 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10" data-event-switch>@foreach($eventOptions as $option)<option value="{{ route('adviser.attendance.show', $option) }}" @selected($option->id === $event->id)>{{ $option->title }} · {{ $option->start_at->format('M j') }}</option>@endforeach</select></label></header>

    <section class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="grid lg:grid-cols-[1.35fr_repeat(4,1fr)]">
            <div class="bg-[#141e46] p-5 text-white sm:p-6"><p class="text-xs font-extrabold uppercase tracking-[.14em] text-emerald-300">Attendance Rate</p><div class="mt-2 flex items-baseline gap-2"><strong class="text-4xl font-black" data-attendance-rate>{{ $summary['rate'] !== null ? $summary['rate'].'%' : '—' }}</strong><span class="text-xs font-semibold text-white/50"><span data-recorded-count>{{ $summary['recorded'] }}</span> of {{ $summary['expected'] }} recorded</span></div><div class="mt-4 h-1.5 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-[#8decb4]" style="width: {{ $summary['rate'] ?? 0 }}%" data-rate-bar></div></div></div>
            @foreach([['present', 'Present', 'text-emerald-700'], ['late', 'Late', 'text-amber-700'], ['absent', 'Absent', 'text-rose-700'], ['excused', 'Excused', 'text-blue-700']] as [$key, $label, $tone])<div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 lg:block lg:border-b-0 lg:border-r lg:p-6"><span class="text-xs font-semibold {{ $tone }}">{{ $label }}</span><strong class="text-2xl font-extrabold text-[#141e46] lg:mt-2 lg:block" data-status-count="{{ $key }}">{{ $counts[$key] }}</strong></div>@endforeach
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 px-5 py-5 sm:px-6"><div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"><div><h2 class="text-lg font-extrabold tracking-tight text-[#141e46]">Student Participants</h2><p class="mt-1 text-xs text-slate-500">{{ $participants->count() }} students in this roster · {{ $summary['unrecorded'] }} awaiting status</p></div>@if($participants->isNotEmpty())<button class="inline-flex min-h-9 items-center justify-center rounded-lg bg-emerald-50 px-3 text-xs font-extrabold text-emerald-700 hover:bg-emerald-100" type="button" data-mark-visible-present>Mark visible as present</button>@endif</div>
            @if($participants->isNotEmpty())<div class="mt-5 grid gap-2 sm:grid-cols-[minmax(240px,1fr)_180px]"><label class="relative"><svg class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-slate-400" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><span class="sr-only">Search students</span><input class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-3 text-sm outline-none focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-500/10" type="search" placeholder="Search student name or ID…" data-roster-search></label><label><span class="sr-only">Filter attendance status</span><select class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-600 outline-none focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-500/10" data-roster-status><option value="">Every status</option><option value="unrecorded">Not recorded</option><option value="present">Present</option><option value="late">Late</option><option value="absent">Absent</option><option value="excused">Excused</option></select></label></div>@endif
        </header>

        @if($errors->has('records') || $errors->has('records.*'))<div class="mx-5 mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-700 sm:mx-6">{{ $errors->first('records') ?: $errors->first('records.*') }}</div>@endif

        @if($participants->isEmpty())
            <div class="px-6 py-12 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-amber-50 text-amber-700"><svg class="h-7 w-7 fill-none stroke-current" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/></svg></span><strong class="mt-4 block text-sm text-slate-600">No students are expected yet</strong><p class="mt-1 text-xs text-slate-400">Update the event’s participant settings or add active student accounts.</p><a class="mt-5 inline-flex min-h-10 items-center rounded-xl bg-emerald-600 px-4 text-xs font-extrabold text-white" href="{{ route('adviser.events.edit', $event) }}">Edit Event Participants</a></div>
        @else
            <form method="POST" action="{{ route('adviser.attendance.update', $event) }}" data-attendance-form>@csrf @method('PUT')
                <div class="overflow-x-auto"><table class="w-full min-w-[760px] border-collapse"><thead class="bg-slate-50 text-left text-[10px] font-extrabold uppercase tracking-wider text-slate-400"><tr><th class="px-6 py-3.5">Student</th><th class="px-4 py-3.5">Tribe / Year</th><th class="px-4 py-3.5">Attendance Status</th><th class="px-6 py-3.5">Check-in</th></tr></thead><tbody class="divide-y divide-slate-100" data-roster-body>
                    @foreach($participants as $student)
                        @php($record = $records->get($student->id))
                        <tr class="transition hover:bg-slate-50/70" data-roster-row data-name="{{ Str::lower($student->full_name.' '.$student->id_number) }}" data-current-status="{{ $record?->status ?? 'unrecorded' }}">
                            <td class="px-6 py-4"><div class="flex items-center gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[#141e46] text-[10px] font-extrabold text-[#fff5e0]">{{ strtoupper(substr($student->first_name, 0, 1).substr($student->last_name, 0, 1)) }}</span><span class="grid min-w-0"><strong class="truncate text-sm text-slate-700">{{ $student->full_name }}</strong><small class="text-[10px] text-slate-400">{{ $student->id_number ?: 'No student ID' }}@unless($expectedParticipantIds->contains($student->id)) · Historical record @endunless</small></span></div></td>
                            <td class="px-4 py-4"><span class="block max-w-52 truncate text-xs font-semibold text-slate-600">{{ $student->teams->pluck('name')->join(', ') ?: 'No tribe' }}</span><small class="text-[10px] text-slate-400">{{ $student->yearLevel?->label ?: 'Year level not set' }}</small></td>
                            <td class="px-4 py-4"><select class="h-10 w-full max-w-44 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10" name="records[{{ $student->id }}][status]" data-attendance-select data-original-status="{{ $record?->status ?? '' }}"><option value="" @selected(! $record)>Not recorded</option><option value="present" @selected($record?->status === 'present')>Present</option><option value="late" @selected($record?->status === 'late')>Late</option><option value="absent" @selected($record?->status === 'absent')>Absent</option><option value="excused" @selected($record?->status === 'excused')>Excused</option></select></td>
                            <td class="px-6 py-4 text-xs text-slate-400" data-check-in>{{ $record?->checked_in_at?->format('M j · g:i A') ?: '—' }}</td>
                        </tr>
                    @endforeach
                    <tr class="hidden" data-no-roster-results><td class="px-6 py-12 text-center text-sm text-slate-500" colspan="4">No students match these filters.</td></tr>
                </tbody></table></div>
                <footer class="sticky bottom-3 z-10 flex flex-col gap-3 border-t border-slate-100 bg-white/95 px-5 py-4 backdrop-blur sm:flex-row sm:items-center sm:justify-between sm:px-6"><p class="text-xs text-slate-400"><strong class="text-slate-600" data-change-count>0</strong> unsaved changes</p><div class="flex gap-2"><a class="inline-flex min-h-10 flex-1 items-center justify-center rounded-xl border border-slate-200 px-4 text-xs font-extrabold text-slate-600 sm:flex-none" href="{{ route('adviser.attendance.show', $event) }}">Reset</a><button class="inline-flex min-h-10 flex-1 items-center justify-center rounded-xl bg-emerald-600 px-5 text-xs font-extrabold text-white disabled:cursor-not-allowed disabled:bg-slate-300 sm:flex-none" type="submit" data-loading-text="Saving…" data-save-attendance disabled>Save Attendance</button></div></footer>
            </form>
        @endif
    </section>
@endsection

@push('scripts')
<script>
    document.querySelector('[data-event-switch]')?.addEventListener('change', (event) => { window.location.href = event.target.value; });
    const attendanceForm = document.querySelector('[data-attendance-form]');
    const rosterRows = [...document.querySelectorAll('[data-roster-row]')];
    const rosterSearch = document.querySelector('[data-roster-search]');
    const rosterStatus = document.querySelector('[data-roster-status]');
    const noRosterResults = document.querySelector('[data-no-roster-results]');
    const saveAttendance = document.querySelector('[data-save-attendance]');
    const changeCount = document.querySelector('[data-change-count]');
    const refresh = () => {
        let visible = 0;
        rosterRows.forEach((row) => {
            const select = row.querySelector('[data-attendance-select]');
            row.dataset.currentStatus = select.value || 'unrecorded';
            const matches = row.dataset.name.includes((rosterSearch?.value || '').trim().toLowerCase()) && (!rosterStatus?.value || row.dataset.currentStatus === rosterStatus.value);
            row.classList.toggle('hidden', !matches);
            if (matches) visible++;
        });
        noRosterResults?.classList.toggle('hidden', visible !== 0);
        const changed = [...document.querySelectorAll('[data-attendance-select]')].filter((select) => select.value !== select.dataset.originalStatus).length;
        if (changeCount) changeCount.textContent = changed;
        if (saveAttendance) saveAttendance.disabled = changed === 0;
        const values = [...document.querySelectorAll('[data-attendance-select]')].map((select) => select.value).filter(Boolean);
        ['present', 'late', 'absent', 'excused'].forEach((status) => { const target = document.querySelector(`[data-status-count="${status}"]`); if (target) target.textContent = values.filter((value) => value === status).length; });
        const attended = values.filter((value) => ['present', 'late'].includes(value)).length;
        const rate = values.length ? Math.round((attended / values.length) * 1000) / 10 : null;
        const rateText = document.querySelector('[data-attendance-rate]');
        const rateBar = document.querySelector('[data-rate-bar]');
        const recordedCount = document.querySelector('[data-recorded-count]');
        if (rateText) rateText.textContent = rate === null ? '—' : `${rate}%`;
        if (rateBar) rateBar.style.width = `${rate || 0}%`;
        if (recordedCount) recordedCount.textContent = values.length;
    };
    document.querySelectorAll('[data-attendance-select]').forEach((select) => select.addEventListener('change', refresh));
    rosterSearch?.addEventListener('input', refresh);
    rosterStatus?.addEventListener('change', refresh);
    document.querySelector('[data-mark-visible-present]')?.addEventListener('click', () => { rosterRows.filter((row) => !row.classList.contains('hidden')).forEach((row) => { row.querySelector('[data-attendance-select]').value = 'present'; }); refresh(); });
    attendanceForm?.addEventListener('submit', () => { if (saveAttendance) saveAttendance.disabled = true; });
</script>
@endpush
