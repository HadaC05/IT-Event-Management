<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Dashboard | IT Event Management</title>
    @vite('resources/css/app.css')
    <script src="{{ asset('js/notifications.js') }}" defer></script>
</head>
<body class="grid min-h-screen place-items-center bg-[#fff5e0] p-5 font-sans text-[#141e46] antialiased">
    <main class="w-full max-w-2xl rounded-3xl border border-white/70 bg-white p-8 shadow-2xl shadow-[#141e46]/10 sm:p-14">
        <div class="mb-14 inline-flex items-center gap-3 text-xl font-extrabold tracking-tight"><span class="flex h-9 w-9 items-end gap-0.5 rounded-xl bg-[#fff5e0] p-2"><i class="h-2 w-1.5 rounded-t bg-[#8decb4]"></i><i class="h-4 w-1.5 rounded-t bg-[#41b06e]"></i><i class="h-5 w-1.5 rounded-t bg-[#141e46]"></i></span>IT Events</div>
        <p class="mb-3 text-xs font-extrabold uppercase tracking-[.14em] text-emerald-700">Dashboard</p>
        <h1 class="text-4xl font-black tracking-tight sm:text-5xl">Welcome, {{ auth()->user()->first_name }}.</h1>
        <p class="mt-3 text-base text-slate-500">You are signed in as <strong class="text-slate-700">{{ auth()->user()->role?->name ?? 'Unassigned' }}</strong>.</p>
        <div class="my-7 grid gap-1.5 border-l-4 border-emerald-300 bg-emerald-50 px-5 py-4 text-sm text-slate-600"><span class="font-bold">{{ auth()->user()->full_name }}</span><span>{{ auth()->user()->email }}</span></div>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 font-extrabold text-white shadow-lg shadow-emerald-600/20 hover:bg-emerald-700" type="submit" data-loading-text="Signing out…">Sign out <span class="text-xl">→</span></button></form>
    </main>
</body>
</html>
