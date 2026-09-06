@php
    $editingEvent = $event ?? null;
    $audienceType = old('audience_type', $editingEvent?->audience_type ?? 'all_students');
    $selectedTribes = collect(old('tribe_ids', $editingEvent?->audienceTeams->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id);
    $selectedYearLevels = collect(old('year_level_ids', $editingEvent?->audienceYearLevels->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id);
    $selectedParticipants = collect(old('participant_ids', $editingEvent?->participants->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id);
    $initialExpected = match ($audienceType) {
        'selected_tribes' => $audienceTeams->whereIn('id', $selectedTribes)->flatMap->members->unique('id')->count(),
        'selected_year_levels' => $audienceYearLevels->whereIn('id', $selectedYearLevels)->flatMap->users->unique('id')->count(),
        'specific_students' => $selectedParticipants->count(),
        default => $activeStudents->count(),
    };
@endphp

<div data-audience-selector>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <label class="grid flex-1 gap-2"><span class="text-xs font-bold text-slate-700">Who should attend?</span><select class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10" name="audience_type" data-audience-type><option value="all_students" @selected($audienceType === 'all_students')>All active students</option><option value="selected_tribes" @selected($audienceType === 'selected_tribes')>Selected tribes</option><option value="selected_year_levels" @selected($audienceType === 'selected_year_levels')>Selected year levels</option><option value="specific_students" @selected($audienceType === 'specific_students')>Specific students</option></select></label>
        <div class="shrink-0 rounded-xl bg-emerald-50 px-4 py-2.5 text-right"><strong class="block text-lg font-black leading-none text-emerald-800" data-expected-count>{{ $initialExpected }}</strong><span class="mt-1 block text-[10px] font-bold uppercase tracking-wider text-emerald-700">Expected</span></div>
    </div>
    <x-form-error class="mt-2" name="audience_type" />

    <div class="mt-3 rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-500" data-audience-panel="all_students" @if($audienceType !== 'all_students') hidden @endif>
        @if($activeStudents->isEmpty()) No active students are currently available. <a class="font-extrabold text-emerald-700" href="{{ route('adviser.users.index', ['role' => 'Student']) }}">Manage students →</a>@else Every active student will be included automatically. @endif
    </div>

    <div class="mt-3" data-audience-panel="selected_tribes" @if($audienceType !== 'selected_tribes') hidden @endif>
        @if($audienceTeams->isEmpty())
            <div class="rounded-xl bg-amber-50 px-4 py-3 text-xs text-amber-800">No active tribes are available. <a class="font-extrabold" href="{{ route('adviser.teams.index') }}">Manage tribes →</a></div>
        @else
            <div class="grid max-h-40 gap-2 overflow-y-auto sm:grid-cols-2">@foreach($audienceTeams as $tribe)<label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-slate-200 p-3 text-xs hover:border-emerald-300 has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-50"><input class="h-4 w-4 accent-emerald-600" type="checkbox" name="tribe_ids[]" value="{{ $tribe->id }}" data-audience-choice data-member-ids="{{ $tribe->members->pluck('id')->values()->toJson() }}" @checked($selectedTribes->contains($tribe->id)) @disabled($audienceType !== 'selected_tribes')><span class="min-w-0 flex-1"><strong class="block truncate text-slate-700">{{ $tribe->name }}</strong><small class="text-[10px] text-slate-400">{{ $tribe->members->count() }} members · SY {{ $tribe->schoolYear?->label }}</small></span></label>@endforeach</div>
        @endif
        <x-form-error class="mt-2" name="tribe_ids" />
    </div>

    <div class="mt-3" data-audience-panel="selected_year_levels" @if($audienceType !== 'selected_year_levels') hidden @endif>
        <div class="flex flex-wrap gap-2">@foreach($audienceYearLevels as $level)<label class="cursor-pointer"><input class="peer sr-only" type="checkbox" name="year_level_ids[]" value="{{ $level->id }}" data-audience-choice data-member-ids="{{ $level->users->pluck('id')->values()->toJson() }}" @checked($selectedYearLevels->contains($level->id)) @disabled($audienceType !== 'selected_year_levels')><span class="inline-flex min-h-10 items-center rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-600 peer-checked:border-emerald-400 peer-checked:bg-emerald-50 peer-checked:text-emerald-700">{{ $level->label }} · {{ $level->users->count() }}</span></label>@endforeach</div>
        <x-form-error class="mt-2" name="year_level_ids" />
    </div>

    <div class="mt-3" data-audience-panel="specific_students" @if($audienceType !== 'specific_students') hidden @endif>
        @if($activeStudents->isEmpty())
            <div class="rounded-xl bg-amber-50 px-4 py-3 text-xs text-amber-800">No active students are available. <a class="font-extrabold" href="{{ route('adviser.users.index', ['role' => 'Student']) }}">Manage students →</a></div>
        @else
            <div class="grid max-h-44 gap-2 overflow-y-auto sm:grid-cols-2">@foreach($activeStudents as $student)<label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-slate-200 p-2.5 text-xs hover:border-emerald-300 has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-50"><input class="h-4 w-4 accent-emerald-600" type="checkbox" name="participant_ids[]" value="{{ $student->id }}" data-audience-choice data-student-id="{{ $student->id }}" @checked($selectedParticipants->contains($student->id)) @disabled($audienceType !== 'specific_students')><span class="truncate font-semibold text-slate-700">{{ $student->full_name }}</span></label>@endforeach</div>
        @endif
        <x-form-error class="mt-2" name="participant_ids" />
    </div>
</div>

@push('scripts')
<script>
    document.querySelectorAll('[data-audience-selector]').forEach((selector) => {
        const type = selector.querySelector('[data-audience-type]');
        const expected = selector.querySelector('[data-expected-count]');
        const totalStudents = {{ $activeStudents->count() }};
        const update = () => {
            selector.querySelectorAll('[data-audience-panel]').forEach((panel) => {
                const active = panel.dataset.audiencePanel === type.value;
                panel.hidden = !active;
                panel.querySelectorAll('input').forEach((input) => { input.disabled = !active; });
            });
            const activePanel = selector.querySelector(`[data-audience-panel="${type.value}"]`);
            let ids = new Set();
            if (type.value === 'all_students') {
                expected.textContent = totalStudents;
                return;
            }
            activePanel?.querySelectorAll('[data-audience-choice]:checked').forEach((choice) => {
                if (choice.dataset.studentId) ids.add(Number(choice.dataset.studentId));
                if (choice.dataset.memberIds) JSON.parse(choice.dataset.memberIds).forEach((id) => ids.add(Number(id)));
            });
            expected.textContent = ids.size;
        };
        type.addEventListener('change', update);
        selector.querySelectorAll('[data-audience-choice]').forEach((choice) => choice.addEventListener('change', update));
        update();
    });
</script>
@endpush
