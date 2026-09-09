<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Student Dashboard | CITE Events</title>
    @vite(['resources/css/app.css', 'resources/js/student-dashboard.js'])
    <script src="{{ asset('js/notifications.js') }}" defer></script>
    <script src="{{ asset('js/interactions.js') }}" defer></script>
</head>
<body class="app-shell min-h-screen bg-[#F3F0E9]/45 font-sans text-[#121017] antialiased">
    <span class="route-progress" data-route-progress data-active="false" aria-hidden="true"></span>
    <header class="border-b border-[#121017]/8 bg-white">
        <nav class="mx-auto flex h-[72px] max-w-6xl items-center gap-3 px-4 sm:px-6" aria-label="Student navigation">
            <a class="flex items-center gap-2 text-lg font-black" href="{{ route('dashboard') }}"><img class="h-10 w-10 rounded-full object-contain" src="{{ asset('images/cite-logo.png') }}" alt="CITE logo">CITE<span class="text-[#397565]">.</span></a>
            <span class="ml-auto hidden text-right sm:grid"><strong class="text-xs font-black">{{ $user->full_name }}</strong><small class="text-[9px] font-bold uppercase tracking-wider text-[#397565]">Student · {{ $user->teams->pluck('name')->join(', ') ?: 'No team' }}</small></span>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="min-h-10 rounded-xl border border-[#121017]/10 px-4 text-xs font-black text-[#FF6B2C]" type="submit">Sign out</button></form>
        </nav>
    </header>

    <main class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 sm:py-12" data-page-content>
        <header class="mb-8">
            <p class="text-[10px] font-black uppercase tracking-[.17em] text-[#397565]">Student attendance pass</p>
            <h1 class="mt-2 text-4xl font-black tracking-[-.045em]">Welcome, {{ $user->first_name }}.</h1>
            <p class="mt-2 text-sm text-[#121017]/50">Open the correct session QR and present it to your team’s SBO Officer.</p>
        </header>

        <section class="mb-6 grid gap-3 sm:grid-cols-3" aria-label="Student profile">
            <div class="rounded-2xl border border-[#121017]/8 bg-white p-5"><span class="text-[9px] font-black uppercase tracking-wider text-[#121017]/35">Student number</span><strong class="mt-2 block font-mono text-sm">{{ $user->display_id_number }}</strong></div>
            <div class="rounded-2xl border border-[#121017]/8 bg-white p-5"><span class="text-[9px] font-black uppercase tracking-wider text-[#121017]/35">Team</span><strong class="mt-2 block text-sm">{{ $user->teams->pluck('name')->join(', ') ?: 'Not assigned' }}</strong></div>
            <div class="rounded-2xl border border-[#121017]/8 bg-white p-5"><span class="text-[9px] font-black uppercase tracking-wider text-[#121017]/35">Year level</span><strong class="mt-2 block text-sm">{{ $user->display_year_level?->label ?? 'Not assigned' }}</strong></div>
        </section>

        <section>
            <div class="mb-4"><h2 class="text-xl font-black">My events</h2><p class="mt-1 text-xs text-[#121017]/45">QR passes are unique to your account and selected event.</p></div>
            <div class="grid gap-5 lg:grid-cols-2">
                @forelse($events as $event)
                    <article class="overflow-hidden rounded-3xl border border-[#121017]/9 bg-white shadow-[0_14px_40px_rgba(18,16,23,.05)]">
                        <header class="border-b border-[#121017]/7 p-6"><div class="flex items-start justify-between gap-4"><div><p class="text-[9px] font-black uppercase tracking-[.15em] text-[#397565]">{{ $event->start_at->isSameDay($event->end_at) ? $event->start_at->format('M j, Y') : $event->start_at->format('M j').'–'.$event->end_at->format('M j, Y') }}</p><h3 class="mt-2 text-2xl font-black">{{ $event->title }}</h3><p class="mt-1 text-xs text-[#121017]/45">{{ $event->location ?: 'CITE Campus' }}</p></div><span class="rounded-full bg-[#C6F24E]/35 px-3 py-1.5 text-[9px] font-black uppercase text-[#397565]">{{ $event->schedule_state }}</span></div></header>
                        <div class="grid gap-4 p-6">
                            @php($eventDays = $event->attendanceSchedules)
                            @foreach($eventDays as $day)
                                <section class="rounded-2xl border border-[#121017]/8 bg-[#F3F0E9]/40 p-4"><strong class="text-xs font-black text-[#397565]">Day {{ $loop->iteration }} · {{ $day?->schedule_date?->format('D, M j') ?? $event->start_at->format('D, M j') }}</strong><div class="mt-3 grid gap-3 sm:grid-cols-2">
                                @php($sessions = $day->attendanceSessionMode?->code === 'whole_day' ? [['Whole day', $day->whole_day_in_time ? \Carbon\Carbon::parse($day->whole_day_in_time) : null, $day->whole_day_out_time ? \Carbon\Carbon::parse($day->whole_day_out_time) : null, $event->morning_qr_payload]] : [
                                    ['Morning', $day->morning_in_time ? \Carbon\Carbon::parse($day->morning_in_time) : null, $day->morning_out_time ? \Carbon\Carbon::parse($day->morning_out_time) : null, $event->morning_qr_payload],
                                    ['Afternoon', $day->afternoon_in_time ? \Carbon\Carbon::parse($day->afternoon_in_time) : null, $day->afternoon_out_time ? \Carbon\Carbon::parse($day->afternoon_out_time) : null, $event->afternoon_qr_payload],
                                ])
                                @foreach($sessions as [$session, $startsAt, $endsAt, $payload])
                                    <div class="rounded-xl border border-[#121017]/7 bg-white p-3"><span class="text-[9px] font-black uppercase tracking-wider text-[#121017]/35">{{ $session }} session</span><strong class="mt-1 block text-sm">{{ $startsAt?->format('g:i A') ?? 'Not set' }}–{{ $endsAt?->format('g:i A') ?? 'Not set' }}</strong><button class="mt-3 inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl bg-[#397565] px-3 text-[10px] font-black text-white disabled:bg-[#121017]/15" type="button" data-open-qr data-event-title="{{ $event->title }}" data-session="{{ $session }}" data-schedule="{{ $day?->schedule_date?->format('M j') }} · {{ $startsAt?->format('g:i A') }}–{{ $endsAt?->format('g:i A') }}" data-payload="{{ $payload }}" @disabled(!$startsAt || !$endsAt)>Open {{ $session }} QR</button></div>
                                @endforeach
                                </div></section>
                            @endforeach
                        </div>
                    </article>
                @empty
                    <div class="col-span-full rounded-3xl border border-dashed border-[#121017]/15 bg-white px-6 py-14 text-center"><strong class="text-base">No available events</strong><p class="mt-2 text-sm text-[#121017]/45">Events assigned to your team will appear here.</p></div>
                @endforelse
            </div>
        </section>
    </main>

    <dialog class="m-auto w-[min(420px,calc(100%_-_2rem))] rounded-3xl border-0 bg-white p-0 shadow-2xl backdrop:bg-[#121017]/70 backdrop:backdrop-blur-sm" data-qr-dialog>
        <header class="flex items-start justify-between gap-4 border-b border-[#121017]/8 p-5"><div><p class="text-[9px] font-black uppercase tracking-[.15em] text-[#397565]">Attendance pass</p><h2 class="mt-1 text-lg font-black" data-qr-title>Session QR</h2><p class="mt-1 text-xs text-[#121017]/45" data-qr-schedule></p></div><button class="grid h-10 w-10 place-items-center rounded-xl bg-[#F3F0E9] text-xl" type="button" data-qr-close aria-label="Close QR">&times;</button></header>
        <div class="grid place-items-center p-6"><div class="rounded-2xl border border-[#121017]/8 bg-white p-3"><canvas class="block h-72 w-72 max-w-full" data-qr-canvas></canvas></div><p class="mt-4 text-center text-xs leading-5 text-[#121017]/45">Show this code to your assigned SBO Officer. Use the QR matching the current session.</p><p class="mt-2 text-center text-xs font-bold text-[#FF6B2C]" data-qr-error></p></div>
    </dialog>
    <x-notifications />
</body>
</html>
