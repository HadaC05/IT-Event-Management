@extends('layouts.adviser')

@section('title', 'Announcements')

@section('content')
    <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-black uppercase tracking-[.14em] text-[#397565]">Official communication</p>
            <h1 class="mt-2 text-4xl font-black tracking-[-.05em] text-[#121017] sm:text-5xl">Announcements</h1>
            <p class="mt-3 max-w-2xl text-base leading-7 text-[#121017]/55">Publish verified updates directly to the student feed or save them as drafts for later.</p>
        </div>
        <a class="inline-flex min-h-12 w-fit items-center justify-center gap-2 rounded-xl border border-[#397565]/20 bg-white px-5 text-sm font-black text-[#397565] transition hover:bg-[#397565]/7" href="{{ route('adviser.posts.index') }}">
            Review student posts
            <span class="rounded-full bg-[#C6F24E] px-2 py-0.5 text-xs text-[#121017]">{{ number_format(\App\Models\Post::community()->where('status', 'pending')->count()) }}</span>
        </a>
    </header>

    <section class="mt-8 overflow-hidden rounded-2xl bg-[#121017] text-white shadow-[0_22px_55px_rgba(18,16,23,.16)]" aria-labelledby="announcement-composer-title">
        <div class="grid xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,.65fr)]">
            <form class="p-5 sm:p-7" method="POST" action="{{ route('adviser.announcements.store') }}" enctype="multipart/form-data" data-announcement-form>
                @csrf
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div><p class="text-xs font-black uppercase tracking-[.14em] text-[#C6F24E]">New announcement</p><h2 class="mt-2 text-2xl font-black" id="announcement-composer-title">What should students know?</h2></div>
                    <span class="w-fit rounded-full bg-white/8 px-3 py-1.5 text-xs font-bold text-white/55" data-announcement-count>0 / 3,000</span>
                </div>

                <label class="mt-5 block"><span class="sr-only">Announcement message</span><textarea class="min-h-40 w-full resize-y rounded-xl border border-white/12 bg-white/[.07] p-4 text-base leading-7 text-white outline-none placeholder:text-white/30 focus:border-[#C6F24E]/60 focus:bg-white/10 focus:ring-4 focus:ring-[#C6F24E]/10" name="content" placeholder="Write a clear update, deadline, reminder, or event instruction…" required maxlength="3000" data-announcement-content>{{ old('content') }}</textarea></label>
                <x-form-error class="mt-2 text-[#FF9A70]" name="content" />

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="grid gap-2"><span class="text-sm font-bold text-white/65">Where should it appear?</span><select class="h-12 rounded-xl border border-white/12 bg-[#242129] px-4 text-sm font-bold text-white outline-none focus:border-[#C6F24E]/60" name="event_id" data-announcement-event><option value="">General student feed</option>@foreach($events as $event)<option value="{{ $event->id }}" @selected((int) old('event_id') === $event->id)>{{ $event->title }} · {{ $event->start_at->format('M j') }}</option>@endforeach</select><small class="text-xs leading-5 text-white/38">Choose an event when the message only concerns that activity.</small><x-form-error class="text-[#FF9A70]" name="event_id" /></label>
                    <label class="grid gap-2"><span class="text-sm font-bold text-white/65">Optional image</span><span class="flex min-h-12 cursor-pointer items-center gap-3 rounded-xl border border-dashed border-white/18 bg-white/[.05] px-4 text-sm font-bold text-white/55 transition hover:border-[#C6F24E]/50 hover:text-white"><svg class="h-5 w-5 fill-none stroke-current stroke-2" viewBox="0 0 24 24"><path d="M4 16l4-4 4 4 3-3 5 5M4 5h16v14H4V5Z"/></svg><span class="truncate" data-announcement-file>Choose JPG, PNG or WebP</span><input class="sr-only" type="file" name="image" accept="image/jpeg,image/png,image/webp" data-announcement-image></span><small class="text-xs leading-5 text-white/38">Up to 5 MB and 4,096 × 4,096 pixels.</small><x-form-error class="text-[#FF9A70]" name="image" /></label>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                    <button class="min-h-12 rounded-xl border border-white/15 px-5 text-sm font-black text-white/70 transition hover:bg-white/8 hover:text-white" type="submit" name="intent" value="draft">Save draft</button>
                    <button class="min-h-12 rounded-xl bg-[#C6F24E] px-6 text-sm font-black text-[#121017] shadow-[0_10px_25px_rgba(198,242,78,.16)] transition hover:-translate-y-0.5 hover:bg-[#d2f970]" type="submit" name="intent" value="publish">Publish announcement</button>
                </div>
            </form>

            <aside class="border-t border-white/10 bg-white/[.045] p-5 sm:p-7 xl:border-l xl:border-t-0">
                <p class="text-xs font-black uppercase tracking-[.14em] text-[#C6F24E]">Before publishing</p>
                <ul class="mt-5 space-y-4 text-sm leading-6 text-white/58">
                    <li class="flex gap-3"><span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-[#C6F24E] text-xs font-black text-[#121017]">1</span><span>State the action students need to take and the deadline.</span></li>
                    <li class="flex gap-3"><span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-[#C6F24E] text-xs font-black text-[#121017]">2</span><span>Select an event only when the announcement is event-specific.</span></li>
                    <li class="flex gap-3"><span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-[#C6F24E] text-xs font-black text-[#121017]">3</span><span>Published announcements appear immediately and carry an official badge.</span></li>
                </ul>
                <div class="mt-6 rounded-xl border border-[#FF6B2C]/20 bg-[#FF6B2C]/8 p-4"><strong class="block text-sm text-[#FF9A70]">Need another review?</strong><p class="mt-1 text-sm leading-6 text-white/48">Use Save draft. Drafts never appear in the student feed.</p></div>
            </aside>
        </div>
    </section>

    <section class="mt-5 overflow-hidden rounded-2xl border border-[#121017]/9 bg-white shadow-[0_16px_45px_rgba(18,16,23,.05)]" aria-label="Announcement summary">
        <div class="grid grid-cols-2 xl:grid-cols-4">
            <div class="border-b border-r border-[#121017]/7 px-5 py-5 xl:border-b-0"><span class="text-xs font-bold text-[#121017]/45">Published</span><strong class="mt-1 block text-2xl font-black text-[#397565]">{{ number_format($summary['published']) }}</strong></div>
            <div class="border-b border-[#121017]/7 px-5 py-5 xl:border-b-0 xl:border-r"><span class="text-xs font-bold text-[#121017]/45">Drafts</span><strong class="mt-1 block text-2xl font-black">{{ number_format($summary['drafts']) }}</strong></div>
            <div class="border-r border-[#121017]/7 px-5 py-5"><span class="text-xs font-bold text-[#121017]/45">Event feeds</span><strong class="mt-1 block text-2xl font-black">{{ number_format($summary['events']) }}</strong></div>
            <div class="px-5 py-5"><span class="text-xs font-bold text-[#121017]/45">Archived</span><strong class="mt-1 block text-2xl font-black text-[#121017]/45">{{ number_format($summary['archived']) }}</strong></div>
        </div>
    </section>

    <section class="mt-5 overflow-hidden rounded-2xl border border-[#121017]/9 bg-white shadow-[0_16px_45px_rgba(18,16,23,.05)]" aria-labelledby="announcement-library-title">
        <header class="border-b border-[#121017]/7 p-5 sm:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"><div><p class="text-xs font-black uppercase tracking-[.14em] text-[#397565]">Announcement library</p><h2 class="mt-1 text-2xl font-black" id="announcement-library-title">Published updates and drafts</h2></div><span class="text-sm font-bold text-[#121017]/40">{{ number_format($announcements->total()) }} {{ Str::plural('announcement', $announcements->total()) }}</span></div>
            <form class="mt-5 grid gap-3 md:grid-cols-[1fr_220px_220px_auto]" method="GET" action="{{ route('adviser.announcements.index') }}">
                <label class="grid gap-2"><span class="text-sm font-bold text-[#121017]/60">Search</span><input class="h-12 rounded-xl border border-[#121017]/10 bg-[#F3F0E9]/45 px-4 text-sm outline-none placeholder:text-[#121017]/30 focus:border-[#397565] focus:bg-white focus:ring-4 focus:ring-[#397565]/8" name="search" value="{{ request('search') }}" placeholder="Search announcement text…"></label>
                <label class="grid gap-2"><span class="text-sm font-bold text-[#121017]/60">Status</span><select class="h-12 rounded-xl border border-[#121017]/10 bg-white px-4 text-sm font-bold outline-none focus:border-[#397565]" name="status"><option value="all" @selected(request('status', 'all') === 'all')>Published and drafts</option><option value="published" @selected(request('status') === 'published')>Published only</option><option value="draft" @selected(request('status') === 'draft')>Drafts only</option><option value="archived" @selected(request('status') === 'archived')>Archived</option></select></label>
                <label class="grid gap-2"><span class="text-sm font-bold text-[#121017]/60">Event</span><select class="h-12 rounded-xl border border-[#121017]/10 bg-white px-4 text-sm font-bold outline-none focus:border-[#397565]" name="event_id"><option value="">All feeds</option>@foreach($events as $event)<option value="{{ $event->id }}" @selected((int) request('event_id') === $event->id)>{{ $event->title }}</option>@endforeach</select></label>
                <button class="mt-auto min-h-12 rounded-xl bg-[#397565] px-5 text-sm font-black text-white transition hover:bg-[#2f6658]" type="submit">Apply filters</button>
            </form>
        </header>

        <div class="divide-y divide-[#121017]/7">
            @forelse($announcements as $announcement)
                @php
                    $isPublished = $announcement->status === 'approved' && ! $announcement->trashed();
                    $statusLabel = $announcement->trashed() ? 'Archived' : ($isPublished ? 'Published' : 'Draft');
                    $statusTone = $announcement->trashed() ? 'bg-[#121017]/7 text-[#121017]/48' : ($isPublished ? 'bg-[#C6F24E]/35 text-[#397565]' : 'bg-[#FF6B2C]/10 text-[#c84510]');
                @endphp
                <article class="p-5 sm:p-6">
                    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_auto]">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2"><span class="rounded-full px-3 py-1.5 text-xs font-black {{ $statusTone }}">{{ $statusLabel }}</span><span class="text-xs font-bold text-[#121017]/38">{{ $announcement->event?->title ?? 'General student feed' }}</span><span class="text-xs text-[#121017]/30">· Updated {{ $announcement->updated_at->diffForHumans() }}</span></div>
                            <p class="mt-4 whitespace-pre-line text-base leading-7 text-[#121017]/75">{{ $announcement->content }}</p>
                            @if($announcement->image_path)<div class="mt-4 flex items-center gap-3 rounded-xl bg-[#F3F0E9]/60 p-3"><img class="h-16 w-16 rounded-lg object-cover" src="{{ asset('storage/'.$announcement->image_path) }}" alt="Announcement attachment"><span><strong class="block text-sm">Image attached</strong><small class="text-xs text-[#121017]/42">Shown below the announcement in the student feed</small></span></div>@endif
                        </div>

                        <div class="flex flex-wrap items-start gap-2 lg:max-w-64 lg:justify-end">
                            @if($announcement->trashed())
                                <form method="POST" action="{{ route('adviser.announcements.restore', $announcement->id) }}">@csrf<button class="min-h-11 rounded-xl bg-[#397565] px-4 text-sm font-black text-white" type="submit">Restore as draft</button></form>
                            @else
                                <form method="POST" action="{{ route('adviser.announcements.status', $announcement) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $isPublished ? 'draft' : 'approved' }}"><button class="min-h-11 rounded-xl px-4 text-sm font-black {{ $isPublished ? 'border border-[#121017]/10 text-[#121017]/60 hover:bg-[#121017]/5' : 'bg-[#C6F24E] text-[#121017] hover:bg-[#d2f970]' }}" type="submit">{{ $isPublished ? 'Unpublish' : 'Publish' }}</button></form>
                                <details class="group"><summary class="inline-flex min-h-11 cursor-pointer list-none items-center rounded-xl border border-[#2F3AE0]/20 px-4 text-sm font-black text-[#2F3AE0] hover:bg-[#2F3AE0]/5">Edit</summary><form class="mt-3 grid w-full gap-4 rounded-xl border border-[#121017]/9 bg-[#F3F0E9]/55 p-4 lg:w-[520px]" method="POST" action="{{ route('adviser.announcements.update', $announcement) }}" enctype="multipart/form-data">@csrf @method('PUT')<label class="grid gap-2"><span class="text-sm font-bold">Message</span><textarea class="min-h-32 rounded-xl border border-[#121017]/10 bg-white p-4 text-sm leading-6 outline-none focus:border-[#397565]" name="content" maxlength="3000" required>{{ $announcement->content }}</textarea></label><label class="grid gap-2"><span class="text-sm font-bold">Event feed</span><select class="h-11 rounded-xl border border-[#121017]/10 bg-white px-3 text-sm" name="event_id"><option value="">General student feed</option>@foreach($events as $event)<option value="{{ $event->id }}" @selected($announcement->event_id === $event->id)>{{ $event->title }}</option>@endforeach</select></label><label class="grid gap-2"><span class="text-sm font-bold">Replace image</span><input class="rounded-xl border border-[#121017]/10 bg-white p-3 text-sm" type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>@if($announcement->image_path)<label class="flex items-center gap-2 text-sm text-[#121017]/60"><input type="checkbox" name="remove_image" value="1"> Remove current image</label>@endif<div class="flex flex-wrap justify-end gap-2"><button class="min-h-11 rounded-xl border border-[#121017]/10 bg-white px-4 text-sm font-black" type="submit" name="intent" value="draft">Save as draft</button><button class="min-h-11 rounded-xl bg-[#2F3AE0] px-4 text-sm font-black text-white" type="submit" name="intent" value="publish">Save and publish</button></div></form></details>
                                <form method="POST" action="{{ route('adviser.announcements.destroy', $announcement) }}" onsubmit="return confirm('Archive this announcement? It will disappear from the student feed.')">@csrf @method('DELETE')<button class="min-h-11 rounded-xl px-3 text-sm font-black text-[#c84510] hover:bg-[#FF6B2C]/8" type="submit">Archive</button></form>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="px-6 py-16 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-[#397565]/8 text-[#397565]"><svg class="h-6 w-6 fill-none stroke-current stroke-2" viewBox="0 0 24 24"><path d="M4 13V9l11-5v14L4 13Zm0 0v5h4v-3m7-7h3a3 3 0 0 1 0 6h-3"/></svg></span><h3 class="mt-5 text-lg font-black">No announcements found</h3><p class="mt-2 text-sm text-[#121017]/45">Create the first official update above or clear the current filters.</p></div>
            @endforelse
        </div>
        @if($announcements->hasPages())<div class="border-t border-[#121017]/7 px-5 py-4">{{ $announcements->links() }}</div>@endif
    </section>
@endsection

@push('scripts')
    <script>
        const announcementContent = document.querySelector('[data-announcement-content]');
        const announcementCount = document.querySelector('[data-announcement-count]');
        const announcementImage = document.querySelector('[data-announcement-image]');
        const announcementFile = document.querySelector('[data-announcement-file]');
        const updateAnnouncementCount = () => {
            if (announcementCount) announcementCount.textContent = `${announcementContent?.value.length || 0} / 3,000`;
        };
        announcementContent?.addEventListener('input', updateAnnouncementCount);
        announcementImage?.addEventListener('change', () => {
            if (announcementFile) announcementFile.textContent = announcementImage.files?.[0]?.name || 'Choose JPG, PNG or WebP';
        });
        updateAnnouncementCount();
    </script>
@endpush
