@extends('layouts.adviser')

@section('title', 'Tribe Management')

@section('content')
    <header class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="mb-2 text-xs font-extrabold uppercase tracking-[.14em] text-emerald-700">Community</p>
            <h1 class="text-3xl font-extrabold tracking-tight text-[#141e46] sm:text-4xl">Tribe Management</h1>
            <p class="mt-2 text-sm text-slate-500">Organize students into tribes for events, attendance, and scoring.</p>
        </div>
        @if($teamSummary['total'] > 0 && $teamSummary['students'] > 0)<a class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 text-sm font-extrabold text-white shadow-lg shadow-emerald-600/15 transition hover:bg-emerald-700" href="{{ route('adviser.teams.create') }}"><span class="text-xl font-normal" aria-hidden="true">+</span>Create Tribe</a>@endif
    </header>

    <section class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-label="Tribe overview">
        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5"><p class="text-xs font-extrabold uppercase tracking-[.14em] text-slate-500">Tribe Overview</p><span class="text-[11px] font-semibold text-slate-400">Current student roster</span></header>
        <div class="grid divide-y divide-slate-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-4">
            @foreach ([['total', 'Total Tribes'], ['active', 'Active Tribes'], ['students', 'Active Students'], ['assigned', 'Assigned to Tribes']] as [$key, $label])
                <div class="flex items-center justify-between gap-4 px-5 py-4 xl:block xl:p-5">
                    <span class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500">@if ($key === 'active')<i class="h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true"></i>@endif{{ $label }}</span>
                    <strong class="text-2xl font-extrabold text-[#141e46] xl:mt-2 xl:block">{{ number_format($teamSummary[$key]) }}</strong>
                </div>
            @endforeach
        </div>
        <div class="flex flex-col gap-3 border-t px-5 py-4 sm:flex-row sm:items-center sm:justify-between {{ $teamSummary['unassigned'] > 0 ? 'border-amber-200 bg-amber-50/70' : 'border-slate-100 bg-slate-50/60' }}">
            <div class="flex items-center gap-3"><span class="grid h-9 w-9 place-items-center rounded-full {{ $teamSummary['unassigned'] > 0 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}"><svg class="h-4 w-4 fill-none stroke-current" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4m0 4h.01M10.3 3.7 2.6 17a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 3.7a2 2 0 0 0-3.4 0Z"/></svg></span><div><strong class="text-sm text-[#141e46]">{{ number_format($teamSummary['unassigned']) }} {{ Str::plural('student', $teamSummary['unassigned']) }} not yet assigned</strong><p class="mt-0.5 text-[11px] text-slate-500">{{ $teamSummary['unassigned'] > 0 ? 'Review active students and add them to a tribe.' : 'Every active student currently belongs to a tribe.' }}</p></div></div>
            @if($teamSummary['students'] > 0)<a class="text-xs font-extrabold {{ $teamSummary['unassigned'] > 0 ? 'text-amber-800' : 'text-emerald-700' }}" href="{{ route('adviser.users.index', ['role' => 'Student', 'status' => 'active']) }}">View students <span aria-hidden="true">→</span></a>@endif
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 px-5 py-5 sm:px-6">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div><h2 class="text-lg font-extrabold tracking-tight text-[#141e46]">Tribe Directory</h2><p class="mt-1 text-xs text-slate-500">{{ number_format($teams->total()) }} {{ Str::plural('tribe', $teams->total()) }} found</p></div>
                @if($teamSummary['total'] > 0 && request()->anyFilled(['search', 'school_year', 'status']))<a class="mt-2 text-xs font-extrabold text-emerald-700 sm:mt-0" href="{{ route('adviser.teams.index') }}">Clear all filters</a>@endif
            </div>

            @if($teamSummary['total'] > 0)<form class="mt-5 grid gap-2 md:grid-cols-[minmax(260px,1fr)_180px_160px]" method="GET" action="{{ route('adviser.teams.index') }}" data-tribe-filters>
                <label class="relative"><svg class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-slate-400" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><span class="sr-only">Search tribes</span><input class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-3 text-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-500/10" name="search" value="{{ request('search') }}" placeholder="Search tribe or member…"></label>
                <label><span class="sr-only">Filter by school year</span><select class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-600 outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-500/10" name="school_year" data-auto-submit><option value="">All school years</option>@foreach($schoolYears as $schoolYear)<option value="{{ $schoolYear->id }}" @selected((string) request('school_year') === (string) $schoolYear->id)>SY {{ $schoolYear->label }}</option>@endforeach</select></label>
                <label><span class="sr-only">Filter by status</span><select class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-600 outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-500/10" name="status" data-auto-submit><option value="">Any status</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></select></label>
                <button class="sr-only" type="submit">Apply filters</button>
            </form>@endif
        </header>

        <div class="grid gap-4 p-4 sm:p-6 lg:grid-cols-2 2xl:grid-cols-3">
            @forelse($teams as $team)
                <article class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-lg hover:shadow-slate-200/50">
                    <div class="h-1.5" style="background-color: {{ $team->color }}"></div>
                    <div class="p-5">
                        <header class="flex items-start justify-between gap-4">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-sm font-black text-white shadow-sm" style="background-color: {{ $team->color }}">{{ strtoupper(substr($team->name, 0, 2)) }}</span>
                                <div class="min-w-0"><h3 class="truncate text-base font-extrabold text-[#141e46]">{{ $team->name }}</h3><p class="mt-0.5 text-xs text-slate-400">School Year {{ $team->schoolYear->label }}</p></div>
                            </div>
                            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-extrabold {{ $team->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}"><i class="h-1.5 w-1.5 rounded-full {{ $team->is_active ? 'bg-emerald-500' : 'bg-rose-500' }}"></i>{{ $team->is_active ? 'Active' : 'Inactive' }}</span>
                        </header>

                        <div class="mt-5 grid grid-cols-2 divide-x divide-slate-100 rounded-xl bg-slate-50 px-1 py-3">
                            <div class="px-3"><span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Members</span><strong class="mt-1 block text-xl font-black text-[#141e46]">{{ number_format($team->members_count) }}</strong></div>
                            <div class="px-4"><span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total Score</span><strong class="mt-1 block text-xl font-black text-[#141e46]">{{ number_format($team->scores_sum_points ?? 0) }} <small class="text-[10px] font-bold text-slate-400">pts</small></strong></div>
                        </div>

                        <div class="mt-5 min-h-12">
                            @if($team->members->isNotEmpty())
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex pl-1">@foreach($team->members->take(5) as $member)<span class="-ml-1 grid h-9 w-9 place-items-center rounded-full border-2 border-white bg-[#141e46] text-[9px] font-extrabold text-[#fff5e0]" title="{{ $member->full_name }}">{{ strtoupper(substr($member->first_name, 0, 1).substr($member->last_name, 0, 1)) }}</span>@endforeach</div>
                                    <span class="text-xs font-semibold text-slate-400">{{ $team->members_count > 5 ? '+'.($team->members_count - 5).' more' : Str::plural('student', $team->members_count) }}</span>
                                </div>
                            @else
                                <div class="rounded-xl border border-dashed border-slate-200 px-3 py-2.5 text-center text-xs text-slate-400">No students assigned yet</div>
                            @endif
                        </div>

                        <footer class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4">
                            <a class="inline-flex min-h-9 items-center gap-2 rounded-lg px-2 text-xs font-extrabold text-emerald-700 hover:bg-emerald-50" href="{{ route('adviser.teams.edit', $team) }}"><svg class="h-4 w-4 fill-none stroke-current" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg>Edit tribe</a>
                            <form method="POST" action="{{ route('adviser.teams.status', $team) }}" @if($team->is_active) data-confirm-title="Deactivate tribe?" data-confirm-message="{{ $team->name }} will be marked inactive. Its members and scores will be preserved." data-confirm-action="Deactivate" @endif>@csrf @method('PATCH')<button class="min-h-9 rounded-lg px-3 text-xs font-bold {{ $team->is_active ? 'text-rose-600 hover:bg-rose-50' : 'bg-emerald-50 text-emerald-700' }}" type="submit" data-loading-text="Updating…">{{ $team->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                        </footer>
                    </div>
                </article>
            @empty
                <div class="col-span-full px-6 py-10 text-center sm:py-12">
                    <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-emerald-50 text-emerald-700"><svg class="h-7 w-7 fill-none stroke-current" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm8 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM2 20v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2m0-5.5a4 4 0 0 1 3-1.5h1a4 4 0 0 1 4 4v3"/></svg></span>
                    @if(request()->anyFilled(['search', 'school_year', 'status']))
                        <strong class="mt-4 block text-sm text-slate-600">No tribes match these filters</strong><p class="mt-1 text-xs text-slate-400">Try adjusting or clearing the filters.</p>
                    @elseif($teamSummary['students'] === 0)
                        <strong class="mt-4 block text-sm text-slate-600">Students need to be added first</strong><p class="mt-1 text-xs text-slate-400">Add or activate student accounts before organizing them into tribes.</p><a class="mt-5 inline-flex min-h-10 items-center rounded-xl bg-emerald-600 px-4 text-xs font-extrabold text-white" href="{{ route('adviser.users.index', ['role' => 'Student']) }}">Manage Students</a>
                    @else
                        <strong class="mt-4 block text-sm text-slate-600">No tribes created yet</strong><p class="mt-1 text-xs text-slate-400">Create your first tribe and assign students to it.</p><a class="mt-5 inline-flex min-h-10 items-center rounded-xl bg-emerald-600 px-4 text-xs font-extrabold text-white" href="{{ route('adviser.teams.create') }}">Create Tribe</a>
                    @endif
                </div>
            @endforelse
        </div>

        @if($teams->hasPages())
            <nav class="flex flex-col items-center justify-between gap-3 border-t border-slate-100 px-5 py-4 text-xs sm:flex-row"><small class="text-slate-400">Showing {{ $teams->firstItem() }}–{{ $teams->lastItem() }} of {{ $teams->total() }}</small><div class="flex items-center gap-2">@if($teams->onFirstPage())<span class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-slate-300">Previous</span>@else<a class="rounded-lg border border-slate-200 px-3 py-2 font-bold text-slate-600 hover:bg-slate-50" href="{{ $teams->previousPageUrl() }}">Previous</a>@endif<span class="px-2 text-slate-400">{{ $teams->currentPage() }} / {{ $teams->lastPage() }}</span>@if($teams->hasMorePages())<a class="rounded-lg border border-slate-200 px-3 py-2 font-bold text-slate-600 hover:bg-slate-50" href="{{ $teams->nextPageUrl() }}">Next</a>@else<span class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-slate-300">Next</span>@endif</div></nav>
        @endif
    </section>
@endsection

@push('scripts')
<script>
    const tribeFilters = document.querySelector('[data-tribe-filters]');
    tribeFilters?.querySelectorAll('[data-auto-submit]').forEach((filter) => filter.addEventListener('change', () => tribeFilters.requestSubmit()));

    const tribeSearch = tribeFilters?.querySelector('[name="search"]');
    let tribeSearchTimer;
    tribeSearch?.addEventListener('input', () => {
        window.clearTimeout(tribeSearchTimer);
        tribeSearchTimer = window.setTimeout(() => tribeFilters.requestSubmit(), 450);
    });
</script>
@endpush
