@extends('layouts.adviser')

@section('title', 'Leaderboard')

@section('content')
    @php
        $formatPoints = fn ($points) => rtrim(rtrim(number_format((float) $points, 2), '0'), '.');
        $contextTitle = $selectedCategory?->name ?? ($selectedEvent?->title ?? 'All events');
        $contextSubtitle = collect([
            $selectedEvent ? $selectedEvent->start_at->format('M j, Y') : 'Cumulative results',
            $selectedSchoolYear?->label,
            $selectedCategory ? 'Category ranking' : null,
        ])->filter()->join(' · ');
    @endphp

    <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[.17em] text-[#397565]"><i class="h-2 w-2 rounded-full bg-[#C6F24E] ring-2 ring-[#397565]/15"></i>Competition standings</p>
            <h1 class="mt-3 text-4xl font-black tracking-[-.055em] text-[#121017] sm:text-5xl">Leaderboard</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-[#121017]/52">Compare tribe performance across every scored event or focus on one competition and criterion.</p>
        </div>
        <a class="inline-flex min-h-12 w-fit items-center justify-center gap-2 rounded-xl bg-[#2F3AE0] px-5 text-sm font-black text-white shadow-[0_10px_25px_rgba(47,58,224,.18)] transition hover:-translate-y-0.5 hover:bg-[#2732c9]" href="{{ route('adviser.scores.index') }}">Manage scores <span aria-hidden="true">→</span></a>
    </header>

    <section class="mt-8 overflow-hidden rounded-2xl border border-[#121017]/9 bg-white shadow-[0_18px_50px_rgba(18,16,23,.05)]" aria-label="Leaderboard summary">
        <div class="grid sm:grid-cols-2 xl:grid-cols-4">
            <div class="border-b border-[#121017]/7 px-5 py-5 sm:border-r xl:border-b-0"><span class="text-[9px] font-black uppercase tracking-[.15em] text-[#121017]/35">Ranked tribes</span><strong class="mt-2 block text-2xl font-black">{{ number_format($summary['ranked_teams']) }}</strong><small class="mt-1 block text-xs text-[#121017]/42">of {{ number_format($summary['eligible_teams']) }} shown in this view</small></div>
            <div class="border-b border-[#121017]/7 px-5 py-5 xl:border-b-0 xl:border-r"><span class="text-[9px] font-black uppercase tracking-[.15em] text-[#121017]/35">Points awarded</span><strong class="mt-2 block text-2xl font-black">{{ $formatPoints($summary['points']) }}</strong><small class="mt-1 block text-xs text-[#121017]/42">Across {{ number_format($summary['events']) }} {{ Str::plural('event', $summary['events']) }}</small></div>
            <div class="border-b border-[#121017]/7 px-5 py-5 sm:border-r xl:border-b-0"><span class="text-[9px] font-black uppercase tracking-[.15em] text-[#121017]/35">Current leader</span><strong class="mt-2 block truncate text-lg font-black">{{ $summary['leader']?->name ?? 'Not available' }}</strong><small class="mt-1 block text-xs text-[#121017]/42">{{ $summary['leader'] ? $formatPoints($summary['leader']->total_score).' points' : 'Record scores to establish a leader' }}</small></div>
            <div class="px-5 py-5"><span class="text-[9px] font-black uppercase tracking-[.15em] text-[#121017]/35">Lead margin</span><strong class="mt-2 block text-2xl font-black text-[#397565]">{{ $summary['lead'] === null ? '—' : $formatPoints($summary['lead']) }}</strong><small class="mt-1 block text-xs text-[#121017]/42">{{ $summary['lead'] === null ? 'A second ranked tribe is needed' : ($summary['lead'] == 0 ? 'The lead is currently tied' : 'points ahead of second place') }}</small></div>
        </div>
    </section>

    <section class="mt-5 rounded-2xl border border-[#121017]/9 bg-[#F3F0E9]/65 p-4 sm:p-5" aria-labelledby="leaderboard-filters-title">
        <div class="mb-4 flex items-center justify-between gap-4"><div><p class="text-[9px] font-black uppercase tracking-[.15em] text-[#397565]">Ranking scope</p><h2 class="mt-1 text-lg font-black" id="leaderboard-filters-title">Choose what to compare</h2></div>@if(request()->hasAny(['event_id', 'school_year_id', 'category_id', 'search']))<a class="text-xs font-black text-[#2F3AE0] hover:underline" href="{{ route('adviser.leaderboard.index') }}">Clear filters</a>@endif</div>
        <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-[1.2fr_1fr_1fr_1.2fr_auto]" method="GET" action="{{ route('adviser.leaderboard.index') }}" data-leaderboard-filters>
            <label class="grid gap-1.5"><span class="text-[9px] font-black uppercase tracking-wider text-[#121017]/42">Event</span><select class="h-12 rounded-xl border border-[#121017]/10 bg-white px-3 text-xs font-bold outline-none focus:border-[#397565] focus:ring-4 focus:ring-[#397565]/10" name="event_id" data-event-filter><option value="">All scored events</option>@foreach($events as $event)<option value="{{ $event->id }}" @selected($selectedEvent?->id === $event->id)>{{ $event->title }} · {{ $event->start_at->format('M j, Y') }}</option>@endforeach</select></label>
            <label class="grid gap-1.5"><span class="text-[9px] font-black uppercase tracking-wider text-[#121017]/42">School year</span><select class="h-12 rounded-xl border border-[#121017]/10 bg-white px-3 text-xs font-bold outline-none focus:border-[#397565] focus:ring-4 focus:ring-[#397565]/10" name="school_year_id"><option value="">All school years</option>@foreach($schoolYears as $year)<option value="{{ $year->id }}" @selected($selectedSchoolYear?->id === $year->id)>{{ $year->label }}</option>@endforeach</select></label>
            <label class="grid gap-1.5"><span class="text-[9px] font-black uppercase tracking-wider text-[#121017]/42">Criterion</span><select class="h-12 rounded-xl border border-[#121017]/10 bg-white px-3 text-xs font-bold outline-none disabled:cursor-not-allowed disabled:bg-[#121017]/5 disabled:text-[#121017]/30 focus:border-[#397565] focus:ring-4 focus:ring-[#397565]/10" name="category_id" data-category-filter @disabled(!$selectedEvent)><option value="">Overall event score</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected($selectedCategory?->id === $category->id)>{{ $category->name }}</option>@endforeach</select></label>
            <label class="grid gap-1.5"><span class="text-[9px] font-black uppercase tracking-wider text-[#121017]/42">Find tribe</span><span class="relative"><svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-[#121017]/30 stroke-2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input class="h-12 w-full rounded-xl border border-[#121017]/10 bg-white pl-10 pr-3 text-xs font-bold outline-none placeholder:font-normal placeholder:text-[#121017]/30 focus:border-[#397565] focus:ring-4 focus:ring-[#397565]/10" name="search" value="{{ request('search') }}" placeholder="Search tribe name…"></span></label>
            <button class="mt-auto min-h-12 rounded-xl bg-[#397565] px-5 text-xs font-black text-white shadow-[0_8px_20px_rgba(57,117,101,.16)] transition hover:bg-[#2e6355]" type="submit">Apply</button>
        </form>
        @error('category_id')<p class="mt-3 text-xs font-bold text-[#FF6B2C]">{{ $message }}</p>@enderror
    </section>

    @if($summary['leader'])
        <section class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1.25fr)_minmax(310px,.75fr)]" aria-label="Top performers">
            <article class="relative overflow-hidden rounded-2xl bg-[#121017] p-6 text-white shadow-[0_18px_45px_rgba(18,16,23,.14)] sm:p-8">
                <div class="absolute -right-12 -top-16 h-52 w-52 rounded-full border-[42px] border-[#C6F24E]/10" aria-hidden="true"></div>
                <div class="relative flex h-full flex-col justify-between gap-8"><div class="flex items-start justify-between gap-4"><div><p class="text-[10px] font-black uppercase tracking-[.17em] text-[#C6F24E]">Leading this view</p><p class="mt-2 text-xs text-white/42">{{ $contextTitle }} · {{ $contextSubtitle }}</p></div><span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-[#C6F24E] text-sm font-black text-[#121017]">#1</span></div><div class="flex items-end gap-5"><span class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl text-xl font-black text-white ring-1 ring-white/15" style="background:{{ $summary['leader']->color }}">{{ strtoupper(substr($summary['leader']->name, 0, 2)) }}</span><div class="min-w-0"><h2 class="truncate text-3xl font-black tracking-[-.04em]">{{ $summary['leader']->name }}</h2><p class="mt-1 text-sm text-white/48">{{ $summary['leader']->schoolYear?->label ?? 'School year not set' }} · {{ number_format($summary['leader']->members_count) }} {{ Str::plural('member', $summary['leader']->members_count) }}</p></div></div><div><strong class="text-5xl font-black tracking-[-.055em]">{{ $formatPoints($summary['leader']->total_score) }}</strong><span class="ml-2 text-sm font-bold text-white/40">points</span></div></div>
            </article>
            <article class="overflow-hidden rounded-2xl border border-[#121017]/9 bg-white shadow-[0_18px_45px_rgba(18,16,23,.05)]"><header class="border-b border-[#121017]/7 px-5 py-4"><p class="text-[9px] font-black uppercase tracking-[.15em] text-[#397565]">Podium</p><h2 class="mt-1 text-lg font-black">Top performers</h2></header><div class="divide-y divide-[#121017]/7">@foreach($podium as $team)@php($rankTone = match($team->rank) { 1 => 'bg-[#C6F24E] text-[#121017]', 2 => 'bg-[#121017]/8 text-[#121017]/65', 3 => 'bg-[#FF6B2C]/12 text-[#d34e16]', default => 'bg-[#121017]/6 text-[#121017]/50' })<div class="flex items-center gap-3 px-5 py-4"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-xs font-black {{ $rankTone }}">{{ $team->rank }}</span><i class="h-3 w-3 shrink-0 rounded-full" style="background:{{ $team->color }}"></i><span class="min-w-0 flex-1"><strong class="block truncate text-sm">{{ $team->name }}</strong><small class="text-[10px] text-[#121017]/38">{{ number_format($team->score_entries_count) }} scored {{ Str::plural('entry', $team->score_entries_count) }}</small></span><strong class="text-sm text-[#397565]">{{ $formatPoints($team->total_score) }}</strong></div>@endforeach</div></article>
        </section>
    @endif

    <section class="mt-5 overflow-hidden rounded-2xl border border-[#121017]/9 bg-white shadow-[0_18px_50px_rgba(18,16,23,.05)]" aria-labelledby="standings-title">
        <header class="flex flex-col gap-3 border-b border-[#121017]/7 px-5 py-5 sm:flex-row sm:items-end sm:justify-between sm:px-6"><div><p class="text-[9px] font-black uppercase tracking-[.15em] text-[#397565]">Full standings</p><h2 class="mt-1 text-xl font-black" id="standings-title">{{ $contextTitle }}</h2><p class="mt-1 text-xs text-[#121017]/42">{{ $contextSubtitle }}</p></div><span class="text-xs font-bold text-[#121017]/38">{{ number_format($rankings->count()) }} {{ Str::plural('tribe', $rankings->count()) }}</span></header>
        @if($rankings->isNotEmpty())
            <div class="overflow-x-auto"><table class="w-full min-w-[820px] border-collapse text-left"><thead class="bg-[#F3F0E9]/55 text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/38"><tr><th class="w-24 px-6 py-3.5">Rank</th><th class="px-3 py-3.5">Tribe</th><th class="px-3 py-3.5">School year</th><th class="px-3 py-3.5">Coverage</th><th class="px-3 py-3.5">Last update</th><th class="px-6 py-3.5 text-right">Points</th></tr></thead><tbody class="divide-y divide-[#121017]/7">
                @foreach($rankings as $team)
                    <tr class="transition hover:bg-[#397565]/[.025]"><td class="px-6 py-4">@if($team->has_score)<span @class(['grid h-9 w-9 place-items-center rounded-full text-xs font-black', 'bg-[#C6F24E] text-[#121017]' => $team->rank === 1, 'bg-[#121017]/8 text-[#121017]/65' => $team->rank === 2, 'bg-[#FF6B2C]/12 text-[#d34e16]' => $team->rank === 3, 'bg-[#121017]/5 text-[#121017]/45' => $team->rank > 3])>{{ $team->rank }}</span>@else<span class="text-xs font-black text-[#121017]/25">—</span>@endif</td><td class="px-3 py-4"><div class="flex items-center gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-[10px] font-black text-white" style="background:{{ $team->color }}">{{ strtoupper(substr($team->name, 0, 2)) }}</span><span class="min-w-0"><strong class="block truncate text-sm">{{ $team->name }}</strong><small class="mt-0.5 block text-[10px] text-[#121017]/38">{{ number_format($team->members_count) }} {{ Str::plural('member', $team->members_count) }}</small></span></div></td><td class="px-3 py-4 text-xs font-bold text-[#121017]/55">{{ $team->schoolYear?->label ?? '—' }}</td><td class="px-3 py-4"><strong class="block text-xs">{{ number_format($team->score_entries_count) }} {{ Str::plural('entry', $team->score_entries_count) }}</strong><small class="mt-0.5 block text-[10px] text-[#121017]/38">{{ number_format($team->scored_events_count) }} scored {{ Str::plural('event', $team->scored_events_count) }}</small></td><td class="px-3 py-4 text-xs text-[#121017]/45">{{ $team->last_scored_at?->diffForHumans() ?? 'Not scored' }}</td><td class="px-6 py-4 text-right">@if($team->has_score)<strong class="text-lg font-black text-[#397565]">{{ $formatPoints($team->total_score) }}</strong><small class="ml-1 text-[10px] font-bold text-[#121017]/30">pts</small>@else<span class="rounded-full bg-[#121017]/5 px-3 py-1.5 text-[9px] font-black uppercase tracking-wider text-[#121017]/35">Not scored</span>@endif</td></tr>
                @endforeach
            </tbody></table></div>
        @else
            <div class="px-6 py-16 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-[#397565]/8 text-[#397565]"><svg class="h-6 w-6 fill-none stroke-current stroke-2" viewBox="0 0 24 24"><path d="M4 20V10h4v10H4Zm6 0V4h4v16h-4Zm6 0v-7h4v7h-4Z"/></svg></span><h3 class="mt-5 text-lg font-black">No tribes match this view</h3><p class="mx-auto mt-2 max-w-md text-sm leading-6 text-[#121017]/45">Try clearing the filters, or configure scoring and record tribe results for an event.</p><a class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-[#397565] px-5 text-xs font-black text-white" href="{{ route('adviser.scores.index') }}">Open score management</a></div>
        @endif
    </section>
@endsection

@push('scripts')
    <script>
        const leaderboardEvent = document.querySelector('[data-event-filter]');
        const leaderboardCategory = document.querySelector('[data-category-filter]');
        leaderboardEvent?.addEventListener('change', () => {
            if (leaderboardCategory) leaderboardCategory.value = '';
        });
    </script>
@endpush
