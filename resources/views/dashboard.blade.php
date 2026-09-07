<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Dashboard | CITE Events</title>
    @vite('resources/css/app.css')
    <script src="{{ asset('js/notifications.js') }}" defer></script>
    <script src="{{ asset('js/interactions.js') }}" defer></script>
</head>
<body class="app-shell grid min-h-screen place-items-center bg-white p-5 font-sans text-[#121017] antialiased">
    <span class="route-progress" data-route-progress data-active="false" aria-hidden="true"></span>
    <main class="w-full max-w-2xl rounded-3xl border border-white/70 bg-white p-8 shadow-2xl shadow-[#121017]/10 sm:p-14" data-page-content>
        <a class="mb-14 inline-flex items-center gap-3 text-xl font-extrabold tracking-tight" href="{{ route('home') }}"><img class="h-11 w-11 rounded-full object-contain" src="{{ asset('images/cite-logo.png') }}" alt="CITE logo"><span>CITE<span class="text-[#397565]">.</span></span></a>
        <p class="mb-3 text-xs font-extrabold uppercase tracking-[.14em] text-emerald-700">Dashboard</p>
        <h1 class="text-4xl font-black tracking-tight sm:text-5xl">Welcome, {{ auth()->user()->first_name }}.</h1>
        <p class="mt-3 text-base text-slate-500">You are signed in as <strong class="text-slate-700">{{ auth()->user()->role?->name ?? 'Unassigned' }}</strong>.</p>
        <div class="my-7 grid gap-1.5 border-l-4 border-emerald-300 bg-emerald-50 px-5 py-4 text-sm text-slate-600"><span class="font-bold">{{ auth()->user()->full_name }}</span><span>{{ auth()->user()->email }}</span></div>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 font-extrabold text-white shadow-lg shadow-emerald-600/20 hover:bg-emerald-700" type="submit" data-loading-text="Signing out…">Sign out <span class="text-xl">→</span></button></form>
    </main>
    <x-notifications />
</body>
</html>
