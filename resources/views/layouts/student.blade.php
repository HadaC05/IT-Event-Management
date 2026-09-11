<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Student') | CITE Events</title>
    <x-favicon />
    <script>try{const t=localStorage.getItem('cite-theme')||'light';document.documentElement.classList.toggle('dark',t==='dark'||(t==='system'&&matchMedia('(prefers-color-scheme: dark)').matches));document.documentElement.dataset.theme=t}catch(e){}</script>
    @vite(['resources/css/app.css', 'resources/js/student-portal.js'])
    <script src="{{ asset('js/notifications.js') }}" defer></script><script src="{{ asset('js/interactions.js') }}" defer></script>
</head>
<body class="student-shell app-shell min-h-screen bg-[#F7F4ED] font-sans text-[#121017] antialiased" data-student-shell>
@php
    $studentNavItems = [
        ['student.home', 'Home', 'M3 11.5 12 4l9 7.5V21h-6v-6H9v6H3v-9.5Z'],
        ['student.events.index', 'Events', 'M6 3v3m12-3v3M4 9h16M5 5h14a2 2 0 0 1 2 2v13H3V7a2 2 0 0 1 2-2Z'],
        ['student.attendance.show', 'Attendance', 'M5 4h14v16H5V4Zm3 4h8m-8 4 2 2 5-5m-7 9h8'],
        ['student.team.show', 'Team', 'M8 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-1a3 3 0 1 0 0-6M2 21v-2a5 5 0 0 1 5-5h2a5 5 0 0 1 5 5v2m1-7a5 5 0 0 1 7 4.6V21'],
        ['student.leaderboard.index', 'Rankings', 'M8 21h8m-4-4v4M7 4h10v4a5 5 0 0 1-10 0V4Zm0 2H4v1a4 4 0 0 0 4 4m9-5h3v1a4 4 0 0 1-4 4'],
    ];
@endphp
<span class="route-progress" data-route-progress data-active="false" aria-hidden="true"></span>

<aside class="fixed inset-y-0 left-0 z-50 hidden w-20 flex-col border-r border-[#397565]/35 bg-[#121017] text-[#F3F0E9] shadow-[8px_0_30px_rgba(18,16,23,.16)] transition-[width] duration-200 lg:flex" data-student-sidebar aria-label="Desktop student navigation">
    <div class="flex h-[68px] shrink-0 items-center gap-3 border-b border-white/10 px-3" data-sidebar-header>
        <span class="hidden flex-1 whitespace-nowrap text-center text-xs font-black uppercase tracking-[.12em] text-[#C6F24E]" data-sidebar-label>
            Student Menu
        </span>
        <button class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-[#C6F24E]/25 bg-[#C6F24E]/12 text-[#C6F24E] shadow-sm transition hover:bg-[#C6F24E]/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C6F24E]" type="button" data-student-sidebar-toggle aria-controls="student-desktop-nav" aria-expanded="false" aria-label="Expand navigation">
            <svg class="h-6 w-6 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>
    <nav class="grid gap-2 px-3 py-5" id="student-desktop-nav">
        @foreach($studentNavItems as [$route,$label,$path])
            <a href="{{ route($route) }}" title="{{ $label }}" @class(['group flex min-h-14 items-center gap-4 overflow-hidden rounded-2xl border px-3 text-sm font-black transition','border-[#C6F24E]/30 bg-[#397565]/55 text-[#C6F24E] shadow-lg shadow-black/20'=>request()->routeIs($route),'border-white/5 bg-white/[.06] text-[#F3F0E9]/75 hover:border-[#C6F24E]/20 hover:bg-white/[.1] hover:text-[#C6F24E]'=>!request()->routeIs($route)]) aria-label="{{ $label }}" @if(request()->routeIs($route)) aria-current="page" @endif>
                <svg class="h-6 w-6 shrink-0 fill-none stroke-current stroke-[2.1] transition group-hover:scale-105" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $path }}"/></svg><span class="hidden whitespace-nowrap" data-sidebar-label>{{ $label }}</span>
            </a>
        @endforeach
    </nav>
    <div class="mx-3 mt-auto mb-5 hidden rounded-2xl border border-white/10 bg-white/[.06] p-3" data-sidebar-label><span class="block text-[9px] font-black uppercase tracking-wider text-[#C6F24E]">Signed in as</span><strong class="mt-1 block truncate text-xs text-[#F3F0E9]">{{ auth()->user()->full_name }}</strong></div>
