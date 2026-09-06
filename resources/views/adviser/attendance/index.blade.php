@extends('layouts.adviser')

@section('title', 'Attendance')

@section('content')
    <header class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="mb-2 text-xs font-extrabold uppercase tracking-[.14em] text-emerald-700">Monitoring</p><h1 class="text-3xl font-extrabold tracking-tight text-[#141e46] sm:text-4xl">Attendance</h1><p class="mt-2 text-sm text-slate-500">Monitor participation and open an event roster to record attendance.</p></div>
        @if($summary['events'] > 0)<a class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-extrabold text-slate-600 transition hover:border-emerald-300 hover:text-emerald-700" href="{{ route('adviser.events.index') }}">Manage Events</a>@endif
    </header>

    <section class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-label="Attendance overview">
        <header class="border-b border-slate-100 px-5 py-3.5"><p class="text-xs font-extrabold uppercase tracking-[.14em] text-slate-500">Attendance Overview</p></header>
        <div class="grid divide-y divide-slate-100 sm:grid-cols-2 lg:grid-cols-4 lg:divide-x lg:divide-y-0">
            @foreach([['events', 'Total Events'], ['tracked', 'Events Tracked'], ['records', 'Attendance Records'], ['attended', 'Present or Late']] as [$key, $label])<div class="flex items-center justify-between gap-4 px-5 py-4 lg:block lg:p-5"><span class="text-xs font-semibold text-slate-500">{{ $label }}</span><strong class="text-2xl font-extrabold text-[#141e46] lg:mt-2 lg:block">{{ number_format($summary[$key]) }}</strong></div>@endforeach
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 px-5 py-5 sm:px-6">
            <div class="flex items-end justify-between gap-4"><div><h2 class="text-lg font-extrabold tracking-tight text-[#141e46]">Event Attendance</h2><p class="mt-1 text-xs text-slate-500">{{ number_format($events->total()) }} {{ Str::plural('event', $events->total()) }} found</p></div>@if(request()->anyFilled(['search', 'timing']))<a class="text-xs font-extrabold text-emerald-700" href="{{ route('adviser.attendance.index') }}">Clear filters</a>@endif</div>
            @if($summary['events'] > 0)<form class="mt-5 grid gap-2 sm:grid-cols-[minmax(260px,1fr)_180px_auto]" method="GET" action="{{ route('adviser.attendance.index') }}"><label class="relative"><svg class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-slate-400" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><span class="sr-only">Search events</span><input class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-3 text-sm outline-none focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-500/10" name="search" value="{{ request('search') }}" placeholder="Search event or location…"></label><label><span class="sr-only">Filter by schedule</span><select class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-600 outline-none focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-500/10" name="timing"><option value="">Any schedule</option><option value="upcoming" @selected(request('timing') === 'upcoming')>Upcoming</option><option value="ongoing" @selected(request('timing') === 'ongoing')>Ongoing</option><option value="completed" @selected(request('timing') === 'completed')>Completed</option></select></label><button class="h-11 rounded-xl bg-[#141e46] px-5 text-sm font-extrabold text-white" type="submit">Apply</button></form>@endif
        </header>

        <div class="divide-y divide-slate-100">
            @forelse($events as $event)
                <article class="grid gap-5 px-5 py-5 transition hover:bg-slate-50/60 sm:px-6 lg:grid-cols-[minmax(240px,1fr)_180px_220px_auto] lg:items-center">
                    <div class="min-w-0"><div class="flex items-center gap-2"><h3 class="truncate text-sm font-extrabold text-[#141e46]">{{ $event->title }}</h3><span class="shrink-0 rounded-full px-2 py-0.5 text-[9px] font-extrabold {{ match($event->schedule_state) { 'ongoing' => 'bg-amber-50 text-amber-700', 'completed' => 'bg-slate-100 text-slate-600', default => 'bg-emerald-50 text-emerald-700' } }}">{{ ucfirst($event->schedule_state) }}</span></div><p class="mt-1 truncate text-xs text-slate-400">{{ $event->start_at->format('M j, Y · g:i A') }} · {{ $event->location ?: 'No location' }}</p></div>
                    <div><span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Roster</span><p class="mt-1 text-xs font-bold text-slate-700">{{ number_format($event->attendances_count) }} of {{ number_format($event->expected_count) }} recorded</p></div>
                    <div><div class="flex items-center justify-between text-[10px] font-bold"><span class="text-slate-400">Coverage</span><span class="text-slate-600">{{ $event->coverage_rate !== null ? $event->coverage_rate.'%' : 'No participants' }}</span></div><div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $event->coverage_rate ?? 0 }}%"></div></div>@if($event->attendance_rate !== null)<p class="mt-1.5 text-[10px] text-slate-400">{{ $event->attendance_rate }}% attended</p>@endif</div>
                    <a class="inline-flex min-h-10 items-center justify-center rounded-xl bg-emerald-600 px-4 text-xs font-extrabold text-white shadow-sm transition hover:bg-emerald-700" href="{{ route('adviser.attendance.show', $event) }}">Open Roster</a>
                </article>
            @empty
                <div class="px-6 py-12 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-emerald-50 text-emerald-700"><svg class="h-7 w-7 fill-none stroke-current" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 11l2 2 4-4m6 3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></span><strong class="mt-4 block text-sm text-slate-600">{{ $summary['events'] > 0 ? 'No events match these filters' : 'Create an event first' }}</strong><p class="mt-1 text-xs text-slate-400">{{ $summary['events'] > 0 ? 'Try changing or clearing the filters.' : 'Attendance rosters are generated from each event’s participant settings.' }}</p>@if($summary['events'] === 0)<a class="mt-5 inline-flex min-h-10 items-center rounded-xl bg-emerald-600 px-4 text-xs font-extrabold text-white" href="{{ route('adviser.events.index', ['create' => 1]) }}">Create Event</a>@endif</div>
            @endforelse
        </div>

        @if($events->hasPages())<nav class="flex items-center justify-between border-t border-slate-100 px-5 py-4 text-xs"><small class="text-slate-400">Showing {{ $events->firstItem() }}–{{ $events->lastItem() }} of {{ $events->total() }}</small><div class="flex gap-2">@if($events->onFirstPage())<span class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-slate-300">Previous</span>@else<a class="rounded-lg border border-slate-200 px-3 py-2 font-bold text-slate-600" href="{{ $events->previousPageUrl() }}">Previous</a>@endif @if($events->hasMorePages())<a class="rounded-lg border border-slate-200 px-3 py-2 font-bold text-slate-600" href="{{ $events->nextPageUrl() }}">Next</a>@else<span class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-slate-300">Next</span>@endif</div></nav>@endif
    </section>
@endsection
