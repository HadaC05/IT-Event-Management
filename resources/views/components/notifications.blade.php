@php
    $flash = collect(['success', 'error', 'warning', 'info'])
        ->mapWithKeys(fn ($type) => session()->has($type) ? [$type => session($type)] : [])
        ->first(fn ($message) => filled($message));
    $flashType = collect(['success', 'error', 'warning', 'info'])->first(fn ($type) => session()->has($type));
@endphp

<div class="pointer-events-none fixed right-4 top-4 z-[80] grid w-[min(380px,calc(100%_-_2rem))] gap-2.5 sm:right-6 sm:top-6" data-notification-region data-flash-type="{{ $flashType }}" data-flash-message="{{ $flash }}" aria-live="polite" aria-atomic="true"></div>
<div class="pointer-events-none fixed bottom-4 right-4 z-[75] w-[min(470px,calc(100%_-_2rem))] sm:bottom-6 sm:right-6" data-snackbar-region aria-live="polite" aria-atomic="true"></div>

<dialog class="m-auto w-[min(440px,calc(100%_-_2rem))] rounded-2xl border-0 bg-white p-0 text-slate-700 shadow-2xl backdrop:bg-[#141e46]/60 backdrop:backdrop-blur-[2px]" data-confirm-dialog aria-labelledby="confirm-title" aria-describedby="confirm-message">
    <div class="flex items-start gap-4 p-6 pb-5">
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-red-50 text-red-600" aria-hidden="true"><svg class="h-5 w-5 fill-none stroke-current" viewBox="0 0 24 24"><path d="M12 9v4m0 4h.01M10.3 3.6 2.4 17.2A2 2 0 0 0 4.1 20h15.8a2 2 0 0 0 1.7-2.8L13.7 3.6a2 2 0 0 0-3.4 0Z"/></svg></span>
        <div><h2 class="text-lg font-extrabold tracking-tight text-[#141e46]" id="confirm-title" data-confirm-title>Confirm action</h2><p class="mt-1.5 text-sm leading-6 text-slate-500" id="confirm-message" data-confirm-message>Are you sure you want to continue?</p></div>
    </div>
    <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
        <button class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-600 hover:bg-slate-50" type="button" data-confirm-cancel>Cancel</button>
        <button class="inline-flex min-h-10 items-center justify-center rounded-xl bg-red-600 px-4 text-sm font-bold text-white hover:bg-red-700" type="button" data-confirm-accept>Confirm</button>
    </div>
</dialog>
