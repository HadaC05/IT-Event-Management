@extends('layouts.student')

@section('title', 'Attendance')

@section('content')
<header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="text-[10px] font-black uppercase tracking-[.15em] text-[#397565]">
            Student attendance
        </p>
        <h1 class="mt-1 text-3xl font-black tracking-tight">
            Attendance overview
        </h1>
        <p class="mt-2 text-sm text-[#121017]/50">
            Review your attendance record and present your secure event pass.
        </p>
    </div>

    @if($summary['total'] > 0)
        @php
            $attended = $summary['present'] + $summary['late'];
            $attendanceRate = round(($attended / $summary['total']) * 100);
        @endphp
        <div class="w-fit rounded-2xl bg-[#397565] px-5 py-3 text-white shadow-lg shadow-[#397565]/15">
            <span class="block text-[9px] font-black uppercase tracking-wider text-white/65">
                Attendance rate
            </span>
            <strong class="mt-1 block text-2xl">{{ $attendanceRate }}%</strong>
        </div>
    @endif
</header>


<div class="mt-6 grid items-start gap-6 xl:grid-cols-[430px_minmax(0,1fr)]">
    <section class="overflow-hidden rounded-3xl border border-[#121017]/8 bg-white shadow-[0_20px_55px_rgba(18,16,23,.07)] xl:sticky xl:top-24">
        <header class="bg-[#397565] p-5 text-white">
            <div class="flex items-center gap-4">
                <x-student-avatar :user="$user" size="h-16 w-16"/>
                <div class="min-w-0">
                    <p class="text-[9px] font-black uppercase tracking-wider text-white/60">
                        Secure attendance pass
                    </p>
                    <h2 class="mt-1 truncate text-xl font-black">{{ $user->full_name }}</h2>
                    <p class="mt-1 font-mono text-xs text-white/70">{{ $user->id_number }}</p>
                    <p class="mt-1 text-xs font-bold text-[#C6F24E]">
                        {{ $user->teams->pluck('name')->join(', ') ?: 'No tribe assigned' }}
                    </p>
                </div>
            </div>
        </header>

        @if($event)
            <div class="p-5 sm:p-6">
                <div class="text-center">
                    <span class="text-[9px] font-black uppercase tracking-wider text-[#397565]">
                        Current event
                    </span>
                    <h3 class="mt-1 text-lg font-black">{{ $event->title }}</h3>
                    <p class="mt-1 text-xs text-[#121017]/45">
                        {{ $event->start_at->format('M j, Y') }} · {{ $event->location ?: 'CITE Campus' }}
                    </p>
                    <span class="mt-3 inline-flex rounded-full px-3 py-1 text-[9px] font-black uppercase {{ $currentAttendance ? 'bg-[#397565]/10 text-[#397565]' : 'bg-[#C6F24E]/30 text-[#397565]' }}">
                        {{ $currentAttendance ? str($currentAttendance->status)->title() : 'QR available' }}
                    </span>
                </div>

                <div class="mt-5 flex justify-center gap-2" role="tablist" aria-label="Attendance session">
                    @foreach($passes as $session => $payload)
                        <button
                            class="min-h-11 rounded-xl px-5 text-xs font-black data-[active=true]:bg-[#397565] data-[active=true]:text-white data-[active=false]:bg-[#F7F4ED]"
                            type="button"
                            role="tab"
                            data-qr-tab
                            data-payload="{{ $payload }}"
                            data-active="{{ $loop->first ? 'true' : 'false' }}"
                            aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                        >
                            {{ str($session)->title() }}
                        </button>
                    @endforeach
                </div>

                <div class="mx-auto mt-5 w-fit rounded-3xl border border-[#121017]/10 bg-white p-3">
                    <canvas class="block h-64 w-64 max-w-full" data-qr-canvas></canvas>
                </div>

                <div class="mt-5 rounded-2xl bg-[#C6F24E]/18 p-4 text-center">
                    <strong class="text-xs text-[#397565]">Present this code to your SBO Officer</strong>
                    <p class="mt-1 text-xs leading-5 text-[#121017]/55">
                        Select the correct session and increase your screen brightness. This code belongs only to your account and must not be shared.
                    </p>
                </div>

                <p class="mt-3 text-center text-xs font-bold text-[#FF6B2C]" data-qr-error></p>
            </div>
        @else
            <div class="px-6 py-14 text-center">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-[#397565]/10 text-[#397565]">
                    <svg class="h-6 w-6 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M5 4h14v16H5V4Zm4 4h6m-6 4h6"/>
                    </svg>
                </span>
                <h3 class="mt-4 font-black">Attendance pass unavailable</h3>
                <p class="mt-2 text-sm leading-6 text-[#121017]/45">
                    Your secure QR code will appear when you have a current or upcoming assigned event.
                </p>
            </div>
        @endif
    </section>

    <section class="min-w-0 overflow-hidden rounded-3xl border border-[#121017]/8 bg-white">
        <header class="flex items-center justify-between gap-4 border-b border-[#121017]/8 p-5 sm:p-6">
            <div>
                <h2 class="text-lg font-black">Attendance history</h2>
                <p class="mt-1 text-xs text-[#121017]/45">Your recorded event attendance and session times.</p>
            </div>
            <span class="rounded-full bg-[#F7F4ED] px-3 py-1.5 text-[10px] font-black">
                {{ $attendances->total() }} records
            </span>
        </header>

        <div class="divide-y divide-[#121017]/7">
            @forelse($attendances as $attendance)
                @php
                    $statusClasses = match ($attendance->status) {
                        'present' => 'bg-[#397565]/10 text-[#397565]',
                        'late' => 'bg-[#D88722]/12 text-[#A15D0B]',
                        'absent' => 'bg-[#FF6B2C]/12 text-[#C84510]',
                        default => 'bg-[#2F3AE0]/10 text-[#2F3AE0]',
                    };
                @endphp
                <article class="p-5 sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-black">{{ $attendance->event?->title ?? 'Event' }}</h3>
                            <p class="mt-1 text-xs text-[#121017]/45">
                                {{ $attendance->attendance_date?->format('M j, Y') ?? $attendance->event?->start_at?->format('M j, Y') ?? 'Date unavailable' }}
                            </p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-[9px] font-black uppercase {{ $statusClasses }}">
                            {{ $attendance->status }}
                        </span>
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach([
                            ['Morning in', $attendance->morning_in_at],
                            ['Morning out', $attendance->morning_out_at],
                            ['Afternoon in', $attendance->afternoon_in_at],
                            ['Afternoon out', $attendance->afternoon_out_at],
                        ] as [$label, $time])
                            <div class="rounded-xl bg-[#F7F4ED] p-3">
                                <dt class="text-[9px] font-black uppercase text-[#121017]/35">{{ $label }}</dt>
                                <dd class="mt-1 text-xs font-black">{{ $time?->format('g:i A') ?? '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    @if($attendance->notes)
                        <p class="mt-3 text-xs leading-5 text-[#121017]/50">
                            <strong>Note:</strong> {{ $attendance->notes }}
                        </p>
                    @endif
                </article>
            @empty
                <div class="px-6 py-16 text-center">
                    <h3 class="font-black">No attendance records yet</h3>
                    <p class="mt-2 text-sm text-[#121017]/45">
                        Your completed event attendance will appear here.
                    </p>
                </div>
            @endforelse
        </div>

        @if($attendances->hasPages())
            <div class="border-t border-[#121017]/8 p-5">
                {{ $attendances->links() }}
            </div>
        @endif
    </section>
</div>
@endsection
