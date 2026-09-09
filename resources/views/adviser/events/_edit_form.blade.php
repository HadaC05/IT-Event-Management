@php
    $field = 'h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10';
    $invalid = 'border-red-400 bg-red-50/40';
    $selectedUsers = collect(old('assigned_user_ids', $event->assignedUsers->pluck('id')->all()))->map(fn ($id) => (int) $id);
@endphp

<div class="grid gap-6">
    <section class="rounded-2xl border border-slate-200 bg-slate-50/70 p-5" data-event-step="information">
        <header class="mb-4"><p class="text-[10px] font-extrabold uppercase tracking-[.14em] text-sky-600">01 · Event information</p></header>
        <div class="grid gap-5 sm:grid-cols-2">
            <label class="grid gap-2"><span class="text-xs font-bold text-slate-700">Event type <i class="font-normal text-rose-500">*</i></span><select class="{{ $field }} {{ $errors->has('event_type_id') ? $invalid : '' }}" name="event_type_id" required><option value="">Select event type</option>@foreach($eventTypes as $type)<option value="{{ $type->id }}" @selected((string) old('event_type_id', $event->event_type_id) === (string) $type->id)>{{ $type->label }}</option>@endforeach</select><x-form-error name="event_type_id" /></label>
            <label class="grid gap-2"><span class="text-xs font-bold text-slate-700">Event name <i class="font-normal text-rose-500">*</i></span><input class="{{ $field }} {{ $errors->has('title') ? $invalid : '' }}" name="title" value="{{ old('title', $event->title) }}" required autofocus><x-form-error name="title" /></label>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-slate-50/70 p-5" data-event-step="schedule">
        <header class="mb-4"><p class="text-[10px] font-extrabold uppercase tracking-[.14em] text-sky-600">02 · Date and time</p></header>
        @include('adviser.events._attendance_schedule', ['editing' => true])
    </section>

    <section class="rounded-2xl border border-slate-200 bg-slate-50/70 p-5" data-event-step="people">
        <header class="mb-4"><p class="text-[10px] font-extrabold uppercase tracking-[.14em] text-sky-600">03 · Participants and in-charge</p></header>
        <section><div class="mb-3"><h2 class="text-sm font-extrabold text-[#121017]">Participants</h2></div>@include('adviser.events._audience')</section>
        <section class="mt-5 border-t border-slate-100 pt-5">
            <div class="mb-3"><h2 class="text-sm font-extrabold text-[#121017]">Event-in-Charge</h2><p class="mt-1 text-xs text-slate-500">Choose one or more active SBO Advisers and Faculty members.</p></div>
            @if($assignableUsers->isEmpty())
                <div class="rounded-xl bg-amber-50 px-4 py-3 text-xs text-amber-800"><strong>No eligible users available.</strong> You can assign someone later.</div>
            @else
                <div class="grid gap-2 sm:grid-cols-2">@foreach($assignableUsers as $user)<label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border border-slate-200 bg-slate-100 p-3 text-xs transition hover:border-emerald-300 has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-200"><input class="h-4 w-4 accent-emerald-600" name="assigned_user_ids[]" type="checkbox" value="{{ $user->id }}" data-event-in-charge @checked($selectedUsers->contains($user->id))><span><strong class="block text-slate-700">{{ $user->id === auth()->id() ? 'You' : $user->full_name }}</strong><small class="text-slate-500">{{ $user->role->name }}</small></span></label>@endforeach</div><x-form-error class="mt-2" name="assigned_user_ids" />
            @endif
        </section>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-slate-50/70 p-5" data-event-step="additional">
        <header class="mb-4"><p class="text-[10px] font-extrabold uppercase tracking-[.14em] text-slate-400">04 · Additional details</p></header>
        <label class="mb-5 grid gap-2"><span class="text-xs font-bold text-slate-700">Location <i class="font-normal text-rose-500">*</i></span><select class="{{ $field }} {{ $errors->has('location') ? $invalid : '' }}" name="location" required data-location><option value="">Select location</option>@foreach($generalLocations as $location)<option value="{{ $location->name }}" @selected(old('location', $event->location) === $location->name)>{{ $location->name }}</option>@endforeach</select><x-form-error name="location" /></label>
        <div class="hidden rounded-xl border border-amber-200 bg-amber-50 px-4 py-3" data-conflict-warning><div class="flex items-start gap-3"><span class="mt-0.5 text-amber-700">⚠</span><div><strong class="text-xs text-amber-900">Possible scheduling conflict</strong><div class="mt-1 space-y-1 text-xs leading-5 text-amber-800" data-conflict-list></div><label class="mt-2 inline-flex cursor-pointer items-center gap-2 font-bold text-amber-900"><input class="h-4 w-4 accent-amber-700" type="checkbox" name="acknowledge_conflicts" value="1" @checked(old('acknowledge_conflicts')) data-conflict-acknowledgement>I reviewed this and want to continue</label></div></div></div>
        <div class="mt-5 grid gap-2 sm:grid-cols-2">
            <details class="group rounded-xl border border-slate-200 bg-slate-50/60" @if(filled(old('description', $event->description)) || $errors->has('description')) open @endif><summary class="flex min-h-11 cursor-pointer list-none items-center justify-between px-4 text-xs font-extrabold text-slate-600"><span>+ Add description <small class="ml-1 font-medium text-slate-400">Optional</small></span><span class="transition group-open:rotate-45">+</span></summary><div class="border-t border-slate-200 p-4"><textarea class="min-h-24 w-full resize-y rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm leading-5 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 {{ $errors->has('description') ? $invalid : '' }}" name="description" placeholder="Purpose, activities, or helpful information…">{{ old('description', $event->description) }}</textarea><x-form-error class="mt-2" name="description" /></div></details>
            <details class="group rounded-xl border border-slate-200 bg-slate-50/60" @if($errors->has('poster')) open @endif><summary class="flex min-h-11 cursor-pointer list-none items-center justify-between px-4 text-xs font-extrabold text-slate-600"><span>{{ $event->poster_path ? 'Replace poster' : '+ Add poster' }} <small class="ml-1 font-medium text-slate-400">Optional</small></span><span class="transition group-open:rotate-45">+</span></summary><div class="border-t border-slate-200 p-4"><label class="flex cursor-pointer items-center gap-3 rounded-xl border border-dashed border-emerald-300 bg-emerald-50 px-4 py-4 text-emerald-800"><input class="sr-only" name="poster" type="file" accept="image/jpeg,image/png,image/webp" data-edit-poster-input><svg class="h-5 w-5 shrink-0 fill-none stroke-current" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 16.5V20h16v-3.5M12 3v12m-4-4 4 4 4-4"/></svg><span class="min-w-0"><strong class="block truncate text-xs" data-edit-poster-name>{{ $event->poster_path ? 'Choose a replacement poster' : 'Choose a poster' }}</strong><small class="text-[10px] opacity-75">JPG, PNG, or WebP · 5 MB max</small></span></label><img class="mt-3 {{ $event->poster_path ? '' : 'hidden' }} max-h-40 w-full rounded-xl object-cover" src="{{ $event->poster_path ? asset('storage/'.$event->poster_path) : '' }}" alt="Event poster preview" data-edit-poster-preview>@if($event->poster_path)<label class="mt-3 inline-flex cursor-pointer items-center gap-2 text-xs font-medium text-slate-500"><input class="h-4 w-4 accent-emerald-600" name="remove_poster" type="checkbox" value="1" @checked(old('remove_poster'))>Remove current poster</label>@endif<x-form-error class="mt-2" name="poster" /></div></details>
        </div>
        <p class="mt-4 text-xs text-slate-400">Event status updates automatically according to its start and end time. Archive it from Event actions when needed.</p>
    </section>
