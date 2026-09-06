@php
    $field = 'h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10';
    $invalid = 'border-red-400 bg-red-50/40';
    $selectedUsers = collect(old('assigned_user_ids', []))->map(fn ($id) => (int) $id);
@endphp

<dialog class="m-auto max-h-[calc(100vh_-_2rem)] w-[min(900px,calc(100%_-_2rem))] overflow-y-auto rounded-2xl border-0 bg-white p-0 shadow-2xl backdrop:bg-[#141e46]/60 backdrop:backdrop-blur-[2px]" id="create-event-dialog">
    <form method="POST" action="{{ route('adviser.events.store') }}" enctype="multipart/form-data" class="p-5 sm:p-7">
        @csrf
        <input type="hidden" name="_form" value="create-event">
        <header class="mb-6 flex items-start justify-between gap-5">
            <div><p class="mb-2 text-[10px] font-extrabold uppercase tracking-wider text-emerald-700">New event</p><h2 class="text-2xl font-extrabold tracking-tight text-[#141e46]">Create Event</h2><p class="mt-1.5 text-xs text-slate-500">Add the event details and optionally assign an Event-in-Charge.</p></div>
            <button class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-xl text-slate-500 hover:bg-slate-200" type="button" data-dialog-close aria-label="Close">&times;</button>
        </header>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_280px]">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="grid gap-2 sm:col-span-2"><span class="text-xs font-bold text-slate-700">Event name</span><input class="{{ $field }} {{ $errors->has('title') ? $invalid : '' }}" name="title" value="{{ old('title') }}" required autofocus><x-form-error name="title" /></label>
                <label class="grid gap-2 sm:col-span-2"><span class="flex justify-between text-xs font-bold text-slate-700">Description <small class="font-medium text-slate-400">Optional</small></span><textarea class="min-h-28 w-full resize-y rounded-xl border border-slate-200 px-3 py-3 text-sm leading-5 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 {{ $errors->has('description') ? $invalid : '' }}" name="description" placeholder="Purpose, activities, or helpful information…">{{ old('description') }}</textarea><x-form-error name="description" /></label>
                <label class="grid gap-2 sm:col-span-2"><span class="text-xs font-bold text-slate-700">Location</span><input class="{{ $field }} {{ $errors->has('location') ? $invalid : '' }}" name="location" value="{{ old('location') }}" placeholder="e.g. University Gymnasium" required><x-form-error name="location" /></label>
                <label class="grid gap-2"><span class="text-xs font-bold text-slate-700">Start date</span><input class="{{ $field }} {{ $errors->has('start_date') ? $invalid : '' }}" name="start_date" type="date" value="{{ old('start_date') }}" required><x-form-error name="start_date" /></label>
                <label class="grid gap-2"><span class="text-xs font-bold text-slate-700">Start time</span><input class="{{ $field }} {{ $errors->has('start_time') ? $invalid : '' }}" name="start_time" type="time" value="{{ old('start_time') }}" required><x-form-error name="start_time" /></label>
                <label class="grid gap-2"><span class="text-xs font-bold text-slate-700">End date</span><input class="{{ $field }} {{ $errors->has('end_date') ? $invalid : '' }}" name="end_date" type="date" value="{{ old('end_date') }}" required><x-form-error name="end_date" /></label>
                <label class="grid gap-2"><span class="text-xs font-bold text-slate-700">End time</span><input class="{{ $field }} {{ $errors->has('end_time') ? $invalid : '' }}" name="end_time" type="time" value="{{ old('end_time') }}" required><x-form-error name="end_time" /></label>
            </div>

            <div class="grid content-start gap-5">
                <div>
                    <span class="mb-2 block text-xs font-bold text-slate-700">Event poster <small class="font-medium text-slate-400">Optional</small></span>
                    <label class="block cursor-pointer"><input class="sr-only" name="poster" type="file" accept="image/jpeg,image/png,image/webp" data-create-poster-input><span class="flex min-h-44 flex-col items-center justify-center gap-1.5 overflow-hidden rounded-xl border border-dashed border-emerald-300 bg-emerald-50 bg-cover bg-center p-4 text-center text-emerald-800 hover:border-emerald-500" data-create-poster-preview><svg class="h-7 w-7 fill-none stroke-current" viewBox="0 0 24 24"><path d="M4 16.5V20h16v-3.5M12 3v12m-4-4 4 4 4-4"/></svg><strong class="max-w-full truncate text-xs">Choose poster</strong><small class="text-[10px] opacity-75">JPG, PNG, or WebP · 5 MB max</small></span></label><x-form-error name="poster" class="mt-2" />
                </div>

                <div><span class="mb-2 block text-xs font-bold text-slate-700">Event-in-Charge <small class="font-medium text-slate-400">Optional</small></span><x-form-error name="assigned_user_ids" class="mb-2" />
                    <div class="max-h-44 space-y-2 overflow-y-auto rounded-xl border border-slate-200 p-2">
                        @forelse($assignableUsers as $user)
                            <label class="flex cursor-pointer items-center gap-2.5 rounded-lg p-2 hover:bg-emerald-50 has-[:checked]:bg-emerald-50"><input class="h-4 w-4 accent-emerald-600" name="assigned_user_ids[]" type="checkbox" value="{{ $user->id }}" @checked($selectedUsers->contains($user->id))><span class="grid min-w-0"><strong class="truncate text-xs text-slate-700">{{ $user->full_name }}</strong><small class="text-[10px] text-slate-400">{{ $user->role->name }}</small></span></label>
                        @empty
                            <p class="px-3 py-6 text-center text-xs text-slate-400">No active SBO or Faculty users available.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <footer class="mt-7 flex justify-end gap-2 border-t border-slate-100 pt-5"><button class="inline-flex min-h-10 items-center rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-600 hover:bg-slate-50" type="button" data-dialog-close>Cancel</button><button class="inline-flex min-h-10 items-center rounded-xl bg-emerald-600 px-5 text-sm font-bold text-white hover:bg-emerald-700" type="submit" data-loading-text="Creating…">Create Event</button></footer>
    </form>
</dialog>
