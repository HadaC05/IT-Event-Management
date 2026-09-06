<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') | IT Events</title>
    @vite('resources/css/app.css')
    <script src="{{ asset('js/notifications.js') }}" defer></script>
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-700 antialiased">
    <aside class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-[#141e46] px-5 pb-5 pt-7 text-[#fff5e0] shadow-2xl transition-transform duration-200 lg:translate-x-0 lg:shadow-none" id="sidebar">
        <a class="mx-3 mb-12 inline-flex items-center gap-3 text-xl font-extrabold tracking-tight" href="{{ route('dashboard') }}">
            <span class="flex h-9 w-9 items-end gap-0.5 rounded-xl bg-[#fff5e0] p-2" aria-hidden="true"><i class="h-2 w-1.5 rounded-t bg-[#8decb4]"></i><i class="h-4 w-1.5 rounded-t bg-[#41b06e]"></i><i class="h-5 w-1.5 rounded-t bg-[#141e46]"></i></span>
            <span>IT Events</span>
        </a>

        <nav class="grid gap-2" aria-label="Main navigation">
            <a href="{{ route('dashboard') }}" @class(['flex min-h-12 items-center gap-3 rounded-xl px-4 text-sm font-bold transition', 'bg-[#8decb4] text-[#141e46]' => request()->routeIs('dashboard'), 'text-[#fff5e0]/65 hover:bg-white/5 hover:text-[#fff5e0]' => !request()->routeIs('dashboard')])>
                <svg class="h-5 w-5 fill-none stroke-current" aria-hidden="true" viewBox="0 0 24 24"><path d="M4 13h6V4H4v9Zm0 7h6v-4H4v4Zm10 0h6v-9h-6v9Zm0-16v4h6V4h-6Z"/></svg>Dashboard
            </a>
            <a href="{{ route('adviser.users.index') }}" @class(['flex min-h-12 items-center gap-3 rounded-xl px-4 text-sm font-bold transition', 'bg-[#8decb4] text-[#141e46]' => request()->routeIs('adviser.users.*'), 'text-[#fff5e0]/65 hover:bg-white/5 hover:text-[#fff5e0]' => !request()->routeIs('adviser.users.*')])>
                <svg class="h-5 w-5 fill-none stroke-current" aria-hidden="true" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>User Management
            </a>
            <a href="{{ route('adviser.teams.index') }}" @class(['flex min-h-12 items-center gap-3 rounded-xl px-4 text-sm font-bold transition', 'bg-[#8decb4] text-[#141e46]' => request()->routeIs('adviser.teams.*'), 'text-[#fff5e0]/65 hover:bg-white/5 hover:text-[#fff5e0]' => !request()->routeIs('adviser.teams.*')])>
                <svg class="h-5 w-5 fill-none stroke-current" aria-hidden="true" viewBox="0 0 24 24"><path d="M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm8 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM2 20v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2m0-5.5a4 4 0 0 1 3-1.5h1a4 4 0 0 1 4 4v3"/></svg>Tribe Management
            </a>
            <a href="{{ route('adviser.events.index') }}" @class(['flex min-h-12 items-center gap-3 rounded-xl px-4 text-sm font-bold transition', 'bg-[#8decb4] text-[#141e46]' => request()->routeIs('adviser.events.*'), 'text-[#fff5e0]/65 hover:bg-white/5 hover:text-[#fff5e0]' => !request()->routeIs('adviser.events.*')])>
                <svg class="h-5 w-5 fill-none stroke-current" aria-hidden="true" viewBox="0 0 24 24"><path d="M6 2v4m12-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z"/></svg>Event Management
            </a>
            <a href="{{ route('adviser.attendance.index') }}" @class(['flex min-h-12 items-center gap-3 rounded-xl px-4 text-sm font-bold transition', 'bg-[#8decb4] text-[#141e46]' => request()->routeIs('adviser.attendance.*'), 'text-[#fff5e0]/65 hover:bg-white/5 hover:text-[#fff5e0]' => !request()->routeIs('adviser.attendance.*')])>
                <svg class="h-5 w-5 fill-none stroke-current" aria-hidden="true" viewBox="0 0 24 24"><path d="M9 11l2 2 4-4m6 3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>Attendance
            </a>
        </nav>

        <div class="mt-auto flex items-center gap-2.5 border-t border-white/10 pt-4">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[#8decb4] text-xs font-extrabold text-[#141e46]">{{ strtoupper(substr(auth()->user()->first_name, 0, 1).substr(auth()->user()->last_name, 0, 1)) }}</span>
            <span class="grid min-w-0 flex-1"><strong class="truncate text-xs text-[#fff5e0]">{{ auth()->user()->full_name }}</strong><small class="mt-0.5 text-[10px] text-[#fff5e0]/50">SBO Adviser</small></span>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="grid h-9 w-9 place-items-center rounded-lg text-[#fff5e0]/50 transition hover:bg-white/10 hover:text-[#fff5e0]" type="submit" aria-label="Sign out" title="Sign out"><svg class="h-4 w-4 fill-none stroke-current" aria-hidden="true" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4m7 14 5-5-5-5m5 5H9"/></svg></button></form>
        </div>
    </aside>

    <div class="min-h-screen lg:ml-64">
        <header class="flex h-17 items-center justify-between border-b border-slate-200 bg-white px-5 lg:hidden">
            <button class="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 text-slate-600" type="button" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false" aria-label="Open navigation"><svg class="h-5 w-5 fill-none stroke-current" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
            <span class="inline-flex items-center gap-2.5 text-lg font-extrabold tracking-tight text-[#141e46]"><span class="flex h-8 w-8 items-end gap-0.5 rounded-lg bg-[#fff5e0] p-1.5"><i class="h-2 w-1 rounded-t bg-[#8decb4]"></i><i class="h-3.5 w-1 rounded-t bg-[#41b06e]"></i><i class="h-5 w-1 rounded-t bg-[#141e46]"></i></span>IT Events</span>
        </header>
        <main class="mx-auto w-full max-w-[1500px] px-4 py-7 sm:px-6 lg:px-10 lg:py-11 xl:px-16">@yield('content')</main>
    </div>
    <button class="fixed inset-0 z-30 hidden bg-[#141e46]/50 lg:hidden" data-sidebar-scrim data-sidebar-toggle aria-label="Close navigation"></button>
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

        document.querySelectorAll('[data-dialog-open]').forEach((button) => button.addEventListener('click', () => document.getElementById(button.dataset.dialogOpen)?.showModal()));
        document.querySelectorAll('[data-dialog-close]').forEach((button) => button.addEventListener('click', () => button.closest('dialog')?.close()));
        document.querySelectorAll('dialog').forEach((dialog) => dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); }));
    </script>
    @stack('scripts')
</body>
</html>
