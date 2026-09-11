<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') | CITE Events</title>
    <x-favicon />
    @vite('resources/css/app.css')
    <script src="{{ asset('js/notifications.js') }}" defer></script>
    <script src="{{ asset('js/interactions.js') }}" defer></script>
</head>
<body class="app-shell min-h-screen @yield('body-class', 'bg-white') font-sans text-[#121017] antialiased" data-admin-shell>
    <span class="route-progress" data-route-progress data-active="false" aria-hidden="true"></span>
    <aside class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-[#121017] px-5 pb-5 pt-5 text-[#F3F0E9] shadow-2xl transition-transform duration-200 lg:translate-x-0 lg:shadow-none" id="sidebar">
        <a class="mb-10 flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[.07] p-3 text-xl font-extrabold tracking-tight shadow-[0_12px_30px_rgba(0,0,0,.18)] transition hover:border-[#C6F24E]/30 hover:bg-white/[.1]" href="{{ route('dashboard') }}">
            <img class="h-10 w-10 rounded-full object-contain" src="{{ asset('images/cite-logo.png') }}" alt="CITE logo">
            <span class="grid leading-tight">CITE<span class="text-[9px] font-bold uppercase tracking-[.15em] text-[#F3F0E9]/45">Events Admin</span></span>
        </a>

        <nav class="grid gap-2" aria-label="Main navigation">
            <a href="{{ route('dashboard') }}" @class(['flex min-h-12 items-center gap-3 rounded-xl px-4 text-sm font-bold transition', 'bg-[#397565] text-white shadow-lg shadow-black/10' => request()->routeIs('dashboard'), 'text-[#F3F0E9]/65 hover:bg-white/5 hover:text-[#F3F0E9]' => !request()->routeIs('dashboard')])>
                <svg class="h-5 w-5 fill-none stroke-current" aria-hidden="true" viewBox="0 0 24 24"><path d="M4 13h6V4H4v9Zm0 7h6v-4H4v4Zm10 0h6v-9h-6v9Zm0-16v4h6V4h-6Z"/></svg>Dashboard
            </a>
            <a href="{{ route('adviser.users.index') }}" @class(['flex min-h-12 items-center gap-3 rounded-xl px-4 text-sm font-bold transition', 'bg-[#397565] text-white shadow-lg shadow-black/10' => request()->routeIs('adviser.users.*'), 'text-[#F3F0E9]/65 hover:bg-white/5 hover:text-[#F3F0E9]' => !request()->routeIs('adviser.users.*')])>
                <svg class="h-5 w-5 fill-none stroke-current" aria-hidden="true" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>User Management
            </a>
            <a href="{{ route('adviser.officers.index') }}" @class(['flex min-h-12 items-center gap-3 rounded-xl px-4 text-sm font-bold transition', 'bg-[#397565] text-white shadow-lg shadow-black/10' => request()->routeIs('adviser.officers.*'), 'text-[#F3F0E9]/65 hover:bg-white/5 hover:text-[#F3F0E9]' => !request()->routeIs('adviser.officers.*')])>
                <svg class="h-5 w-5 fill-none stroke-current" aria-hidden="true" viewBox="0 0 24 24"><path d="M12 3 4 7v5c0 4.6 3.2 7.8 8 9 4.8-1.2 8-4.4 8-9V7l-8-4Zm-3 9 2 2 4-5"/></svg>Officer Management
            </a>
            <a href="{{ route('adviser.teams.index') }}" @class(['flex min-h-12 items-center gap-3 rounded-xl px-4 text-sm font-bold transition', 'bg-[#397565] text-white shadow-lg shadow-black/10' => request()->routeIs('adviser.teams.*'), 'text-[#F3F0E9]/65 hover:bg-white/5 hover:text-[#F3F0E9]' => !request()->routeIs('adviser.teams.*')])>
                <svg class="h-5 w-5 fill-none stroke-current" aria-hidden="true" viewBox="0 0 24 24"><path d="M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm8 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM2 20v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2m0-5.5a4 4 0 0 1 3-1.5h1a4 4 0 0 1 4 4v3"/></svg>Team Management
            </a>
            <a href="{{ route('adviser.events.index') }}" @class(['flex min-h-12 items-center gap-3 rounded-xl px-4 text-sm font-bold transition', 'bg-[#397565] text-white shadow-lg shadow-black/10' => request()->routeIs('adviser.events.*'), 'text-[#F3F0E9]/65 hover:bg-white/5 hover:text-[#F3F0E9]' => !request()->routeIs('adviser.events.*')])>
                <svg class="h-5 w-5 fill-none stroke-current" aria-hidden="true" viewBox="0 0 24 24"><path d="M6 2v4m12-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z"/></svg>Event Management
            </a>
            <a href="{{ route('adviser.attendance.index') }}" @class(['flex min-h-12 items-center gap-3 rounded-xl px-4 text-sm font-bold transition', 'bg-[#397565] text-white shadow-lg shadow-black/10' => request()->routeIs('adviser.attendance.*'), 'text-[#F3F0E9]/65 hover:bg-white/5 hover:text-[#F3F0E9]' => !request()->routeIs('adviser.attendance.*')])>
                <svg class="h-5 w-5 fill-none stroke-current" aria-hidden="true" viewBox="0 0 24 24"><path d="M9 11l2 2 4-4m6 3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>Attendance
            </a>
            <a href="{{ route('adviser.scores.index') }}" @class(['flex min-h-12 items-center gap-3 rounded-xl px-4 text-sm font-bold transition', 'bg-[#397565] text-white shadow-lg shadow-black/10' => request()->routeIs('adviser.scores.*'), 'text-[#F3F0E9]/65 hover:bg-white/5 hover:text-[#F3F0E9]' => !request()->routeIs('adviser.scores.*')])>
                <svg class="h-5 w-5 fill-none stroke-current" aria-hidden="true" viewBox="0 0 24 24"><path d="M8 21h8M12 17v4M7 4h10v4a5 5 0 0 1-10 0V4Zm0 2H4v1a4 4 0 0 0 4 4m9-5h3v1a4 4 0 0 1-4 4"/></svg>Scores
            </a>
            <a href="{{ route('adviser.posts.index') }}" @class(['flex min-h-12 items-center gap-3 rounded-xl px-4 text-sm font-bold transition', 'bg-[#397565] text-white shadow-lg shadow-black/10' => request()->routeIs('adviser.posts.*'), 'text-[#F3F0E9]/65 hover:bg-white/5 hover:text-[#F3F0E9]' => !request()->routeIs('adviser.posts.*')])>
                <svg class="h-5 w-5 fill-none stroke-current" aria-hidden="true" viewBox="0 0 24 24"><path d="M4 5h16v12H8l-4 4V5Zm4 4h8m-8 4h5"/></svg>Post Review
            </a>
        </nav>

        <p class="mt-auto px-3 text-[9px] font-bold uppercase tracking-[.15em] text-[#F3F0E9]/30">Campus Event Management</p>
    </aside>

    <div class="min-h-screen lg:ml-64">
        <header class="sticky top-0 z-30 border-b border-[#397565]/25 bg-[#DDEBE6]/90 shadow-[0_8px_30px_rgba(57,117,101,.14)] backdrop-blur-xl">
            <div class="mx-auto flex h-[72px] w-full max-w-[1500px] items-center gap-3 px-4 sm:px-6 lg:px-10 xl:px-16">
                <button class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-[#121017]/10 text-[#121017]/65 transition hover:border-[#397565]/35 hover:text-[#397565] lg:hidden" type="button" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false" aria-label="Open navigation"><svg class="h-5 w-5 fill-none stroke-current stroke-2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>

                @if(request()->routeIs('dashboard'))
                    <div class="relative min-w-0 max-w-xl flex-1" role="search">
                        <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-[#121017]/35 stroke-2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                        <label class="sr-only" for="admin-search">Filter dashboard</label>
                        <input class="h-11 w-full rounded-xl border border-[#121017]/9 bg-[#F3F0E9]/55 pl-10 pr-4 text-sm text-[#121017] outline-none transition placeholder:text-[#121017]/35 focus:border-[#397565]/45 focus:bg-white focus:ring-4 focus:ring-[#397565]/8" id="admin-search" type="search" placeholder="Filter dashboard…" autocomplete="off" data-dashboard-search>
                    </div>
                @endif

                <details class="group relative ml-auto shrink-0" data-account-menu>
                    <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2.5 rounded-xl border border-transparent px-1.5 py-1 transition hover:border-[#121017]/8 hover:bg-[#F3F0E9]/60 focus:outline-none focus-visible:ring-4 focus-visible:ring-[#397565]/12">
                        <span class="relative grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#C6F24E] text-[10px] font-black text-[#121017] ring-2 ring-[#397565]/12">{{ strtoupper(substr(auth()->user()->first_name, 0, 1).substr(auth()->user()->last_name, 0, 1)) }}<i class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full border-2 border-white bg-[#397565]" aria-hidden="true"></i></span>
                        <span class="hidden min-w-0 text-left sm:grid"><strong class="max-w-36 truncate text-xs font-black text-[#121017]">{{ auth()->user()->full_name }}</strong><small class="mt-0.5 text-[9px] font-bold uppercase tracking-[.1em] text-[#397565]">SBO Adviser</small></span>
                        <svg class="hidden h-4 w-4 fill-none stroke-[#121017]/35 stroke-2 transition group-open:rotate-180 sm:block" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>
                    </summary>
                    <div class="absolute right-0 top-[calc(100%+.65rem)] w-64 overflow-hidden rounded-2xl border border-[#121017]/10 bg-white p-2 shadow-[0_20px_55px_rgba(18,16,23,.16)]">
                        <div class="border-b border-[#121017]/7 px-3 py-3"><strong class="block truncate text-sm font-black">{{ auth()->user()->full_name }}</strong><span class="mt-1 block truncate text-xs text-[#121017]/45">{{ auth()->user()->email }}</span></div>
                        <a class="mt-2 flex min-h-10 items-center gap-3 rounded-xl px-3 text-xs font-bold text-[#121017]/65 transition hover:bg-[#397565]/8 hover:text-[#397565]" href="{{ route('home') }}"><svg class="h-4 w-4 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11 12 3l9 8M5 10v10h14V10"/></svg>View public homepage</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="flex min-h-10 w-full items-center gap-3 rounded-xl px-3 text-xs font-bold text-[#FF6B2C] transition hover:bg-[#FF6B2C]/8" type="submit" data-loading-text="Signing out…"><svg class="h-4 w-4 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4m7 14 5-5-5-5m5 5H9"/></svg>Sign out</button></form>
                    </div>
                </details>
            </div>
        </header>
        <main class="admin-main mx-auto w-full max-w-[1500px] px-4 py-7 sm:px-6 lg:px-10 lg:py-9 xl:px-16" data-page-content>
            @yield('content')
            @if(request()->routeIs('dashboard'))
                <div class="hidden rounded-2xl border border-dashed border-[#397565]/25 bg-[#397565]/5 px-6 py-12 text-center" data-dashboard-search-empty>
                    <span class="mx-auto grid h-11 w-11 place-items-center rounded-full bg-[#397565]/10 text-[#397565]" aria-hidden="true"><svg class="h-5 w-5 fill-none stroke-current stroke-2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg></span>
                    <strong class="mt-4 block text-base font-black">No dashboard cards found</strong>
                    <p class="mt-1 text-sm text-[#121017]/50">Try a term such as attendance, events, users, teams, or scores.</p>
                </div>
            @endif
        </main>
    </div>
    <button class="fixed inset-0 z-30 hidden bg-[#121017]/60 lg:hidden" data-sidebar-scrim data-sidebar-toggle aria-label="Close navigation"></button>
    <x-notifications />

    <script>
        const sidebar = document.getElementById('sidebar');
        const sidebarScrim = document.querySelector('[data-sidebar-scrim]');
        const sidebarTrigger = document.querySelector('[aria-controls="sidebar"]');
        document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => button.addEventListener('click', () => {
            const opening = sidebar.classList.contains('-translate-x-full');
            sidebar.classList.toggle('-translate-x-full', !opening);
            sidebar.classList.toggle('translate-x-0', opening);
            sidebarScrim.classList.toggle('hidden', !opening);
            sidebarTrigger?.setAttribute('aria-expanded', String(opening));
        }));

        const accountMenu = document.querySelector('[data-account-menu]');
        document.addEventListener('click', (event) => {
            if (accountMenu?.open && !accountMenu.contains(event.target)) accountMenu.removeAttribute('open');
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') accountMenu?.removeAttribute('open');
        });

        document.querySelectorAll('[data-dialog-open]').forEach((button) => button.addEventListener('click', () => document.getElementById(button.dataset.dialogOpen)?.showModal()));
        document.querySelectorAll('[data-dialog-close]').forEach((button) => button.addEventListener('click', () => button.closest('dialog')?.close()));
        document.querySelectorAll('dialog').forEach((dialog) => dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); }));

        const dashboardSearch = document.querySelector('[data-dashboard-search]');
        const dashboardMain = document.querySelector('.admin-main');
        const dashboardEmpty = document.querySelector('[data-dashboard-search-empty]');
        if (dashboardSearch && dashboardMain) {
            const groups = [...dashboardMain.querySelectorAll(':scope > section')].map((section) => {
                const cards = [...section.querySelectorAll(':scope > article')];
                const items = cards.length ? cards : [section];
                return {
                    section,
                    items: items.map((item) => ({ item, text: item.innerText.toLowerCase() })),
                };
            });

            const filterDashboard = () => {
                const query = dashboardSearch.value.trim().toLowerCase();
                let matches = 0;

                groups.forEach((group) => {
                    let groupMatches = 0;
                    group.items.forEach(({ item, text }) => {
                        const visible = !query || text.includes(query);
                        if (item !== group.section) item.classList.toggle('hidden', !visible);
                        groupMatches += Number(visible);
                    });
                    group.section.classList.toggle('hidden', groupMatches === 0);
                    matches += groupMatches;
                });

                dashboardEmpty?.classList.toggle('hidden', !query || matches > 0);
            };

            dashboardSearch.addEventListener('input', filterDashboard);
            dashboardSearch.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') return;
                dashboardSearch.value = '';
                filterDashboard();
            });
        }
    </script>
    @stack('scripts')
</body>
</html>
