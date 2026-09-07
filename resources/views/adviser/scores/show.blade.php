@extends('layouts.adviser')

@section('title', $event->title.' Scores')
@section('body-class', 'bg-[#F3F0E9]')

@section('content')
    @php
        $rankedTeams = $teams
            ->filter(fn ($team) => $scores->contains(fn ($score) => $score->team_id === $team->id))
            ->take(3);
        $formatPoints = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    @endphp

    <div class="mb-6 flex items-center justify-between gap-4">
        <a class="inline-flex items-center gap-2 text-xs font-black text-[#397565] transition hover:text-[#2F3AE0]" href="{{ route('adviser.scores.index') }}">
            <span aria-hidden="true">←</span> All event scoreboards
        </a>
        <span class="hidden text-[10px] font-black uppercase tracking-[.18em] text-[#121017]/35 sm:block">Adviser workspace</span>
    </div>

    <header class="border-b border-[#121017]/12 pb-7">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px] lg:items-end">
            <div class="min-w-0">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-2 rounded-full bg-[#397565]/10 px-3 py-1 text-[10px] font-black uppercase tracking-[.14em] text-[#397565]">
                        <span @class(['h-1.5 w-1.5 rounded-full ring-2 ring-[#397565]/15', 'bg-[#C6F24E]' => $event->schedule_state === 'ongoing', 'bg-[#2F3AE0]' => $event->schedule_state === 'upcoming', 'bg-[#397565]' => $event->schedule_state === 'completed'])></span>
                        Event scoring
                    </span>
                    <span class="rounded-full bg-white/55 px-3 py-1 text-[10px] font-extrabold text-[#121017]/55">{{ ucfirst($event->schedule_state) }}</span>
                </div>
                <h1 class="truncate text-3xl font-black tracking-[-.045em] text-[#121017] sm:text-4xl lg:text-5xl">{{ $event->title }}</h1>
                <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-xs font-semibold text-[#121017]/55 sm:text-sm">
                    <span class="inline-flex items-center gap-2"><svg class="h-4 w-4 fill-none stroke-[#397565] stroke-2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4m8-4v4M3 10h18"/></svg>{{ $event->start_at->format('D, M j, Y') }} · {{ $event->start_at->format('g:i A') }}–{{ $event->end_at->format('g:i A') }}</span>
                    <span class="inline-flex items-center gap-2"><svg class="h-4 w-4 fill-none stroke-[#FF6B2C] stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>{{ $event->location ?: 'Venue not specified' }}</span>
                </div>
            </div>

            <label class="grid gap-2">
                <span class="text-[10px] font-black uppercase tracking-[.16em] text-[#121017]/45">Switch event</span>
                <span class="relative">
                    <select class="h-12 w-full appearance-none rounded-xl border border-[#121017]/12 bg-white/75 pl-4 pr-11 text-sm font-extrabold text-[#121017] outline-none transition focus:border-[#2F3AE0] focus:ring-4 focus:ring-[#2F3AE0]/10" data-event-switch>
                        @foreach($eventOptions as $option)
                            <option value="{{ route('adviser.scores.show', $option) }}" @selected($option->id === $event->id)>{{ $option->title }} · {{ $option->start_at->format('M j') }}</option>
                        @endforeach
                    </select>
                    <svg class="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-[#397565] stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>
                </span>
            </label>
        </div>

        <div class="mt-7 grid gap-5 border-t border-[#121017]/10 pt-5 sm:grid-cols-[auto_auto_minmax(220px,1fr)] sm:items-center">
            <div><span class="text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/35">Criteria</span><strong class="mt-1 block text-lg font-black text-[#121017]">{{ $summary['categories'] }}</strong></div>
            <div><span class="text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/35">Eligible tribes</span><strong class="mt-1 block text-lg font-black text-[#121017]">{{ $summary['teams'] }}</strong></div>
            <div class="sm:ml-auto sm:w-full sm:max-w-md">
                <div class="flex items-center justify-between gap-4 text-[10px] font-extrabold"><span class="text-[#121017]/45">Scoring progress</span><span class="text-[#397565]" data-completion>{{ $summary['completion'] !== null ? $summary['completion'].'%' : 'Not configured' }}</span></div>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-[#121017]/8"><div class="h-full rounded-full bg-[#397565] transition-all" style="width: {{ $summary['completion'] ?? 0 }}%" data-completion-bar></div></div>
                <p class="mt-1.5 text-right text-[9px] text-[#121017]/35"><span data-entry-count>{{ $summary['entries'] }}</span> recorded {{ Str::plural('entry', $summary['entries']) }}@if($summary['entries'] > 0) · <span data-grand-total>{{ $formatPoints($summary['total_points']) }}</span> points awarded @endif</p>
            </div>
        </div>
    </header>

    <nav class="border-b border-[#121017]/12" aria-label="Scoring workflow">
        <ol class="grid sm:grid-cols-3">
            <li class="flex items-center gap-3 py-4 sm:pr-5"><span class="grid h-7 w-7 place-items-center rounded-full {{ $categories->isNotEmpty() ? 'bg-[#C6F24E] text-[#121017]' : 'bg-[#397565] text-white' }} text-[10px] font-black">{{ $categories->isNotEmpty() ? '✓' : '1' }}</span><span><strong class="block text-xs text-[#121017]">Configure criteria</strong><small class="text-[9px] text-[#121017]/40">Names and maximums</small></span></li>
            <li class="flex items-center gap-3 border-[#121017]/10 py-4 sm:border-l sm:px-5"><span class="grid h-7 w-7 place-items-center rounded-full {{ $categories->isNotEmpty() ? 'bg-[#397565] text-white' : 'bg-[#121017]/8 text-[#121017]/35' }} text-[10px] font-black">2</span><span><strong class="block text-xs {{ $categories->isNotEmpty() ? 'text-[#121017]' : 'text-[#121017]/40' }}">Enter tribe scores</strong><small class="text-[9px] text-[#121017]/40">Record judged points</small></span></li>
            <li class="flex items-center gap-3 border-[#121017]/10 py-4 sm:border-l sm:pl-5"><span class="grid h-7 w-7 place-items-center rounded-full {{ $scores->isNotEmpty() ? 'bg-[#2F3AE0] text-white' : 'bg-[#121017]/8 text-[#121017]/35' }} text-[10px] font-black">3</span><span><strong class="block text-xs {{ $scores->isNotEmpty() ? 'text-[#121017]' : 'text-[#121017]/40' }}">Review results</strong><small class="text-[9px] text-[#121017]/40">Totals and standings</small></span></li>
        </ol>
    </nav>

    <section class="py-8" aria-labelledby="criteria-heading">
        @if($categories->isEmpty())
            <div class="grid overflow-hidden rounded-2xl border border-[#121017]/10 bg-white/70 lg:grid-cols-[minmax(0,.8fr)_minmax(420px,1.2fr)]">
                <div class="border-b border-[#121017]/8 p-6 sm:p-8 lg:border-b-0 lg:border-r">
                    <p class="text-[10px] font-black uppercase tracking-[.16em] text-[#FF6B2C]">Start here</p>
                    <h2 id="criteria-heading" class="mt-3 text-2xl font-black tracking-[-.035em] text-[#121017]">Define how this event is judged.</h2>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-[#121017]/55">Add the first criterion and its highest possible score. You can add more criteria before entering tribe results.</p>
                    <p class="mt-5 inline-flex items-center gap-2 text-[10px] font-bold text-[#397565]"><span class="grid h-5 w-5 place-items-center rounded-full bg-[#C6F24E] text-[9px] text-[#121017]">i</span>Examples: Performance, Creativity, Sportsmanship</p>
                </div>
                <form class="p-6 sm:p-8" method="POST" action="{{ route('adviser.scores.categories.store', $event) }}">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_150px]">
                        <label class="grid gap-2"><span class="text-xs font-extrabold text-[#121017]">Criterion name</span><input class="h-12 rounded-xl border border-[#121017]/12 bg-white px-4 text-sm text-[#121017] outline-none transition placeholder:text-[#121017]/30 focus:border-[#397565] focus:ring-4 focus:ring-[#397565]/10" name="name" value="{{ old('name') }}" maxlength="80" placeholder="e.g. Creativity" required><x-form-error name="name" /></label>
                        <label class="grid gap-2"><span class="text-xs font-extrabold text-[#121017]">Maximum points</span><input class="h-12 rounded-xl border border-[#121017]/12 bg-white px-4 text-sm font-bold text-[#121017] outline-none transition focus:border-[#397565] focus:ring-4 focus:ring-[#397565]/10" type="number" name="max_points" value="{{ old('max_points', 100) }}" min="0.01" max="1000000" step="0.01" required><x-form-error name="max_points" /></label>
                    </div>
                    <button class="mt-5 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#397565] px-5 text-sm font-black text-white shadow-[0_8px_20px_rgba(57,117,101,.18)] transition hover:-translate-y-0.5 hover:bg-[#2f6658] focus:outline-none focus:ring-4 focus:ring-[#397565]/20" type="submit" data-loading-text="Adding criterion…">Add First Criterion <span aria-hidden="true">→</span></button>
                </form>
            </div>
        @else
            <div class="overflow-hidden rounded-2xl border border-[#121017]/10 bg-white/70">
                <header class="flex flex-col gap-2 border-b border-[#121017]/8 px-5 py-5 sm:flex-row sm:items-end sm:justify-between sm:px-6">
                    <div><p class="text-[10px] font-black uppercase tracking-[.16em] text-[#397565]">Scoring rules</p><h2 id="criteria-heading" class="mt-1 text-xl font-black tracking-[-.025em] text-[#121017]">{{ $categories->count() }} configured {{ Str::plural('criterion', $categories->count()) }}</h2></div>
                    <p class="text-[10px] font-bold text-[#121017]/40">{{ $formatPoints($summary['maximum']) }} maximum points per tribe</p>
                </header>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[620px] border-collapse">
                        <thead class="bg-[#121017]/[.025] text-left text-[9px] font-black uppercase tracking-[.15em] text-[#121017]/40"><tr><th class="px-6 py-3">Criterion</th><th class="px-4 py-3">Maximum</th><th class="px-4 py-3">Recorded</th><th class="px-6 py-3 text-right">Actions</th></tr></thead>
                        <tbody class="divide-y divide-[#121017]/8">
                            @foreach($categories as $category)
                                <tr class="transition hover:bg-white/80">
                                    <td class="px-6 py-3.5"><div class="flex items-center gap-3"><span class="grid h-7 w-7 place-items-center rounded-lg bg-[#397565]/10 text-[10px] font-black text-[#397565]">{{ $loop->iteration }}</span><strong class="text-sm text-[#121017]">{{ $category->name }}</strong></div></td>
                                    <td class="px-4 py-3.5 text-xs font-black text-[#121017]">{{ $formatPoints($category->max_points) }} <span class="font-semibold text-[#121017]/35">pts</span></td>
                                    <td class="px-4 py-3.5"><span class="inline-flex items-center gap-1.5 text-[10px] font-bold text-[#121017]/50"><span class="h-1.5 w-1.5 rounded-full {{ $category->scores_count ? 'bg-[#C6F24E]' : 'bg-[#121017]/15' }}"></span>{{ $category->scores_count }} {{ Str::plural('entry', $category->scores_count) }}</span></td>
                                    <td class="px-6 py-3.5"><div class="flex justify-end gap-1"><button class="min-h-8 rounded-lg px-3 text-[10px] font-black text-[#2F3AE0] transition hover:bg-[#2F3AE0]/8" type="button" data-dialog-open="category-{{ $category->id }}">Edit</button><form method="POST" action="{{ route('adviser.scores.categories.destroy', [$event, $category]) }}" data-confirm-title="Remove scoring category?" data-confirm-message="{{ $category->name }} and its {{ $category->scores_count }} score entries will be permanently removed." data-confirm-action="Remove category">@csrf @method('DELETE')<button class="min-h-8 rounded-lg px-3 text-[10px] font-black text-[#FF6B2C] transition hover:bg-[#FF6B2C]/8" type="submit" data-loading-text="Removing…">Remove</button></form></div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <form class="grid gap-3 border-t border-[#121017]/8 bg-[#121017]/[.025] p-4 sm:grid-cols-[minmax(220px,1fr)_150px_auto] sm:items-start sm:px-6" method="POST" action="{{ route('adviser.scores.categories.store', $event) }}">
                    @csrf
                    <label><span class="sr-only">New criterion name</span><input class="h-11 w-full rounded-xl border border-[#121017]/10 bg-white px-4 text-sm text-[#121017] outline-none placeholder:text-[#121017]/30 focus:border-[#397565] focus:ring-4 focus:ring-[#397565]/10" name="name" value="{{ old('name') }}" maxlength="80" placeholder="Add another criterion…" required><x-form-error name="name" /></label>
                    <label><span class="sr-only">Maximum points</span><input class="h-11 w-full rounded-xl border border-[#121017]/10 bg-white px-4 text-sm font-bold text-[#121017] outline-none focus:border-[#397565] focus:ring-4 focus:ring-[#397565]/10" type="number" name="max_points" value="{{ old('max_points', 100) }}" min="0.01" max="1000000" step="0.01" aria-label="Maximum points" required><x-form-error name="max_points" /></label>
                    <button class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#397565] px-5 text-xs font-black text-white transition hover:bg-[#2f6658]" type="submit" data-loading-text="Adding…">+ Add Criterion</button>
                </form>
            </div>
        @endif
    </section>

    <section class="pb-10" aria-labelledby="score-matrix-heading">
        @if($categories->isEmpty())
            <div class="flex items-center gap-4 border-y border-[#121017]/10 py-5 text-[#121017]/45"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#121017]/6 text-xs font-black">2</span><div><h2 id="score-matrix-heading" class="text-sm font-black">Score entry unlocks after criteria are configured</h2><p class="mt-0.5 text-[10px]">Use the form above to add the first judging criterion.</p></div></div>
        @elseif($teams->isEmpty())
            <div class="flex flex-col gap-4 rounded-2xl border border-[#FF6B2C]/20 bg-[#FF6B2C]/[.06] p-6 sm:flex-row sm:items-center sm:justify-between"><div><h2 id="score-matrix-heading" class="text-sm font-black text-[#121017]">No active tribes are available</h2><p class="mt-1 text-xs text-[#121017]/50">Create or activate a tribe before recording event results.</p></div><a class="inline-flex min-h-10 items-center justify-center rounded-xl bg-[#2F3AE0] px-4 text-xs font-black text-white" href="{{ route('adviser.teams.index') }}">Manage Tribes</a></div>
        @else
            <div class="overflow-hidden rounded-2xl border border-[#121017]/10 bg-white/80 shadow-[0_18px_55px_rgba(18,16,23,.06)]">
                <header class="border-b border-[#121017]/8 px-5 py-5 sm:px-6">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div><p class="text-[10px] font-black uppercase tracking-[.16em] text-[#397565]">Main workspace</p><h2 id="score-matrix-heading" class="mt-1 text-xl font-black tracking-[-.025em] text-[#121017]">Tribe Score Matrix</h2><p class="mt-1 text-xs text-[#121017]/45">Enter awarded points below. Blank fields remain unjudged.</p></div>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <p class="inline-flex min-h-10 items-center gap-2 rounded-xl bg-[#C6F24E]/25 px-3 text-[10px] font-black text-[#397565]" data-save-state><span class="h-1.5 w-1.5 rounded-full bg-[#397565]"></span>All changes saved</p>
                            <label class="relative"><svg class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-[#121017]/35 stroke-2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><span class="sr-only">Search tribes</span><input class="h-10 w-full rounded-xl border border-[#121017]/10 bg-[#F3F0E9]/55 pl-10 pr-3 text-xs text-[#121017] outline-none placeholder:text-[#121017]/35 focus:border-[#2F3AE0] focus:bg-white focus:ring-4 focus:ring-[#2F3AE0]/10 sm:w-60" type="search" placeholder="Find a tribe…" data-team-search></label>
                        </div>
                    </div>

                    @if($rankedTeams->isNotEmpty())
                        <div class="mt-5 flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-[#121017]/8 pt-4" aria-label="Current leaders">
                            <span class="text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/35">Current standing</span>
                            @foreach($rankedTeams as $team)
                                <span class="inline-flex items-center gap-2 text-[10px] font-bold text-[#121017]/65"><strong class="grid h-5 w-5 place-items-center rounded-full {{ $team->rank === 1 ? 'bg-[#C6F24E] text-[#121017]' : ($team->rank === 2 ? 'bg-[#2F3AE0]/10 text-[#2F3AE0]' : 'bg-[#FF6B2C]/10 text-[#FF6B2C]') }} text-[9px]">{{ $team->rank }}</strong>{{ $team->name }} <span class="text-[#121017]/35">{{ $formatPoints($team->event_score_total) }}</span></span>
                            @endforeach
                        </div>
                    @endif
                </header>

                @if($errors->has('scores') || $errors->has('scores.*'))
                    <div class="m-5 flex items-start gap-3 rounded-xl border border-[#FF6B2C]/25 bg-[#FF6B2C]/[.07] px-4 py-3 text-xs font-bold text-[#9b3513] sm:mx-6"><span aria-hidden="true">!</span><span>{{ $errors->first('scores') ?: $errors->first('scores.*') }}</span></div>
                @endif

                <form method="POST" action="{{ route('adviser.scores.update', $event) }}" data-score-form>
                    @csrf
                    @method('PUT')
                    <div class="max-h-[62vh] overflow-auto">
                        <table class="w-full border-collapse" style="min-width: {{ 470 + ($categories->count() * 154) }}px">
                            <thead class="sticky top-0 z-20 bg-[#F3F0E9] text-left text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/40 shadow-[0_1px_0_rgba(18,16,23,.08)]">
                                <tr><th class="sticky left-0 z-30 min-w-60 bg-[#F3F0E9] px-6 py-3.5">Rank / Tribe</th>@foreach($categories as $category)<th class="min-w-36 px-3 py-3.5"><span class="block text-[#121017]/65">{{ $category->name }}</span><small class="mt-0.5 block font-bold normal-case tracking-normal text-[#FF6B2C]">0–{{ $formatPoints($category->max_points) }} pts</small></th>@endforeach<th class="min-w-28 px-6 py-3.5 text-right">Total</th></tr>
                            </thead>
                            <tbody class="divide-y divide-[#121017]/8" data-score-body>
                                @foreach($teams as $team)
                                    <tr class="group transition hover:bg-[#397565]/[.035]" data-team-row data-team-name="{{ Str::lower($team->name) }}">
                                        <td class="sticky left-0 z-10 bg-white px-6 py-3.5 transition group-hover:bg-[#f8f7f1]"><div class="flex items-center gap-3"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-full {{ $team->rank === 1 && $scores->isNotEmpty() ? 'bg-[#C6F24E] text-[#121017]' : 'bg-[#121017]/6 text-[#121017]/55' }} text-[10px] font-black" data-rank>{{ $scores->isNotEmpty() ? $team->rank : '—' }}</span><span class="h-8 w-1 shrink-0 rounded-full" style="background: {{ $team->color }}"></span><span class="grid min-w-0"><strong class="truncate text-sm text-[#121017]">{{ $team->name }}</strong><small class="text-[9px] text-[#121017]/38">{{ $team->schoolYear?->label ?: 'School year not set' }}@unless($team->is_active) · Inactive @endunless</small></span></div></td>
                                        @foreach($categories as $category)
                                            @php($score = $scores->get($category->id.'-'.$team->id))
                                            <td class="px-3 py-3"><label><span class="sr-only">{{ $category->name }} score for {{ $team->name }}</span><input class="h-10 w-full rounded-lg border border-[#121017]/10 bg-white px-3 text-right text-sm font-black text-[#121017] outline-none transition placeholder:text-[#121017]/20 hover:border-[#397565]/35 focus:border-[#2F3AE0] focus:ring-4 focus:ring-[#2F3AE0]/10 invalid:border-[#FF6B2C] invalid:bg-[#FF6B2C]/5" type="number" name="scores[{{ $category->id }}][{{ $team->id }}]" value="{{ old('scores.'.$category->id.'.'.$team->id, $score?->points) }}" min="0" max="{{ $category->max_points }}" step="0.01" placeholder="—" data-score-input data-original-score="{{ $score?->points ?? '' }}"><x-form-error name="scores.{{ $category->id }}.{{ $team->id }}" /></label></td>
                                        @endforeach
                                        <td class="px-6 py-3.5 text-right"><strong class="text-lg font-black text-[#121017]" data-team-total>{{ $formatPoints($team->event_score_total ?? 0) }}</strong><small class="ml-1 text-[9px] font-bold text-[#121017]/30">pts</small></td>
                                    </tr>
                                @endforeach
                                <tr class="hidden" data-no-team-results><td class="px-6 py-10 text-center text-sm font-bold text-[#121017]/45" colspan="{{ $categories->count() + 2 }}">No tribes match your search.</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <footer class="sticky bottom-0 z-30 flex flex-col gap-3 border-t border-[#121017]/10 bg-white/95 px-5 py-4 backdrop-blur sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div><p class="text-xs font-bold text-[#121017]/45"><strong class="text-[#121017]" data-change-count>0</strong> unsaved changes</p><p class="mt-0.5 text-[9px] text-[#121017]/35">Maximum {{ $formatPoints($summary['maximum']) }} points per tribe</p></div>
                        <div class="flex gap-2"><a class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border border-[#121017]/12 px-4 text-xs font-black text-[#121017]/60 transition hover:bg-[#F3F0E9] sm:flex-none" href="{{ route('adviser.scores.show', $event) }}">Reset</a><button class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-[#2F3AE0] px-6 text-xs font-black text-white shadow-[0_8px_20px_rgba(47,58,224,.18)] transition hover:-translate-y-0.5 hover:bg-[#252fc4] disabled:cursor-not-allowed disabled:bg-[#121017]/15 disabled:text-[#121017]/30 disabled:shadow-none sm:flex-none" type="submit" data-loading-text="Saving scores…" data-save-scores disabled>Save Scores</button></div>
                    </footer>
                </form>
            </div>
        @endif
    </section>

    @foreach($categories as $category)
        <dialog class="w-[min(92vw,430px)] rounded-2xl bg-[#F3F0E9] p-0 text-[#121017] shadow-2xl backdrop:bg-[#121017]/65" id="category-{{ $category->id }}">
            <form class="p-6" method="POST" action="{{ route('adviser.scores.categories.update', [$event, $category]) }}">
                @csrf
                @method('PUT')
                <div class="mb-5 flex items-start justify-between gap-4"><div><p class="text-[9px] font-black uppercase tracking-[.16em] text-[#397565]">Edit criterion</p><h3 class="mt-1 text-xl font-black tracking-[-.025em]">{{ $category->name }}</h3></div><button class="grid h-9 w-9 place-items-center rounded-full text-xl text-[#121017]/40 transition hover:bg-[#121017]/6 hover:text-[#121017]" type="button" data-dialog-close aria-label="Close">&times;</button></div>
                <div class="grid gap-4"><label class="grid gap-2"><span class="text-xs font-extrabold">Criterion name</span><input class="h-11 rounded-xl border border-[#121017]/12 bg-white px-3 text-sm outline-none focus:border-[#397565] focus:ring-4 focus:ring-[#397565]/10" name="name" value="{{ $category->name }}" maxlength="80" required></label><label class="grid gap-2"><span class="text-xs font-extrabold">Maximum points</span><input class="h-11 rounded-xl border border-[#121017]/12 bg-white px-3 text-sm outline-none focus:border-[#397565] focus:ring-4 focus:ring-[#397565]/10" type="number" name="max_points" value="{{ $category->max_points }}" min="0.01" max="1000000" step="0.01" required><small class="text-[9px] text-[#121017]/40">Cannot be lower than an already-recorded score.</small></label></div>
                <div class="mt-6 flex gap-2"><button class="min-h-11 flex-1 rounded-xl border border-[#121017]/12 text-xs font-black text-[#121017]/60" type="button" data-dialog-close>Cancel</button><button class="min-h-11 flex-1 rounded-xl bg-[#2F3AE0] text-xs font-black text-white" type="submit" data-loading-text="Saving…">Save Changes</button></div>
            </form>
        </dialog>
    @endforeach
@endsection

@push('scripts')
<script>
    document.querySelector('[data-event-switch]')?.addEventListener('change', (event) => { window.location.href = event.target.value; });

    const scoreInputs = [...document.querySelectorAll('[data-score-input]')];
    const teamRows = [...document.querySelectorAll('[data-team-row]')];
    const teamSearch = document.querySelector('[data-team-search]');
    const saveScores = document.querySelector('[data-save-scores]');
    const saveState = document.querySelector('[data-save-state]');
    const normalizeScore = (value) => value === '' ? '' : String(Number(value));

    const formatScore = (value) => Number.isInteger(value) ? String(value) : value.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
    const refreshScores = () => {
        let entries = 0;
        let grandTotal = 0;
        const totals = teamRows.map((row) => {
            const total = [...row.querySelectorAll('[data-score-input]')].reduce((sum, input) => {
                if (input.value !== '') entries++;
                return sum + (Number(input.value) || 0);
            }, 0);
            grandTotal += total;
            row.querySelector('[data-team-total]').textContent = formatScore(total);
            return total;
        });

        const ordered = [...totals].sort((a, b) => b - a);
        teamRows.forEach((row, index) => { row.querySelector('[data-rank]').textContent = ordered.indexOf(totals[index]) + 1; });

        const changed = scoreInputs.filter((input) => normalizeScore(input.value) !== normalizeScore(input.dataset.originalScore)).length;
        document.querySelector('[data-change-count]').textContent = changed;
        if (saveScores) saveScores.disabled = changed === 0;
        if (saveState) {
            saveState.innerHTML = changed === 0
                ? '<span class="h-1.5 w-1.5 rounded-full bg-[#397565]"></span>All changes saved'
                : `<span class="h-1.5 w-1.5 rounded-full bg-[#FF6B2C]"></span>${changed} unsaved ${changed === 1 ? 'change' : 'changes'}`;
            saveState.classList.toggle('bg-[#C6F24E]/25', changed === 0);
            saveState.classList.toggle('bg-[#FF6B2C]/10', changed > 0);
            saveState.classList.toggle('text-[#397565]', changed === 0);
            saveState.classList.toggle('text-[#9b3513]', changed > 0);
        }

        document.querySelector('[data-entry-count]').textContent = entries;
        const grandTotalTarget = document.querySelector('[data-grand-total]');
        if (grandTotalTarget) grandTotalTarget.textContent = formatScore(grandTotal);
        const completion = scoreInputs.length ? Math.round((entries / scoreInputs.length) * 1000) / 10 : null;
        document.querySelector('[data-completion]').textContent = completion === null ? 'Not configured' : `${completion}%`;
        document.querySelector('[data-completion-bar]').style.width = `${completion || 0}%`;
    };

    const filterTeams = () => {
        let visible = 0;
        teamRows.forEach((row) => {
            const matches = row.dataset.teamName.includes((teamSearch?.value || '').trim().toLowerCase());
            row.classList.toggle('hidden', !matches);
            if (matches) visible++;
        });
        document.querySelector('[data-no-team-results]')?.classList.toggle('hidden', visible !== 0);
    };

    scoreInputs.forEach((input) => input.addEventListener('input', refreshScores));
    teamSearch?.addEventListener('input', filterTeams);
    document.querySelector('[data-score-form]')?.addEventListener('submit', () => {
        if (saveScores) saveScores.disabled = true;
        if (saveState) saveState.innerHTML = '<span class="h-1.5 w-1.5 animate-pulse rounded-full bg-[#2F3AE0]"></span>Saving scores…';
    });
</script>
@endpush
