@extends('layouts.student')
@section('title', 'Account Settings')
@section('content')
<div class="mx-auto max-w-3xl">
    <header><p class="text-[10px] font-black uppercase tracking-[.15em] text-[#397565]">Student account</p><h1 class="mt-1 text-3xl font-black">Account settings</h1><p class="mt-2 text-sm text-[#121017]/50">Personalize how your CITE portal looks and manage account security.</p></header>

    <section class="mt-7 rounded-3xl border border-[#121017]/8 bg-white p-5 sm:p-6">
        <div><h2 class="text-lg font-black">Appearance</h2><p class="mt-1 text-xs text-[#121017]/45">Choose the theme used across your student pages.</p></div>
        <div class="mt-5 grid gap-3 sm:grid-cols-3" role="group" aria-label="Color theme">
            @foreach([
                ['light', 'Light', 'Bright and warm', 'bg-[#F7F4ED]', 'bg-white'],
                ['dark', 'Dark', 'Easy on the eyes', 'bg-[#0d1714]', 'bg-[#17231f]'],
                ['system', 'System', 'Match your device', 'bg-gradient-to-r from-[#F7F4ED] to-[#0d1714]', 'bg-white'],
            ] as [$value,$label,$description,$preview,$card])
            <button class="relative min-h-32 rounded-2xl border border-[#121017]/10 p-3 text-left transition hover:border-[#397565]/40" type="button" data-theme-choice="{{ $value }}" aria-pressed="false">
                <span class="mb-3 block h-14 overflow-hidden rounded-xl {{ $preview }}" data-theme-swatch><i class="m-2 block h-10 w-3/4 rounded-lg {{ $card }} shadow-sm" data-theme-swatch></i></span>
                <strong class="block text-sm">{{ $label }}</strong><span class="text-[10px] text-[#121017]/45">{{ $description }}</span>
                <span class="absolute right-3 top-3 grid h-6 w-6 place-items-center rounded-full bg-[#397565] text-white opacity-0" data-theme-check aria-hidden="true">✓</span>
            </button>
            @endforeach
        </div>
    </section>

    <section class="mt-5 flex flex-col gap-4 rounded-3xl border border-[#121017]/8 bg-white p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6"><div><h2 class="font-black">Password and security</h2><p class="mt-1 text-xs text-[#121017]/45">Update the password used to access your student account.</p></div><a class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl border border-[#397565]/20 px-5 text-xs font-black text-[#397565] hover:bg-[#397565]/8" href="{{ route('password.change') }}">Change password</a></section>
</div>
@endsection
