@extends('layouts.officer')

@section('title', 'Mark Attendance')

@section('content')
    <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[.17em] text-[#397565]">{{ $officer->officerTeam?->name ?? 'Tribe not assigned' }}</p>
            <h1 class="mt-2 text-4xl font-black tracking-[-.055em] sm:text-5xl">Mark attendance.</h1>
            @if($event)<p class="mt-3 text-sm text-[#121017]/55"><strong class="text-[#121017]/75">{{ $event->title }}</strong> · {{ $event->location ?: 'CITE Campus' }} · {{ $event->start_at->format('M j, g:i A') }}</p>@else<p class="mt-3 text-sm text-[#121017]/55">Choose an available event to begin recording attendance.</p>@endif
        </div>
        @if($events->isNotEmpty())
            <label class="grid gap-1.5 lg:w-80"><span class="text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/35">Attendance event</span><span class="relative"><select class="h-12 w-full appearance-none rounded-xl border border-[#121017]/10 bg-white px-4 pr-10 text-xs font-black outline-none focus:border-[#397565] focus:ring-4 focus:ring-[#397565]/10" data-event-switch>@foreach($events as $option)<option value="{{ route('officer.attendance.index', $option) }}" @selected($event?->id === $option->id)>{{ $option->title }} · {{ $option->start_at->format('M j') }}</option>@endforeach</select><svg class="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-[#397565] stroke-2" viewBox="0 0 24 24"><path d="m7 10 5 5 5-5"/></svg></span></label>
        @endif
    </header>

    @if ($event)
        @can('feature', $event)
            <div class="mt-8">
                <x-event-feature-controls :event="$event" :action="route('officer.events.feature', $event)" />
            </div>
        @endcan
    @endif

    @if(!$officer->officerTeam)
        <section class="mt-8 rounded-2xl border border-[#FF6B2C]/20 bg-[#FF6B2C]/7 px-6 py-12 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-[#FF6B2C]/12 text-2xl text-[#FF6B2C]">!</span><h2 class="mt-5 text-xl font-black">No tribe assigned</h2><p class="mx-auto mt-2 max-w-md text-sm leading-6 text-[#121017]/52">Ask the SBO Adviser to assign your officer account to a tribe before recording attendance.</p></section>
    @elseif(!$event)
        <section class="mt-8 rounded-2xl border border-[#121017]/10 bg-white px-6 py-12 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-[#C6F24E] text-xl">✦</span><h2 class="mt-5 text-xl font-black">No available events</h2><p class="mt-2 text-sm text-[#121017]/50">Events containing students from {{ $officer->officerTeam->name }} will appear here.</p></section>
    @else
        @php
            $nextSlot = $activeSlot ? collect($slots)->first(fn ($slot) => $slot['at']->gt($activeSlot['at'])) : null;
            $windowClose = $nextSlot['at'] ?? $event->end_at;
        @endphp
        <section class="mt-8 grid gap-5 xl:grid-cols-[minmax(300px,.72fr)_minmax(0,1.28fr)]">
            <div class="grid content-start gap-5">
                <section class="rounded-2xl border border-[#121017]/10 bg-white p-5 shadow-[0_14px_40px_rgba(18,16,23,.05)]">
                    <div class="flex items-start justify-between gap-4"><div><p class="text-[9px] font-black uppercase tracking-[.15em] text-[#397565]">Current checkpoint</p><h2 class="mt-2 text-2xl font-black tracking-[-.035em]">{{ $activeSlot['label'] ?? 'Scanning closed' }}</h2></div><span class="mt-1 h-3 w-3 rounded-full {{ $activeSlot ? 'bg-[#C6F24E] shadow-[0_0_0_6px_rgba(198,242,78,.2)]' : 'bg-[#121017]/15' }}"></span></div>
                    @if($activeSlot)<p class="mt-2 text-xs text-[#121017]/48">Open since {{ $activeSlot['at']->format('g:i A') }} · closes {{ $windowClose->format('g:i A') }}</p>@else<p class="mt-2 text-xs leading-5 text-[#121017]/48">Scanning becomes available when a scheduled checkpoint begins.</p>@endif
                    <button class="mt-5 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#397565] px-5 text-sm font-black text-white shadow-[0_8px_20px_rgba(57,117,101,.2)] transition hover:bg-[#2e6355] disabled:cursor-not-allowed disabled:bg-[#121017]/15 disabled:text-[#121017]/35" type="button" data-scanner-open @disabled(!$activeSlot)><svg class="h-5 w-5 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7V4h3m10 0h3v3M4 17v3h3m10 0h3v-3M7 12h10"/></svg>Open QR scanner</button>
                    <div class="my-4 flex items-center gap-3"><i class="h-px flex-1 bg-[#121017]/8"></i><span class="text-[9px] font-black uppercase tracking-wider text-[#121017]/30">or enter manually</span><i class="h-px flex-1 bg-[#121017]/8"></i></div>
                    <form method="POST" action="{{ route('officer.attendance.scan', $event) }}" data-attendance-scan-form>@csrf<input name="qr_content" type="hidden" data-qr-content><label class="grid gap-2"><span class="text-xs font-black">Student number</span><input class="h-12 w-full rounded-xl border border-[#121017]/10 bg-[#F3F0E9]/45 px-4 font-mono text-sm font-bold uppercase outline-none placeholder:font-sans placeholder:font-normal placeholder:normal-case placeholder:text-[#121017]/30 focus:border-[#397565] focus:bg-white focus:ring-4 focus:ring-[#397565]/10 disabled:cursor-not-allowed disabled:opacity-50" name="id_number" value="{{ old('id_number') }}" placeholder="02-xxxx-xxxxxx" autocomplete="off" @disabled(!$activeSlot)><x-form-error name="id_number" /><button class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-[#397565]/20 text-xs font-black text-[#397565] hover:bg-[#397565]/5 disabled:opacity-40" type="submit" @disabled(!$activeSlot)>Record manually</button></label></form>
                </section>

                <section class="rounded-2xl border border-[#121017]/10 bg-white p-5">
                    <p class="text-[9px] font-black uppercase tracking-[.15em] text-[#397565]">{{ $attendanceDate?->isToday() ? 'Today’s' : $attendanceDate?->format('M j') }} scan schedule</p>
                    <ol class="mt-4 grid grid-cols-2 gap-3">
                        @foreach($attendanceSchedule?->attendanceSessionMode?->code === 'whole_day' ? [['morning_in', 'Time in'], ['morning_out', 'Time out']] : [['morning_in', 'Morning in'], ['morning_out', 'Morning out'], ['afternoon_in', 'Afternoon in'], ['afternoon_out', 'Afternoon out']] as [$key, $label])
                            @php($slot = collect($slots)->firstWhere('key', $key))
                            <li class="rounded-xl border px-3 py-3 {{ $activeSlot && $activeSlot['key'] === $key ? 'border-[#397565]/25 bg-[#C6F24E]/18' : 'border-[#121017]/8 bg-[#F3F0E9]/35' }}"><span class="block text-[9px] font-black uppercase tracking-wider text-[#121017]/38">{{ $label }}</span><strong class="mt-1 block text-sm">{{ $slot ? $slot['at']->format('g:i A') : 'Not set' }}</strong></li>
                        @endforeach
                    </ol>
                </section>
            </div>

            <section class="overflow-hidden rounded-2xl border border-[#121017]/10 bg-white shadow-[0_14px_40px_rgba(18,16,23,.05)]">
                <header class="border-b border-[#121017]/8 p-5 sm:p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-lg font-black">Roster · {{ method_exists($participants, 'total') ? $participants->total() : $participants->count() }} students</h2><p class="mt-1 text-xs text-[#121017]/45">Only student names and student numbers from {{ $officer->officerTeam->name }} are shown.</p></div><div class="flex gap-2">@foreach(['all' => 'All', 'unmarked' => 'Unmarked', 'complete' => 'Complete'] as $key => $label)<a class="rounded-full px-3.5 py-2 text-[10px] font-black {{ request('status', 'all') === $key ? 'bg-[#397565] text-white' : 'border border-[#121017]/8 text-[#121017]/50' }}" href="{{ route('officer.attendance.index', array_filter(['event' => $event, 'status' => $key, 'search' => request('search')])) }}">{{ $label }}</a>@endforeach</div></div>
                    <form class="relative mt-4" method="GET" action="{{ route('officer.attendance.index', $event) }}"><input type="hidden" name="status" value="{{ request('status', 'all') }}"><svg class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-[#121017]/30 stroke-2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input class="h-11 w-full rounded-xl border border-[#121017]/9 bg-[#F3F0E9]/40 pl-10 pr-4 text-sm outline-none placeholder:text-[#121017]/30 focus:border-[#397565] focus:bg-white focus:ring-4 focus:ring-[#397565]/8" name="search" value="{{ request('search') }}" placeholder="Find a student name or number…"></form>
                </header>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] border-collapse">
                        <thead class="bg-[#F3F0E9]/45 text-left text-[9px] font-black uppercase tracking-[.12em] text-[#121017]/38"><tr><th class="px-5 py-3.5 sm:px-6">Student</th><th class="px-3 py-3.5">Morning in</th><th class="px-3 py-3.5">Morning out</th><th class="px-3 py-3.5">Afternoon in</th><th class="px-5 py-3.5 sm:px-6">Afternoon out</th></tr></thead>
                        <tbody class="divide-y divide-[#121017]/7">
                            @forelse($participants as $student)
                                @php($record = $records->get($student->id))
                                <tr class="transition hover:bg-[#397565]/[.025]"><td class="px-5 py-4 sm:px-6"><div class="flex items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#121017] text-[9px] font-black text-white">{{ strtoupper(substr($student->first_name, 0, 1).substr($student->last_name, 0, 1)) }}</span><span class="grid"><strong class="text-xs font-black">{{ $student->full_name }}</strong><small class="mt-0.5 font-mono text-[10px] font-bold text-[#397565]">{{ $student->id_number ?: 'No student number' }}</small></span></div></td>@foreach(['morning_in_at', 'morning_out_at', 'afternoon_in_at', 'afternoon_out_at'] as $column)<td class="px-3 py-4 last:px-5 last:sm:px-6">@if($record?->{$column})<span class="inline-flex items-center gap-1.5 rounded-full bg-[#C6F24E]/35 px-2.5 py-1 text-[10px] font-black text-[#397565]"><i class="h-1.5 w-1.5 rounded-full bg-[#397565]"></i>{{ $record->{$column}->format('g:i A') }}</span>@else<span class="text-xs font-bold text-[#121017]/22">—</span>@endif</td>@endforeach</tr>
                            @empty
                                <tr><td class="px-6 py-16 text-center" colspan="5"><strong class="text-sm">No students found</strong><p class="mt-1 text-xs text-[#121017]/42">Try another filter or student number.</p></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if(method_exists($participants, 'hasPages') && $participants->hasPages())<div class="border-t border-[#121017]/8 px-5 py-4">{{ $participants->links() }}</div>@endif
            </section>
        </section>
        <dialog class="m-auto w-[min(520px,calc(100%_-_2rem))] overflow-hidden rounded-3xl border-0 bg-[#121017] p-0 text-white shadow-2xl backdrop:bg-[#121017]/70 backdrop:backdrop-blur-sm" data-scanner-dialog>
            <header class="flex items-start justify-between gap-4 border-b border-white/10 p-5"><div><p class="text-[9px] font-black uppercase tracking-[.16em] text-[#C6F24E]">QR camera</p><h2 class="mt-1 text-xl font-black">Scan student QR</h2></div><button class="grid h-10 w-10 place-items-center rounded-xl bg-white/10 text-xl" type="button" data-scanner-close aria-label="Close scanner">&times;</button></header>
            <div class="relative aspect-square overflow-hidden bg-black sm:aspect-[4/3]"><video class="h-full w-full object-cover" playsinline muted data-scanner-preview></video><div class="pointer-events-none absolute inset-[12%] rounded-3xl border-2 border-[#C6F24E] shadow-[0_0_0_999px_rgba(0,0,0,.28)]"></div></div>
            <div class="p-5">
                <p class="text-sm font-bold" data-scanner-status>Starting camera…</p>
                <p class="mt-2 text-xs leading-5 text-white/50">Keep the entire student QR, including its white border, inside the frame.</p>
            </div>
        </dialog>
    @endif
@endsection

@push('scripts')
<script>document.querySelector('[data-event-switch]')?.addEventListener('change', event => window.location.assign(event.target.value));</script>
@if($event && $officer->officerTeam)
    @vite('resources/js/officer-attendance.js')
@endif
@endpush
