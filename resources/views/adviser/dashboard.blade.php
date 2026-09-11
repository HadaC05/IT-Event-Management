@extends('layouts.adviser')

@section('title', 'Adviser Dashboard')

@section('content')
    <header class="mb-8 flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <p class="mb-2 text-xs font-extrabold uppercase tracking-[.14em] text-emerald-700">Overview</p>
            <h1 class="text-3xl font-extrabold tracking-tight text-[#121017] sm:text-4xl">
                Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ auth()->user()->first_name }}.
            </h1>
            <p class="mt-2 text-sm text-slate-500">Monitor attendance, events, people, and team performance.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="mr-2 hidden text-xs font-bold text-slate-400 sm:inline">{{ now()->format('l, F j') }}</span>
            <a class="inline-flex min-h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-600 transition hover:border-emerald-200 hover:text-emerald-700" href="{{ route('adviser.users.index') }}">Manage Users</a>
            <a class="inline-flex min-h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-600 transition hover:border-emerald-200 hover:text-emerald-700" href="{{ route('adviser.events.index') }}">View Events</a>
            <a class="inline-flex min-h-10 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-extrabold text-white shadow-lg shadow-emerald-600/15 transition hover:bg-emerald-700" href="{{ route('adviser.events.create') }}"><span class="text-lg font-normal" aria-hidden="true">+</span>Create Event</a>
        </div>
    </header>

    @if ($startingTomorrow)
        <x-notification-banner type="warning" title="{{ $startingTomorrow->title }} starts tomorrow">The event begins at {{ $startingTomorrow->start_at->format('g:i A') }}. Review its assignments and preparations before it starts.</x-notification-banner>
    @endif

    <section class="mb-5 grid gap-4 lg:grid-cols-3" aria-label="Primary dashboard indicators">
        <article class="relative overflow-hidden rounded-2xl bg-[#121017] p-6 text-white shadow-lg shadow-slate-900/10">
            <div class="absolute -right-12 -top-12 h-36 w-36 rounded-full border-[28px] border-emerald-300/10" aria-hidden="true"></div>
            <div class="relative">
                <p class="text-xs font-extrabold uppercase tracking-[.14em] text-emerald-300">Attendance Today</p>
                <div class="mt-5 flex items-end gap-3">
                    <strong class="text-5xl font-black leading-none tracking-tight">{{ $stats['attendance_rate'] === null ? '—' : number_format($stats['attendance_rate'], 1).'%' }}</strong>
                    @if ($attendanceTrend !== null)
                        <span class="mb-1 rounded-full px-2.5 py-1 text-xs font-bold {{ $attendanceTrend >= 0 ? 'bg-emerald-300/15 text-emerald-200' : 'bg-rose-300/15 text-rose-200' }}">{{ $attendanceTrend >= 0 ? '↑' : '↓' }} {{ number_format(abs($attendanceTrend), 1) }}%</span>
                    @endif
                </div>
                <p class="mt-3 text-sm text-white/60">
                    @if ($todayAttendance['marked'] > 0)
                        {{ number_format($todayAttendance['attended']) }} of {{ number_format($todayAttendance['marked']) }} marked records attended
                    @else
                        No attendance has been marked today
                    @endif
                </p>
                @if ($previousAttendanceEvent && $attendanceTrend !== null)
                    <p class="mt-1 text-xs text-white/40">Compared with {{ $previousAttendanceEvent->title }}</p>
                @endif
            </div>
        </article>

        <article class="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between"><p class="text-xs font-extrabold uppercase tracking-[.14em] text-slate-400">Students Present</p><span class="h-2.5 w-2.5 rounded-full bg-[#C6F24E] ring-2 ring-[#397565]/15" aria-hidden="true"></span></div>
            <div class="mt-6"><strong class="text-5xl font-black leading-none tracking-tight text-[#121017]">{{ number_format($stats['students_present']) }}</strong><p class="mt-3 text-sm text-slate-500">of {{ number_format($stats['students']) }} registered students</p></div>
        </article>

        <article class="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between"><p class="text-xs font-extrabold uppercase tracking-[.14em] text-slate-400">Upcoming Events</p><span class="h-2.5 w-2.5 rounded-full bg-[#2F3AE0]" aria-hidden="true"></span></div>
            <div class="mt-6"><strong class="text-5xl font-black leading-none tracking-tight text-[#121017]">{{ number_format($stats['upcoming_events']) }}</strong>
                @if ($upcomingEvents->first())
                    <p class="mt-3 truncate text-sm text-slate-500">Next: <span class="font-bold text-slate-700">{{ $upcomingEvents->first()->title }}</span></p>
                @else
                    <p class="mt-3 text-sm text-slate-400">No upcoming events scheduled</p>
                @endif
            </div>
        </article>
    </section>

    <section class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-label="People overview">
        <div class="grid divide-y divide-slate-100 sm:grid-cols-[1.2fr_repeat(3,1fr)] sm:divide-x sm:divide-y-0">
            <div class="p-5 sm:p-6"><p class="text-xs font-extrabold uppercase tracking-[.14em] text-emerald-700">People Overview</p><div class="mt-2 flex items-baseline gap-2"><strong class="text-3xl font-black text-[#121017]">{{ number_format($stats['total_users']) }}</strong><span class="text-xs font-semibold text-slate-400">Total Users</span></div></div>
            <div class="flex items-center justify-between gap-4 px-5 py-4 sm:block sm:p-6"><span class="text-sm font-semibold text-slate-500">Students</span><strong class="text-2xl font-extrabold text-[#121017] sm:mt-2 sm:block">{{ number_format($stats['students']) }}</strong></div>
            <div class="flex items-center justify-between gap-4 px-5 py-4 sm:block sm:p-6"><span class="text-sm font-semibold text-slate-500">Faculty</span><strong class="text-2xl font-extrabold text-[#121017] sm:mt-2 sm:block">{{ number_format($stats['faculty']) }}</strong></div>
            <div class="flex items-center justify-between gap-4 px-5 py-4 sm:block sm:p-6"><span class="text-sm font-semibold text-slate-500">SBO Officers</span><strong class="text-2xl font-extrabold text-[#121017] sm:mt-2 sm:block">{{ number_format($stats['sbo']) }}</strong></div>
        </div>
    </section>

    <section class="mb-6 grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(340px,.65fr)]">
        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <header class="flex flex-wrap items-end justify-between gap-4 border-b border-slate-100 px-6 py-5">
                <div><h2 class="text-lg font-extrabold tracking-tight text-[#121017]">Attendance Overview</h2><p class="mt-1 text-xs text-slate-500">Today’s attendance status distribution</p></div>
                @if ($todayAttendance['rate'] !== null)<strong class="text-2xl font-extrabold text-emerald-700">{{ number_format($todayAttendance['rate'], 1) }}%</strong>@endif
            </header>

            @if ($todayAttendance['marked'] > 0)
                <div class="p-6">
                    <div class="h-3 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-label="Today's attendance rate" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $todayAttendance['rate'] }}"><div class="h-full rounded-full bg-[#397565]" style="width: {{ min(100, $todayAttendance['rate']) }}%"></div></div>
                    <div class="mt-6 grid grid-cols-2 gap-x-6 gap-y-5 sm:grid-cols-4">
                        <div><span class="mb-2 block h-1 w-8 rounded-full bg-[#C6F24E]"></span><strong class="text-2xl font-extrabold text-[#121017]">{{ number_format($todayAttendance['present']) }}</strong><p class="mt-1 text-xs font-semibold text-slate-500">Present</p></div>
                        <div><span class="mb-2 block h-1 w-8 rounded-full bg-[#FF6B2C]"></span><strong class="text-2xl font-extrabold text-[#121017]">{{ number_format($todayAttendance['late']) }}</strong><p class="mt-1 text-xs font-semibold text-slate-500">Late</p></div>
                        <div><span class="mb-2 block h-1 w-8 rounded-full bg-[#FF6B2C]"></span><strong class="text-2xl font-extrabold text-[#121017]">{{ number_format($todayAttendance['absent']) }}</strong><p class="mt-1 text-xs font-semibold text-slate-500">Absent</p></div>
                        <div><span class="mb-2 block h-1 w-8 rounded-full bg-[#121017]/25"></span><strong class="text-2xl font-extrabold text-[#121017]">{{ number_format($todayAttendance['excused']) }}</strong><p class="mt-1 text-xs font-semibold text-slate-500">Excused</p></div>
                    </div>
                </div>
            @else
                <div class="px-6 py-12 text-center"><strong class="block text-sm text-slate-600">No attendance recorded yet</strong><p class="mt-1 text-xs text-slate-400">Today’s distribution will appear after attendance is marked.</p></div>
            @endif
        </article>

        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <header class="border-b border-slate-100 px-6 py-5"><h2 class="text-lg font-extrabold tracking-tight text-[#121017]">Today’s Events</h2><p class="mt-1 text-xs text-slate-500">Schedule for {{ now()->format('F j') }}</p></header>
            @forelse ($todayEvents as $event)
                @php($stateTone = match($event->schedule_state) { 'ongoing' => 'bg-[#C6F24E]', 'completed' => 'bg-[#121017]/20', default => 'bg-[#2F3AE0]' })
                <a class="group flex gap-4 border-b border-slate-100 px-6 py-4 last:border-b-0 hover:bg-slate-50" href="{{ route('adviser.events.show', $event) }}">
                    <span class="mt-1 flex flex-col items-center"><i class="h-2.5 w-2.5 rounded-full {{ $stateTone }}"></i><i class="mt-1 h-full min-h-8 w-px bg-slate-200 group-last:hidden"></i></span>
                    <span class="grid min-w-0 flex-1 gap-1"><strong class="truncate text-sm text-slate-700">{{ $event->title }}</strong><small class="text-xs text-slate-400">{{ $event->start_at->format('g:i A') }}–{{ $event->end_at->format('g:i A') }} · {{ $event->location ?: 'Location not set' }}</small><small class="text-xs text-slate-400">{{ $event->assignedUsers->count() }} {{ Str::plural('person', $event->assignedUsers->count()) }} in charge</small></span>
                </a>
            @empty
                <div class="px-6 py-10 text-center"><strong class="block text-sm text-slate-600">No events today</strong>
                    @if ($upcomingEvents->isNotEmpty())<p class="mt-1 text-xs text-slate-400">Next: {{ $upcomingEvents->first()->title }} on {{ $upcomingEvents->first()->start_at->format('M j') }}</p>@else<p class="mt-1 text-xs text-slate-400">Create an event to start building the schedule.</p>@endif
                </div>
            @endforelse
        </article>
    </section>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(340px,.65fr)]">
        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <header class="border-b border-slate-100 px-6 py-5"><h2 class="text-lg font-extrabold tracking-tight text-[#121017]">Recent Attendance</h2><p class="mt-1 text-xs text-slate-500">Latest attendance activity across events</p></header>
            @if ($recentAttendances->isNotEmpty())
                <div class="overflow-x-auto"><table class="w-full min-w-[620px] text-left"><thead class="bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-400"><tr><th class="px-6 py-3">Student</th><th class="px-4 py-3">Event</th><th class="px-4 py-3">Status</th><th class="px-6 py-3 text-right">Recorded</th></tr></thead><tbody class="divide-y divide-slate-100">
                    @foreach ($recentAttendances as $attendance)
                        @php($statusTone = match($attendance->status) { 'present' => 'bg-[#C6F24E]/35 text-[#397565]', 'late' => 'bg-[#FF6B2C]/10 text-[#FF6B2C]', 'absent' => 'bg-[#FF6B2C]/10 text-[#FF6B2C]', default => 'bg-[#121017]/6 text-[#121017]/60' })
                        <tr><td class="px-6 py-4"><strong class="text-sm text-slate-700">{{ $attendance->user->full_name }}</strong></td><td class="px-4 py-4 text-sm text-slate-500">{{ $attendance->event->title }}</td><td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $statusTone }}">{{ ucfirst($attendance->status) }}</span></td><td class="px-6 py-4 text-right text-xs text-slate-400">{{ ($attendance->checked_in_at ?? $attendance->updated_at)->diffForHumans() }}</td></tr>
                    @endforeach
                </tbody></table></div>
            @else
                <div class="px-6 py-12 text-center"><strong class="block text-sm text-slate-600">No recent attendance</strong><p class="mt-1 text-xs text-slate-400">Student attendance activity will appear here.</p></div>
            @endif
        </article>

        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <header class="flex items-end justify-between gap-4 border-b border-slate-100 px-6 py-5"><div><h2 class="text-lg font-extrabold tracking-tight text-[#121017]">Team Leaderboard</h2><p class="mt-1 text-xs text-slate-500">Overall team rankings</p></div><div class="flex items-center gap-4"><span class="text-right"><strong class="block text-sm text-[#121017]">{{ rtrim(rtrim(number_format($stats['points_awarded'], 2), '0'), '.') }}</strong><small class="text-xs text-slate-400">total points</small></span><a class="text-xs font-extrabold text-[#2F3AE0] hover:underline" href="{{ route('adviser.leaderboard.index') }}">View all</a></div></header>
            @forelse ($leaderboard as $team)
                @php($rankTone = match($team->rank) { 1 => 'bg-[#C6F24E] text-[#121017]', 2 => 'bg-[#121017]/10 text-[#121017]/70', 3 => 'bg-[#FF6B2C]/15 text-[#FF6B2C]', default => 'bg-[#121017]/6 text-[#121017]/60' })
                <div class="flex items-center gap-4 border-b border-slate-100 px-6 py-4 last:border-b-0"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-xs font-extrabold {{ $rankTone }}" aria-label="Rank {{ $team->rank }}">{{ $team->rank }}</span><span class="grid min-w-0 flex-1 gap-1"><strong class="truncate text-sm text-slate-700">{{ $team->name }}</strong><small class="text-xs text-slate-400">{{ number_format($team->members_count) }} {{ Str::plural('member', $team->members_count) }}</small></span><strong class="text-lg font-extrabold text-[#121017]">{{ rtrim(rtrim(number_format($team->total_score, 2), '0'), '.') }}</strong></div>
            @empty
                <div class="px-6 py-12 text-center"><strong class="block text-sm text-slate-600">No team rankings yet</strong><p class="mt-1 text-xs text-slate-400">The leaderboard will populate after teams receive scores.</p></div>
            @endforelse
        </article>
    </section>
@endsection
