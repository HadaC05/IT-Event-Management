@extends('layouts.adviser')

@section('title', 'Reports')

@section('content')
    @php
        $definitions = [
            'attendance' => ['label' => 'Attendance', 'eyebrow' => 'Student records', 'title' => 'Attendance report', 'description' => 'Review every recorded attendance status and check-in across events.'],
            'participation' => ['label' => 'Participation', 'eyebrow' => 'Event coverage', 'title' => 'Event participation', 'description' => 'Compare expected audiences with recorded and attended students for each event.'],
            'scores' => ['label' => 'Scores', 'eyebrow' => 'Competition results', 'title' => 'Score report', 'description' => 'Audit recorded tribe points by event, school year, and scoring criterion.'],
            'rankings' => ['label' => 'Rankings', 'eyebrow' => 'Tribe standings', 'title' => 'Ranking report', 'description' => 'Review current tribe positions, total points, and scoring coverage.'],
        ];
        $current = $definitions[$type];
        $summaryDefinitions = match ($type) {
            'participation' => [
                ['Events', $report['summary']['events'], 'Included in this report'],
                ['Expected', $report['summary']['expected'], 'Student opportunities'],
                ['Attended', $report['summary']['attended'], 'Present or late'],
                ['Participation', $report['summary']['rate'] === null ? '—' : $report['summary']['rate'].'%', 'Across included events'],
            ],
            'scores' => [
                ['Entries', $report['summary']['entries'], 'Recorded score values'],
                ['Tribes', $report['summary']['teams'], 'With a result'],
                ['Points', rtrim(rtrim(number_format($report['summary']['points'], 2), '0'), '.'), 'Total awarded'],
                ['Average', $report['summary']['average'] === null ? '—' : rtrim(rtrim(number_format($report['summary']['average'], 2), '0'), '.'), 'Points per entry'],
            ],
            'rankings' => [
                ['Ranked tribes', $report['summary']['ranked'], 'With recorded scores'],
                ['Eligible tribes', $report['summary']['eligible'], 'Included in this scope'],
                ['Points', rtrim(rtrim(number_format($report['summary']['points'], 2), '0'), '.'), 'Total awarded'],
                ['Leader', $report['summary']['leader'] ?? '—', $report['summary']['leader'] ? 'Current first place' : 'No results yet'],
            ],
            default => [
                ['Records', $report['summary']['records'], 'Included in this report'],
                ['Attended', $report['summary']['attended'], 'Present or late'],
                ['Absent', $report['summary']['absent'], 'Marked absent'],
                ['Attendance rate', $report['summary']['rate'] === null ? '—' : $report['summary']['rate'].'%', 'Across recorded statuses'],
            ],
        };
        $hasFilters = request()->hasAny(['event_id', 'school_year_id', 'category_id', 'status', 'date_from', 'date_to', 'search']);
        $formatPoints = fn ($points) => rtrim(rtrim(number_format((float) $points, 2), '0'), '.');
    @endphp

    <header class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <p class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[.17em] text-[#397565]"><i class="h-2 w-2 rounded-full bg-[#C6F24E] ring-2 ring-[#397565]/15"></i>Insights &amp; exports</p>
            <h1 class="mt-3 text-4xl font-black tracking-[-.055em] text-[#121017] sm:text-5xl">Adviser Reports</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-[#121017]/52">Turn attendance and competition activity into focused, export-ready records.</p>
        </div>
        <a class="inline-flex min-h-12 w-fit items-center justify-center gap-2 rounded-xl bg-[#2F3AE0] px-5 text-sm font-black text-white shadow-[0_10px_25px_rgba(47,58,224,.18)] transition hover:-translate-y-0.5 hover:bg-[#2732c9]" href="{{ route('adviser.reports.export', request()->except('page')) }}">
            <svg class="h-4 w-4 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14"/></svg>Export CSV
        </a>
    </header>

    <nav class="mt-8 grid overflow-hidden rounded-2xl border border-[#121017]/9 bg-white p-1.5 shadow-[0_16px_45px_rgba(18,16,23,.045)] sm:grid-cols-2 xl:grid-cols-4" aria-label="Report type">
        @foreach($definitions as $reportType => $definition)
            <a href="{{ route('adviser.reports.index', ['type' => $reportType]) }}" @class(['rounded-xl px-4 py-3.5 transition', 'bg-[#397565] text-white shadow-sm' => $type === $reportType, 'text-[#121017]/50 hover:bg-[#397565]/7 hover:text-[#397565]' => $type !== $reportType]) aria-current="{{ $type === $reportType ? 'page' : 'false' }}">
                <span class="block text-[9px] font-black uppercase tracking-[.15em] opacity-60">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                <strong class="mt-1 block text-sm font-black">{{ $definition['label'] }}</strong>
            </a>
        @endforeach
    </nav>

    <section class="mt-5 overflow-hidden rounded-2xl border border-[#121017]/9 bg-white shadow-[0_18px_50px_rgba(18,16,23,.05)]" aria-labelledby="report-heading">
        <div class="border-b border-[#121017]/7 px-5 py-5 sm:px-7">
            <p class="text-[9px] font-black uppercase tracking-[.16em] text-[#397565]">{{ $current['eyebrow'] }}</p>
            <div class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div><h2 class="text-2xl font-black tracking-[-.035em]" id="report-heading">{{ $current['title'] }}</h2><p class="mt-1 text-xs leading-5 text-[#121017]/45">{{ $current['description'] }}</p></div>
                <span class="text-[10px] font-bold text-[#121017]/35">Generated {{ now()->format('M j, Y · g:i A') }}</span>
            </div>
        </div>
        <div class="grid sm:grid-cols-2 xl:grid-cols-4">
            @foreach($summaryDefinitions as [$label, $value, $hint])
                <div @class(['px-5 py-5 sm:px-7', 'border-b border-[#121017]/7 sm:border-r' => $loop->index < 2, 'border-b border-[#121017]/7 xl:border-b-0 xl:border-r' => $loop->index === 2, 'sm:border-r-0' => $loop->index === 1])>
                    <span class="text-[9px] font-black uppercase tracking-[.15em] text-[#121017]/35">{{ $label }}</span>
                    <strong class="mt-2 block truncate text-2xl font-black {{ $loop->last ? 'text-[#397565]' : '' }}">{{ is_numeric($value) ? number_format($value) : $value }}</strong>
                    <small class="mt-1 block text-xs text-[#121017]/40">{{ $hint }}</small>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mt-5 rounded-2xl border border-[#121017]/9 bg-[#F3F0E9]/65 p-4 sm:p-5" aria-labelledby="report-filters-heading">
        <div class="mb-4 flex items-center justify-between gap-4">
            <div><p class="text-[9px] font-black uppercase tracking-[.15em] text-[#397565]">Report scope</p><h2 class="mt-1 text-lg font-black" id="report-filters-heading">Filter the records</h2></div>
            @if($hasFilters)<a class="text-xs font-black text-[#2F3AE0] hover:underline" href="{{ route('adviser.reports.index', ['type' => $type]) }}">Clear filters</a>@endif
        </div>
        <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-4" method="GET" action="{{ route('adviser.reports.index') }}" data-report-filters>
            <input type="hidden" name="type" value="{{ $type }}" data-report-type>
            <label class="grid gap-1.5"><span class="text-[9px] font-black uppercase tracking-wider text-[#121017]/42">Event</span><select class="h-12 rounded-xl border border-[#121017]/10 bg-white px-3 text-xs font-bold outline-none focus:border-[#397565] focus:ring-4 focus:ring-[#397565]/10" name="event_id" data-report-event><option value="">All events</option>@foreach($events as $event)<option value="{{ $event->id }}" @selected((string) request('event_id') === (string) $event->id)>{{ $event->title }} · {{ $event->start_at->format('M j, Y') }}</option>@endforeach</select></label>
            <label class="grid gap-1.5"><span class="text-[9px] font-black uppercase tracking-wider text-[#121017]/42">School year</span><select class="h-12 rounded-xl border border-[#121017]/10 bg-white px-3 text-xs font-bold outline-none focus:border-[#397565] focus:ring-4 focus:ring-[#397565]/10" name="school_year_id"><option value="">All school years</option>@foreach($schoolYears as $year)<option value="{{ $year->id }}" @selected((string) request('school_year_id') === (string) $year->id)>{{ $year->label }}</option>@endforeach</select></label>
            <label class="grid gap-1.5" data-category-field @if(!in_array($type, ['scores', 'rankings'])) hidden @endif><span class="text-[9px] font-black uppercase tracking-wider text-[#121017]/42">Criterion</span><select class="h-12 rounded-xl border border-[#121017]/10 bg-white px-3 text-xs font-bold outline-none disabled:bg-[#121017]/5 disabled:text-[#121017]/30 focus:border-[#397565]" name="category_id" data-report-category @disabled(!$selectedEvent)><option value="">All criteria</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></label>
            <label class="grid gap-1.5" data-status-field @if($type !== 'attendance') hidden @endif><span class="text-[9px] font-black uppercase tracking-wider text-[#121017]/42">Attendance status</span><select class="h-12 rounded-xl border border-[#121017]/10 bg-white px-3 text-xs font-bold outline-none focus:border-[#397565]" name="status"><option value="">All statuses</option>@foreach(\App\Models\Attendance::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
            <label class="grid gap-1.5" data-date-field @if($type === 'rankings') hidden @endif><span class="text-[9px] font-black uppercase tracking-wider text-[#121017]/42">From date</span><input class="h-12 rounded-xl border border-[#121017]/10 bg-white px-3 text-xs font-bold outline-none focus:border-[#397565] focus:ring-4 focus:ring-[#397565]/10" type="date" name="date_from" value="{{ request('date_from') }}"></label>
            <label class="grid gap-1.5" data-date-field @if($type === 'rankings') hidden @endif><span class="text-[9px] font-black uppercase tracking-wider text-[#121017]/42">To date</span><input class="h-12 rounded-xl border border-[#121017]/10 bg-white px-3 text-xs font-bold outline-none focus:border-[#397565] focus:ring-4 focus:ring-[#397565]/10" type="date" name="date_to" value="{{ request('date_to') }}"></label>
            <label class="grid gap-1.5 md:col-span-2"><span class="text-[9px] font-black uppercase tracking-wider text-[#121017]/42">Search</span><span class="relative"><svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-[#121017]/35 stroke-2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input class="h-12 w-full rounded-xl border border-[#121017]/10 bg-white pl-10 pr-3 text-xs font-bold outline-none placeholder:text-[#121017]/30 focus:border-[#397565] focus:ring-4 focus:ring-[#397565]/10" type="search" name="search" value="{{ request('search') }}" placeholder="Student, event, tribe, or venue…"></span></label>
            <div class="flex items-end md:col-span-2 xl:col-span-4"><button class="min-h-12 w-full rounded-xl bg-[#397565] px-6 text-xs font-black text-white shadow-sm transition hover:bg-[#2f6255] sm:w-auto" type="submit">Apply filters</button></div>
        </form>
        @error('category_id')<p class="mt-3 text-xs font-bold text-[#FF6B2C]">{{ $message }}</p>@enderror
    </section>

    <section class="mt-5 overflow-hidden rounded-2xl border border-[#121017]/9 bg-white shadow-[0_18px_50px_rgba(18,16,23,.045)]" aria-label="Report results">
        <div class="flex items-center justify-between border-b border-[#121017]/7 px-5 py-4 sm:px-7"><div><p class="text-[9px] font-black uppercase tracking-[.15em] text-[#397565]">Detailed records</p><strong class="mt-1 block text-sm font-black">{{ number_format($report['rows']->total()) }} {{ Str::plural('result', $report['rows']->total()) }}</strong></div><span class="rounded-full bg-[#C6F24E]/30 px-3 py-1.5 text-[9px] font-black uppercase tracking-wider text-[#397565]">Live data</span></div>

        @if($report['rows']->isNotEmpty())
            <div class="overflow-x-auto">
                @if($type === 'attendance')
                    <table class="w-full min-w-[980px] text-left"><thead><tr class="bg-[#121017]/[.025] text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/38"><th class="px-6 py-3.5">Student</th><th class="px-3 py-3.5">Event</th><th class="px-3 py-3.5">Date</th><th class="px-3 py-3.5">Tribe / Year</th><th class="px-3 py-3.5">Status</th><th class="px-6 py-3.5">Check-in</th></tr></thead><tbody class="divide-y divide-[#121017]/7">@foreach($report['rows'] as $record)<tr class="transition hover:bg-[#397565]/[.025]"><td class="px-6 py-4"><strong class="block text-xs">{{ $record->user?->full_name ?? 'Deleted user' }}</strong><small class="mt-0.5 block text-[10px] text-[#121017]/38">{{ $record->user?->id_number ?? 'No student ID' }}</small></td><td class="px-3 py-4 text-xs font-bold">{{ $record->event?->title ?? 'Deleted event' }}</td><td class="px-3 py-4 text-xs text-[#121017]/55">{{ $record->attendance_date?->format('M j, Y') ?? '—' }}</td><td class="px-3 py-4"><span class="block text-xs font-bold">{{ $record->user?->teams?->first()?->name ?? 'No tribe' }}</span><small class="text-[10px] text-[#121017]/38">{{ $record->user?->yearLevel?->label ?? 'No year level' }}</small></td><td class="px-3 py-4"><span @class(['rounded-full px-2.5 py-1 text-[9px] font-black uppercase tracking-wider', 'bg-[#C6F24E]/35 text-[#397565]' => in_array($record->status, ['present', 'late']), 'bg-[#FF6B2C]/10 text-[#FF6B2C]' => $record->status === 'absent', 'bg-[#2F3AE0]/8 text-[#2F3AE0]' => $record->status === 'excused'])>{{ $record->status }}</span></td><td class="px-6 py-4 text-xs text-[#121017]/48">{{ $record->checked_in_at?->format('g:i A') ?? 'Not checked in' }}</td></tr>@endforeach</tbody></table>
                @elseif($type === 'participation')
                    <table class="w-full min-w-[900px] text-left"><thead><tr class="bg-[#121017]/[.025] text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/38"><th class="px-6 py-3.5">Event</th><th class="px-3 py-3.5">Schedule</th><th class="px-3 py-3.5">Expected</th><th class="px-3 py-3.5">Recorded</th><th class="px-3 py-3.5">Attended</th><th class="px-6 py-3.5 text-right">Participation</th></tr></thead><tbody class="divide-y divide-[#121017]/7">@foreach($report['rows'] as $event)<tr class="transition hover:bg-[#397565]/[.025]"><td class="px-6 py-4"><strong class="block text-xs">{{ $event->title }}</strong><small class="mt-0.5 block text-[10px] text-[#121017]/38">{{ $event->location ?: 'Venue not set' }}</small></td><td class="px-3 py-4"><span class="block text-xs font-bold">{{ $event->start_at->format('M j, Y') }}</span><small class="text-[10px] text-[#121017]/38">{{ $event->start_at->format('g:i A') }}</small></td><td class="px-3 py-4 text-xs font-black">{{ number_format($event->expected_count) }}</td><td class="px-3 py-4 text-xs font-black">{{ number_format($event->recorded_count) }}</td><td class="px-3 py-4 text-xs font-black text-[#397565]">{{ number_format($event->attended_count) }}</td><td class="px-6 py-4 text-right"><strong class="text-sm font-black">{{ $event->participation_rate === null ? '—' : $event->participation_rate.'%' }}</strong><div class="ml-auto mt-2 h-1.5 w-24 overflow-hidden rounded-full bg-[#121017]/7"><i class="block h-full rounded-full bg-[#397565]" style="width: {{ $event->participation_rate ?? 0 }}%"></i></div></td></tr>@endforeach</tbody></table>
                @elseif($type === 'scores')
                    <table class="w-full min-w-[980px] text-left"><thead><tr class="bg-[#121017]/[.025] text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/38"><th class="px-6 py-3.5">Event</th><th class="px-3 py-3.5">Tribe</th><th class="px-3 py-3.5">Criterion</th><th class="px-3 py-3.5">Points</th><th class="px-3 py-3.5">Recorded by</th><th class="px-6 py-3.5">Updated</th></tr></thead><tbody class="divide-y divide-[#121017]/7">@foreach($report['rows'] as $score)<tr class="transition hover:bg-[#397565]/[.025]"><td class="px-6 py-4"><strong class="block text-xs">{{ $score->event?->title ?? 'Deleted event' }}</strong><small class="text-[10px] text-[#121017]/38">{{ $score->event?->start_at?->format('M j, Y') }}</small></td><td class="px-3 py-4"><span class="inline-flex items-center gap-2 text-xs font-black"><i class="h-2.5 w-2.5 rounded-full" style="background: {{ $score->team?->color ?? '#397565' }}"></i>{{ $score->team?->name ?? 'Deleted tribe' }}</span><small class="mt-0.5 block text-[10px] text-[#121017]/38">{{ $score->team?->schoolYear?->label }}</small></td><td class="px-3 py-4 text-xs font-bold">{{ $score->category?->name ?? 'Removed criterion' }}</td><td class="px-3 py-4"><strong class="text-base font-black text-[#397565]">{{ $formatPoints($score->points) }}</strong><small class="ml-1 text-[10px] text-[#121017]/35">/ {{ $formatPoints($score->category?->max_points ?? 0) }}</small></td><td class="px-3 py-4 text-xs text-[#121017]/52">{{ $score->recorder?->full_name ?? 'System' }}</td><td class="px-6 py-4 text-xs text-[#121017]/45">{{ $score->updated_at?->format('M j, Y · g:i A') }}</td></tr>@endforeach</tbody></table>
                @else
                    <table class="w-full min-w-[900px] text-left"><thead><tr class="bg-[#121017]/[.025] text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/38"><th class="px-6 py-3.5">Rank</th><th class="px-3 py-3.5">Tribe</th><th class="px-3 py-3.5">School year</th><th class="px-3 py-3.5">Coverage</th><th class="px-6 py-3.5 text-right">Total points</th></tr></thead><tbody class="divide-y divide-[#121017]/7">@foreach($report['rows'] as $team)<tr class="transition hover:bg-[#397565]/[.025]"><td class="px-6 py-4"><span @class(['grid h-9 w-9 place-items-center rounded-full text-xs font-black', 'bg-[#C6F24E] text-[#121017]' => $team->rank === 1, 'bg-[#397565]/10 text-[#397565]' => $team->rank !== 1])>{{ $team->rank ?? '—' }}</span></td><td class="px-3 py-4"><span class="inline-flex items-center gap-2 text-sm font-black"><i class="h-3 w-3 rounded-full" style="background: {{ $team->color }}"></i>{{ $team->name }}</span><small class="mt-0.5 block text-[10px] text-[#121017]/38">{{ number_format($team->members_count) }} {{ Str::plural('member', $team->members_count) }}</small></td><td class="px-3 py-4 text-xs font-bold text-[#121017]/55">{{ $team->schoolYear?->label ?? '—' }}</td><td class="px-3 py-4"><strong class="block text-xs">{{ number_format($team->score_entries_count) }} {{ Str::plural('entry', $team->score_entries_count) }}</strong><small class="text-[10px] text-[#121017]/38">{{ number_format($team->scored_events_count) }} scored {{ Str::plural('event', $team->scored_events_count) }}</small></td><td class="px-6 py-4 text-right">@if($team->has_score)<strong class="text-lg font-black text-[#397565]">{{ $formatPoints($team->total_score) }}</strong><small class="ml-1 text-[10px] text-[#121017]/30">pts</small>@else<span class="rounded-full bg-[#121017]/5 px-3 py-1.5 text-[9px] font-black uppercase tracking-wider text-[#121017]/35">Not scored</span>@endif</td></tr>@endforeach</tbody></table>
                @endif
            </div>
            @if($report['rows']->hasPages())<div class="border-t border-[#121017]/7 px-5 py-4 sm:px-7">{{ $report['rows']->links() }}</div>@endif
        @else
            <div class="px-6 py-16 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-[#397565]/8 text-[#397565]"><svg class="h-6 w-6 fill-none stroke-current stroke-2" viewBox="0 0 24 24"><path d="M5 3h10l4 4v14H5V3Zm10 0v5h4M8 12h8M8 16h6"/></svg></span><h3 class="mt-5 text-lg font-black">No report data found</h3><p class="mx-auto mt-2 max-w-md text-sm leading-6 text-[#121017]/45">Try clearing the filters or record activity in the related management screen first.</p></div>
        @endif
    </section>
@endsection

@push('scripts')
    <script>
        const reportEvent = document.querySelector('[data-report-event]');
        const reportCategory = document.querySelector('[data-report-category]');
        reportEvent?.addEventListener('change', () => { if (reportCategory) reportCategory.value = ''; });
    </script>
@endpush
