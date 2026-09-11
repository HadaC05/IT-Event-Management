@extends('layouts.student')
@section('title', 'Profile')
@section('content')
<div class="grid items-start gap-6 lg:grid-cols-[380px_minmax(0,1fr)] xl:grid-cols-[420px_minmax(0,1fr)]">
    <aside class="space-y-5 lg:sticky lg:top-24">
        <section class="overflow-hidden rounded-3xl border border-[#121017]/8 bg-white shadow-[0_16px_45px_rgba(18,16,23,.06)]">
        <div class="h-28 bg-gradient-to-br from-[#397565] to-[#244f44]"></div>
        <div class="px-5 pb-6 sm:px-6">
            <div class="-mt-12 flex items-end justify-between gap-3">
                <x-student-avatar :user="$user" size="h-24 w-24"/>
                <a class="mb-1 inline-flex min-h-11 items-center rounded-xl bg-[#397565] px-4 text-xs font-black text-white shadow-lg shadow-[#397565]/15 hover:bg-[#2f6658]" href="{{ route('student.profile.edit') }}">Edit Profile</a>
            </div>

            <div class="mt-4"><h1 class="text-2xl font-black leading-tight">{{ $user->full_name }}</h1><p class="mt-1 text-sm font-semibold text-[#397565]">{{ '@'.$user->username }}</p></div>
            <p class="mt-5 text-sm leading-6 text-[#121017]/60">{{ $user->bio ?: 'No bio added yet.' }}</p>

            <dl class="mt-6 grid gap-3">
                <div class="flex items-center gap-3 rounded-2xl bg-[#F7F4ED] p-4"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white text-[#397565]"><svg class="h-4 w-4 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5V4Zm4 4h6m-6 4h6"/></svg></span><div><dt class="text-[9px] font-black uppercase tracking-wider text-[#121017]/40">Student ID</dt><dd class="mt-0.5 text-sm font-black">{{ $user->id_number }}</dd></div></div>
                <div class="flex items-center gap-3 rounded-2xl bg-[#F7F4ED] p-4"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white text-[#397565]"><svg class="h-4 w-4 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v14H4V6Zm4-3v6m8-6v6"/></svg></span><div><dt class="text-[9px] font-black uppercase tracking-wider text-[#121017]/40">Year level</dt><dd class="mt-0.5 text-sm font-black">{{ $user->yearLevel?->label ?? 'Not assigned' }}</dd></div></div>
                <div class="flex items-center gap-3 rounded-2xl bg-[#F7F4ED] p-4"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white text-[#397565]"><svg class="h-4 w-4 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-1a3 3 0 1 0 0-6M2 21v-2a5 5 0 0 1 5-5h2a5 5 0 0 1 5 5v2m1-7a5 5 0 0 1 7 5v2"/></svg></span><div><dt class="text-[9px] font-black uppercase tracking-wider text-[#121017]/40">Tribe</dt><dd class="mt-0.5 text-sm font-black">{{ $user->teams->pluck('name')->join(', ') ?: 'Not assigned' }}</dd></div></div>
            </dl>
        </div>
        </section>
        @include('student._submissions')
    </aside>

    <section class="min-w-0">
        <header class="mb-5 flex items-end justify-between gap-4"><div><p class="text-[10px] font-black uppercase tracking-[.15em] text-[#397565]">Profile feed</p><h2 class="mt-1 text-2xl font-black">Your posts</h2></div><span class="text-xs text-[#121017]/40">{{ $posts->total() }} published</span></header>

        <div class="space-y-5">
            @forelse($posts as $post)
            <article class="overflow-hidden rounded-3xl border border-[#121017]/8 bg-white shadow-[0_12px_35px_rgba(18,16,23,.045)]">
                <header class="flex items-center gap-3 p-5"><x-student-avatar :user="$user"/><div class="min-w-0 flex-1"><strong class="block truncate text-sm">{{ $user->full_name }}</strong><div class="flex flex-wrap items-center gap-1.5 text-[10px] text-[#121017]/40"><time datetime="{{ $post->reviewed_at?->toIso8601String() }}">{{ $post->reviewed_at?->diffForHumans() }}</time><span>·</span><span class="font-bold text-[#397565]">{{ str($post->category)->replace('-', ' ')->title() }}</span>@if($post->event)<span>· {{ $post->event->title }}</span>@endif</div></div><span class="rounded-full bg-[#397565]/8 px-2.5 py-1 text-[9px] font-black text-[#397565]">Published</span></header>
                <div class="px-5 pb-5"><p class="whitespace-pre-line text-sm leading-6">{{ $post->content }}</p></div>
                @if($post->image_path)<img class="max-h-[620px] w-full border-t border-[#121017]/7 bg-[#F7F4ED] object-contain" src="{{ asset('storage/'.$post->image_path) }}" alt="Image attached to your post" loading="lazy">@endif
            </article>
            @empty
            <div class="rounded-3xl border border-dashed border-[#397565]/25 bg-white px-6 py-20 text-center"><span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-[#397565]/10 text-[#397565]"><svg class="h-6 w-6 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5V4Zm4 4h6m-6 4h6m-6 4h4"/></svg></span><h3 class="mt-4 font-black">No published posts yet</h3><p class="mt-1 text-sm text-[#121017]/45">Posts appear here after an adviser approves them.</p><a class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-[#397565] px-5 text-xs font-black text-white" href="{{ route('student.home') }}">Create a post</a></div>
            @endforelse
        </div>

        @if($posts->hasPages())<div class="mt-6">{{ $posts->links() }}</div>@endif
    </section>
</div>
@endsection
