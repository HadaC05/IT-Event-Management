@extends('layouts.adviser')

@section('title', $event->title.' Scores')

@section('content')
    <div class="mb-5"><a class="text-xs font-extrabold text-slate-500 transition hover:text-emerald-700" href="{{ route('adviser.scores.index') }}">← Back to scores</a></div>
    <header class="mb-7 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"><div><p class="mb-2 text-xs font-extrabold uppercase tracking-[.14em] text-emerald-700">Event Scoreboard</p><h1 class="text-3xl font-extrabold tracking-tight text-[#141e46] sm:text-4xl">{{ $event->title }}</h1><p class="mt-2 text-sm text-slate-500">{{ $event->start_at->format('l, F j, Y · g:i A') }}–{{ $event->end_at->format('g:i A') }} · {{ $event->location ?: 'No location' }}</p></div><label class="grid gap-1.5"><span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Switch event</span><select class="h-11 min-w-64 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-600 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10" data-event-switch>@foreach($eventOptions as $option)<option value="{{ route('adviser.scores.show', $option) }}" @selected($option->id === $event->id)>{{ $option->title }} · {{ $option->start_at->format('M j') }}</option>@endforeach</select></label></header>

    <section class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="grid lg:grid-cols-[1.35fr_repeat(3,1fr)]">
            <div class="bg-[#141e46] p-5 text-white sm:p-6"><p class="text-xs font-extrabold uppercase tracking-[.14em] text-emerald-300">Score Completion</p><div class="mt-2 flex items-baseline gap-2"><strong class="text-4xl font-black" data-completion>{{ $summary['completion'] !== null ? $summary['completion'].'%' : '—' }}</strong><span class="text-xs font-semibold text-white/50"><span data-entry-count>{{ $summary['entries'] }}</span> entries recorded</span></div><div class="mt-4 h-1.5 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-[#8decb4]" style="width: {{ $summary['completion'] ?? 0 }}%" data-completion-bar></div></div></div>
            @foreach([['categories', 'Categories', $summary['categories']], ['teams', 'Eligible Tribes', $summary['teams']], ['total_points', 'Points Awarded', rtrim(rtrim(number_format($summary['total_points'], 2), '0'), '.')]] as [$key, $label, $value])<div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 lg:block lg:border-b-0 lg:border-r lg:p-6"><span class="text-xs font-semibold text-slate-500">{{ $label }}</span><strong class="text-2xl font-extrabold text-[#141e46] lg:mt-2 lg:block" @if($key === 'total_points') data-grand-total @endif>{{ $value }}</strong></div>@endforeach
        </div>
    </section>

    <section class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 px-5 py-5 sm:px-6"><div><p class="text-[10px] font-extrabold uppercase tracking-[.14em] text-emerald-700">Step 1</p><h2 class="mt-1 text-lg font-extrabold tracking-tight text-[#141e46]">Scoring Categories</h2><p class="mt-1 text-xs text-slate-500">Describe what judges will score and set the highest possible points.</p></div></header>
        <div class="grid gap-5 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div>
                @if($categories->isEmpty())
                    <div class="rounded-xl bg-amber-50 px-4 py-4"><strong class="text-sm text-amber-900">Add your first criterion</strong><p class="mt-1 text-xs leading-5 text-amber-700">Examples include Performance, Creativity, Sportsmanship, or Overall Result.</p></div>
                @else
                    <div class="divide-y divide-slate-100 rounded-xl border border-slate-200">
                        @foreach($categories as $category)
                            <div class="flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-center"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-emerald-50 text-xs font-black text-emerald-700">{{ $loop->iteration }}</span><span class="min-w-0 flex-1"><strong class="block truncate text-sm text-slate-700">{{ $category->name }}</strong><small class="text-[10px] text-slate-400">Up to {{ rtrim(rtrim(number_format($category->max_points, 2), '0'), '.') }} points · {{ $category->scores_count }} {{ Str::plural('entry', $category->scores_count) }}</small></span><div class="flex gap-1"><button class="min-h-8 rounded-lg px-3 text-xs font-extrabold text-emerald-700 hover:bg-emerald-50" type="button" data-dialog-open="category-{{ $category->id }}">Edit</button><form method="POST" action="{{ route('adviser.scores.categories.destroy', [$event, $category]) }}" data-confirm-title="Remove scoring category?" data-confirm-message="{{ $category->name }} and its {{ $category->scores_count }} score entries will be permanently removed." data-confirm-action="Remove category">@csrf @method('DELETE')<button class="min-h-8 rounded-lg px-3 text-xs font-bold text-rose-600 hover:bg-rose-50" type="submit" data-loading-text="Removing…">Remove</button></form></div></div>
                        @endforeach
                    </div>
                @endif
            </div>
            <form class="rounded-xl bg-slate-50 p-4" method="POST" action="{{ route('adviser.scores.categories.store', $event) }}">@csrf<div class="mb-3"><strong class="text-sm text-[#141e46]">Add category</strong><p class="mt-0.5 text-[10px] text-slate-400">The maximum keeps every score within the rules.</p></div><div class="grid gap-3 sm:grid-cols-[1fr_110px] lg:grid-cols-1"><label class="grid gap-1.5"><span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Category name</span><input class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10" name="name" value="{{ old('name') }}" maxlength="80" placeholder="e.g. Creativity" required><x-form-error name="name" /></label><label class="grid gap-1.5"><span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Maximum</span><input class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10" type="number" name="max_points" value="{{ old('max_points', 100) }}" min="0.01" max="1000000" step="0.01" required><x-form-error name="max_points" /></label></div><button class="mt-3 inline-flex min-h-10 w-full items-center justify-center rounded-xl bg-[#141e46] px-4 text-xs font-extrabold text-white transition hover:bg-slate-800" type="submit" data-loading-text="Adding…">Add Category</button></form>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 px-5 py-5 sm:px-6"><div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-[10px] font-extrabold uppercase tracking-[.14em] text-emerald-700">Step 2</p><h2 class="mt-1 text-lg font-extrabold tracking-tight text-[#141e46]">Tribe Score Matrix</h2><p class="mt-1 text-xs text-slate-500">Enter awarded points. Leave a field blank when that result has not been judged.</p></div>@if($categories->isNotEmpty() && $teams->isNotEmpty())<label class="relative"><svg class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-slate-400" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><span class="sr-only">Search tribes</span><input class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-3 text-xs outline-none focus:border-emerald-500 focus:bg-white sm:w-64" type="search" placeholder="Search tribe…" data-team-search></label>@endif</div></header>

        @if($errors->has('scores') || $errors->has('scores.*'))<div class="mx-5 mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-700 sm:mx-6">{{ $errors->first('scores') ?: $errors->first('scores.*') }}</div>@endif

        @if($categories->isEmpty())
            <div class="px-6 py-12 text-center"><strong class="block text-sm text-slate-600">Scoring rules come first</strong><p class="mt-1 text-xs text-slate-400">Add at least one category above before recording results.</p></div>
        @elseif($teams->isEmpty())
            <div class="px-6 py-12 text-center"><strong class="block text-sm text-slate-600">No active tribes available</strong><p class="mt-1 text-xs text-slate-400">Create or activate a tribe before recording event scores.</p><a class="mt-5 inline-flex min-h-10 items-center rounded-xl bg-emerald-600 px-4 text-xs font-extrabold text-white" href="{{ route('adviser.teams.index') }}">Manage Tribes</a></div>
        @else
            <form method="POST" action="{{ route('adviser.scores.update', $event) }}" data-score-form>@csrf @method('PUT')
                <div class="overflow-x-auto"><table class="w-full border-collapse" style="min-width: {{ 430 + ($categories->count() * 150) }}px"><thead class="bg-slate-50 text-left text-[10px] font-extrabold uppercase tracking-wider text-slate-400"><tr><th class="sticky left-0 z-10 min-w-56 bg-slate-50 px-6 py-3.5">Rank / Tribe</th>@foreach($categories as $category)<th class="min-w-36 px-3 py-3.5"><span class="block text-slate-500">{{ $category->name }}</span><small class="font-bold normal-case tracking-normal text-slate-400">Max {{ rtrim(rtrim(number_format($category->max_points, 2), '0'), '.') }}</small></th>@endforeach<th class="min-w-28 px-6 py-3.5 text-right">Total</th></tr></thead><tbody class="divide-y divide-slate-100" data-score-body>
                    @foreach($teams as $team)
                        <tr class="transition hover:bg-slate-50/70" data-team-row data-team-name="{{ Str::lower($team->name) }}">
                            <td class="sticky left-0 z-10 bg-white px-6 py-4"><div class="flex items-center gap-3"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-slate-100 text-[10px] font-black text-[#141e46]" data-rank>{{ $team->rank }}</span><span class="h-8 w-1 shrink-0 rounded-full" style="background: {{ $team->color }}"></span><span class="grid min-w-0"><strong class="truncate text-sm text-slate-700">{{ $team->name }}</strong><small class="text-[10px] text-slate-400">{{ $team->schoolYear?->label ?: 'No school year' }}@unless($team->is_active) · Inactive @endunless</small></span></div></td>
                            @foreach($categories as $category)
                                @php($score = $scores->get($category->id.'-'.$team->id))
                                <td class="px-3 py-3"><label><span class="sr-only">{{ $category->name }} score for {{ $team->name }}</span><input class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-right text-sm font-bold text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 invalid:border-rose-400 invalid:bg-rose-50" type="number" name="scores[{{ $category->id }}][{{ $team->id }}]" value="{{ old('scores.'.$category->id.'.'.$team->id, $score?->points) }}" min="0" max="{{ $category->max_points }}" step="0.01" placeholder="—" data-score-input data-original-score="{{ $score?->points ?? '' }}"><x-form-error name="scores.{{ $category->id }}.{{ $team->id }}" /></label></td>
                            @endforeach
                            <td class="px-6 py-4 text-right"><strong class="text-lg font-black text-[#141e46]" data-team-total>{{ rtrim(rtrim(number_format($team->event_score_total ?? 0, 2), '0'), '.') }}</strong><small class="ml-1 text-[9px] font-bold text-slate-400">pts</small></td>
                        </tr>
                    @endforeach
                    <tr class="hidden" data-no-team-results><td class="px-6 py-12 text-center text-sm text-slate-500" colspan="{{ $categories->count() + 2 }}">No tribes match your search.</td></tr>
                </tbody></table></div>
                <footer class="sticky bottom-3 z-20 flex flex-col gap-3 border-t border-slate-100 bg-white/95 px-5 py-4 backdrop-blur sm:flex-row sm:items-center sm:justify-between sm:px-6"><div><p class="text-xs text-slate-400"><strong class="text-slate-600" data-change-count>0</strong> unsaved changes</p><p class="mt-0.5 text-[10px] text-slate-400">Event maximum: {{ rtrim(rtrim(number_format($summary['maximum'], 2), '0'), '.') }} points per tribe</p></div><div class="flex gap-2"><a class="inline-flex min-h-10 flex-1 items-center justify-center rounded-xl border border-slate-200 px-4 text-xs font-extrabold text-slate-600 sm:flex-none" href="{{ route('adviser.scores.show', $event) }}">Reset</a><button class="inline-flex min-h-10 flex-1 items-center justify-center rounded-xl bg-emerald-600 px-5 text-xs font-extrabold text-white disabled:cursor-not-allowed disabled:bg-slate-300 sm:flex-none" type="submit" data-loading-text="Saving…" data-save-scores disabled>Save Scores</button></div></footer>
            </form>
        @endif
    </section>

    @foreach($categories as $category)
        <dialog class="w-[min(92vw,430px)] rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-[#141e46]/55" id="category-{{ $category->id }}"><form class="p-6" method="POST" action="{{ route('adviser.scores.categories.update', [$event, $category]) }}">@csrf @method('PUT')<div class="mb-5 flex items-start justify-between gap-4"><div><p class="text-[10px] font-extrabold uppercase tracking-[.14em] text-emerald-700">Edit Criterion</p><h3 class="mt-1 text-xl font-extrabold text-[#141e46]">{{ $category->name }}</h3></div><button class="grid h-9 w-9 place-items-center rounded-lg text-xl text-slate-400 hover:bg-slate-100" type="button" data-dialog-close aria-label="Close">&times;</button></div><div class="grid gap-4"><label class="grid gap-1.5"><span class="text-xs font-bold text-slate-600">Category name</span><input class="h-11 rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10" name="name" value="{{ $category->name }}" maxlength="80" required></label><label class="grid gap-1.5"><span class="text-xs font-bold text-slate-600">Maximum points</span><input class="h-11 rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10" type="number" name="max_points" value="{{ $category->max_points }}" min="0.01" max="1000000" step="0.01" required><small class="text-[10px] text-slate-400">Cannot be lower than an already-recorded score.</small></label></div><div class="mt-6 flex gap-2"><button class="min-h-10 flex-1 rounded-xl border border-slate-200 text-xs font-extrabold text-slate-600" type="button" data-dialog-close>Cancel</button><button class="min-h-10 flex-1 rounded-xl bg-emerald-600 text-xs font-extrabold text-white" type="submit" data-loading-text="Saving…">Save Changes</button></div></form></dialog>
    @endforeach
@endsection

@push('scripts')
<script>
    document.querySelector('[data-event-switch]')?.addEventListener('change', (event) => { window.location.href = event.target.value; });
    const scoreInputs = [...document.querySelectorAll('[data-score-input]')];
    const teamRows = [...document.querySelectorAll('[data-team-row]')];
    const teamSearch = document.querySelector('[data-team-search]');
    const saveScores = document.querySelector('[data-save-scores]');
    const normalizeScore = (value) => value === '' ? '' : String(Number(value));
    const refreshScores = () => {
        let entries = 0;
        let grandTotal = 0;
        const totals = teamRows.map((row) => {
            const total = [...row.querySelectorAll('[data-score-input]')].reduce((sum, input) => {
                if (input.value !== '') entries++;
                return sum + (Number(input.value) || 0);
            }, 0);
            grandTotal += total;
            row.querySelector('[data-team-total]').textContent = Number.isInteger(total) ? total : total.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
            return total;
        });
        const ordered = [...totals].sort((a, b) => b - a);
        teamRows.forEach((row, index) => { row.querySelector('[data-rank]').textContent = ordered.indexOf(totals[index]) + 1; });
        const changed = scoreInputs.filter((input) => normalizeScore(input.value) !== normalizeScore(input.dataset.originalScore)).length;
        if (document.querySelector('[data-change-count]')) document.querySelector('[data-change-count]').textContent = changed;
        if (saveScores) saveScores.disabled = changed === 0;
        if (document.querySelector('[data-entry-count]')) document.querySelector('[data-entry-count]').textContent = entries;
        if (document.querySelector('[data-grand-total]')) document.querySelector('[data-grand-total]').textContent = Number.isInteger(grandTotal) ? grandTotal : grandTotal.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
        const possible = scoreInputs.length;
        const completion = possible ? Math.round((entries / possible) * 1000) / 10 : null;
        if (document.querySelector('[data-completion]')) document.querySelector('[data-completion]').textContent = completion === null ? '—' : `${completion}%`;
        if (document.querySelector('[data-completion-bar]')) document.querySelector('[data-completion-bar]').style.width = `${completion || 0}%`;
    };
    const filterTeams = () => {
        let visible = 0;
        teamRows.forEach((row) => { const matches = row.dataset.teamName.includes((teamSearch?.value || '').trim().toLowerCase()); row.classList.toggle('hidden', !matches); if (matches) visible++; });
        document.querySelector('[data-no-team-results]')?.classList.toggle('hidden', visible !== 0);
    };
    scoreInputs.forEach((input) => input.addEventListener('input', refreshScores));
    teamSearch?.addEventListener('input', filterTeams);
    document.querySelector('[data-score-form]')?.addEventListener('submit', () => { if (saveScores) saveScores.disabled = true; });
</script>
@endpush
