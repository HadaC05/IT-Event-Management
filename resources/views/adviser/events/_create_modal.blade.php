@php
    $field = 'h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10';
    $invalid = 'border-red-400 bg-red-50/40';
    $selectedUsers = collect(old('assigned_user_ids', [auth()->id()]))->map(fn ($id) => (int) $id);
@endphp

<dialog class="m-auto max-h-[calc(100vh_-_2rem)] w-[min(820px,calc(100%_-_2rem))] overflow-y-auto rounded-2xl border-0 bg-white p-0 shadow-2xl backdrop:bg-[#121017]/60 backdrop:backdrop-blur-[2px]" id="create-event-dialog">
    <form method="POST" action="{{ route('adviser.events.store') }}" enctype="multipart/form-data" class="p-5 sm:p-7" data-create-event-form>
        @csrf
        <input type="hidden" name="_form" value="create-event">
        <header class="mb-6 flex items-start justify-between gap-5">
            <div><h2 class="text-2xl font-extrabold tracking-tight text-[#121017]">Create Event</h2></div>
            <button class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-xl text-slate-500 hover:bg-slate-200" type="button" data-dialog-close aria-label="Close">&times;</button>
        </header>

        <div class="grid gap-6">
            <section class="rounded-2xl border border-slate-200 bg-slate-50/70 p-5" data-event-step="information">
                <header class="mb-4"><p class="text-[10px] font-extrabold uppercase tracking-[.14em] text-sky-600">01 · Event information</p></header>
                <div class="grid gap-5 sm:grid-cols-2">
            <label class="grid gap-2"><span class="text-xs font-bold text-slate-700">Event type <i class="font-normal text-rose-500">*</i></span><select class="{{ $field }} {{ $errors->has('event_type_id') ? $invalid : '' }}" name="event_type_id" required data-event-type data-event-required><option value="">Select event type</option>@foreach($eventTypes as $type)<option value="{{ $type->id }}" data-event-type-label="{{ $type->label }}" @selected((string) old('event_type_id') === (string) $type->id)>{{ $type->label }}</option>@endforeach</select><x-form-error name="event_type_id" /></label>
            <label class="grid gap-2"><span class="text-xs font-bold text-slate-700">Event name <i class="font-normal text-rose-500">*</i></span><input class="{{ $field }} {{ $errors->has('title') ? $invalid : '' }}" name="title" value="{{ old('title') }}" placeholder="e.g. IT Days 2026" required autofocus data-event-required><x-form-error name="title" /></label>

                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-slate-50/70 p-5" data-event-step="schedule">
                <header class="mb-4"><p class="text-[10px] font-extrabold uppercase tracking-[.14em] text-sky-600">02 · Date and time</p></header>
                @include('adviser.events._attendance_schedule', ['editing' => false])
            </section>

            <section class="rounded-2xl border border-slate-200 bg-slate-50/70 p-5" data-event-step="people">
                <header class="mb-4"><p class="text-[10px] font-extrabold uppercase tracking-[.14em] text-sky-600">03 · Participants and in-charge</p></header>
            <section>
                <div class="mb-3"><h3 class="text-sm font-extrabold text-[#121017]">Participants</h3></div>
                @include('adviser.events._audience')
            </section>

            <section class="mt-5 border-t border-slate-100 pt-5">
                <div class="mb-2 flex items-center justify-between gap-4"><div><h3 class="text-sm font-extrabold text-[#121017]">Event-in-Charge</h3><p class="mt-1 text-xs text-slate-500">Choose one or more active SBO Advisers and Faculty members.</p></div></div>
                @if($assignableUsers->isEmpty())
                    <div class="flex flex-col gap-3 rounded-xl bg-amber-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"><p class="text-xs text-amber-800"><strong>No eligible users available.</strong> You can assign someone later.</p><a class="shrink-0 text-xs font-extrabold text-amber-900" href="{{ route('adviser.users.index') }}">Manage eligible users →</a></div>
                @else
                    <div class="grid gap-2 sm:grid-cols-2">@foreach($assignableUsers as $user)<label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border border-slate-200 bg-slate-100 p-3 text-xs transition hover:border-emerald-300 has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-200"><input class="h-4 w-4 accent-emerald-600" name="assigned_user_ids[]" type="checkbox" value="{{ $user->id }}" data-event-in-charge @checked($selectedUsers->contains($user->id))><span><strong class="block text-slate-700">{{ $user->id === auth()->id() ? 'You' : $user->full_name }}</strong><small class="text-slate-500">{{ $user->role->name }}</small></span></label>@endforeach</div><x-form-error name="assigned_user_ids" />
                @endif
            </section>

            </section>

            <section class="rounded-2xl border border-slate-200 bg-slate-50/70 p-5" data-event-step="additional">
                <header class="mb-4"><p class="text-[10px] font-extrabold uppercase tracking-[.14em] text-slate-400">04 · Additional details</p></header>
            <label class="mb-5 grid gap-2"><span class="text-xs font-bold text-slate-700">Location <i class="font-normal text-rose-500">*</i></span><select class="{{ $field }} {{ $errors->has('location') ? $invalid : '' }}" name="location" required data-location data-event-required><option value="">Select location</option>@foreach($generalLocations as $location)<option value="{{ $location->name }}" @selected(old('location') === $location->name)>{{ $location->name }}</option>@endforeach</select><x-form-error name="location" /></label>
            <div class="hidden rounded-xl border border-amber-200 bg-amber-50 px-4 py-3" data-conflict-warning>
                <div class="flex items-start gap-3"><span class="mt-0.5 text-amber-700">⚠</span><div><strong class="text-xs text-amber-900">Possible scheduling conflict</strong><div class="mt-1 space-y-1 text-xs leading-5 text-amber-800" data-conflict-list></div><label class="mt-2 inline-flex cursor-pointer items-center gap-2 font-bold text-amber-900"><input class="h-4 w-4 accent-amber-700" type="checkbox" name="acknowledge_conflicts" value="1" @checked(old('acknowledge_conflicts')) data-conflict-acknowledgement>I reviewed this and want to continue</label></div></div>
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
                <details class="group rounded-xl border border-slate-200 bg-slate-50/60" @if(filled(old('description')) || $errors->has('description')) open @endif>
                    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between px-4 text-xs font-extrabold text-slate-600"><span>+ Add description <small class="ml-1 font-medium text-slate-400">Optional</small></span><span class="transition group-open:rotate-45">+</span></summary>
                    <div class="border-t border-slate-200 p-4"><textarea class="min-h-24 w-full resize-y rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm leading-5 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 {{ $errors->has('description') ? $invalid : '' }}" name="description" placeholder="Purpose, activities, or helpful information…">{{ old('description') }}</textarea><x-form-error class="mt-2" name="description" /></div>
                </details>
                <details class="group rounded-xl border border-slate-200 bg-slate-50/60" @if($errors->has('poster')) open @endif>
                    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between px-4 text-xs font-extrabold text-slate-600"><span>+ Add poster <small class="ml-1 font-medium text-slate-400">Optional</small></span><span class="transition group-open:rotate-45">+</span></summary>
                    <div class="border-t border-slate-200 p-4"><label class="flex cursor-pointer items-center gap-3 rounded-xl border border-dashed border-emerald-300 bg-emerald-50 px-4 py-4 text-emerald-800"><input class="sr-only" name="poster" type="file" accept="image/jpeg,image/png,image/webp" data-quick-poster-input><svg class="h-5 w-5 shrink-0 fill-none stroke-current" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 16.5V20h16v-3.5M12 3v12m-4-4 4 4 4-4"/></svg><span class="min-w-0"><strong class="block truncate text-xs" data-quick-poster-name>Choose a poster</strong><small class="text-[10px] opacity-75">JPG, PNG, or WebP · 5 MB max</small></span></label><img class="mt-3 hidden max-h-40 w-full rounded-xl object-cover" alt="Selected event poster preview" data-quick-poster-preview><x-form-error class="mt-2" name="poster" /></div>
                </details>
            </div>
            </section>
        </div>

        <footer class="mt-7 flex justify-end gap-2 border-t border-slate-100 pt-5"><button class="inline-flex min-h-10 items-center rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-600 hover:bg-slate-50" type="button" data-dialog-close>Cancel</button><button class="inline-flex min-h-10 items-center rounded-xl bg-emerald-600 px-5 text-sm font-bold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-300" type="submit" data-loading-text="Creating…" data-create-event-submit disabled>Create Event</button></footer>
    </form>
