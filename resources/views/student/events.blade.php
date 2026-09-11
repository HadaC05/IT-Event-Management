@extends('layouts.student')

@section('title', 'Events')

@section('content')
    <header>
        <p class="text-[10px] font-black uppercase tracking-[.15em] text-[#397565]">CITE calendar</p>
        <h1 class="mt-1 text-3xl font-black tracking-tight">Your events</h1>
        <p class="mt-2 text-sm text-[#121017]/50">
            Only events available to you, your year level, or your tribe are shown.
        </p>
    </header>

    <section class="mt-7" aria-labelledby="current-events-heading">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[.15em] text-[#397565]">Now and next</p>
                <h2 class="mt-1 text-2xl font-black" id="current-events-heading">Current and upcoming events</h2>
            </div>
            <span class="text-xs font-bold text-[#121017]/40">{{ $events->count() }} events</span>
        </div>

        <div class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($events as $event)
                <article class="overflow-hidden rounded-3xl border border-[#121017]/8 bg-white">
                    @if ($event->poster_path)
                        <img class="h-44 w-full object-cover" src="{{ asset('storage/'.$event->poster_path) }}" alt="{{ $event->title }} poster">
                    @else
                        <div class="h-24 bg-gradient-to-r from-[#397565] to-[#2F3AE0]/70"></div>
                    @endif
                    <div class="p-5">
                        <span class="rounded-full bg-[#C6F24E]/30 px-2.5 py-1 text-[9px] font-black uppercase text-[#397565]">
                            {{ $event->schedule_state }}
                        </span>
                        <h3 class="mt-3 text-xl font-black">{{ $event->title }}</h3>
                        <p class="mt-2 text-xs text-[#121017]/45">
                            {{ $event->start_at->format('M j, Y · g:i A') }}<br>
                            {{ $event->location ?: 'CITE Campus' }}
                        </p>
                        <p class="mt-4 line-clamp-3 text-sm leading-6 text-[#121017]/65">
                            {{ $event->description ?: 'More details will be announced soon.' }}
                        </p>
                    </div>
                </article>
            @empty
                <div class="col-span-full rounded-3xl border border-dashed border-[#397565]/25 bg-white py-16 text-center">
                    <h3 class="font-black">No current or upcoming events</h3>
                    <p class="mt-1 text-sm text-[#121017]/45">Assigned events will show up here.</p>
                </div>
            @endforelse
        </div>
    </section>

    <section class="mt-12" aria-labelledby="past-events-heading">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[.15em] text-[#397565]">Event archive</p>
                <h2 class="mt-1 text-2xl font-black" id="past-events-heading">Past Events</h2>
            </div>
            <span class="text-xs font-bold text-[#121017]/40">{{ $pastEvents->count() }} events</span>
        </div>

        <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($pastEvents as $event)
                <article class="flex gap-4 rounded-2xl border border-[#121017]/8 bg-white p-4">
                    @if ($event->poster_path)
                        <img class="h-20 w-20 shrink-0 rounded-xl object-cover" src="{{ asset('storage/'.$event->poster_path) }}" alt="">
                    @else
                        <span class="grid h-20 w-20 shrink-0 place-items-center rounded-xl bg-[#121017]/7 text-[#397565]">
                            <svg class="h-7 w-7 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3v3m12-3v3M4 9h16M5 5h14a2 2 0 0 1 2 2v13H3V7a2 2 0 0 1 2-2Z"/></svg>
                        </span>
                    @endif
                    <div class="min-w-0">
                        <span class="text-[9px] font-black uppercase tracking-wider text-[#121017]/40">Completed</span>
                        <h3 class="mt-1 truncate font-black">{{ $event->title }}</h3>
                        <p class="mt-1 text-xs leading-5 text-[#121017]/50">
                            {{ $event->start_at->format('M j, Y') }} · {{ $event->location ?: 'CITE Campus' }}
                        </p>
                        <p class="mt-2 text-[10px] font-bold text-[#397565]">Event posts remain available in the community feed.</p>
                    </div>
                </article>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-[#121017]/10 bg-white/60 px-5 py-10 text-center">
                    <p class="text-sm font-bold text-[#121017]/45">Completed events will appear here.</p>
                </div>
            @endforelse
        </div>
    </section>
@endsection
