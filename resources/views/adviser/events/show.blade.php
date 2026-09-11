@extends('layouts.adviser')

@section('title', $event->title)

@section('content')
    <div class="mb-5"><a class="text-xs font-bold text-slate-500 transition hover:text-emerald-700" href="{{ route('adviser.events.index') }}">← Back to events</a></div>

    <section class="relative mb-6 grid min-h-80 overflow-visible rounded-3xl border border-slate-200 bg-white shadow-sm lg:grid-cols-[minmax(280px,.7fr)_minmax(0,1.3fr)]">
        <div class="grid min-h-64 place-items-center rounded-t-3xl bg-emerald-50 bg-cover bg-center text-emerald-700 lg:min-h-80 lg:rounded-l-3xl lg:rounded-tr-none" @if($event->poster_path) style="background-image:linear-gradient(rgba(20,30,70,.15),rgba(20,30,70,.42)),url('{{ asset('storage/'.$event->poster_path) }}')" @endif>
            @unless($event->poster_path)<svg class="h-12 w-12 fill-none stroke-current" viewBox="0 0 24 24"><path d="M6 2v4m12-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z"/></svg>@endunless
        </div>
        <div class="flex flex-col items-start justify-center p-6 sm:p-10 lg:p-12">
            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-[#2F3AE0]/8 px-2.5 py-1 text-[10px] font-bold text-[#2F3AE0]"><i class="h-1.5 w-1.5 rounded-full bg-[#2F3AE0]"></i>{{ ucfirst($event->status?->label ?? 'Unspecified') }}</span>
                @if($event->type)<span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-500">{{ $event->type->label }}</span>@endif
            </div>
            <p class="mt-5 text-[10px] font-extrabold uppercase tracking-[.16em] text-emerald-700">Event overview</p>
            <h1 class="mt-2 text-3xl font-black leading-tight tracking-tight text-[#121017] sm:text-4xl xl:text-5xl">{{ $event->title }}</h1>
            <p class="mt-3 max-w-3xl whitespace-pre-line text-sm leading-6 text-slate-500">{{ $event->description ?: 'No event description has been added.' }}</p>
            <div class="mt-6 flex flex-wrap gap-2">
                <a class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-bold text-slate-600 hover:border-emerald-200 hover:text-emerald-700" href="{{ route('adviser.scores.show', $event) }}">Manage Scores</a>
                <a class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#2F3AE0] px-5 text-sm font-bold text-white shadow-lg shadow-[#2F3AE0]/15 transition hover:bg-[#2F3AE0]/90" href="{{ route('adviser.attendance.show', $event) }}">View Attendance</a>
            </div>
        </div>
        <details class="group absolute right-4 top-4 z-20 sm:right-6 sm:top-6">
            <summary class="inline-flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-600 shadow-sm transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-emerald-500/15">Event actions<svg class="h-4 w-4 fill-none stroke-current stroke-2 transition group-open:rotate-180" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>
            </summary>
            <div class="absolute right-0 top-[calc(100%+.5rem)] w-64 overflow-hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-[0_18px_45px_rgba(18,16,23,.16)]">
                <a class="flex min-h-10 items-center gap-3 rounded-xl px-3 text-xs font-bold text-slate-700 transition hover:bg-emerald-50 hover:text-emerald-700" href="{{ route('adviser.events.edit', $event) }}"><svg class="h-4 w-4 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg>Edit event details</a>
                <form class="mt-1 border-t border-slate-100 px-3 pb-2 pt-3" method="POST" action="{{ route('adviser.events.status', $event) }}">
                    @csrf @method('PATCH')
                    <label class="grid gap-1.5"><span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Update event status</span><select class="h-10 rounded-lg border border-slate-200 bg-white px-2 text-xs font-bold text-slate-700 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10" name="event_status_id" onchange="this.form.querySelector('button').disabled = this.value === '{{ $event->event_status_id }}'">@foreach($statuses as $status)<option value="{{ $status->id }}" @selected((string) $event->event_status_id === (string) $status->id)>{{ ucfirst($status->label) }}</option>@endforeach</select></label>
                    <button class="mt-2 h-9 w-full rounded-lg bg-emerald-600 text-xs font-bold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-200" type="submit" disabled data-loading-text="Updating…">Update status</button>
                </form>
            </div>
        </details>
    </section>

    @if($event->status?->label === 'inactive')
        <x-notification-banner type="warning" title="This event is deactivated">Activate the event before assigning additional people or using future attendance features.</x-notification-banner>
    @endif

    @can('feature', $event)
        <x-event-feature-controls :event="$event" :action="route('adviser.events.feature', $event)" />
    @endcan

    <section class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Starts</span><strong class="mt-2 block text-sm text-[#121017]">{{ $event->start_at->format('M j, Y') }}</strong><span class="mt-1 block text-xs text-slate-500">{{ $event->start_at->format('g:i A') }}</span></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Ends</span><strong class="mt-2 block text-sm text-[#121017]">{{ $event->end_at->format('M j, Y') }}</strong><span class="mt-1 block text-xs text-slate-500">{{ $event->end_at->format('g:i A') }}</span></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Location</span><strong class="mt-2 block truncate text-sm text-[#121017]">{{ $event->location ?: 'Not specified' }}</strong><span class="mt-1 block text-xs text-slate-500">Event venue</span></article>
        <article class="rounded-2xl border border-emerald-100 bg-emerald-50/60 p-5"><span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-700">Expected attendees</span><strong class="mt-2 block text-2xl font-black text-emerald-900">{{ number_format($expectedParticipants->count()) }}</strong><span class="mt-1 block text-xs text-emerald-800/70">{{ match($event->audience_type) { 'selected_tribes' => 'Selected tribes', 'selected_year_levels' => 'Selected year levels', 'specific_students' => 'Specific students', default => 'All active students' } }}</span></article>
    </section>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[1.1fr_.9fr]">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <header class="border-b border-slate-100 px-6 py-5"><p class="text-[10px] font-extrabold uppercase tracking-[.14em] text-sky-600">Schedule</p><h2 class="mt-1 text-lg font-extrabold tracking-tight text-[#121017]">Event details</h2><p class="mt-1 text-xs text-slate-500">Timing, attendance sessions, and event record.</p></header>
            <dl class="px-6">
                <div class="grid grid-cols-[90px_1fr] gap-3 border-b border-slate-100 py-5"><dt class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Starts</dt><dd class="grid gap-0.5 text-xs font-bold text-slate-700">{{ $event->start_at->format('l, F j, Y') }}<span class="font-medium text-slate-400">{{ $event->start_at->format('g:i A') }}</span></dd></div>
                <div class="grid grid-cols-[90px_1fr] gap-3 border-b border-slate-100 py-5"><dt class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Ends</dt><dd class="grid gap-0.5 text-xs font-bold text-slate-700">{{ $event->end_at->format('l, F j, Y') }}<span class="font-medium text-slate-400">{{ $event->end_at->format('g:i A') }}</span></dd></div>
                <div class="grid grid-cols-[90px_1fr] gap-3 border-b border-slate-100 py-5"><dt class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Daily scans</dt><dd><div class="grid gap-2">@foreach($event->attendanceSchedules as $day)<section class="rounded-lg border border-slate-100 bg-[#397565]/7 px-3 py-2.5"><strong class="text-[10px] font-black text-[#397565]">Day {{ $loop->iteration }} · {{ $day->schedule_date->format('D, M j') }}</strong>@if(count($day->slots()) > 0)<div class="mt-2 grid grid-cols-2 gap-1 text-[9px] font-semibold text-[#121017]/60">@foreach($day->slots() as $slot)<span>{{ $slot['label'] }}: <b class="text-[#121017]">{{ $slot['at']->format('g:i A') }}</b></span>@endforeach</div>@else<p class="mt-1 text-[9px] text-slate-400">Attendance scans not configured</p>@endif</section>@endforeach</div></dd></div>
                <div class="grid grid-cols-[90px_1fr] gap-3 border-b border-slate-100 py-5"><dt class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Location</dt><dd class="text-xs font-bold text-slate-700">{{ $event->location ?: 'Not specified' }}</dd></div>
                <div class="grid grid-cols-[90px_1fr] gap-3 border-b border-slate-100 py-5"><dt class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Participants</dt><dd class="grid gap-0.5 text-xs font-bold text-slate-700">{{ number_format($expectedParticipants->count()) }} expected<span class="font-medium text-slate-400">{{ match($event->audience_type) { 'selected_tribes' => 'Selected tribes', 'selected_year_levels' => 'Selected year levels', 'specific_students' => 'Specific students', default => 'All active students' } }}</span></dd></div>
                <div class="grid grid-cols-[90px_1fr] gap-3 py-5"><dt class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Created by</dt><dd class="grid gap-0.5 text-xs font-bold text-slate-700">{{ $event->creator->full_name ?: 'System' }}<span class="font-medium text-slate-400">{{ $event->created_at->format('M j, Y') }}</span></dd></div>
            </dl>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <header class="border-b border-slate-100 px-6 py-5"><p class="text-[10px] font-extrabold uppercase tracking-[.14em] text-sky-600">Team</p><h2 class="mt-1 text-lg font-extrabold tracking-tight text-[#121017]">Event-in-Charge</h2><p class="mt-1 text-xs text-slate-500">SBO and Faculty responsible for this event.</p></header>
            <div class="px-6">
                @forelse($event->assignedUsers as $user)
                    <div class="flex min-h-16 items-center gap-3 border-b border-slate-100 py-3" data-assignment-item>
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-emerald-100 text-xs font-extrabold text-emerald-800">{{ strtoupper(substr($user->first_name, 0, 1).substr($user->last_name, 0, 1)) }}</span>
                        <span class="grid flex-1 gap-0.5"><strong class="text-xs text-slate-700">{{ $user->full_name }}</strong><small class="text-[10px] text-slate-400">{{ $user->role->name }}</small></span>
                        <form method="POST" action="{{ route('adviser.users.events.destroy', [$user, $event]) }}" data-async-unassign>@csrf @method('DELETE')<button class="grid h-8 w-8 place-items-center rounded-lg bg-slate-100 text-lg text-slate-500 hover:bg-red-50 hover:text-red-600" type="submit" aria-label="Unassign {{ $user->full_name }}" title="Unassign">&times;</button></form>
                    </div>
                @empty
                    <div class="py-9 text-center"><strong class="text-sm text-slate-600">No one assigned yet</strong><p class="mt-1 text-xs text-slate-400">Assign an active SBO or Faculty member below.</p></div>
                @endforelse
            </div>
            @if($event->status?->label !== 'inactive' && $availableUsers->isNotEmpty())
                <form class="grid grid-cols-1 items-end gap-3 border-t border-slate-100 bg-slate-50/60 p-5 sm:grid-cols-[1fr_auto]" method="POST" action="{{ route('adviser.events.assignments.store', $event) }}">@csrf<label class="grid gap-2"><span class="text-xs font-bold text-slate-700">Assign another person</span><select class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 {{ $errors->has('user_id') ? 'border-red-400 bg-red-50/40' : '' }}" name="user_id" required><option value="">Select SBO or Faculty</option>@foreach($availableUsers as $user)<option value="{{ $user->id }}">{{ $user->full_name }} · {{ $user->role->name }}</option>@endforeach</select><x-form-error name="user_id" /></label><button class="inline-flex h-11 items-center justify-center rounded-xl bg-emerald-600 px-5 text-sm font-bold text-white hover:bg-emerald-700" type="submit" data-loading-text="Assigning…">Assign</button></form>
            @endif
        </section>
    </div>
@endsection
