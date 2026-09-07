@extends('layouts.adviser')

@section('title', 'Manage Scores')
@section('body-class', 'bg-[#F3F0E9]')

@section('content')
    @php
        $formatPoints = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    @endphp

    <header class="border-b border-[#121017]/12 pb-7">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="mb-3 inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-[.18em] text-[#397565]"><span class="h-1.5 w-1.5 rounded-full bg-[#C6F24E] ring-2 ring-[#397565]/15"></span>Competition results</p>
                <h1 class="text-4xl font-black tracking-[-.05em] text-[#121017] sm:text-5xl">Event Scoreboards</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-[#121017]/52">Choose an event to configure its judging criteria, record tribe scores, and review the current standings.</p>
            </div>
            @if($summary['events'] > 0)
                <a class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-[#2F3AE0]/25 bg-white/45 px-5 text-xs font-black text-[#2F3AE0] transition hover:border-[#2F3AE0] hover:bg-[#2F3AE0] hover:text-white" href="{{ route('adviser.events.index') }}">Manage Events <span aria-hidden="true">→</span></a>
            @endif
        </div>

        <dl class="mt-7 grid gap-x-8 gap-y-5 border-t border-[#121017]/10 pt-5 sm:grid-cols-2 lg:grid-cols-[repeat(3,auto)_minmax(220px,1fr)]">
            <div><dt class="text-[9px] font-black uppercase tracking-[.15em] text-[#121017]/35">Events</dt><dd class="mt-1 text-xl font-black text-[#121017]">{{ number_format($summary['events']) }}</dd></div>
            <div><dt class="text-[9px] font-black uppercase tracking-[.15em] text-[#121017]/35">Configured</dt><dd class="mt-1 text-xl font-black text-[#121017]">{{ number_format($summary['configured']) }}</dd></div>
            <div><dt class="text-[9px] font-black uppercase tracking-[.15em] text-[#121017]/35">Tribe results</dt><dd class="mt-1 text-xl font-black text-[#121017]">{{ number_format($summary['results']) }}</dd></div>
            <div class="lg:ml-auto lg:text-right"><dt class="text-[9px] font-black uppercase tracking-[.15em] text-[#121017]/35">Points awarded</dt><dd class="mt-1 text-xl font-black {{ $summary['points'] > 0 ? 'text-[#397565]' : 'text-[#121017]/30' }}">{{ $summary['points'] > 0 ? $formatPoints($summary['points']) : '—' }}</dd></div>
        </dl>
    </header>

    <section class="py-8" aria-labelledby="scoreboard-directory-heading">
        <div class="overflow-hidden rounded-2xl border border-[#121017]/10 bg-white/75 shadow-[0_18px_55px_rgba(18,16,23,.05)]">
            <header class="border-b border-[#121017]/8 px-5 py-5 sm:px-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div><p class="text-[10px] font-black uppercase tracking-[.16em] text-[#397565]">Scoring workspace</p><h2 id="scoreboard-directory-heading" class="mt-1 text-xl font-black tracking-[-.025em] text-[#121017]">Select an event</h2><p class="mt-1 text-[10px] font-semibold text-[#121017]/40">{{ number_format($events->total()) }} {{ Str::plural('event', $events->total()) }} available</p></div>
                    @if(request()->anyFilled(['search', 'timing']))<a class="text-[10px] font-black text-[#FF6B2C] transition hover:text-[#c94414]" href="{{ route('adviser.scores.index') }}">Clear all filters</a>@endif
                </div>

                @if($summary['events'] > 0)
                    <form class="mt-5 grid gap-2 md:grid-cols-[minmax(260px,1fr)_190px_auto]" method="GET" action="{{ route('adviser.scores.index') }}">
                        <label class="relative"><svg class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-[#121017]/35 stroke-2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><span class="sr-only">Search events</span><input class="h-11 w-full rounded-xl border border-[#121017]/10 bg-[#F3F0E9]/55 pl-10 pr-3 text-sm text-[#121017] outline-none transition placeholder:text-[#121017]/30 focus:border-[#397565] focus:bg-white focus:ring-4 focus:ring-[#397565]/10" name="search" value="{{ request('search') }}" placeholder="Search event or venue…"></label>
                        <label class="relative"><span class="sr-only">Filter by schedule</span><select class="h-11 w-full appearance-none rounded-xl border border-[#121017]/10 bg-[#F3F0E9]/55 px-3 pr-9 text-xs font-bold text-[#121017]/60 outline-none transition focus:border-[#397565] focus:bg-white focus:ring-4 focus:ring-[#397565]/10" name="timing"><option value="">Any schedule</option><option value="upcoming" @selected(request('timing') === 'upcoming')>Upcoming</option><option value="ongoing" @selected(request('timing') === 'ongoing')>Ongoing</option><option value="completed" @selected(request('timing') === 'completed')>Completed</option></select><svg class="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-[#397565] stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></label>
                        <button class="h-11 rounded-xl bg-[#397565] px-5 text-xs font-black text-white transition hover:bg-[#2f6658] focus:outline-none focus:ring-4 focus:ring-[#397565]/20" type="submit">Apply Filters</button>
                    </form>
                @endif
            </header>

            <div class="divide-y divide-[#121017]/8">
                @forelse($events as $event)
                    @php
                        $points = (float) ($event->scores_sum_points ?? 0);
                        $isConfigured = $event->score_categories_count > 0;
                        $hasResults = $event->scored_teams_count > 0;
                    @endphp
                    <article class="group grid gap-5 px-5 py-5 transition hover:bg-[#397565]/[.035] sm:px-6 lg:grid-cols-[minmax(250px,1fr)_160px_190px_auto] lg:items-center">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="truncate text-base font-black tracking-[-.015em] text-[#121017]">{{ $event->title }}</h3>
                                <span @class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[9px] font-black uppercase tracking-wide', 'bg-[#C6F24E]/40 text-[#397565]' => $event->schedule_state === 'ongoing', 'bg-[#2F3AE0]/8 text-[#2F3AE0]' => $event->schedule_state === 'upcoming', 'bg-[#121017]/6 text-[#121017]/45' => $event->schedule_state === 'completed'])><span @class(['h-1.5 w-1.5 rounded-full', 'bg-[#C6F24E]' => $event->schedule_state === 'ongoing', 'bg-[#2F3AE0]' => $event->schedule_state === 'upcoming', 'bg-[#121017]/25' => $event->schedule_state === 'completed'])></span>{{ ucfirst($event->schedule_state) }}</span>
                            </div>
                            <p class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[10px] font-semibold text-[#121017]/40"><span>{{ $event->start_at->format('M j, Y · g:i A') }}</span><span>·</span><span>{{ $event->location ?: 'Venue not specified' }}</span></p>
                        </div>

                        <div><span class="text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/30">Criteria</span><p class="mt-1.5 text-xs font-black {{ $isConfigured ? 'text-[#121017]' : 'text-[#FF6B2C]' }}">{{ $isConfigured ? $event->score_categories_count.' '.Str::plural('criterion', $event->score_categories_count) : 'Setup needed' }}</p></div>
                        <div><span class="text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/30">Results</span><p class="mt-1.5 text-xs font-black text-[#121017]">{{ $hasResults ? $event->scored_teams_count.' '.Str::plural('tribe', $event->scored_teams_count) : 'No results yet' }}</p>@if($points > 0)<small class="mt-1 block text-[9px] font-bold text-[#397565]">{{ $formatPoints($points) }} points awarded</small>@endif</div>

                        <a class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-5 text-xs font-black transition focus:outline-none focus:ring-4 {{ $isConfigured ? 'bg-[#2F3AE0] text-white shadow-[0_8px_20px_rgba(47,58,224,.16)] hover:-translate-y-0.5 hover:bg-[#252fc4] focus:ring-[#2F3AE0]/20' : 'bg-[#397565] text-white hover:bg-[#2f6658] focus:ring-[#397565]/20' }}" href="{{ route('adviser.scores.show', $event) }}">{{ $isConfigured ? 'Open Scoreboard' : 'Set Up Scoring' }} <span class="transition group-hover:translate-x-0.5" aria-hidden="true">→</span></a>
                    </article>
                @empty
                    <div class="px-6 py-10 text-center">
                        <strong class="block text-sm font-black text-[#121017]">{{ $summary['events'] > 0 ? 'No events match these filters' : 'There are no events to score yet' }}</strong>
                        <p class="mt-1 text-xs text-[#121017]/42">{{ $summary['events'] > 0 ? 'Change or clear the current filters.' : 'Create an event before configuring its scoring rules.' }}</p>
                        @if($summary['events'] === 0)<a class="mt-5 inline-flex min-h-10 items-center rounded-xl bg-[#2F3AE0] px-4 text-xs font-black text-white" href="{{ route('adviser.events.index', ['create' => 1]) }}">Create Event</a>@endif
                    </div>
                @endforelse
            </div>

            @if($events->hasPages())
                <nav class="flex items-center justify-between border-t border-[#121017]/8 px-5 py-4 text-[10px] sm:px-6"><small class="font-semibold text-[#121017]/35">Showing {{ $events->firstItem() }}–{{ $events->lastItem() }} of {{ $events->total() }}</small><div class="flex gap-2">@if($events->onFirstPage())<span class="rounded-lg border border-[#121017]/6 px-3 py-2 text-[#121017]/20">Previous</span>@else<a class="rounded-lg border border-[#121017]/10 px-3 py-2 font-black text-[#397565]" href="{{ $events->previousPageUrl() }}">Previous</a>@endif @if($events->hasMorePages())<a class="rounded-lg border border-[#121017]/10 px-3 py-2 font-black text-[#397565]" href="{{ $events->nextPageUrl() }}">Next</a>@else<span class="rounded-lg border border-[#121017]/6 px-3 py-2 text-[#121017]/20">Next</span>@endif</div></nav>
            @endif
        </div>
    </section>
@endsection
