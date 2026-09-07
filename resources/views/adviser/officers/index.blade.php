@extends('layouts.adviser')

@section('title', 'SBO Officer Management')

@section('content')
<header class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div><p class="mb-2 text-xs font-black uppercase tracking-[.14em] text-[#397565]">Account assignments</p><h1 class="text-3xl font-black tracking-tight sm:text-4xl">SBO Officer Management</h1><p class="mt-2 text-sm text-[#121017]/55">Create a separate officer login linked to an existing student profile.</p></div>
    <button class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#397565] px-5 text-sm font-black text-white shadow-lg" type="button" data-dialog-open="assign-officer-dialog">+ Assign Officer</button>
</header>

<section class="overflow-hidden rounded-2xl border border-[#121017]/10 bg-white shadow-sm">
    <form method="POST" action="{{ route('adviser.officers.batch-unassign') }}" data-confirm-title="Unassign selected officers?" data-confirm-message="Their officer logins will be disabled immediately. Student accounts and assignment history will remain unchanged." data-confirm-action="Unassign selected">@csrf @method('PATCH')
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#121017]/8 bg-[#F3F0E9]/35 px-5 py-3.5"><label class="inline-flex cursor-pointer items-center gap-2 text-xs font-black text-[#121017]/65"><input class="h-4 w-4 accent-[#397565]" type="checkbox" data-select-all-officers>Select all active</label><button class="min-h-10 rounded-xl border border-[#FF6B2C]/25 bg-[#FF6B2C]/9 px-4 text-xs font-black text-[#d9470a] disabled:cursor-not-allowed disabled:opacity-40" type="submit" data-batch-unassign disabled data-loading-text="Unassigning…">Unassign Selected</button></div>
        <div class="divide-y divide-[#121017]/8">
            @forelse($assignments as $assignment)
                <article class="grid gap-4 px-5 py-5 lg:grid-cols-[auto_minmax(220px,1fr)_minmax(180px,.7fr)_minmax(180px,.7fr)_auto] lg:items-center">
                    <input class="h-4 w-4 accent-[#397565] disabled:opacity-25" name="assignment_ids[]" value="{{ $assignment->id }}" type="checkbox" data-officer-checkbox @disabled($assignment->status !== 'Active') aria-label="Select {{ $assignment->studentProfile->full_name }}">
                    <div><strong class="block text-sm font-black">{{ $assignment->studentProfile->full_name }}</strong><span class="mt-1 block text-xs text-[#121017]/45">{{ $assignment->studentProfile->student_id }} · {{ $assignment->studentProfile->email }}</span></div>
                    <div><span class="text-[10px] font-black uppercase tracking-wider text-[#397565]">{{ $assignment->position }}</span><strong class="mt-1 block text-sm">{{ $assignment->term }}</strong></div>
                    <div><span class="text-xs font-bold text-[#121017]/55">{{ $assignment->team?->name ?? 'No tribe' }}</span><span class="mt-1 block text-[10px] text-[#121017]/35">Officer login: {{ $assignment->officerAccount->username }}</span></div>
                    <div class="flex items-center justify-end gap-2"><span class="rounded-full px-3 py-1.5 text-[10px] font-black uppercase {{ $assignment->status === 'Active' ? 'bg-[#C6F24E]/35 text-[#397565]' : 'bg-[#121017]/6 text-[#121017]/40' }}">{{ $assignment->status }}</span>@if($assignment->status === 'Active')<button class="min-h-9 rounded-lg border border-[#FF6B2C]/25 bg-[#FF6B2C]/9 px-3 text-xs font-black text-[#d9470a]" type="submit" formaction="{{ route('adviser.officers.unassign', $assignment) }}" name="assignment_ids[]" value="{{ $assignment->id }}">Unassign</button>@endif</div>
                </article>
            @empty
                <div class="px-6 py-14 text-center"><strong class="text-base font-black">No officer assignments yet</strong><p class="mt-1 text-sm text-[#121017]/45">Assign an existing student to create their separate officer login.</p></div>
            @endforelse
        </div>
    </form>
    @if($assignments->hasPages())<div class="border-t border-[#121017]/8 px-5 py-4">{{ $assignments->links() }}</div>@endif
</section>

