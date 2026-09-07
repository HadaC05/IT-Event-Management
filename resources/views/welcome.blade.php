<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Discover competitions, activities, and memorable moments created for the CITE community.">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>CITE | Campus Events</title>
    @vite('resources/css/app.css')
    <style>
        :root { --bone:#F3F0E9; --ink:#121017; --teal:#397565; --cobalt:#2F3AE0; --lime:#C6F24E; --tangerine:#FF6B2C; }
        body { position:relative; isolation:isolate; overflow-x:hidden; background:#fff; color:var(--ink); }
        body::before {
            content:"";
            position:fixed;
            inset:-90px;
            z-index:-1;
            pointer-events:none;
            background-image:
                radial-gradient(circle at 12% 18%,rgba(57,117,101,.045),transparent 25%),
                radial-gradient(circle at 88% 68%,rgba(47,58,224,.025),transparent 22%),
                linear-gradient(rgba(57,117,101,.03) 1px,transparent 1px),
                linear-gradient(90deg,rgba(57,117,101,.03) 1px,transparent 1px);
            background-size:auto,auto,88px 88px,88px 88px;
            opacity:.75;
        }
        .hero-grain { background:transparent; }
        .poster-glow {
            background-color:#1f5548;
            background-image:
                linear-gradient(rgba(255,255,255,.07) 1px,transparent 1px),
                linear-gradient(90deg,rgba(255,255,255,.07) 1px,transparent 1px),
                radial-gradient(circle at 82% 18%,rgba(198,242,78,.38),transparent 28%),
                radial-gradient(circle at 8% 88%,rgba(47,58,224,.28),transparent 32%),
                linear-gradient(135deg,#43816e 0%,#1f5548 100%);
            background-size:42px 42px,42px 42px,auto,auto,auto;
        }
        .page-atmosphere { position:fixed; inset:0; z-index:-1; overflow:hidden; pointer-events:none; }
        .page-object { position:absolute; display:block; will-change:transform; }
        .page-ring { top:10%; left:-105px; width:290px; height:290px; border:46px solid rgba(198,242,78,.16); border-radius:999px; }
        .page-tile { top:48%; right:-72px; width:210px; height:210px; border:1px solid rgba(57,117,101,.08); border-radius:48px; background:rgba(57,117,101,.075); transform:rotate(17deg); }
        .page-orb { top:32%; right:13%; width:18px; height:18px; border-radius:999px; background:rgba(255,107,44,.2); }
        .page-capsule { bottom:8%; left:8%; width:150px; height:46px; border-radius:999px; border:1px solid rgba(47,58,224,.07); background:rgba(47,58,224,.04); transform:rotate(-12deg); }
        .event-row[hidden], .agenda-item[hidden] { display:none; }
        .event-filter[aria-pressed="true"] { background:var(--teal); border-color:var(--teal); color:white; box-shadow:0 5px 14px rgba(57,117,101,.2); }
        .login-dialog { animation:modal-in .2s ease-out; }
        .login-dialog::backdrop { background:rgba(18,16,23,.66); backdrop-filter:blur(5px); }
        .login-alert-progress { animation:alert-countdown 6.5s linear forwards; transform-origin:left; }
        .auth-result { animation:auth-result-in .24s ease-out; }
        .auth-result::backdrop { background:rgba(18,16,23,.7); backdrop-filter:blur(6px); }
        .auth-result[data-type="success"] .auth-result-icon { background:#C6F24E; color:#121017; }
        .auth-result[data-type="success"] .auth-result-button { background:#397565; color:white; }
        .auth-result[data-type="error"] .auth-result-icon { background:rgba(255,107,44,.14); color:#D64A12; }
        .auth-result[data-type="error"] .auth-result-button { background:#FF6B2C; color:white; }
        @keyframes modal-in { from { opacity:0; transform:translateY(12px) scale(.98); } }
        @keyframes auth-result-in { from { opacity:0; transform:translateY(10px) scale(.96); } }
        @keyframes alert-countdown { to { transform:scaleX(0); } }
        @media (prefers-reduced-motion:no-preference) {
            body::before { animation:page-drift 32s ease-in-out infinite alternate; }
            .page-ring { animation:page-ring-float 14s ease-in-out infinite; }
            .page-tile { animation:page-tile-float 17s ease-in-out infinite; }
            .page-orb { animation:page-orb-float 9s ease-in-out infinite; }
            .page-capsule { animation:page-capsule-float 19s ease-in-out infinite; }
            .float-shape { animation:float 7s ease-in-out infinite; }
            .ring-shape { animation:ring-drift 9s ease-in-out infinite; }
            .accent-dot { animation:dot-pulse 3.5s ease-in-out infinite; }
            .feature-count { animation:badge-breathe 5s ease-in-out infinite; }
            @keyframes float { 50% { transform:translateY(-9px) rotate(9deg); } }
            @keyframes ring-drift { 50% { transform:translate(-8px,7px) scale(1.025); opacity:.82; } }
            @keyframes dot-pulse { 50% { transform:scale(.72); box-shadow:0 0 0 10px rgba(255,107,44,0); } }
            @keyframes badge-breathe { 50% { background-color:rgba(255,255,255,.22); } }
            @keyframes page-drift { to { transform:translate3d(22px,14px,0); } }
            @keyframes page-ring-float { 50% { transform:translate3d(18px,26px,0) scale(1.04); } }
            @keyframes page-tile-float { 50% { transform:translate3d(-20px,-24px,0) rotate(11deg); } }
            @keyframes page-orb-float { 50% { transform:translate3d(-15px,28px,0) scale(.72); } }
            @keyframes page-capsule-float { 50% { transform:translate3d(28px,-18px,0) rotate(-7deg); } }
        }
        @media (max-width:640px) { .page-ring { left:-180px; opacity:.72; } .page-tile { right:-135px; } .page-capsule { display:none; } }
    </style>
</head>
<body class="min-h-screen font-sans antialiased selection:bg-[#C6F24E] selection:text-[#121017]">
    <div class="page-atmosphere" aria-hidden="true">
        <span class="page-object page-ring"></span>
        <span class="page-object page-tile"></span>
        <span class="page-object page-orb"></span>
        <span class="page-object page-capsule"></span>
    </div>
    <header class="relative z-30 border-b border-[#121017]/10 bg-white/95 backdrop-blur">
        <nav class="mx-auto flex h-[72px] max-w-[1440px] items-center gap-5 px-5 sm:px-8 lg:px-12" aria-label="Main navigation">
            <a class="flex shrink-0 items-center gap-2.5" href="{{ route('home') }}" aria-label="CITE home">
                <img class="h-11 w-11 rounded-full object-contain" src="{{ asset('images/cite-logo.png') }}" alt="CITE logo">
                <span class="text-xl font-black tracking-[-.04em]">CITE<span class="text-[#397565]">.</span></span>
            </a>

            <label class="relative ml-1 hidden max-w-[440px] flex-1 lg:block">
                <span class="sr-only">Search events</span>
                <svg class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-[#121017]/45 stroke-2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                <input id="event-search" class="h-11 w-full rounded-lg border border-[#121017]/10 bg-white/45 pl-11 pr-4 text-sm outline-none transition placeholder:text-[#121017]/45 focus:border-[#397565] focus:bg-white focus:ring-4 focus:ring-[#397565]/10" type="search" placeholder="Search events, competitions, activities..." data-event-search>
            </label>

            <div class="ml-auto hidden items-center gap-1 lg:flex">
                <a class="inline-flex h-11 items-center rounded-lg px-3 text-sm font-extrabold text-[#121017]/65 transition hover:bg-[#397565]/8 hover:text-[#397565]" href="#discover">Discover</a>
                <a class="inline-flex h-11 items-center rounded-lg px-3 text-sm font-extrabold text-[#121017]/65 transition hover:bg-[#397565]/8 hover:text-[#397565]" href="#upcoming">Events</a>
                @auth
                    <a class="ml-3 inline-flex h-11 shrink-0 items-center gap-2 rounded-lg bg-[#397565] px-5 text-sm font-extrabold text-white shadow-[0_7px_18px_rgba(57,117,101,.22)] transition hover:-translate-y-0.5 hover:bg-[#2e6355]" href="{{ route('dashboard') }}">Dashboard <span aria-hidden="true">→</span></a>
                @else
                    <button class="ml-3 inline-flex h-11 shrink-0 items-center gap-2 rounded-lg bg-[#397565] px-5 text-sm font-extrabold text-white shadow-[0_7px_18px_rgba(57,117,101,.22)] transition hover:-translate-y-0.5 hover:bg-[#2e6355]" type="button" data-login-open>Sign in <span aria-hidden="true">→</span></button>
                @endauth
            </div>

            <div class="relative ml-auto lg:hidden">
                <button class="grid h-11 w-11 place-items-center rounded-lg border border-[#121017]/12 bg-white/35 text-[#121017] transition hover:border-[#397565]/45 hover:bg-white/70" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="nav-menu" data-nav-toggle>
                    <svg class="h-5 w-5 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
                <div id="nav-menu" class="absolute right-0 top-[calc(100%+.75rem)] z-50 w-[min(19rem,calc(100vw-2.5rem))] rounded-xl border border-[#121017]/10 bg-white/95 p-3 shadow-[0_20px_50px_rgba(18,16,23,.16)] backdrop-blur-xl" hidden>
                    <label class="relative mb-2 block">
                        <span class="sr-only">Search events</span>
                        <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-[#121017]/40 stroke-2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                        <input class="h-11 w-full rounded-lg border border-[#121017]/10 bg-white/60 pl-10 pr-3 text-sm outline-none focus:border-[#397565]" type="search" placeholder="Search events..." data-event-search>
                    </label>
                    <a class="flex min-h-11 items-center justify-between rounded-lg px-3 text-sm font-extrabold transition hover:bg-[#397565]/10 hover:text-[#397565]" href="#discover" data-nav-close>Discover <span aria-hidden="true">→</span></a>
                    <a class="flex min-h-11 items-center justify-between rounded-lg px-3 text-sm font-extrabold transition hover:bg-[#397565]/10 hover:text-[#397565]" href="#upcoming" data-nav-close>Events <span aria-hidden="true">→</span></a>
                    <div class="mt-2 border-t border-[#121017]/8 pt-3">
                        @auth
                            <a class="flex min-h-11 items-center justify-between rounded-lg bg-[#397565] px-4 text-sm font-extrabold text-white" href="{{ route('dashboard') }}">Dashboard <span aria-hidden="true">→</span></a>
                        @else
                            <button class="flex min-h-11 w-full items-center justify-between rounded-lg bg-[#397565] px-4 text-sm font-extrabold text-white" type="button" data-login-open data-nav-close>Sign in <span aria-hidden="true">→</span></button>
                        @endauth
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <section id="discover" class="hero-grain border-b border-[#121017]/8">
            <div class="mx-auto grid max-w-[1440px] gap-10 px-5 py-12 sm:px-8 lg:grid-cols-[minmax(0,1.35fr)_minmax(350px,.65fr)] lg:px-12 lg:py-16 xl:gap-16">
                <div class="min-w-0">
                    <p class="mb-5 flex items-center gap-2 text-xs font-black uppercase tracking-[.18em] text-[#397565]"><span class="h-2 w-2 rounded-full bg-[#FF6B2C]"></span>CITE Fest 2026</p>
                    <h1 class="max-w-3xl text-[clamp(2.8rem,6vw,5.8rem)] font-black leading-[.96] tracking-[-.065em]">Where IT events<br class="hidden sm:block"> come alive.</h1>
                    <p class="mt-6 max-w-2xl text-base leading-7 text-[#121017]/58 sm:text-lg">Discover competitions, activities, and memorable moments created for the CITE community.</p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <a class="inline-flex min-h-12 items-center gap-3 rounded-lg bg-[#397565] px-5 text-sm font-extrabold text-white shadow-[0_8px_20px_rgba(57,117,101,.22)] transition hover:-translate-y-0.5 hover:bg-[#2e6355]" href="#upcoming">Explore Events <span class="text-lg" aria-hidden="true">→</span></a>
                        <a class="inline-flex min-h-12 items-center gap-3 rounded-lg border border-[#2F3AE0] bg-white/45 px-5 text-sm font-extrabold text-[#2F3AE0] transition hover:-translate-y-0.5 hover:bg-[#2F3AE0] hover:text-white" href="#upcoming">View Event Calendar
                            <svg class="h-4 w-4 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4m8-4v4M3 10h18"/></svg>
                        </a>
                    </div>

                    <div class="poster-glow poster-grid relative mt-10 min-h-[300px] overflow-hidden rounded-[14px] p-7 text-white shadow-[0_22px_45px_rgba(18,16,23,.16)] sm:min-h-[340px] sm:p-10">
                        <div class="ring-shape absolute -right-10 -top-16 h-52 w-52 rounded-full border-[34px] border-[#C6F24E]/20"></div>
                        <div class="float-shape absolute bottom-[-50px] right-[8%] h-36 w-36 rotate-12 rounded-[32px] bg-white/15 backdrop-blur-sm"></div>
                        <div class="accent-dot absolute bottom-8 right-[30%] h-5 w-5 rounded-full bg-[#FF6B2C] shadow-[0_0_0_0_rgba(255,107,44,.4)]"></div>
                        <div class="relative flex h-full min-h-[235px] flex-col justify-between">
                            <div class="flex items-start justify-between gap-4">
                                <p class="text-xs font-black uppercase tracking-[.2em] text-white/85">CITE Fest 2026<br><span class="mt-2 inline-block text-[10px] text-white/50">Featured event</span></p>
                                <span class="feature-count rounded-full bg-white/15 px-3 py-1 text-xs font-extrabold backdrop-blur">{{ max($featuredEvents->count(), 1) }} featured</span>
                            </div>
                            @if($featuredEvents->isNotEmpty())
                                @php($featured = $featuredEvents->first())
                                <div class="max-w-2xl">
                                    <span class="mb-3 inline-flex rounded-full bg-[#C6F24E] px-3 py-1 text-[10px] font-black uppercase tracking-wider text-[#121017]">{{ $featured->type?->label ?? 'Community Event' }}</span>
                                    <h2 class="text-3xl font-black tracking-[-.04em] sm:text-5xl">{{ $featured->title }}</h2>
                                    <p class="mt-3 text-sm text-white/70">{{ $featured->start_at->format('M j · g:i A') }} · {{ $featured->location ?: 'CITE Campus' }}</p>
                                </div>
                            @else
                                <div class="max-w-xl">
                                    <span class="mb-3 inline-flex rounded-full bg-[#C6F24E] px-3 py-1 text-[10px] font-black uppercase tracking-wider text-[#121017]">The next big thing</span>
                                    <h2 class="text-3xl font-black tracking-[-.04em] sm:text-5xl">Made by the tribe,<br>for the tribe.</h2>
                                    <p class="mt-3 text-sm text-white/65">New CITE experiences are on the way.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <aside class="self-start px-1 py-5 lg:sticky lg:top-24 lg:px-0 lg:py-0" aria-labelledby="calendar-heading">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[.16em] text-[#397565]">Mark your calendar</p>
                        <h2 id="calendar-heading" class="mt-4 text-3xl font-black tracking-[-.045em]">Upcoming in CITE</h2>
                        <p class="mt-2 text-sm leading-6 text-[#121017]/55">Don’t miss the next activities and competitions.</p>

                        <div class="mt-5 flex flex-wrap gap-2" aria-label="Filter upcoming events">
                            <button class="event-filter rounded-full border border-[#121017]/8 bg-[#F3F0E9] px-4 py-2 text-xs font-extrabold text-[#121017]/55 transition" type="button" data-filter="all" aria-pressed="true">All</button>
                            <button class="event-filter rounded-full border border-[#121017]/8 bg-[#F3F0E9] px-4 py-2 text-xs font-extrabold text-[#121017]/55 transition hover:border-[#397565]/40" type="button" data-filter="current" aria-pressed="false">Current</button>
                            <button class="event-filter rounded-full border border-[#121017]/8 bg-[#F3F0E9] px-4 py-2 text-xs font-extrabold text-[#121017]/55 transition hover:border-[#397565]/40" type="button" data-filter="upcoming" aria-pressed="false">Upcoming</button>
                        </div>
                    </div>

                    <div class="mt-7 divide-y divide-[#121017]/8" id="agenda-list">
                        @foreach($calendarEvents as $event)
                            <article class="agenda-item grid grid-cols-[56px_minmax(0,1fr)] gap-4 py-5 first:pt-0 sm:grid-cols-[56px_minmax(0,1fr)_auto] sm:items-center" data-name="{{ Str::lower($event['name'].' '.$event['title'].' '.$event['location']) }}" data-type="{{ $event['timing'] }}">
                                <time class="grid h-14 place-content-center rounded-lg {{ $event['timing'] === 'current' ? 'bg-[#C6F24E]/35 text-[#397565]' : 'bg-[#FF6B2C]/10 text-[#FF6B2C]' }} text-center" datetime="{{ $event['start_at']->toDateString() }}"><strong class="text-xl font-black leading-none">{{ $event['start_at']->format('d') }}</strong><span class="mt-1 text-[9px] font-black uppercase">{{ $event['start_at']->format('M') }}</span></time>
                                <div class="min-w-0">
                                    <p class="mb-1 text-[9px] font-black uppercase tracking-[.14em] text-[#397565]">{{ $event['name'] }}</p>
                                    <h3 class="truncate text-sm font-black sm:text-base">{{ $event['title'] }}</h3>
                                    <p class="mt-1 truncate text-xs text-[#121017]/52">{{ $event['start_at']->isSameDay($event['end_at']) ? $event['start_at']->format('M j · g:i A') : $event['start_at']->format('M j').'–'.$event['end_at']->format('j') }} · {{ $event['location'] }}</p>
                                </div>
                                <span class="col-start-2 w-fit rounded-full px-2.5 py-1 text-[9px] font-black uppercase tracking-wide sm:col-start-auto {{ $event['timing'] === 'current' ? 'bg-[#C6F24E] text-[#121017]' : 'bg-[#2F3AE0]/10 text-[#2F3AE0]' }}">{{ $event['timing'] }}</span>
                            </article>
                        @endforeach
                    </div>
                    <p id="agenda-empty" class="mt-6 hidden rounded-lg bg-[#FF6B2C]/8 p-4 text-sm font-bold text-[#121017]/65">No events match that filter.</p>
                </aside>
            </div>
        </section>

        <section id="upcoming" class="mx-auto grid max-w-[1440px] gap-12 px-5 py-16 sm:px-8 lg:grid-cols-[minmax(280px,.68fr)_minmax(0,1.32fr)] lg:px-12 lg:py-24 xl:gap-24">
            <header>
                <p class="text-xs font-black uppercase tracking-[.18em] text-[#397565]">Coming up</p>
                <h2 class="mt-4 text-4xl font-black leading-tight tracking-[-.05em] sm:text-5xl">Never miss the moment.</h2>
                <p class="mt-5 max-w-md text-lg leading-8 text-[#121017]/56">See what’s next, save your spot, and arrive with your tribe.</p>
                <a class="mt-8 inline-flex min-h-12 items-center gap-5 rounded-lg border border-[#397565]/45 bg-white/35 px-5 text-sm font-extrabold shadow-sm transition hover:border-[#2F3AE0] hover:text-[#2F3AE0]" href="#event-list">View all events <span class="text-xl" aria-hidden="true">→</span></a>
            </header>

            <div id="event-list" class="border-t border-[#121017]/12">
                @forelse($upcomingEvents as $event)
                    <article class="event-row group grid grid-cols-[64px_1fr_auto] items-center gap-4 border-b border-[#121017]/12 py-6 sm:grid-cols-[76px_1fr_auto] sm:gap-6" data-name="{{ Str::lower($event->title.' '.$event->location.' '.$event->type?->label) }}" data-type="{{ Str::slug($event->type?->label ?? 'event') }}">
                        <time class="grid h-[72px] place-content-center rounded-lg {{ $loop->index % 3 === 1 ? 'bg-[#2F3AE0]/8 text-[#2F3AE0]' : ($loop->index % 3 === 2 ? 'bg-[#FF6B2C]/10 text-[#FF6B2C]' : 'bg-[#397565]/12 text-[#397565]') }} text-center" datetime="{{ $event->start_at->toDateString() }}"><strong class="text-2xl font-black leading-none">{{ $event->start_at->format('d') }}</strong><span class="mt-1 text-[10px] font-black uppercase">{{ $event->start_at->format('M') }}</span></time>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2"><h3 class="truncate text-lg font-black tracking-[-.02em] sm:text-xl">{{ $event->title }}</h3>@if($event->start_at->isToday())<span class="rounded-full bg-[#C6F24E] px-2 py-1 text-[9px] font-black uppercase">Today</span>@endif</div>
                            <p class="mt-2 flex items-center gap-2 truncate text-sm text-[#121017]/55 sm:text-base"><svg class="h-4 w-4 shrink-0 fill-none stroke-[#397565] stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>{{ $event->start_at->format('M j · g:i A') }} · {{ $event->location ?: 'CITE Campus' }}</p>
                        </div>
                        <span class="text-3xl text-[#397565]/65 transition group-hover:translate-x-1 group-hover:text-[#2F3AE0]" aria-hidden="true">→</span>
                    </article>
                @empty
                    <div class="grid min-h-[260px] place-items-center border-b border-[#121017]/12 text-center">
                        <div><span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-[#C6F24E] text-xl">✦</span><h3 class="mt-5 text-xl font-black">The calendar is clearing its throat.</h3><p class="mt-2 text-sm text-[#121017]/50">Upcoming events will appear here once they’re published.</p></div>
                    </div>
                @endforelse
                <p id="search-empty" class="hidden border-b border-[#121017]/12 py-12 text-center text-sm font-bold text-[#121017]/55">No upcoming events match your search.</p>
            </div>
        </section>
    </main>

    <footer class="bg-[#1f3430] text-white">
        <div class="mx-auto flex max-w-[1440px] flex-col gap-5 px-5 py-9 sm:px-8 md:flex-row md:items-center md:justify-between lg:px-12">
            <a class="flex items-center gap-3 text-2xl font-black tracking-[-.05em]" href="{{ route('home') }}"><img class="h-11 w-11 rounded-full object-contain" src="{{ asset('images/cite-logo.png') }}" alt=""><span>CITE<span class="text-[#C6F24E]">.</span></span></a>
            <p class="text-sm text-[#C6F24E]/85">Plan. Connect. Celebrate.</p>
            <p class="text-sm text-white/55">Campus Event Management System</p>
        </div>
    </footer>

    @guest
        <dialog id="login-modal" class="login-dialog m-auto w-[calc(100%-2rem)] max-w-[460px] overflow-hidden rounded-2xl border-0 bg-[#F3F0E9] p-0 text-[#121017] shadow-[0_30px_90px_rgba(18,16,23,.35)]">
            <div class="relative p-6 sm:p-9">
                <button class="absolute right-4 top-4 grid h-10 w-10 place-items-center rounded-full text-2xl text-[#121017]/45 transition hover:bg-[#121017]/7 hover:text-[#121017]" type="button" data-login-close aria-label="Close sign-in dialog">×</button>

                <div class="flex items-center gap-3 pr-10">
                    <img class="h-14 w-14 rounded-full object-contain" src="{{ asset('images/cite-logo.png') }}" alt="CITE logo">
                    <div><p class="text-lg font-black tracking-[-.03em]">CITE<span class="text-[#397565]">.</span></p><p class="text-[10px] font-black uppercase tracking-[.14em] text-[#397565]">Campus Events</p></div>
                </div>

                <header class="mt-7">
                    <p class="text-xs font-black uppercase tracking-[.16em] text-[#397565]">Welcome back</p>
                    <h2 class="mt-2 text-3xl font-black tracking-[-.045em]">Sign in to your account</h2>
                    <p class="mt-2 text-sm leading-6 text-[#121017]/52">Enter your credentials to continue to the event portal.</p>
                </header>

                @if($errors->any())
                    <div class="relative mt-5 flex items-start gap-3 overflow-hidden rounded-xl border border-[#FF6B2C]/25 border-l-4 border-l-[#FF6B2C] bg-[#FF6B2C]/8 p-3.5 pr-10 shadow-[0_10px_28px_rgba(255,107,44,.1)]" role="alert" data-login-alert>
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#FF6B2C]/15 text-[#D64A12]" aria-hidden="true"><svg class="h-4 w-4 fill-none stroke-current stroke-[2.5]" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg></span>
                        <span><strong class="block text-sm font-black text-[#121017]">Sign-in failed</strong><span class="mt-0.5 block text-xs font-semibold leading-5 text-[#121017]/60">{{ $errors->first() }}</span></span>
                        <button class="absolute right-2 top-2 grid h-8 w-8 place-items-center rounded-lg text-lg text-[#121017]/35 hover:bg-[#121017]/6 hover:text-[#121017]" type="button" aria-label="Dismiss alert" data-login-alert-close>&times;</button>
                        <span class="login-alert-progress absolute inset-x-0 bottom-0 h-1 bg-[#FF6B2C]"></span>
                    </div>
                @endif

                <form class="mt-7 grid gap-5" method="POST" action="{{ route('login') }}" data-login-form>
                    @csrf
                    <label class="grid gap-2">
                        <span class="text-sm font-extrabold">Username or email</span>
                        <span class="relative">
                            <svg class="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 fill-none stroke-[#121017]/35 stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z"/></svg>
                            <input class="h-13 w-full rounded-xl border bg-white/65 pl-12 pr-4 text-sm outline-none transition placeholder:text-[#121017]/35 focus:ring-4 {{ $errors->has('login') ? 'border-[#FF6B2C] focus:ring-[#FF6B2C]/10' : 'border-[#121017]/12 focus:border-[#397565] focus:ring-[#397565]/10' }}" id="modal-login" name="login" value="{{ old('login') }}" placeholder="Enter your username or email" autocomplete="username" required>
                        </span>
                        <x-form-error name="login" class="!text-[#D64A12]" />
                    </label>

                    <label class="grid gap-2">
                        <span class="text-sm font-extrabold">Password</span>
                        <span class="relative">
                            <svg class="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 fill-none stroke-[#121017]/35 stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10V7a5 5 0 0 1 10 0v3M5 10h14v11H5z"/></svg>
                            <input class="h-13 w-full rounded-xl border border-[#121017]/12 bg-white/65 pl-12 pr-16 text-sm outline-none transition placeholder:text-[#121017]/35 focus:border-[#397565] focus:ring-4 focus:ring-[#397565]/10" id="modal-password" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required>
                            <button class="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg px-2 py-1 text-xs font-black text-[#397565]" type="button" data-password-toggle>Show</button>
                        </span>
                        <x-form-error name="password" class="!text-[#D64A12]" />
                    </label>

                    <label class="inline-flex w-fit cursor-pointer items-center gap-2 text-sm text-[#121017]/60"><input class="h-4 w-4 accent-[#397565]" name="remember" type="checkbox" value="1"><span>Keep me signed in</span></label>
                    <button class="inline-flex min-h-13 items-center justify-center gap-2 rounded-xl bg-[#397565] px-5 font-extrabold text-white shadow-[0_9px_24px_rgba(57,117,101,.22)] transition hover:bg-[#2e6355] disabled:cursor-wait disabled:opacity-70" type="submit" data-login-submit>Sign in <span class="text-xl" aria-hidden="true">→</span></button>
                </form>
                <p class="mt-6 text-center text-xs text-[#121017]/40">Having trouble signing in? Contact your SBO Adviser.</p>
            </div>
        </dialog>

        <dialog id="auth-result" class="auth-result m-auto w-[calc(100%-2rem)] max-w-sm overflow-hidden rounded-2xl border-0 bg-[#F3F0E9] p-0 text-center text-[#121017] shadow-[0_30px_90px_rgba(18,16,23,.4)]" data-type="success" aria-labelledby="auth-result-title" aria-describedby="auth-result-message">
            <div class="p-7 sm:p-8">
                <span class="auth-result-icon mx-auto grid h-16 w-16 place-items-center rounded-full" aria-hidden="true" data-auth-result-icon>
                    <svg class="h-7 w-7 fill-none stroke-current stroke-[2.5]" viewBox="0 0 24 24" data-success-icon><path d="m5 12 4 4L19 6"/></svg>
                    <svg class="hidden h-7 w-7 fill-none stroke-current stroke-[2.5]" viewBox="0 0 24 24" data-error-icon><path d="M18 6 6 18M6 6l12 12"/></svg>
                </span>
                <p class="mt-5 text-[10px] font-black uppercase tracking-[.16em] text-[#397565]" data-auth-result-eyebrow>Authentication complete</p>
                <h2 class="mt-2 text-2xl font-black tracking-[-.04em]" id="auth-result-title">Welcome to CITE</h2>
                <p class="mt-2 text-sm leading-6 text-[#121017]/58" id="auth-result-message">You are signed in. Opening your portal now.</p>
                <button class="auth-result-button mt-6 inline-flex min-h-11 w-full items-center justify-center rounded-xl px-5 text-sm font-extrabold shadow-lg transition hover:-translate-y-0.5" type="button" data-auth-result-confirm>Continue to portal</button>
            </div>
        </dialog>
    @endguest

    <script>
        (() => {
            const searches = [...document.querySelectorAll('[data-event-search]')];
            const filters = [...document.querySelectorAll('.event-filter')];
            const agendaItems = [...document.querySelectorAll('.agenda-item')];
            const eventRows = [...document.querySelectorAll('.event-row')];
            const agendaEmpty = document.querySelector('#agenda-empty');
            const searchEmpty = document.querySelector('#search-empty');
            let activeType = 'all';
            let searchTerm = '';

            const applyFilters = () => {
                const term = searchTerm;
                let agendaVisible = 0;
                let rowsVisible = 0;
                agendaItems.forEach(item => {
                    const visible = (activeType === 'all' || item.dataset.type === activeType) && item.dataset.name.includes(term);
                    item.hidden = !visible;
                    agendaVisible += Number(visible);
                });
                eventRows.forEach(item => {
                    const visible = item.dataset.name.includes(term);
                    item.hidden = !visible;
                    rowsVisible += Number(visible);
                });
                agendaEmpty?.classList.toggle('hidden', agendaItems.length === 0 || agendaVisible > 0);
                searchEmpty?.classList.toggle('hidden', eventRows.length === 0 || rowsVisible > 0);
            };

            filters.forEach(button => button.addEventListener('click', () => {
                activeType = button.dataset.filter;
                filters.forEach(filter => filter.setAttribute('aria-pressed', String(filter === button)));
                applyFilters();
            }));
            searches.forEach(input => {
                input.addEventListener('input', () => {
                    searchTerm = input.value.trim().toLowerCase();
                    searches.filter(search => search !== input).forEach(search => search.value = input.value);
                    applyFilters();
                });
                input.addEventListener('keydown', event => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        document.querySelector('#upcoming')?.scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });

            const navToggle = document.querySelector('[data-nav-toggle]');
            const navMenu = document.querySelector('#nav-menu');
            const closeNav = () => {
                if (!navMenu || !navToggle) return;
                navMenu.hidden = true;
                navToggle.setAttribute('aria-expanded', 'false');
            };
            navToggle?.addEventListener('click', event => {
                event.stopPropagation();
                const opening = navMenu.hidden;
                navMenu.hidden = !opening;
                navToggle.setAttribute('aria-expanded', String(opening));
            });
            navMenu?.addEventListener('click', event => event.stopPropagation());
            document.querySelectorAll('[data-nav-close]').forEach(link => link.addEventListener('click', closeNav));
            document.addEventListener('click', closeNav);
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') closeNav();
            });

            const loginModal = document.querySelector('#login-modal');
            const password = document.querySelector('#modal-password');
            const loginForm = document.querySelector('[data-login-form]');
            const loginSubmit = document.querySelector('[data-login-submit]');
            const authResult = document.querySelector('#auth-result');
            const authResultTitle = document.querySelector('#auth-result-title');
            const authResultMessage = document.querySelector('#auth-result-message');
            const authResultEyebrow = document.querySelector('[data-auth-result-eyebrow]');
            const authResultConfirm = document.querySelector('[data-auth-result-confirm]');
            let portalRedirect = null;
            let redirectTimer = null;

            const showAuthResult = (type, message, redirectUrl = null) => {
                portalRedirect = redirectUrl;
                if (loginModal?.open) loginModal.close();
                authResult.dataset.type = type;
                authResultTitle.textContent = type === 'success' ? 'Welcome to CITE' : 'Unable to sign in';
                authResultMessage.textContent = message;
                authResultEyebrow.textContent = type === 'success' ? 'Authentication complete' : 'Please try again';
                authResultConfirm.textContent = type === 'success' ? 'Continue to portal' : 'Back to sign in';
                authResult.querySelector('[data-success-icon]').classList.toggle('hidden', type !== 'success');
                authResult.querySelector('[data-error-icon]').classList.toggle('hidden', type === 'success');
                authResult.showModal();
                authResultConfirm.focus();

                window.clearTimeout(redirectTimer);
                if (redirectUrl) redirectTimer = window.setTimeout(() => window.location.assign(redirectUrl), 1800);
            };

            authResultConfirm?.addEventListener('click', () => {
                window.clearTimeout(redirectTimer);
                if (portalRedirect) {
                    window.location.assign(portalRedirect);
                    return;
                }
                authResult.close();
                loginModal?.showModal();
                document.querySelector('#modal-login')?.focus();
            });
            authResult?.addEventListener('cancel', event => {
                event.preventDefault();
                if (!portalRedirect) authResultConfirm.click();
            });

            loginForm?.addEventListener('submit', async event => {
                event.preventDefault();
                if (!loginForm.reportValidity()) return;

                const originalHtml = loginSubmit.innerHTML;
                loginSubmit.disabled = true;
                loginSubmit.innerHTML = '<span class="h-4 w-4 animate-spin rounded-full border-2 border-white border-r-transparent" aria-hidden="true"></span><span>Signing in…</span>';

                try {
                    const response = await fetch(loginForm.action, {
                        method: 'POST',
                        body: new FormData(loginForm),
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                    });
                    const body = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        const validationMessage = Object.values(body.errors || {}).flat()[0];
                        throw new Error(validationMessage || body.message || 'Please check your credentials and try again.');
                    }
                    showAuthResult('success', body.message || 'You are signed in. Opening your portal now.', body.redirect_url);
                } catch (error) {
                    showAuthResult('error', error.message || 'Please check your credentials and try again.');
                } finally {
                    loginSubmit.disabled = false;
                    loginSubmit.innerHTML = originalHtml;
                }
            });
            document.querySelectorAll('[data-login-open]').forEach(button => button.addEventListener('click', () => {
                loginModal?.showModal();
                document.querySelector('#modal-login')?.focus();
            }));
            document.querySelector('[data-login-close]')?.addEventListener('click', () => loginModal?.close());
            loginModal?.addEventListener('click', event => {
                const box = loginModal.getBoundingClientRect();
                const inside = event.clientX >= box.left && event.clientX <= box.right && event.clientY >= box.top && event.clientY <= box.bottom;
                if (!inside) loginModal.close();
            });
            document.querySelector('[data-password-toggle]')?.addEventListener('click', event => {
                const showing = password.type === 'text';
                password.type = showing ? 'password' : 'text';
                event.currentTarget.textContent = showing ? 'Show' : 'Hide';
            });
            const loginAlert = document.querySelector('[data-login-alert]');
            const dismissLoginAlert = () => loginAlert?.remove();
            document.querySelector('[data-login-alert-close]')?.addEventListener('click', dismissLoginAlert);
            if (loginAlert) window.setTimeout(dismissLoginAlert, 6500);
            @if($errors->has('login') || $errors->has('password') || request()->boolean('login'))
                loginModal?.showModal();
            @endif

        })();
    </script>
</body>
</html>
