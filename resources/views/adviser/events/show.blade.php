@extends('layouts.adviser')

@section('title', $event->title)

@section('content')
    <div class="mb-5"><a class="text-xs font-bold text-slate-500 transition hover:text-emerald-700" href="{{ route('adviser.events.index') }}">← Back to events</a></div>

    <section class="mb-5 grid min-h-80 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:grid-cols-[minmax(280px,.7fr)_minmax(0,1.3fr)]">
        <div class="grid min-h-64 place-items-center bg-emerald-50 bg-cover bg-center text-emerald-700 lg:min-h-80" @if($event->poster_path) style="background-image:linear-gradient(rgba(20,30,70,.15),rgba(20,30,70,.42)),url('{{ asset('storage/'.$event->poster_path) }}')" @endif>
            @unless($event->poster_path)<svg class="h-12 w-12 fill-none stroke-current" viewBox="0 0 24 24"><path d="M6 2v4m12-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z"/></svg>@endunless
        </div>
        <div class="flex flex-col items-start justify-center p-6 sm:p-10 lg:p-12">
            <div class="flex flex-wrap gap-2">
                <span class="rounded-full px-2.5 py-1 text-[10px] font-extrabold {{ match($event->schedule_state) { 'ongoing' => 'bg-[#C6F24E]/35 text-[#397565]', 'completed' => 'bg-[#121017]/6 text-[#121017]/55', default => 'bg-[#2F3AE0]/8 text-[#2F3AE0]' } }}">{{ ucfirst($event->schedule_state) }}</span>
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-bold {{ $event->status?->label === 'inactive' ? 'bg-[#FF6B2C]/10 text-[#FF6B2C]' : 'bg-[#C6F24E]/35 text-[#397565]' }}"><i class="h-1.5 w-1.5 rounded-full {{ $event->status?->label === 'inactive' ? 'bg-[#FF6B2C]' : 'bg-[#C6F24E]' }}"></i>{{ $event->status?->label === 'inactive' ? 'Deactivated' : 'Active' }}</span>
            </div>
            <h1 class="mt-4 text-3xl font-black leading-tight tracking-tight text-[#121017] sm:text-4xl xl:text-5xl">{{ $event->title }}</h1>
            <p class="mt-3 max-w-3xl whitespace-pre-line text-sm leading-6 text-slate-500">{{ $event->description ?: 'No event description has been added.' }}</p>
            <div class="mt-6 flex flex-wrap gap-2">
                <a class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-bold text-slate-600 hover:border-emerald-200 hover:text-emerald-700" href="{{ route('adviser.scores.show', $event) }}">Manage Scores</a>
                <a class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#2F3AE0] px-5 text-sm font-bold text-white shadow-lg shadow-[#2F3AE0]/15 transition hover:bg-[#2F3AE0]/90" href="{{ route('adviser.attendance.show', $event) }}">View Attendance</a>
                <a class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-600 px-5 text-sm font-bold text-white shadow-lg shadow-emerald-600/15 hover:bg-emerald-700" href="{{ route('adviser.events.edit', $event) }}">Edit Event</a>
                <form method="POST" action="{{ route('adviser.events.destroy', $event) }}" data-confirm-title="Archive event?" data-confirm-message="{{ $event->title }} will be hidden from active events, but its details and assignments will be preserved." data-confirm-action="Archive">@csrf @method('DELETE')<button class="inline-flex min-h-11 items-center justify-center rounded-xl border border-red-200 bg-white px-5 text-sm font-bold text-red-600 hover:bg-red-50" type="submit" data-loading-text="Archiving…">Archive</button></form>
            </div>
        </div>
    </section>

    @if($event->status?->label === 'inactive')
        <x-notification-banner type="warning" title="This event is deactivated">Activate the event before assigning additional people or using future attendance features.</x-notification-banner>
    @endif

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[.8fr_1.2fr]">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <header class="border-b border-slate-100 px-6 py-5"><h2 class="text-lg font-extrabold tracking-tight text-[#121017]">Event details</h2><p class="mt-1 text-xs text-slate-500">Schedule and venue information</p></header>
            <dl class="px-6">
                <div class="grid grid-cols-[90px_1fr] gap-3 border-b border-slate-100 py-5"><dt class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Starts</dt><dd class="grid gap-0.5 text-xs font-bold text-slate-700">{{ $event->start_at->format('l, F j, Y') }}<span class="font-medium text-slate-400">{{ $event->start_at->format('g:i A') }}</span></dd></div>
                <div class="grid grid-cols-[90px_1fr] gap-3 border-b border-slate-100 py-5"><dt class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Ends</dt><dd class="grid gap-0.5 text-xs font-bold text-slate-700">{{ $event->end_at->format('l, F j, Y') }}<span class="font-medium text-slate-400">{{ $event->end_at->format('g:i A') }}</span></dd></div>
                <div class="grid grid-cols-[90px_1fr] gap-3 border-b border-slate-100 py-5"><dt class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Location</dt><dd class="text-xs font-bold text-slate-700">{{ $event->location ?: 'Not specified' }}</dd></div>
                <div class="grid grid-cols-[90px_1fr] gap-3 border-b border-slate-100 py-5"><dt class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Participants</dt><dd class="grid gap-0.5 text-xs font-bold text-slate-700">{{ number_format($expectedParticipants->count()) }} expected<span class="font-medium text-slate-400">{{ match($event->audience_type) { 'selected_tribes' => 'Selected tribes', 'selected_year_levels' => 'Selected year levels', 'specific_students' => 'Specific students', default => 'All active students' } }}</span></dd></div>
                <div class="grid grid-cols-[90px_1fr] gap-3 py-5"><dt class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Created by</dt><dd class="grid gap-0.5 text-xs font-bold text-slate-700">{{ $event->creator->full_name ?: 'System' }}<span class="font-medium text-slate-400">{{ $event->created_at->format('M j, Y') }}</span></dd></div>
            </dl>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <header class="border-b border-slate-100 px-6 py-5"><h2 class="text-lg font-extrabold tracking-tight text-[#121017]">Event-in-Charge</h2><p class="mt-1 text-xs text-slate-500">SBO and Faculty responsible for this event</p></header>
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
