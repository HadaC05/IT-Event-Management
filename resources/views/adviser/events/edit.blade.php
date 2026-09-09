@extends('layouts.adviser')

@section('title', 'Edit Event')

@section('content')
    <header class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
        <div><p class="mb-2 text-xs font-extrabold uppercase tracking-[.14em] text-emerald-700">Event Management</p><h1 class="text-3xl font-extrabold tracking-tight text-[#121017] sm:text-4xl">Edit Event</h1><p class="mt-2 text-sm text-slate-500">Update {{ $event->title }} details.</p></div>
        <a class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-bold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50" href="{{ route('adviser.events.show', $event) }}">Cancel</a>
    </header>

    <form method="POST" action="{{ route('adviser.events.update', $event) }}" enctype="multipart/form-data" data-edit-event-form>
        @csrf @method('PUT')
        @include('adviser.events._edit_form')
        <div class="sticky bottom-4 z-10 ml-auto mt-5 flex w-full gap-2 rounded-2xl border border-slate-200 bg-white/95 p-2 shadow-xl backdrop-blur sm:w-fit"><a class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border border-slate-200 px-5 text-sm font-bold text-slate-600 hover:bg-slate-50" href="{{ route('adviser.events.show', $event) }}">Cancel</a><button class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-emerald-600 px-5 text-sm font-bold text-white shadow-lg shadow-emerald-600/15 transition hover:bg-emerald-700" type="submit" data-loading-text="Saving…">Save Changes</button></div>
    </form>
@endsection
