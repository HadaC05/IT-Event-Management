@props(['type' => 'info', 'title'])
@php
    $tone = match ($type) {
        'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
        'error' => 'border-red-200 bg-red-50 text-red-900',
        default => 'border-blue-200 bg-blue-50 text-blue-900',
    };
@endphp

<aside {{ $attributes->class("mb-5 flex items-start gap-3 rounded-xl border p-4 {$tone}") }} role="status">
    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-white/70" aria-hidden="true"><svg class="h-4 w-4 fill-none stroke-current" viewBox="0 0 24 24"><path d="M12 9v4m0 4h.01M10.3 3.6 2.4 17.2A2 2 0 0 0 4.1 20h15.8a2 2 0 0 0 1.7-2.8L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg></span>
    <div><strong class="text-sm">{{ $title }}</strong><p class="mt-1 text-xs leading-5 opacity-80">{{ $slot }}</p></div>
</aside>
