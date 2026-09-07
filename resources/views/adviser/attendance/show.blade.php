@extends('layouts.adviser')

@section('title', $event->title.' Attendance')
@section('body-class', 'bg-white')

@section('content')
    @php
        $isSameDay = $event->start_at->isSameDay($event->end_at);
        $schedule = $isSameDay
            ? $event->start_at->format('D, M j, Y · g:i A').'–'.$event->end_at->format('g:i A')
            : $event->start_at->format('D, M j · g:i A').' → '.$event->end_at->format('D, M j, Y · g:i A');
        $filterQuery = request()->only(['search', 'status']);
        $statusStyles = [
            'present' => 'border-[#397565]/25 bg-[#397565]/8 text-[#397565]',
            'late' => 'border-[#FF6B2C]/25 bg-[#FF6B2C]/8 text-[#b94312]',
            'absent' => 'border-red-300/60 bg-red-50 text-red-700',
            'excused' => 'border-[#2F3AE0]/20 bg-[#2F3AE0]/5 text-[#2F3AE0]',
            '' => 'border-[#121017]/10 bg-white text-[#121017]/55',
        ];
    @endphp

    <header class="border-b border-[#121017]/12 pb-6">
        <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
            <div class="min-w-0">
                <a class="mb-5 inline-flex items-center gap-2 text-[10px] font-black text-[#397565] transition hover:text-[#2f6658]" href="{{ route('adviser.attendance.index') }}"><span aria-hidden="true">←</span> All event rosters</a>
                <div class="flex flex-wrap items-center gap-2"><span class="inline-flex items-center gap-1.5 rounded-full bg-[#397565]/8 px-2.5 py-1 text-[9px] font-black uppercase tracking-[.12em] text-[#397565]"><span class="h-1.5 w-1.5 rounded-full bg-[#C6F24E]"></span>Attendance roster</span><span @class(['rounded-full px-2.5 py-1 text-[9px] font-black uppercase tracking-wide', 'bg-[#C6F24E]/40 text-[#397565]' => $event->schedule_state === 'ongoing', 'bg-[#2F3AE0]/8 text-[#2F3AE0]' => $event->schedule_state === 'upcoming', 'bg-[#121017]/6 text-[#121017]/45' => $event->schedule_state === 'completed'])>{{ ucfirst($event->schedule_state) }}</span></div>
                <h1 class="mt-4 truncate text-4xl font-black tracking-[-.055em] text-[#121017] sm:text-5xl">{{ $event->title }}</h1>
                <p class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs font-semibold text-[#121017]/48"><span class="inline-flex items-center gap-1.5"><svg class="h-4 w-4 fill-none stroke-[#397565] stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2v4m12-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z"/></svg>{{ $schedule }}</span><span class="inline-flex items-center gap-1.5"><svg class="h-4 w-4 fill-none stroke-[#FF6B2C] stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2"/></svg>{{ $event->location ?: 'Venue not specified' }}</span></p>
            </div>
            <label class="grid gap-1.5 xl:w-80"><span class="text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/35">Switch event</span><span class="relative"><select class="h-12 w-full appearance-none rounded-xl border border-[#121017]/10 bg-white/70 px-4 pr-10 text-xs font-black text-[#121017] outline-none transition focus:border-[#397565] focus:ring-4 focus:ring-[#397565]/10" data-event-switch>@foreach($eventOptions as $option)<option value="{{ route('adviser.attendance.show', $option) }}" @selected($option->id === $event->id)>{{ $option->title }} · {{ $option->start_at->format('M j') }}</option>@endforeach</select><svg class="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-[#397565] stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></span></label>
        </div>
    </header>

    <section class="grid gap-5 border-b border-[#121017]/12 py-6 lg:grid-cols-[1.25fr_.75fr]" aria-label="Attendance summary" data-summary data-recorded="{{ $summary['recorded'] }}" data-expected="{{ $summary['expected'] }}" data-counts='@json($counts)'>
        <div>
            <div class="flex items-end justify-between gap-4"><div><p class="text-[9px] font-black uppercase tracking-[.15em] text-[#397565]">Roster completion</p><div class="mt-1 flex items-baseline gap-2"><strong class="text-3xl font-black tracking-[-.04em] text-[#121017]" data-completion-rate>{{ $summary['completion'] !== null ? $summary['completion'].'%' : '—' }}</strong><span class="text-[10px] font-semibold text-[#121017]/40"><span data-recorded-count>{{ number_format($summary['recorded']) }}</span> of {{ number_format($summary['expected']) }} expected students recorded</span></div></div><strong class="text-xs font-black {{ $summary['unrecorded'] > 0 ? 'text-[#FF6B2C]' : 'text-[#397565]' }}" data-unrecorded-count>{{ number_format($summary['unrecorded']) }} awaiting status</strong></div>
            <div class="mt-4 h-2 overflow-hidden rounded-full bg-[#121017]/6" role="progressbar" aria-label="Roster completion" aria-valuemin="0" aria-valuemax="100" @if($summary['completion'] !== null) aria-valuenow="{{ $summary['completion'] }}" @endif><div class="h-full rounded-full bg-[#397565] transition-all" style="width: {{ $summary['completion'] ?? 0 }}%" data-completion-bar></div></div>
        </div>
        <div class="border-t border-[#121017]/8 pt-4 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0"><p class="text-[9px] font-black uppercase tracking-[.15em] text-[#121017]/35">Present or late rate</p><div class="mt-1 flex items-baseline gap-2"><strong class="text-3xl font-black tracking-[-.04em] text-[#397565]" data-attendance-rate>{{ $summary['rate'] !== null ? $summary['rate'].'%' : '—' }}</strong><span class="text-[10px] font-semibold text-[#121017]/40">among recorded statuses</span></div></div>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-4 border-t border-[#121017]/8 pt-5 sm:grid-cols-4 lg:col-span-2">
            @foreach([['present', 'Present', 'text-[#397565]', 'bg-[#C6F24E]'], ['late', 'Late', 'text-[#b94312]', 'bg-[#FF6B2C]'], ['absent', 'Absent', 'text-red-700', 'bg-red-500'], ['excused', 'Excused', 'text-[#2F3AE0]', 'bg-[#2F3AE0]']] as [$key, $label, $tone, $dot])
                <div><dt class="flex items-center gap-2 text-[9px] font-black uppercase tracking-[.12em] {{ $tone }}"><span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>{{ $label }}</dt><dd class="mt-1 text-xl font-black text-[#121017]" data-status-count="{{ $key }}">{{ number_format($counts[$key]) }}</dd></div>
            @endforeach
        </dl>
    </section>

    <section class="py-7" aria-labelledby="student-roster-heading">
        <div class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div><p class="text-[10px] font-black uppercase tracking-[.16em] text-[#397565]">Operational workspace</p><h2 id="student-roster-heading" class="mt-1 text-xl font-black tracking-[-.025em] text-[#121017]">Student Participants</h2><p class="mt-1 text-[10px] font-semibold text-[#121017]/40">{{ number_format($participantTotal) }} total in this roster · showing {{ number_format($participants->total()) }} matching {{ Str::plural('student', $participants->total()) }}</p></div>
            @if($participantTotal > 0)
                <form class="grid gap-2 sm:grid-cols-[minmax(250px,1fr)_180px_auto] xl:w-[720px]" method="GET" action="{{ route('adviser.attendance.show', $event) }}" data-roster-filter-form>
                    <label class="relative"><svg class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-[#121017]/35 stroke-2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><span class="sr-only">Search students</span><input class="h-11 w-full rounded-xl border border-[#121017]/10 bg-white/70 pl-10 pr-3 text-sm text-[#121017] outline-none transition placeholder:text-[#121017]/30 focus:border-[#397565] focus:bg-white focus:ring-4 focus:ring-[#397565]/10" type="search" name="search" value="{{ request('search') }}" placeholder="Search name or student ID…"></label>
                    <label class="relative"><span class="sr-only">Filter attendance status</span><select class="h-11 w-full appearance-none rounded-xl border border-[#121017]/10 bg-white/70 px-3 pr-9 text-xs font-bold text-[#121017]/60 outline-none transition focus:border-[#397565] focus:bg-white focus:ring-4 focus:ring-[#397565]/10" name="status"><option value="">Every status</option><option value="unrecorded" @selected(request('status') === 'unrecorded')>Not recorded</option><option value="present" @selected(request('status') === 'present')>Present</option><option value="late" @selected(request('status') === 'late')>Late</option><option value="absent" @selected(request('status') === 'absent')>Absent</option><option value="excused" @selected(request('status') === 'excused')>Excused</option></select><svg class="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-[#397565] stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></label>
                    <button class="h-11 rounded-xl bg-[#397565] px-4 text-xs font-black text-white transition hover:bg-[#2f6658] focus:outline-none focus:ring-4 focus:ring-[#397565]/20" type="submit">Apply</button>
                </form>
            @endif
        </div>

        @if($errors->has('records') || $errors->has('records.*'))<div class="mb-4 rounded-xl border border-red-300/60 bg-red-50 px-4 py-3 text-xs font-bold text-red-700" role="alert">{{ $errors->first('records') ?: $errors->first('records.*') }}</div>@endif

        @if($participantTotal === 0)
            <div class="rounded-2xl border border-[#121017]/10 bg-white/70 px-6 py-12 text-center"><strong class="block text-sm font-black text-[#121017]">No students are expected yet</strong><p class="mt-1 text-xs text-[#121017]/42">Update this event’s participant settings or add active student accounts.</p><a class="mt-5 inline-flex min-h-10 items-center rounded-xl bg-[#2F3AE0] px-4 text-xs font-black text-white" href="{{ route('adviser.events.edit', $event) }}">Edit Event Participants</a></div>
        @elseif($participants->isEmpty())
            <div class="rounded-2xl border border-[#121017]/10 bg-white/70 px-6 py-10 text-center"><strong class="block text-sm font-black text-[#121017]">No students match these filters</strong><p class="mt-1 text-xs text-[#121017]/42">Try another name, student ID, or attendance status.</p><a class="mt-5 inline-flex min-h-10 items-center rounded-xl border border-[#FF6B2C]/25 px-4 text-xs font-black text-[#FF6B2C]" href="{{ route('adviser.attendance.show', $event) }}">Reset Filters</a></div>
        @else
            <form method="POST" action="{{ route('adviser.attendance.update', $event) }}" data-attendance-form>
                @csrf
                @method('PUT')
                <div class="overflow-hidden rounded-2xl border border-[#121017]/10 bg-white/75 shadow-[0_16px_45px_rgba(18,16,23,.04)]">
                    <div class="flex flex-col gap-3 border-b border-[#121017]/8 px-5 py-3 sm:flex-row sm:items-center sm:justify-between"><p class="text-[10px] font-semibold text-[#121017]/42">Editing this page only · changes remain local until saved</p><div class="flex items-center gap-3">@if(request()->anyFilled(['search', 'status']))<a class="text-[10px] font-black text-[#FF6B2C]" href="{{ route('adviser.attendance.show', $event) }}" data-discard-link>Clear filters</a>@endif<button class="inline-flex min-h-9 items-center justify-center rounded-lg border border-[#397565]/20 bg-[#397565]/6 px-3 text-[10px] font-black text-[#397565] transition hover:bg-[#397565]/12 focus:outline-none focus:ring-4 focus:ring-[#397565]/10" type="button" data-mark-page-present>Mark this page present</button></div></div>
                    <div class="overflow-x-auto"><table class="w-full border-collapse sm:min-w-[820px]"><thead class="hidden bg-[#121017]/[.025] text-left text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/35 sm:table-header-group"><tr><th class="px-5 py-3.5 sm:px-6">Student</th><th class="px-4 py-3.5">Tribe / Year</th><th class="px-4 py-3.5">Attendance status</th><th class="px-5 py-3.5 sm:px-6">Check-in</th></tr></thead><tbody class="grid divide-y divide-[#121017]/7 sm:table-row-group">
                        @foreach($participants as $student)
                            @php($record = $records->get($student->id))
                            <tr class="grid grid-cols-2 gap-x-4 gap-y-4 px-5 py-5 transition hover:bg-[#397565]/[.035] sm:table-row sm:px-0 sm:py-0" data-roster-row>
                                <td class="col-span-2 block p-0 sm:table-cell sm:px-6 sm:py-3.5"><div class="flex items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#121017] text-[9px] font-black text-[#F3F0E9]">{{ strtoupper(substr($student->first_name, 0, 1).substr($student->last_name, 0, 1)) }}</span><span class="grid min-w-0"><strong class="truncate text-xs font-black text-[#121017]">{{ $student->full_name }}</strong><small class="mt-0.5 text-[9px] font-semibold text-[#121017]/38">{{ $student->id_number ?: 'No student ID' }}@unless($expectedParticipantIds->contains($student->id)) · Historical record @endunless</small></span></div></td>
                                <td class="block p-0 sm:table-cell sm:px-4 sm:py-3.5"><span class="mb-1.5 block text-[8px] font-black uppercase tracking-[.13em] text-[#121017]/30 sm:hidden">Tribe / Year</span><span class="block max-w-52 truncate text-[10px] font-bold text-[#121017]/65">{{ $student->teams->pluck('name')->join(', ') ?: 'No tribe' }}</span><small class="mt-0.5 block text-[9px] font-semibold text-[#121017]/35">{{ $student->yearLevel?->label ?: 'Year level not set' }}</small></td>
                                <td class="block p-0 sm:table-cell sm:px-4 sm:py-3.5"><span class="mb-1.5 block text-[8px] font-black uppercase tracking-[.13em] text-[#121017]/30 sm:hidden">Status</span><label class="relative block w-full sm:max-w-48"><span class="pointer-events-none absolute left-3 top-1/2 h-1.5 w-1.5 -translate-y-1/2 rounded-full bg-current" data-status-dot></span><select class="h-10 w-full appearance-none rounded-xl border py-0 pl-7 pr-8 text-[10px] font-black outline-none transition focus:ring-4 focus:ring-[#397565]/10 {{ $statusStyles[$record?->status ?? ''] }}" name="records[{{ $student->id }}][status]" data-attendance-select data-original-status="{{ $record?->status ?? '' }}"><option value="" @selected(! $record)>Not recorded</option><option value="present" @selected($record?->status === 'present')>Present</option><option value="late" @selected($record?->status === 'late')>Late</option><option value="absent" @selected($record?->status === 'absent')>Absent</option><option value="excused" @selected($record?->status === 'excused')>Excused</option></select><svg class="pointer-events-none absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></label></td>
                                <td class="col-span-2 block border-t border-[#121017]/6 pt-3 text-[10px] font-semibold text-[#121017]/38 sm:table-cell sm:border-0 sm:px-6 sm:py-3.5"><span class="mr-2 text-[8px] font-black uppercase tracking-[.13em] text-[#121017]/30 sm:hidden">Check-in</span><span data-check-in-value data-original-check-in="{{ $record?->checked_in_at?->format('M j, Y · g:i A') ?: '—' }}">{{ $record?->checked_in_at?->format('M j, Y · g:i A') ?: '—' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody></table></div>

                    @if($participants->hasPages())
                        <nav class="flex items-center justify-between border-t border-[#121017]/8 px-5 py-4 text-[10px] sm:px-6" aria-label="Student roster pagination"><small class="font-semibold text-[#121017]/35">Showing {{ $participants->firstItem() }}–{{ $participants->lastItem() }} of {{ $participants->total() }}</small><div class="flex gap-2">@if($participants->onFirstPage())<span class="rounded-lg border border-[#121017]/6 px-3 py-2 text-[#121017]/20">Previous</span>@else<a class="rounded-lg border border-[#121017]/10 px-3 py-2 font-black text-[#397565]" href="{{ $participants->previousPageUrl() }}" data-discard-link>Previous</a>@endif @if($participants->hasMorePages())<a class="rounded-lg border border-[#121017]/10 px-3 py-2 font-black text-[#397565]" href="{{ $participants->nextPageUrl() }}" data-discard-link>Next</a>@else<span class="rounded-lg border border-[#121017]/6 px-3 py-2 text-[#121017]/20">Next</span>@endif</div></nav>
                    @endif

                    <footer class="sticky bottom-3 z-10 flex flex-col gap-3 border-t border-[#121017]/8 bg-white/95 px-5 py-4 backdrop-blur sm:flex-row sm:items-center sm:justify-between sm:px-6"><p class="text-[10px] font-semibold text-[#121017]/40" aria-live="polite"><strong class="text-[#121017]" data-change-count>0</strong> unsaved changes on this page</p><div class="flex gap-2"><a class="inline-flex min-h-10 flex-1 items-center justify-center rounded-xl border border-[#121017]/12 px-4 text-[10px] font-black text-[#121017]/55 transition hover:border-[#121017]/25 hover:text-[#121017] sm:flex-none" href="{{ route('adviser.attendance.show', array_merge(['event' => $event], $filterQuery)) }}" data-reset-attendance>Discard Changes</a><button class="inline-flex min-h-10 flex-1 items-center justify-center rounded-xl bg-[#397565] px-5 text-[10px] font-black text-white transition hover:bg-[#2f6658] focus:outline-none focus:ring-4 focus:ring-[#397565]/20 disabled:cursor-not-allowed disabled:bg-[#121017]/15 disabled:text-[#121017]/30 sm:flex-none" type="submit" data-save-attendance disabled>Save Attendance</button></div></footer>
                </div>
            </form>
        @endif
    </section>
@endsection

@push('scripts')
<script>
    const attendanceForm = document.querySelector('[data-attendance-form]');
    const attendanceSelects = [...document.querySelectorAll('[data-attendance-select]')];
    const summary = document.querySelector('[data-summary]');
    const saveAttendance = document.querySelector('[data-save-attendance]');
    const changeCount = document.querySelector('[data-change-count]');
    let hasUnsavedChanges = false;
    let isSubmitting = false;
    const statusToneClasses = ['border-[#397565]/25', 'bg-[#397565]/8', 'text-[#397565]', 'border-[#FF6B2C]/25', 'bg-[#FF6B2C]/8', 'text-[#b94312]', 'border-red-300/60', 'bg-red-50', 'text-red-700', 'border-[#2F3AE0]/20', 'bg-[#2F3AE0]/5', 'text-[#2F3AE0]', 'border-[#121017]/10', 'bg-white', 'text-[#121017]/55'];
    const statusTones = {
        present: ['border-[#397565]/25', 'bg-[#397565]/8', 'text-[#397565]'],
        late: ['border-[#FF6B2C]/25', 'bg-[#FF6B2C]/8', 'text-[#b94312]'],
        absent: ['border-red-300/60', 'bg-red-50', 'text-red-700'],
        excused: ['border-[#2F3AE0]/20', 'bg-[#2F3AE0]/5', 'text-[#2F3AE0]'],
        unrecorded: ['border-[#121017]/10', 'bg-white', 'text-[#121017]/55'],
    };

    const setSelectTone = (select) => {
        select.classList.remove(...statusToneClasses);
        select.classList.add(...statusTones[select.value || 'unrecorded']);
    };

    const refreshAttendanceState = () => {
        const counts = {...JSON.parse(summary?.dataset.counts || '{}')};
        attendanceSelects.forEach((select) => {
            const original = select.dataset.originalStatus;
            const current = select.value;
            if (original !== current) {
                if (original) counts[original] = Math.max(0, (counts[original] || 0) - 1);
                if (current) counts[current] = (counts[current] || 0) + 1;
            }
            setSelectTone(select);
            const checkIn = select.closest('tr')?.querySelector('[data-check-in-value]');
            if (checkIn) checkIn.textContent = original === current ? checkIn.dataset.originalCheckIn : (['present', 'late'].includes(current) ? 'Set when saved' : '—');
        });

        const changed = attendanceSelects.filter((select) => select.value !== select.dataset.originalStatus).length;
        hasUnsavedChanges = changed > 0;
        if (changeCount) changeCount.textContent = changed;
        if (saveAttendance) saveAttendance.disabled = !hasUnsavedChanges;
        ['present', 'late', 'absent', 'excused'].forEach((status) => {
            const target = document.querySelector(`[data-status-count="${status}"]`);
            if (target) target.textContent = new Intl.NumberFormat().format(counts[status] || 0);
        });

        const recorded = Object.values(counts).reduce((total, value) => total + Number(value || 0), 0);
        const attended = Number(counts.present || 0) + Number(counts.late || 0);
        const expected = Number(summary?.dataset.expected || 0);
        const attendanceRate = recorded ? Math.round((attended / recorded) * 1000) / 10 : null;
        const completionRate = expected ? Math.min(100, Math.round((recorded / expected) * 1000) / 10) : null;
        const awaiting = Math.max(0, expected - recorded);
        const attendanceRateTarget = document.querySelector('[data-attendance-rate]');
        const completionRateTarget = document.querySelector('[data-completion-rate]');
        const completionBar = document.querySelector('[data-completion-bar]');
        const recordedTarget = document.querySelector('[data-recorded-count]');
        const unrecordedTarget = document.querySelector('[data-unrecorded-count]');
        if (attendanceRateTarget) attendanceRateTarget.textContent = attendanceRate === null ? '—' : `${attendanceRate}%`;
        if (completionRateTarget) completionRateTarget.textContent = completionRate === null ? '—' : `${completionRate}%`;
        if (completionBar) completionBar.style.width = `${completionRate || 0}%`;
        if (recordedTarget) recordedTarget.textContent = new Intl.NumberFormat().format(recorded);
        if (unrecordedTarget) unrecordedTarget.textContent = `${new Intl.NumberFormat().format(awaiting)} awaiting status`;
    };

    attendanceSelects.forEach((select) => select.addEventListener('change', refreshAttendanceState));
    document.querySelector('[data-mark-page-present]')?.addEventListener('click', () => {
        if (!window.confirm(`Mark all ${attendanceSelects.length} students shown on this page as present? These changes will not be recorded until you save.`)) return;
        attendanceSelects.forEach((select) => { select.value = 'present'; });
        refreshAttendanceState();
    });
    document.querySelector('[data-reset-attendance]')?.addEventListener('click', (event) => {
        if (hasUnsavedChanges && !window.confirm('Discard all unsaved attendance changes on this page?')) event.preventDefault();
    });
    document.querySelectorAll('[data-discard-link]').forEach((link) => link.addEventListener('click', (event) => {
        if (hasUnsavedChanges && !window.confirm('Leave this page and discard your unsaved attendance changes?')) event.preventDefault();
    }));
    document.querySelector('[data-roster-filter-form]')?.addEventListener('submit', (event) => {
        if (hasUnsavedChanges && !window.confirm('Apply filters and discard your unsaved attendance changes?')) event.preventDefault();
    });
    document.querySelector('[data-event-switch]')?.addEventListener('change', (event) => {
        if (hasUnsavedChanges && !window.confirm('Switch events and discard your unsaved attendance changes?')) { event.target.value = '{{ route('adviser.attendance.show', $event) }}'; return; }
        window.location.href = event.target.value;
    });
    attendanceForm?.addEventListener('submit', () => {
        isSubmitting = true;
        if (saveAttendance) { saveAttendance.disabled = true; saveAttendance.textContent = 'Saving…'; }
    });
    window.addEventListener('beforeunload', (event) => {
        if (!hasUnsavedChanges || isSubmitting) return;
        event.preventDefault();
        event.returnValue = '';
    });
    refreshAttendanceState();
</script>
@endpush