</div>

@push('scripts')
<script>
(() => {
    const form = document.querySelector('[data-edit-event-form]'); if (!form) return;
    const posterInput = form.querySelector('[data-edit-poster-input]');
    posterInput?.addEventListener('change', () => { const file = posterInput.files?.[0]; if (!file) return; form.querySelector('[data-edit-poster-name]').textContent = file.name; const preview = form.querySelector('[data-edit-poster-preview]'); preview.src = URL.createObjectURL(file); preview.classList.remove('hidden'); });
    const warning = form.querySelector('[data-conflict-warning]'), list = form.querySelector('[data-conflict-list]'), acknowledgement = form.querySelector('[data-conflict-acknowledgement]'); let timer;
    const checkConflicts = () => { window.clearTimeout(timer); const values = ['start_date', 'start_time', 'end_date', 'end_time'].map((name) => form.elements[name]?.value); if (values.some((value) => !value) || new Date(`${values[2]}T${values[3]}`) <= new Date(`${values[0]}T${values[1]}`)) return; timer = window.setTimeout(async () => { const payload = new FormData(); ['start_date', 'start_time', 'end_date', 'end_time', 'location'].forEach((name) => payload.append(name, form.elements[name]?.value || '')); payload.append('event_id', '{{ $event->id }}'); form.querySelectorAll('[data-event-in-charge]:checked').forEach((person) => payload.append('assigned_user_ids[]', person.value)); try { const response = await fetch(@json(route('adviser.events.conflicts')), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }, body: payload }); if (!response.ok) return; const result = await response.json(); warning.classList.toggle('hidden', !result.has_conflicts); if (!result.has_conflicts) { acknowledgement.checked = false; list.innerHTML = ''; return; } const messages = [...result.conflicts.location.map((item) => `${item.location} is already used by ${item.title} · ${item.schedule}`), ...result.conflicts.people.map((item) => `${item.people.join(', ')} is assigned to ${item.title} · ${item.schedule}`)]; list.innerHTML = messages.map((message) => `<p>${message.replace(/[&<>"']/g, (character) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character]))}</p>`).join(''); } catch (error) { /* Server-side validation still protects this update. */ } }, 450); };
    ['start_date', 'start_time', 'end_date', 'end_time', 'location'].forEach((name) => form.elements[name]?.addEventListener('change', checkConflicts)); form.querySelectorAll('[data-event-in-charge]').forEach((field) => field.addEventListener('change', checkConflicts)); checkConflicts();
})();
</script>
@endpush
