@php
    $editing = isset($event);
    $selectedUsers = collect(old('assigned_user_ids', $editing ? $event->assignedUsers->pluck('id')->all() : []))->map(fn ($id) => (int) $id);
    $field = 'h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10';
    $invalid = 'border-red-400 bg-red-50/40';
@endphp

<div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(300px,.65fr)]">
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 px-6 py-5"><h2 class="text-lg font-extrabold tracking-tight text-[#121017]">Event information</h2><p class="mt-1 text-xs text-slate-500">Basic details attendees will use to identify the event.</p></header>
        <div class="grid grid-cols-1 gap-5 p-6 sm:grid-cols-2">
            <label class="grid gap-2 sm:col-span-2">
                <span class="text-sm font-bold text-slate-700">Event name</span>
                <input class="{{ $field }} {{ $errors->has('title') ? $invalid : '' }}" name="title" value="{{ old('title', $editing ? $event->title : '') }}" aria-describedby="title-error" required autofocus>
                <x-form-error name="title" id="title-error" />
            </label>
            <label class="grid gap-2 sm:col-span-2">
                <span class="flex justify-between text-sm font-bold text-slate-700">Description <small class="font-medium text-slate-400">Optional</small></span>
                <textarea class="min-h-32 w-full resize-y rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm leading-6 text-slate-800 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 {{ $errors->has('description') ? $invalid : '' }}" name="description" rows="5" aria-describedby="description-error" placeholder="Purpose, activities, or other helpful information…">{{ old('description', $editing ? $event->description : '') }}</textarea>
                <x-form-error name="description" id="description-error" />
            </label>
            <label class="grid gap-2 sm:col-span-2">
                <span class="text-sm font-bold text-slate-700">Location</span>
                <input class="{{ $field }} {{ $errors->has('location') ? $invalid : '' }}" name="location" value="{{ old('location', $editing ? $event->location : '') }}" aria-describedby="location-error" placeholder="e.g. University Gymnasium" required>
                <x-form-error name="location" id="location-error" />
            </label>
            @if($editing)
                <label class="grid gap-2 sm:col-span-2">
                    <span class="text-sm font-bold text-slate-700">Status</span>
                    <select class="{{ $field }} {{ $errors->has('event_status_id') ? $invalid : '' }}" name="event_status_id" required>
                        <option value="">Select status</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->id }}" @selected((string) old('event_status_id', $event->event_status_id) === (string) $status->id)>{{ $status->label === 'inactive' ? 'Deactivated' : ucfirst($status->label) }}</option>
                        @endforeach
                    </select>
                    <small class="text-xs font-medium text-slate-400">New events start as active. Deactivate this event only when it should no longer be operational.</small>
                    <x-form-error name="event_status_id" />
                </label>
            @endif
        </div>
    </section>

    <div class="grid content-start gap-5 md:grid-cols-2 xl:grid-cols-1">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <header class="border-b border-slate-100 px-6 py-5"><h2 class="text-lg font-extrabold tracking-tight text-[#121017]">Schedule</h2><p class="mt-1 text-xs text-slate-500">Set the complete event duration.</p></header>
            <div class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2">
                <label class="grid gap-2"><span class="text-xs font-bold text-slate-700">Start date</span><input class="{{ $field }} {{ $errors->has('start_date') ? $invalid : '' }}" name="start_date" type="date" value="{{ old('start_date', $editing ? $event->start_at->format('Y-m-d') : '') }}" required><x-form-error name="start_date" /></label>
                <label class="grid gap-2"><span class="text-xs font-bold text-slate-700">Start time</span><input class="{{ $field }} {{ $errors->has('start_time') ? $invalid : '' }}" name="start_time" type="time" value="{{ old('start_time', $editing ? $event->start_at->format('H:i') : '') }}" required><x-form-error name="start_time" /></label>
                <label class="grid gap-2"><span class="text-xs font-bold text-slate-700">End date</span><input class="{{ $field }} {{ $errors->has('end_date') ? $invalid : '' }}" name="end_date" type="date" value="{{ old('end_date', $editing ? $event->end_at->format('Y-m-d') : '') }}" required><x-form-error name="end_date" /></label>
                <label class="grid gap-2"><span class="text-xs font-bold text-slate-700">End time</span><input class="{{ $field }} {{ $errors->has('end_time') ? $invalid : '' }}" name="end_time" type="time" value="{{ old('end_time', $editing ? $event->end_at->format('H:i') : '') }}" required><x-form-error name="end_time" /></label>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-[#397565]/15 bg-white shadow-sm">
            <header class="border-b border-[#121017]/7 px-6 py-5"><div class="flex items-start justify-between gap-3"><div><h2 class="text-lg font-extrabold tracking-tight text-[#121017]">Attendance checkpoints</h2><p class="mt-1 text-xs leading-5 text-slate-500">Set four scan times for the SBO Officers: morning and afternoon time in/out.</p></div><span class="rounded-full bg-[#C6F24E]/35 px-2.5 py-1 text-[9px] font-black uppercase text-[#397565]">Optional</span></div></header>
            <div class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2">
                @foreach([['morning_in_at', 'Morning time in'], ['morning_out_at', 'Morning time out'], ['afternoon_in_at', 'Afternoon time in'], ['afternoon_out_at', 'Afternoon time out']] as [$name, $label])
                    <label class="grid gap-2"><span class="text-xs font-bold text-slate-700">{{ $label }}</span><input class="{{ $field }} {{ $errors->has($name) ? $invalid : '' }}" name="{{ $name }}" type="datetime-local" value="{{ old($name, $editing && $event->{$name} ? $event->{$name}->format('Y-m-d\\TH:i') : '') }}"><x-form-error :name="$name" /></label>
                @endforeach
                <p class="text-[10px] leading-5 text-slate-400 sm:col-span-2">Each checkpoint opens at its scheduled time and remains active until the next checkpoint. The afternoon time-out window closes when the event ends.</p>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <header class="border-b border-slate-100 px-6 py-5"><h2 class="text-lg font-extrabold tracking-tight text-[#121017]">Event poster</h2><p class="mt-1 text-xs text-slate-500">JPG, PNG, or WebP up to 5 MB.</p></header>
            <div class="p-6">
                <label class="block cursor-pointer">
                    <input class="sr-only" name="poster" type="file" accept="image/jpeg,image/png,image/webp" data-poster-input>
                    <span class="relative flex min-h-48 flex-col items-center justify-center gap-1.5 overflow-hidden rounded-xl border border-dashed border-emerald-300 bg-emerald-50 bg-cover bg-center p-4 text-center text-emerald-800 transition hover:border-emerald-500 hover:bg-emerald-100/60 @if($editing && $event->poster_path) text-white shadow-inner @endif" data-poster-preview @if($editing && $event->poster_path) style="background-image:linear-gradient(rgba(20,30,70,.35),rgba(20,30,70,.55)),url('{{ asset('storage/'.$event->poster_path) }}')" @endif>
                        <svg class="h-7 w-7 fill-none stroke-current" aria-hidden="true" viewBox="0 0 24 24"><path d="M4 16.5V20h16v-3.5M12 3v12m-4-4 4 4 4-4"/></svg>
                        <strong class="max-w-full truncate text-xs">{{ $editing && $event->poster_path ? 'Replace poster' : 'Choose poster' }}</strong>
                        <small class="text-[10px] opacity-75">Click to browse</small>
                    </span>
                </label>
                <x-form-error name="poster" class="mt-2" />
                @if($editing && $event->poster_path)
                    <label class="mt-3 inline-flex cursor-pointer items-center gap-2 text-xs font-medium text-slate-500"><input class="h-4 w-4 accent-emerald-600" name="remove_poster" type="checkbox" value="1" @checked(old('remove_poster'))><span>Remove current poster</span></label>
                @endif
            </div>
        </section>
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
        <header class="border-b border-slate-100 px-6 py-5"><h2 class="text-lg font-extrabold tracking-tight text-[#121017]">Participants</h2><p class="mt-1 text-xs text-slate-500">Define who is expected so attendance totals stay meaningful.</p></header>
        <div class="p-6">@include('adviser.events._audience')</div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
        <header class="border-b border-slate-100 px-6 py-5"><h2 class="text-lg font-extrabold tracking-tight text-[#121017]">Event-in-Charge</h2><p class="mt-1 text-xs text-slate-500">Assign active SBO or Faculty members. SBO Officers receive attendance access through their tribe assignment.</p></header>
        <div class="p-6">
            <x-form-error name="assigned_user_ids" class="mb-3 block" />
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($assignableUsers as $user)
                    <label class="relative flex min-h-16 cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 pr-11 transition hover:border-emerald-300 hover:bg-emerald-50/50 has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-50">
                        <input class="peer sr-only" name="assigned_user_ids[]" type="checkbox" value="{{ $user->id }}" @checked($selectedUsers->contains($user->id))>
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-emerald-100 text-xs font-extrabold text-emerald-800">{{ strtoupper(substr($user->first_name, 0, 1).substr($user->last_name, 0, 1)) }}</span>
                        <span class="grid min-w-0 gap-0.5"><strong class="truncate text-xs text-slate-700">{{ $user->full_name }}</strong><small class="text-[10px] text-slate-400">{{ $user->role->name }}</small></span>
                        <i class="absolute right-3 grid h-5 w-5 place-items-center rounded-full border border-slate-300 text-[10px] not-italic text-transparent peer-checked:border-emerald-600 peer-checked:bg-emerald-600 peer-checked:text-white" aria-hidden="true">✓</i>
                    </label>
                @empty
                    <div class="col-span-full rounded-xl border border-dashed border-slate-200 px-5 py-8 text-center"><strong class="text-sm text-slate-600">No assignable users</strong><p class="mt-1 text-xs text-slate-400">Add or activate an SBO or Faculty account first.</p></div>
                @endforelse
            </div>
        </div>
    </section>
</div>

@push('scripts')
<script>
    const posterInput = document.querySelector('[data-poster-input]');
    posterInput?.addEventListener('change', () => {
        const file = posterInput.files?.[0];
        if (!file) return;
        const preview = document.querySelector('[data-poster-preview]');
        preview.style.backgroundImage = `linear-gradient(rgba(20,30,70,.35),rgba(20,30,70,.55)),url(${URL.createObjectURL(file)})`;
        preview.classList.add('text-white', 'shadow-inner');
        preview.querySelector('strong').textContent = file.name;
        preview.querySelector('small').textContent = 'Click to replace';
    });
</script>
@endpush
