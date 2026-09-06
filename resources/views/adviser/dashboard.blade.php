@extends('layouts.adviser')

@section('title', 'Adviser Dashboard')

@section('content')
    <header class="mb-8 flex items-end justify-between gap-5">
        <div><p class="mb-2 text-xs font-extrabold uppercase tracking-[.14em] text-emerald-700">Overview</p><h1 class="text-3xl font-extrabold tracking-tight text-[#141e46] sm:text-4xl">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ auth()->user()->first_name }}.</h1><p class="mt-2 text-sm text-slate-500">Here’s what’s happening across your event system.</p></div>
        <span class="hidden shrink-0 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-500 sm:block">{{ now()->format('l, F j') }}</span>
    </header>

    @if ($startingTomorrow)
        <x-notification-banner type="warning" title="{{ $startingTomorrow->title }} starts tomorrow">The event begins at {{ $startingTomorrow->start_at->format('g:i A') }}. Review its assignments and preparations before it starts.</x-notification-banner>
    @endif

    @php
        $cards = [
            ['total_users', 'Total Users', 'bg-emerald-50 text-emerald-700'],
            ['sbo', 'Total SBO', 'bg-violet-50 text-violet-700'],
            ['faculty', 'Total Faculty', 'bg-amber-50 text-amber-700'],
            ['students', 'Total Students', 'bg-sky-50 text-sky-700'],
            ['events', 'Total Events', 'bg-indigo-50 text-indigo-700'],
            ['upcoming', 'Upcoming Events', 'bg-yellow-50 text-yellow-700'],
            ['ongoing', 'Ongoing Events', 'bg-green-50 text-green-700'],
        ];
    @endphp
    <section class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="System overview">
        @foreach ($cards as [$key, $label, $tone])
            <article class="flex min-h-28 items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl {{ $tone }}" aria-hidden="true">
                    @if (in_array($key, ['total_users', 'sbo', 'faculty', 'students']))
                        <svg class="h-5 w-5 fill-none stroke-current" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    @else
                        <svg class="h-5 w-5 fill-none stroke-current" viewBox="0 0 24 24"><path d="M6 2v4m12-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z"/></svg>
                    @endif
                </span>
                <span class="grid gap-1"><strong class="text-3xl font-extrabold leading-none text-[#141e46]">{{ number_format($stats[$key]) }}</strong><small class="text-xs font-semibold text-slate-500">{{ $label }}</small></span>
            </article>
        @endforeach
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 px-6 py-5"><h2 class="text-lg font-extrabold tracking-tight text-[#141e46]">Recent Activity</h2><p class="mt-1 text-xs text-slate-500">Latest changes made across the system</p></header>
        @forelse ($activities as $activity)
            <div class="flex items-start gap-4 border-b border-slate-100 px-6 py-4 last:border-b-0">
                <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full border-2 {{ $activity->action === 'event_unassigned' ? 'border-red-200 bg-red-500 ring-4 ring-red-50' : 'border-emerald-200 bg-emerald-500 ring-4 ring-emerald-50' }}"></span>
                <span class="grid gap-1"><strong class="text-sm font-semibold text-slate-700">{{ $activity->description }}</strong><small class="text-xs text-slate-400">{{ $activity->actor->full_name ?: 'System' }} · {{ $activity->created_at->diffForHumans() }}</small></span>
            </div>
        @empty
            <div class="flex items-center gap-3 px-6 py-9"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-50 text-emerald-700"><svg class="h-5 w-5 fill-none stroke-current" viewBox="0 0 24 24"><path d="M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></span><div><strong class="text-sm text-slate-600">No activity yet</strong><p class="mt-1 text-xs text-slate-400">User and event assignment changes will appear here.</p></div></div>
        @endforelse
    </section>
@endsection
