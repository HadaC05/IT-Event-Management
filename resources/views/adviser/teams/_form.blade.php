@php
    $editingTeam = $team ?? null;
    $selectedMembers = collect(old('member_ids', $editingTeam?->members->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id);
    $selectedSchoolYear = old('school_year_id', $editingTeam?->school_year_id ?? $schoolYears->first()?->id);
    $submittedColor = old('color', $editingTeam?->color ?? '#397565');
    $selectedColor = is_string($submittedColor) && preg_match('/^#[0-9A-Fa-f]{6}$/', $submittedColor) ? strtoupper($submittedColor) : '#397565';
    $palette = [
        ['Green', '#397565'],
        ['Cobalt', '#2F3AE0'],
        ['Lime', '#C6F24E'],
        ['Tangerine', '#FF6B2C'],
        ['Ink', '#121017'],
    ];
    $paletteValues = collect($palette)->pluck(1);
    $customColor = $paletteValues->contains($selectedColor) ? '#397565' : $selectedColor;
    $isCustomColor = ! $paletteValues->contains($selectedColor);
    $identityReady = trim((string) old('name', $editingTeam?->name)) !== '' && filled($selectedSchoolYear);
    $inputClass = 'h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-500/10';
@endphp

<div class="space-y-6" data-tribe-workflow>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="flex items-start gap-3 border-b border-slate-100 px-5 py-5 sm:px-6">
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#121017] text-xs font-black text-white">1</span>
            <div><h2 class="text-lg font-extrabold tracking-tight text-[#121017]">Tribe Details</h2><p class="mt-1 text-xs text-slate-500">Describe the tribe and choose how it will appear across the system.</p></div>
        </header>

        <div class="grid gap-8 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start">
            <div class="grid gap-6">
                <label class="grid gap-2">
                    <span class="text-sm font-bold text-slate-700">What is the tribe called? <i class="font-normal text-rose-500">*</i></span>
                    <input class="{{ $inputClass }} {{ $errors->has('name') ? 'border-rose-400 bg-rose-50/40' : '' }}" name="name" value="{{ old('name', $editingTeam?->name) }}" maxlength="100" placeholder="e.g. Green Falcons" required data-tribe-name>
                    <x-form-error name="name" />
                    <small class="text-xs text-slate-400">This name appears on attendance, rankings, and event results.</small>
                </label>

                <label class="grid gap-2">
                    <span class="text-sm font-bold text-slate-700">Which school year? <i class="font-normal text-rose-500">*</i></span>
                    <select class="{{ $inputClass }} {{ $errors->has('school_year_id') ? 'border-rose-400 bg-rose-50/40' : '' }}" name="school_year_id" required data-school-year>
                        <option value="">Select school year</option>
                        @foreach($schoolYears as $schoolYear)<option value="{{ $schoolYear->id }}" @selected((string) $selectedSchoolYear === (string) $schoolYear->id)>SY {{ $schoolYear->label }}</option>@endforeach
                    </select>
                    <x-form-error name="school_year_id" />
                    @if($schoolYears->count() === 1)<small class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-[#C6F24E] ring-2 ring-[#397565]/15"></span>Current school year selected automatically</small>@endif
                </label>

                <fieldset>
                    <legend class="text-sm font-bold text-slate-700">Choose a tribe color <i class="font-normal text-rose-500">*</i></legend>
                    <p class="mt-1 text-xs text-slate-400">Used for the tribe badge, accents, and quick identification.</p>
                    <input type="hidden" name="color" value="{{ $selectedColor }}" data-color-value required>
                    <div class="mt-4 flex flex-wrap gap-3" aria-label="Tribe color choices">
                        @foreach($palette as [$colorName, $colorValue])
                            <label class="cursor-pointer text-center">
                                <input class="peer sr-only" type="radio" name="color_palette" value="{{ $colorValue }}" @checked($selectedColor === $colorValue) data-color-option>
                                <span class="grid h-11 w-11 place-items-center rounded-full border-4 border-white shadow-sm ring-1 ring-slate-200 transition peer-checked:scale-105 peer-checked:ring-2 peer-checked:ring-[#121017] peer-checked:[&>svg]:opacity-100" style="background-color: {{ $colorValue }}"><svg class="h-4 w-4 stroke-white opacity-0 transition" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg></span>
                                <span class="mt-1.5 block text-[10px] font-bold text-slate-500">{{ $colorName }}</span>
                            </label>
                        @endforeach
                        <label class="cursor-pointer text-center">
                            <input class="peer sr-only" type="radio" name="color_palette" value="custom" @checked($isCustomColor) data-custom-option>
                            <span class="relative grid h-11 w-11 place-items-center overflow-hidden rounded-full border-4 border-white shadow-sm ring-1 ring-slate-200 transition peer-checked:scale-105 peer-checked:ring-2 peer-checked:ring-[#121017]">
                                <input class="absolute inset-[-8px] h-16 w-16 cursor-pointer border-0 p-0" type="color" value="{{ $customColor }}" aria-label="Custom tribe color" data-custom-color>
                            </span>
                            <span class="mt-1.5 block text-[10px] font-bold text-slate-500">Custom</span>
                        </label>
                    </div>
                    <x-form-error class="mt-2" name="color" />
                </fieldset>
            </div>

            <aside>
                <p class="mb-3 text-[10px] font-extrabold uppercase tracking-[.14em] text-slate-400">Live Tribe Preview</p>
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-lg shadow-slate-200/50">
                    <div class="h-2 transition-colors" style="background-color: {{ $selectedColor }}" data-preview-accent></div>
                    <div class="p-5">
                        <div class="flex items-center gap-3">
                            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl text-sm font-black text-white shadow-sm transition-colors" style="background-color: {{ $selectedColor }}" data-preview-badge>{{ strtoupper(substr(old('name', $editingTeam?->name) ?: 'TR', 0, 2)) }}</span>
                            <div class="min-w-0"><strong class="block truncate text-base text-[#121017]" data-preview-name>{{ old('name', $editingTeam?->name) ?: 'Your tribe' }}</strong><span class="mt-0.5 block text-xs text-slate-400" data-preview-year>{{ $schoolYears->firstWhere('id', (int) $selectedSchoolYear)?->label ? 'School Year '.$schoolYears->firstWhere('id', (int) $selectedSchoolYear)->label : 'Choose a school year' }}</span></div>
                        </div>
                        <div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4"><span class="text-xs font-semibold text-slate-500">Student members</span><strong class="text-sm text-[#121017]"><span data-preview-members>{{ $selectedMembers->count() }}</span> <span data-preview-member-label>{{ Str::plural('member', $selectedMembers->count()) }}</span></strong></div>
                    </div>
                </div>
                <p class="mt-3 text-xs leading-5 text-slate-400">This identity will help advisers and students recognize the tribe throughout the application.</p>
            </aside>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-5 sm:px-6">
            <div class="flex items-start gap-3"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#121017] text-xs font-black text-white">2</span><div><h2 class="text-lg font-extrabold tracking-tight text-[#121017]">Choose Members</h2><p class="mt-1 text-xs text-slate-500">Select the active students who belong to this tribe.</p></div></div>
            @if($students->isNotEmpty())<span class="inline-flex h-8 shrink-0 items-center rounded-full bg-emerald-50 px-3 text-xs font-extrabold text-emerald-700"><span data-selected-count>{{ $selectedMembers->count() }}</span>&nbsp;selected</span>@endif
        </header>

        @if($errors->has('member_ids') || $errors->has('member_ids.*'))<div class="mx-5 mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-700 sm:mx-6">{{ $errors->first('member_ids') ?: $errors->first('member_ids.*') }}</div>@endif

        @if($students->isEmpty())
            <div class="flex flex-col gap-5 px-5 py-7 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div class="flex items-start gap-4"><span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-amber-50 text-amber-700"><svg class="h-5 w-5 fill-none stroke-current" viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm10-2v6m-3-3h6"/></svg></span><div><strong class="text-sm text-[#121017]">No active students are available</strong><p class="mt-1 max-w-xl text-xs leading-5 text-slate-500">Add new student accounts or activate existing ones before building this tribe’s roster.</p></div></div>
                <a class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-xl bg-[#2F3AE0] px-4 text-xs font-extrabold text-white transition hover:bg-[#2F3AE0]/90" href="{{ route('adviser.users.index', ['role' => 'Student']) }}">Go to User Management</a>
            </div>
        @else
            <div class="p-5 sm:p-6">
                <label class="relative block"><svg class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-slate-400" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><span class="sr-only">Search students</span><input class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-3 text-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-500/10" type="search" placeholder="Search by name, student ID, or year level…" data-member-search></label>

                <div class="mt-4 grid max-h-[520px] gap-2 overflow-y-auto pr-1 lg:grid-cols-2" data-member-list>
                    @foreach($students as $student)
                        @php
                            $otherTeams = $student->teams->when($editingTeam, fn ($teams) => $teams->where('id', '!=', $editingTeam->id));
                            $searchValue = Str::lower($student->full_name.' '.$student->id_number.' '.$student->display_year_level?->label);
                        @endphp
                        <label class="group flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-3.5 py-3 transition has-[:checked]:border-emerald-300 has-[:checked]:bg-emerald-50/60 hover:border-slate-300" data-member-item data-search="{{ $searchValue }}">
                            <input class="h-4 w-4 shrink-0 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" type="checkbox" name="member_ids[]" value="{{ $student->id }}" @checked($selectedMembers->contains($student->id)) data-member-checkbox>
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#121017] text-[9px] font-extrabold text-[#F3F0E9]">{{ strtoupper(substr($student->first_name, 0, 1).substr($student->last_name, 0, 1)) }}</span>
                            <span class="grid min-w-0 flex-1 gap-0.5"><strong class="truncate text-sm text-slate-700">{{ $student->full_name }}</strong><small class="truncate text-[11px] text-slate-400">{{ $student->id_number ?: 'No student ID' }}@if($student->display_year_level) · {{ $student->display_year_level->label }}@endif</small></span>
                            @if($otherTeams->isNotEmpty())<span class="hidden max-w-36 text-right text-[10px] font-semibold text-slate-400 sm:block">{{ $otherTeams->take(1)->map(fn ($team) => $team->name.' · SY '.$team->schoolYear?->label)->join() }}</span>@endif
                        </label>
                    @endforeach
                    <div class="hidden rounded-xl border border-dashed border-slate-200 px-5 py-10 text-center lg:col-span-2" data-no-member-results><strong class="text-sm text-slate-600">No students match your search</strong><p class="mt-1 text-xs text-slate-400">Try a different name, ID, or year level.</p></div>
                </div>
            </div>
        @endif
    </section>

    <div class="flex flex-col gap-4 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
        <p class="flex max-w-xl items-start gap-2 text-xs leading-5 text-slate-500"><span class="mt-0.5 grid h-4 w-4 shrink-0 place-items-center rounded-full bg-slate-200 text-[10px] font-black text-slate-600">i</span><span>A student may belong to one tribe per school year, but can join a different tribe in another year.</span></p>
        <div class="flex shrink-0 flex-col-reverse gap-2 sm:flex-row">
            @if($modal ?? false)<button class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-extrabold text-slate-600 transition hover:bg-slate-50" type="button" data-dialog-close>Cancel</button>@else<a class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-extrabold text-slate-600 transition hover:bg-slate-50" href="{{ route('adviser.teams.index') }}">Cancel</a>@endif
            <button class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-600 px-6 text-sm font-extrabold text-white shadow-lg shadow-emerald-600/15 transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none" type="submit" data-loading-text="Saving…" data-submit-tribe @disabled(! $identityReady)>{{ $editingTeam ? 'Save Changes' : 'Create Tribe' }}</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const workflow = document.querySelector('[data-tribe-workflow]');
    const tribeName = workflow?.querySelector('[data-tribe-name]');
    const schoolYear = workflow?.querySelector('[data-school-year]');
    const colorValue = workflow?.querySelector('[data-color-value]');
    const submitTribe = workflow?.querySelector('[data-submit-tribe]');
    const previewName = workflow?.querySelector('[data-preview-name]');
    const previewYear = workflow?.querySelector('[data-preview-year]');
    const previewBadge = workflow?.querySelector('[data-preview-badge]');
    const previewAccent = workflow?.querySelector('[data-preview-accent]');
    const previewMembers = workflow?.querySelector('[data-preview-members]');
    const previewMemberLabel = workflow?.querySelector('[data-preview-member-label]');

    const initials = (name) => {
        const words = name.trim().split(/\s+/).filter(Boolean);
        return (words.length > 1 ? words.slice(0, 2).map((word) => word[0]).join('') : (words[0] || 'TR').slice(0, 2)).toUpperCase();
    };
    const updateIdentity = () => {
        const name = tribeName?.value.trim() || '';
        const selectedYear = schoolYear?.selectedOptions[0];
        if (previewName) previewName.textContent = name || 'Your tribe';
        if (previewBadge) previewBadge.textContent = initials(name);
        if (previewYear) previewYear.textContent = selectedYear?.value ? `School Year ${selectedYear.textContent.replace(/^SY\s+/, '')}` : 'Choose a school year';
        if (submitTribe) submitTribe.disabled = !name || !schoolYear?.value || !colorValue?.value;
    };
    tribeName?.addEventListener('input', updateIdentity);
    schoolYear?.addEventListener('change', updateIdentity);

    const applyColor = (color) => {
        if (colorValue) colorValue.value = color.toUpperCase();
        if (previewBadge) previewBadge.style.backgroundColor = color;
        if (previewAccent) previewAccent.style.backgroundColor = color;
        updateIdentity();
    };
    workflow?.querySelectorAll('[data-color-option]').forEach((option) => option.addEventListener('change', () => applyColor(option.value)));
    const customOption = workflow?.querySelector('[data-custom-option]');
    const customColor = workflow?.querySelector('[data-custom-color]');
    customOption?.addEventListener('change', () => applyColor(customColor.value));
    customColor?.addEventListener('input', () => { customOption.checked = true; applyColor(customColor.value); });

    const memberSearch = workflow?.querySelector('[data-member-search]');
    const memberItems = [...(workflow?.querySelectorAll('[data-member-item]') || [])];
    const memberCheckboxes = [...(workflow?.querySelectorAll('[data-member-checkbox]') || [])];
    const selectedCount = workflow?.querySelector('[data-selected-count]');
    const noMemberResults = workflow?.querySelector('[data-no-member-results]');
    const updateCount = () => {
        const count = memberCheckboxes.filter((checkbox) => checkbox.checked).length;
        if (selectedCount) selectedCount.textContent = count;
        if (previewMembers) previewMembers.textContent = count;
        if (previewMemberLabel) previewMemberLabel.textContent = count === 1 ? 'member' : 'members';
    };
    memberCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', updateCount));
    memberSearch?.addEventListener('input', () => {
        const term = memberSearch.value.trim().toLowerCase();
        let visible = 0;
        memberItems.forEach((item) => {
            const matches = item.dataset.search.includes(term);
            item.classList.toggle('hidden', !matches);
            if (matches) visible++;
        });
        noMemberResults?.classList.toggle('hidden', visible !== 0);
    });
    updateIdentity();
</script>
@endpush