</aside>

<header class="sticky top-0 z-40 border-b border-[#121017]/8 bg-white/90 backdrop-blur-xl transition-[margin] duration-200 lg:ml-20" data-student-sidebar-content>
    <nav class="flex h-[68px] w-full items-center pl-3 pr-2 sm:pl-4 sm:pr-3 lg:pl-4 lg:pr-4" aria-label="Student header">
        <a class="flex items-center gap-2.5 text-lg font-black tracking-tight" href="{{ route('student.home') }}"><img class="h-9 w-9 rounded-full object-contain" src="{{ asset('images/cite-logo.png') }}" alt="CITE logo"><span>CITE<span class="text-[#397565]">.</span></span></a>
        <details class="group relative ml-auto" data-notification-menu>
            <summary
                class="relative grid h-11 w-11 cursor-pointer list-none place-items-center rounded-full bg-[#121017]/8 text-[#121017] transition hover:bg-[#397565]/15 hover:text-[#397565] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#397565]"
                aria-label="{{ $studentUnreadNotificationCount > 0 ? $studentUnreadNotificationCount.' unread '.str('notification')->plural($studentUnreadNotificationCount) : 'Notifications' }}"
            >
                <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 22a2.5 2.5 0 0 0 2.35-1.65h-4.7A2.5 2.5 0 0 0 12 22Zm7-6.5-1.5-2V9a5.5 5.5 0 0 0-4.25-5.35V3a1.25 1.25 0 0 0-2.5 0v.65A5.5 5.5 0 0 0 6.5 9v4.5l-1.5 2V18h14v-2.5Z"/>
                </svg>

                @if ($studentUnreadNotificationCount > 0)
                    <span class="absolute -right-1 -top-1 grid min-h-5 min-w-5 place-items-center rounded-full border-2 border-white bg-[#FF4D4F] px-1 text-[9px] font-black leading-none text-white">
                        {{ $studentUnreadNotificationCount > 9 ? '9+' : $studentUnreadNotificationCount }}
                    </span>
                @endif
            </summary>

            <div class="absolute right-0 top-[calc(100%+.65rem)] w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-[#121017]/10 bg-white shadow-[0_22px_60px_rgba(18,16,23,.18)]">
                <div class="flex min-h-14 items-center justify-between gap-3 border-b border-[#121017]/8 px-4 py-3">
                    <div>
                        <strong class="block text-sm font-black">Notifications</strong>
                        <span class="text-[10px] text-[#121017]/45">
                            {{ $studentUnreadNotificationCount }} unread
                        </span>
                    </div>

                    @if ($studentUnreadNotificationCount > 0)
                        <form method="POST" action="{{ route('student.notifications.read') }}">
                            @csrf
                            @method('PATCH')
                            <button class="min-h-9 rounded-lg px-2.5 text-[10px] font-black text-[#397565] transition hover:bg-[#397565]/10" type="submit">
                                Mark all as read
                            </button>
                        </form>
                    @endif
                </div>

                <div class="max-h-96 overflow-y-auto">
                    @forelse ($studentNotifications as $notification)
                        @php
                            $notificationStatus = $notification->data['status'] ?? null;
                            $isApprovedNotification = $notificationStatus === 'approved';
                        @endphp
                        <article @class([
                            'flex gap-3 border-b border-[#121017]/7 px-4 py-3 last:border-b-0',
                            'bg-[#397565]/6' => is_null($notification->read_at),
                        ])>
                            <span @class([
                                'mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-full',
                                'bg-[#C6F24E]/30 text-[#397565]' => $isApprovedNotification,
                                'bg-[#FF6B2C]/12 text-[#c84510]' => !$isApprovedNotification,
                            ])>
                                @if ($isApprovedNotification)
                                    <svg class="h-4 w-4 fill-none stroke-current stroke-[2.5]" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
                                @else
                                    <svg class="h-4 w-4 fill-none stroke-current stroke-[2.5]" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                                @endif
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-bold leading-5 text-[#121017]">
                                    {{ $notification->data['message'] ?? 'You have a new notification.' }}
                                </p>
                                @if (!empty($notification->data['reason']))
                                    <p class="mt-1 line-clamp-2 text-[10px] leading-4 text-[#121017]/55">
                                        {{ $notification->data['reason'] }}
                                    </p>
                                @endif
                                <time class="mt-1 block text-[10px] font-bold text-[#397565]" datetime="{{ $notification->created_at->toIso8601String() }}">
                                    {{ $notification->created_at->format('M j, Y · g:i A') }}
                                </time>
                            </div>
                            @if (is_null($notification->read_at))
                                <span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-[#397565]" aria-label="Unread"></span>
                            @endif
                        </article>
                    @empty
                        <div class="px-6 py-10 text-center">
                            <span class="mx-auto grid h-11 w-11 place-items-center rounded-full bg-[#121017]/7 text-[#121017]/45">
                                <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22a2.5 2.5 0 0 0 2.35-1.65h-4.7A2.5 2.5 0 0 0 12 22Zm7-6.5-1.5-2V9a5.5 5.5 0 0 0-4.25-5.35V3a1.25 1.25 0 0 0-2.5 0v.65A5.5 5.5 0 0 0 6.5 9v4.5l-1.5 2V18h14v-2.5Z"/></svg>
                            </span>
                            <strong class="mt-3 block text-sm">No notifications yet</strong>
                            <p class="mt-1 text-xs text-[#121017]/45">Post review updates will appear here.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </details>

        <details class="group relative ml-1" data-account-menu>
            <summary class="flex min-h-11 cursor-pointer list-none items-center gap-1 rounded-full p-1 transition hover:bg-[#397565]/7">
                <x-student-avatar :user="auth()->user()" size="h-9 w-9" />
                <svg class="h-4 w-4 fill-none stroke-current stroke-2 transition group-open:rotate-180" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m7 10 5 5 5-5"/>
                </svg>
            </summary>
            <div class="absolute right-0 top-[calc(100%+.6rem)] w-64 rounded-2xl border border-[#121017]/10 bg-white p-2 shadow-[0_22px_60px_rgba(18,16,23,.16)]">
                <div class="border-b border-[#121017]/7 px-3 py-3"><strong class="block truncate text-sm">{{ auth()->user()->full_name }}</strong><span class="text-xs text-[#121017]/45">{{ auth()->user()->email }}</span></div>
                @foreach([[route('student.profile.show'),'View Profile'],[route('student.profile.edit'),'Edit Profile'],[route('student.settings'),'Account Settings']] as [$url,$label])<a class="flex min-h-11 items-center rounded-xl px-3 text-sm font-bold text-[#121017]/65 hover:bg-[#397565]/8 hover:text-[#397565]" href="{{ $url }}">{{ $label }}</a>@endforeach
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="flex min-h-11 w-full items-center rounded-xl px-3 text-sm font-bold text-[#FF6B2C] hover:bg-[#FF6B2C]/8" type="submit">Logout</button></form>
            </div>
        </details>
    </nav>
</header>
<main class="w-auto px-4 pb-28 pt-6 transition-[margin] duration-200 sm:px-6 sm:pt-8 lg:ml-20 lg:pb-10 lg:pl-6 lg:pr-5" data-page-content data-student-sidebar-content>@yield('content')</main>
<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-[#397565]/35 bg-[#121017]/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-12px_35px_rgba(18,16,23,.18)] backdrop-blur-xl lg:hidden" aria-label="Student pages">
    <div class="mx-auto grid h-[76px] max-w-3xl grid-cols-5 gap-1 px-1.5 py-1.5">
        @foreach ($studentNavItems as [$route, $label, $path])
            <a
                href="{{ route($route) }}"
                @class([
                    'group flex min-h-11 flex-col items-center justify-center gap-1 rounded-xl text-[10px] font-black transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#C6F24E] focus-visible:ring-inset',
                    'bg-[#397565]/55 text-[#C6F24E] shadow-sm ring-1 ring-[#C6F24E]/20' => request()->routeIs($route),
                    'text-[#F3F0E9]/65 hover:bg-white/[.08] hover:text-[#C6F24E]' => !request()->routeIs($route),
                ])
                aria-label="{{ $label }}"
                @if (request()->routeIs($route)) aria-current="page" @endif
            >
                <svg class="h-5 w-5 fill-none stroke-current stroke-[2.1] group-active:scale-90" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="{{ $path }}"/>
                </svg>
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </div>
</nav>
<x-notifications />
@stack('scripts')
</body></html>
