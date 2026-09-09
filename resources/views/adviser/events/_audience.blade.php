@php
    $editingEvent = $event ?? null;
    $audienceType = old('audience_type', $editingEvent?->audience_type === 'selected_year_levels' ? 'selected_year_levels' : 'all_students');
    $defaultYearLevelIds = $editingEvent?->audienceYearLevels->pluck('id')->all() ?? $audienceYearLevels->pluck('id')->all();
    $selectedYearLevels = collect(old('year_level_ids', $defaultYearLevelIds))->map(fn ($id) => (int) $id);
    $initialExpected = $audienceType === 'selected_year_levels'
        ? $audienceYearLevels->whereIn('id', $selectedYearLevels)->flatMap->users->unique('id')->count()
        : $activeStudents->count();
@endphp

<div data-audience-selector>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <label class="grid flex-1 gap-2"><span class="text-xs font-bold text-slate-700">Who should attend? <i class="font-normal text-rose-500">*</i></span><select class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10" name="audience_type" data-audience-type><option value="all_students" @selected($audienceType === 'all_students')>All active students</option><option value="selected_year_levels" @selected($audienceType === 'selected_year_levels')>By year level</option></select></label>
        <div class="shrink-0 rounded-xl bg-emerald-50 px-4 py-2.5 text-right"><strong class="block text-lg font-black leading-none text-emerald-800" data-expected-count>{{ $initialExpected }}</strong><span class="mt-1 block text-[10px] font-bold uppercase tracking-wider text-emerald-700">Expected</span></div>
    </div>
    <x-form-error class="mt-2" name="audience_type" />
    <div class="mt-3 rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-500" data-audience-panel="all_students" @if($audienceType !== 'all_students') hidden @endif>
        @if($activeStudents->isEmpty()) No active students are currently available. <a class="font-extrabold text-emerald-700" href="{{ route('adviser.users.index', ['role' => 'Student']) }}">Manage students →</a>@else Every active student will be included automatically. @endif
    </div>
    <div class="mt-3" data-audience-panel="selected_year_levels" @if($audienceType !== 'selected_year_levels') hidden @endif>
        <div class="grid gap-2 sm:grid-cols-2">@foreach($audienceYearLevels as $level)<label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border border-slate-200 bg-slate-100 px-3 text-xs font-bold text-slate-600 transition hover:border-emerald-300 has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-200 has-[:checked]:text-emerald-900"><input class="h-4 w-4 shrink-0 accent-emerald-600" type="checkbox" name="year_level_ids[]" value="{{ $level->id }}" data-audience-choice data-member-ids="{{ $level->users->pluck('id')->values()->toJson() }}" @checked($selectedYearLevels->contains($level->id)) @disabled($audienceType !== 'selected_year_levels')><span class="flex-1">{{ $level->label }}</span><small class="font-semibold text-slate-400">{{ $level->users->count() }} students</small></label>@endforeach</div>
        <x-form-error class="mt-2" name="year_level_ids" />
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
            if (type.value === 'all_students') { expected.textContent = totalStudents; return; }
            const ids = new Set();
            selector.querySelectorAll('[data-audience-panel="selected_year_levels"] [data-audience-choice]:checked').forEach((choice) => JSON.parse(choice.dataset.memberIds).forEach((id) => ids.add(Number(id))));
            expected.textContent = ids.size;
        };
        type.addEventListener('change', () => {
            if (type.value === 'selected_year_levels') {
                selector.querySelectorAll('[data-audience-panel="selected_year_levels"] [data-audience-choice]').forEach((choice) => { choice.checked = true; });
            }
            update();
        });
        selector.querySelectorAll('[data-audience-choice]').forEach((choice) => choice.addEventListener('change', update));
        update();
    });
</script>
@endpush
