@extends('layouts.adviser')

@section('title', 'Edit '.$team->name)

@section('content')
    <a class="mb-6 inline-flex items-center gap-2 text-xs font-extrabold text-slate-500 transition hover:text-emerald-700" href="{{ route('adviser.teams.index') }}"><span aria-hidden="true">←</span>Back to tribe management</a>
    <header class="mb-8 flex items-start gap-4"><span class="mt-1 grid h-12 w-12 shrink-0 place-items-center rounded-xl text-sm font-black text-white shadow-sm" style="background-color: {{ $team->color }}">{{ strtoupper(substr($team->name, 0, 2)) }}</span><div><p class="mb-2 text-xs font-extrabold uppercase tracking-[.14em] text-emerald-700">Tribe Details</p><h1 class="text-3xl font-extrabold tracking-tight text-[#121017] sm:text-4xl">Edit {{ $team->name }}</h1><p class="mt-2 text-sm text-slate-500">Update its identity, school year, or student roster.</p></div></header>

    <form method="POST" action="{{ route('adviser.teams.update', $team) }}" data-tribe-form>@csrf @method('PUT')
        @include('adviser.teams._form')
    </form>
@endsection