</dialog>

@push('scripts')
<script>
    const createEventDialog = document.getElementById('create-event-dialog');
    const createEventForm = createEventDialog?.querySelector('[data-create-event-form]');
    const requiredEventFields = [...(createEventForm?.querySelectorAll('[data-event-required]') || [])];
    const createEventSubmit = createEventForm?.querySelector('[data-create-event-submit]');
    const startDate = createEventForm?.querySelector('[name="start_date"]');
    const startTime = createEventForm?.querySelector('[name="start_time"]');
    const endDate = createEventForm?.querySelector('[name="end_date"]');
    const endTime = createEventForm?.querySelector('[name="end_time"]');
    const createAudience = createEventForm?.querySelector('[data-audience-selector]');
    const validateSchedule = () => {
        if (!startDate?.value || !startTime?.value || !endDate?.value || !endTime?.value) return true;
        const valid = new Date(`${endDate.value}T${endTime.value}`) > new Date(`${startDate.value}T${startTime.value}`);
        return valid;
    };
    const updateReadiness = () => {
        if (!createEventSubmit) return;
        const audienceType = createAudience?.querySelector('[data-audience-type]')?.value;
        const audienceReady = audienceType === 'all_students' || Boolean(createAudience?.querySelector(`[data-audience-panel="${audienceType}"] [data-audience-choice]:checked`));
        createEventSubmit.disabled = !requiredEventFields.every((field) => field.value.trim()) || !validateSchedule() || !audienceReady;
    };
    [startDate, startTime, endDate, endTime].forEach((field) => field?.addEventListener('change', updateReadiness));
    requiredEventFields.forEach((field) => field.addEventListener('input', updateReadiness));
    createAudience?.addEventListener('change', updateReadiness);

    const posterInput = createEventForm?.querySelector('[data-quick-poster-input]');
    posterInput?.addEventListener('change', () => {
        const file = posterInput.files?.[0];
        if (!file) return;
        createEventForm.querySelector('[data-quick-poster-name]').textContent = file.name;
    const preview = createEventForm.querySelector('[data-quick-poster-preview]');
        preview.src = URL.createObjectURL(file);
        preview.classList.remove('hidden');
    });

    const conflictWarning = createEventForm?.querySelector('[data-conflict-warning]');
    const conflictList = createEventForm?.querySelector('[data-conflict-list]');
    const conflictAcknowledgement = createEventForm?.querySelector('[data-conflict-acknowledgement]');
    let conflictTimer;
    const checkConflicts = () => {
        window.clearTimeout(conflictTimer);
        if (!startDate.value || !startTime.value || !endDate.value || !endTime.value || !validateSchedule()) return;
        conflictTimer = window.setTimeout(async () => {
            const payload = new FormData();
            ['start_date', 'start_time', 'end_date', 'end_time', 'location'].forEach((name) => payload.append(name, createEventForm.elements[name]?.value || ''));
            createEventForm.querySelectorAll('[data-event-in-charge]:checked').forEach((person) => payload.append('assigned_user_ids[]', person.value));
            try {
                const response = await fetch(@json(route('adviser.events.conflicts')), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }, body: payload });
                if (!response.ok) return;
                const result = await response.json();
                conflictWarning.classList.toggle('hidden', !result.has_conflicts);
                if (!result.has_conflicts) { conflictAcknowledgement.checked = false; conflictList.innerHTML = ''; return; }
                const messages = [
                    ...result.conflicts.location.map((item) => `${item.location} is already used by ${item.title} · ${item.schedule}`),
                    ...result.conflicts.people.map((item) => `${item.people.join(', ')} is assigned to ${item.title} · ${item.schedule}`),
                ];
                conflictList.innerHTML = messages.map((message) => `<p>${message.replace(/[&<>"']/g, (character) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character]))}</p>`).join('');
            } catch (error) { /* The server validates conflicts again on submission. */ }
        }, 450);
    };
    [startDate, startTime, endDate, endTime, createEventForm?.querySelector('[data-location]'), ...(createEventForm ? createEventForm.querySelectorAll('[data-event-in-charge]') : [])].forEach((field) => field?.addEventListener('change', checkConflicts));

    createEventForm?.querySelectorAll('[data-event-type]').forEach((typeInput) => typeInput.addEventListener('change', () => {
        const label = typeInput.selectedOptions[0]?.dataset.eventTypeLabel;
        const title = createEventForm.querySelector('[name="title"]');
        if (label && title) { title.value = `${label} ${new Date().getFullYear()}`; title.dispatchEvent(new Event('input')); }
    }));

    updateReadiness();
    checkConflicts();
</script>
@endpush
