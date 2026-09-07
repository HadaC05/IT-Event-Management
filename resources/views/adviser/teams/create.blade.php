@extends('layouts.adviser')

@section('title', 'Create Tribe')

@section('content')
    <a class="mb-6 inline-flex items-center gap-2 text-xs font-extrabold text-slate-500 transition hover:text-emerald-700" href="{{ route('adviser.teams.index') }}"><span aria-hidden="true">←</span>Back to tribe management</a>
    <header class="mb-8"><p class="mb-2 text-xs font-extrabold uppercase tracking-[.14em] text-emerald-700">New Tribe</p><h1 class="text-3xl font-extrabold tracking-tight text-[#121017] sm:text-4xl">Create a Tribe</h1><p class="mt-2 text-sm text-slate-500">Set up the tribe identity and choose its student members.</p></header>

    <form method="POST" action="{{ route('adviser.teams.store') }}" data-tribe-form>@csrf
        @include('adviser.teams._form')
    </form>
@endsection