<dialog class="m-auto max-h-[calc(100vh_-_2rem)] w-[min(680px,calc(100%_-_2rem))] overflow-y-auto rounded-2xl border-0 bg-white p-0 shadow-2xl backdrop:bg-[#121017]/60" id="assign-officer-dialog">
    <form method="POST" action="{{ route('adviser.officers.store') }}">@csrf<input type="hidden" name="_form" value="assign-officer">
        <header class="sticky top-0 z-10 flex items-start justify-between border-b border-[#121017]/8 border-t-4 border-t-[#397565] bg-white px-6 py-5"><div><p class="text-xs font-black uppercase tracking-wider text-[#397565]">Existing student</p><h2 class="mt-1 text-2xl font-black">Assign SBO Officer</h2><p class="mt-1 text-sm text-[#121017]/50">The student account stays unchanged.</p></div><button class="grid h-10 w-10 place-items-center rounded-xl bg-[#F3F0E9] text-xl" type="button" data-dialog-close aria-label="Close">×</button></header>
        <div class="grid gap-5 p-6">
            <fieldset class="grid gap-3"><legend class="text-sm font-bold">Select student</legend>
                <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_180px]">
                    <label class="relative"><span class="sr-only">Search students</span><svg class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-[#397565] stroke-2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input class="h-11 w-full rounded-xl border border-[#121017]/12 bg-[#F3F0E9]/45 pl-10 pr-3 text-sm outline-none focus:border-[#397565] focus:bg-white focus:ring-4 focus:ring-[#397565]/10" type="search" placeholder="Name, ID, or email…" autocomplete="off" data-officer-student-search></label>
                    <label><span class="sr-only">Filter by year level</span><select class="h-11 w-full rounded-xl border border-[#121017]/12 bg-[#F3F0E9]/45 px-3 text-sm outline-none focus:border-[#397565] focus:bg-white" data-officer-year-filter><option value="">All year levels</option>@foreach($yearLevels as $level)<option value="{{ $level->id }}">{{ $level->label }}</option>@endforeach<option value="none">No year level</option></select></label>
                </div>
                <div class="max-h-52 space-y-2 overflow-y-auto rounded-xl border border-[#121017]/10 bg-[#F3F0E9]/25 p-2" data-officer-student-list>
                    @forelse($students as $student)
                        @php($profile = $student->studentProfile)
                        <label class="group flex cursor-pointer items-center gap-3 rounded-xl border border-transparent bg-white px-3 py-2.5 transition hover:border-[#397565]/25 hover:bg-[#397565]/5 has-[:checked]:border-[#397565] has-[:checked]:bg-[#397565]/8" data-officer-student-option data-search="{{ Str::lower($profile->full_name.' '.$profile->student_id.' '.$profile->email) }}" data-year="{{ $profile->year_level_id ?: 'none' }}">
                            <input class="h-4 w-4 shrink-0 accent-[#397565]" name="student_user_id" value="{{ $student->id }}" type="radio" data-suggested-username="{{ Str::slug($profile->first_name.'.'.$profile->last_name, '.') }}.sbo" @checked((string)old('student_user_id') === (string)$student->id) required>
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#121017] text-[10px] font-black text-white">{{ strtoupper(substr($profile->first_name, 0, 1).substr($profile->last_name, 0, 1)) }}</span>
                            <span class="min-w-0 flex-1"><strong class="block truncate text-xs font-black">{{ $profile->full_name }}</strong><small class="mt-0.5 block truncate text-[10px] text-[#121017]/45">{{ $profile->student_id }} · {{ $profile->email }}</small></span>
                            <span class="shrink-0 rounded-full bg-[#2F3AE0]/8 px-2.5 py-1 text-[9px] font-black text-[#2F3AE0]">{{ $profile->yearLevel?->label ?? 'No year' }}</span>
                        </label>
                    @empty
                        <p class="px-4 py-8 text-center text-sm text-[#121017]/45">No active student profiles are available.</p>
                    @endforelse
                    <p class="hidden px-4 py-8 text-center text-sm text-[#121017]/45" data-officer-student-empty>No students match your search and year level.</p>
                </div>
                <p class="text-[10px] font-bold text-[#397565]" data-selected-student-label aria-live="polite">Select one student to continue.</p><x-form-error name="student_user_id" />
            </fieldset>
            <div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-2"><span class="text-sm font-bold">Officer position</span><input class="h-12 rounded-xl border border-[#121017]/12 px-3 text-sm outline-none focus:border-[#397565]" name="position" value="{{ old('position') }}" placeholder="e.g. Attendance Officer" required></label><label class="grid gap-2"><span class="text-sm font-bold">School year / term</span><input class="h-12 rounded-xl border border-[#121017]/12 px-3 text-sm outline-none focus:border-[#397565]" name="term" value="{{ old('term') }}" placeholder="e.g. 2026–2027 · 1st Term" required></label></div>
            <label class="grid gap-2"><span class="text-sm font-bold">Assigned tribe</span><select class="h-12 rounded-xl border border-[#121017]/12 px-3 text-sm outline-none focus:border-[#397565]" name="team_id" required><option value="">Select a tribe</option>@foreach($teams as $team)<option value="{{ $team->id }}">{{ $team->name }}</option>@endforeach</select></label>
            <label class="grid gap-2"><span class="flex items-center justify-between text-sm font-bold">Officer username <button class="text-xs font-black text-[#2F3AE0]" type="button" data-generate-officer-username>Generate</button></span><input class="h-12 rounded-xl border border-[#121017]/12 px-3 text-sm outline-none focus:border-[#397565]" name="username" value="{{ old('username') }}" required data-officer-username><x-form-error name="username" /></label>
            <div class="grid gap-4 sm:grid-cols-2"><label class="grid gap-2"><span class="text-sm font-bold">Temporary password</span><input class="h-12 rounded-xl border border-[#121017]/12 px-3 text-sm outline-none focus:border-[#397565]" name="password" type="password" required autocomplete="new-password"></label><label class="grid gap-2"><span class="text-sm font-bold">Confirm password</span><input class="h-12 rounded-xl border border-[#121017]/12 px-3 text-sm outline-none focus:border-[#397565]" name="password_confirmation" type="password" required autocomplete="new-password"></label></div>
            <p class="rounded-xl bg-[#2F3AE0]/7 px-4 py-3 text-xs leading-5 text-[#2F3AE0]">The officer must change this temporary password after their first sign-in.</p>
        </div>
        <footer class="sticky bottom-0 flex justify-end gap-2 border-t border-[#121017]/8 bg-white/95 px-6 py-4"><button class="min-h-11 rounded-xl border border-[#121017]/12 px-4 text-sm font-bold" type="button" data-dialog-close>Cancel</button><button class="min-h-11 rounded-xl bg-[#397565] px-5 text-sm font-black text-white" type="submit" data-loading-text="Creating officer…">Assign Officer</button></footer>
    </form>
</dialog>
@endsection

@push('scripts')
<script>
    const officerChecks = [...document.querySelectorAll('[data-officer-checkbox]:not(:disabled)')];
    const selectAllOfficers = document.querySelector('[data-select-all-officers]');
    const batchUnassign = document.querySelector('[data-batch-unassign]');
    const syncOfficerSelection = () => {
        const selected = officerChecks.filter(input => input.checked).length;
        if (selectAllOfficers) selectAllOfficers.checked = officerChecks.length > 0 && selected === officerChecks.length;
        if (batchUnassign) batchUnassign.disabled = selected === 0;
    };
    selectAllOfficers?.addEventListener('change', () => { officerChecks.forEach(input => input.checked = selectAllOfficers.checked); syncOfficerSelection(); });
    officerChecks.forEach(input => input.addEventListener('change', syncOfficerSelection));
    document.querySelector('[data-generate-officer-username]')?.addEventListener('click', () => {
        const option = document.querySelector('input[name="student_user_id"]:checked');
        const username = document.querySelector('[data-officer-username]');
        if (option?.dataset.suggestedUsername && username) username.value = option.dataset.suggestedUsername;
    });
    const studentSearch = document.querySelector('[data-officer-student-search]');
    const studentYear = document.querySelector('[data-officer-year-filter]');
    const studentOptions = [...document.querySelectorAll('[data-officer-student-option]')];
    const studentEmpty = document.querySelector('[data-officer-student-empty]');
    const selectedStudentLabel = document.querySelector('[data-selected-student-label]');
    const showSelectedStudent = option => {
        if (!selectedStudentLabel) return;
        selectedStudentLabel.textContent = option
            ? `Selected: ${option.querySelector('strong').textContent}`
            : 'Select one student to continue.';
    };
    const filterOfficerStudents = () => {
        const term = studentSearch?.value.trim().toLowerCase() || '';
        const year = studentYear?.value || '';
        let visible = 0;
        studentOptions.forEach(option => {
            const matches = (!term || option.dataset.search.includes(term)) && (!year || option.dataset.year === year);
            option.classList.toggle('hidden', !matches);
            const selectedRadio = option.querySelector('input[name="student_user_id"]:checked');
            if (!matches && selectedRadio) {
                selectedRadio.checked = false;
                showSelectedStudent(null);
            }
            visible += Number(matches);
        });
        studentEmpty?.classList.toggle('hidden', visible > 0);
    };
    studentSearch?.addEventListener('input', filterOfficerStudents);
    studentYear?.addEventListener('change', filterOfficerStudents);
    document.querySelector('[data-officer-student-list]')?.addEventListener('change', event => {
        if (!event.target.matches('input[name="student_user_id"]')) return;
        const option = event.target.closest('[data-officer-student-option]');
        showSelectedStudent(option);
    });
    filterOfficerStudents();
    showSelectedStudent(document.querySelector('input[name="student_user_id"]:checked')?.closest('[data-officer-student-option]'));
    @if($errors->any() && old('_form') === 'assign-officer') document.getElementById('assign-officer-dialog')?.showModal(); @endif
</script>
@endpush
