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
<body class="app-shell min-h-screen bg-white font-sans text-[#121017] antialiased" data-admin-shell>
    <span class="route-progress" data-route-progress data-active="false" aria-hidden="true"></span>
    <header class="sticky top-0 z-30 border-b border-[#121017]/8 bg-white/95 backdrop-blur-xl">
        <nav class="mx-auto flex h-[72px] max-w-[1440px] items-center gap-3 px-4 sm:px-6 lg:px-10" aria-label="Officer navigation">
            <a class="flex shrink-0 items-center gap-2 rounded-xl border border-[#121017]/8 bg-[#F3F0E9]/55 p-1.5 pr-3" href="{{ route('officer.attendance.index') }}">
                <img class="h-9 w-9 rounded-full object-contain" src="{{ asset('images/cite-logo.png') }}" alt="CITE logo">
                <span class="hidden text-sm font-black sm:block">CITE<span class="text-[#397565]">.</span></span>
            </a>
            <span class="hidden h-6 w-px bg-[#121017]/10 sm:block"></span>
            <span class="hidden text-[10px] font-black uppercase tracking-[.14em] text-[#397565] sm:block">Attendance Desk</span>

            <details class="group relative ml-auto" data-account-menu>
                <summary class="flex cursor-pointer list-none items-center gap-2 rounded-xl px-1.5 py-1 transition hover:bg-[#F3F0E9]/70 focus:outline-none focus-visible:ring-4 focus-visible:ring-[#397565]/12">
                    <span class="relative grid h-9 w-9 place-items-center rounded-full bg-[#C6F24E] text-[10px] font-black ring-2 ring-[#397565]/12">{{ strtoupper(substr(auth()->user()->first_name, 0, 1).substr(auth()->user()->last_name, 0, 1)) }}<i class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full border-2 border-white bg-[#397565]"></i></span>
                    <span class="hidden text-left sm:grid"><strong class="max-w-36 truncate text-xs font-black">{{ auth()->user()->full_name }}</strong><small class="text-[9px] font-bold uppercase tracking-wider text-[#397565]">SBO Officer</small></span>
                    <svg class="hidden h-4 w-4 fill-none stroke-[#121017]/35 stroke-2 transition group-open:rotate-180 sm:block" viewBox="0 0 24 24"><path d="m7 10 5 5 5-5"/></svg>
                </summary>
                <div class="absolute right-0 top-[calc(100%+.65rem)] w-64 overflow-hidden rounded-2xl border border-[#121017]/10 bg-white p-2 shadow-[0_20px_55px_rgba(18,16,23,.16)]">
                    <div class="border-b border-[#121017]/7 px-3 py-3"><strong class="block truncate text-sm font-black">{{ auth()->user()->full_name }}</strong><span class="mt-1 block truncate text-xs text-[#121017]/45">{{ auth()->user()->officerTeam?->name ?? 'No tribe assigned' }}</span></div>
                    <form class="mt-2" method="POST" action="{{ route('logout') }}">@csrf<button class="flex min-h-10 w-full items-center gap-3 rounded-xl px-3 text-xs font-bold text-[#FF6B2C] transition hover:bg-[#FF6B2C]/8" type="submit" data-loading-text="Signing out…"><svg class="h-4 w-4 fill-none stroke-current stroke-2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4m7 14 5-5-5-5m5 5H9"/></svg>Sign out</button></form>
                </div>
            </details>
        </nav>
    </header>
    <main class="admin-main mx-auto w-full max-w-[1440px] px-4 py-7 sm:px-6 lg:px-10 lg:py-10" data-page-content>@yield('content')</main>
    <x-notifications />
    <script>
        const accountMenu = document.querySelector('[data-account-menu]');
        document.addEventListener('click', event => { if (accountMenu?.open && !accountMenu.contains(event.target)) accountMenu.removeAttribute('open'); });
        document.addEventListener('keydown', event => { if (event.key === 'Escape') accountMenu?.removeAttribute('open'); });
    </script>
    @stack('scripts')
</body>
</html>
