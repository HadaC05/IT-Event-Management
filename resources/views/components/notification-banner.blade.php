@props(['type' => 'info', 'title'])
@php
    $tone = match ($type) {
        'warning' => 'border-[#FF6B2C]/25 bg-[#FF6B2C]/8 text-[#121017]',
        'success' => 'border-[#397565]/25 bg-[#C6F24E]/25 text-[#121017]',
        'error' => 'border-[#FF6B2C]/25 bg-[#FF6B2C]/8 text-[#121017]',
        default => 'border-[#2F3AE0]/20 bg-[#2F3AE0]/8 text-[#121017]',
    };
@endphp

<aside {{ $attributes->class("mb-5 flex items-start gap-3 rounded-xl border p-4 {$tone}") }} role="status">
    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-white/70" aria-hidden="true"><svg class="h-4 w-4 fill-none stroke-current" viewBox="0 0 24 24"><path d="M12 9v4m0 4h.01M10.3 3.6 2.4 17.2A2 2 0 0 0 4.1 20h15.8a2 2 0 0 0 1.7-2.8L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg></span>
    <div><strong class="text-sm">{{ $title }}</strong><p class="mt-1 text-xs leading-5 opacity-80">{{ $slot }}</p></div>
</aside>
