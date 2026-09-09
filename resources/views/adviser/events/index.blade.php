@extends('layouts.adviser')

@section('title', 'Event Management')

@section('content')
    <header class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="mb-2 text-xs font-extrabold uppercase tracking-[.14em] text-emerald-700">Administration</p><h1 class="text-3xl font-extrabold tracking-tight text-[#121017] sm:text-4xl">Event Management</h1><p class="mt-2 text-sm text-slate-500">Create, organize, and assign the people responsible for each event.</p></div>
        <button class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 text-sm font-extrabold text-white shadow-lg shadow-emerald-600/15 transition hover:bg-emerald-700" type="button" data-dialog-open="create-event-dialog"><span class="text-xl font-normal" aria-hidden="true">+</span> Create Event</button>
    </header>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <form class="flex flex-wrap items-center gap-2 border-b border-slate-100 p-4" method="GET" action="{{ route('adviser.events.index') }}">
            <label class="relative min-w-56 flex-1"><svg class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-slate-400" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><span class="sr-only">Search events</span><input class="h-11 w-full rounded-xl border border-slate-200 pl-10 pr-3 text-sm outline-none placeholder:text-slate-400 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10" name="search" value="{{ request('search') }}" placeholder="Search events or locations…"></label>
            <label><span class="sr-only">Filter by status</span><select class="h-11 min-w-36 rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-600 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10" name="status"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status->id }}" @selected((string)request('status') === (string)$status->id)>{{ $status->label === 'inactive' ? 'Deactivated' : ucfirst($status->label) }}</option>@endforeach</select></label>
            <label><span class="sr-only">Filter by event type</span><select class="h-11 min-w-36 rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-600 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10" name="event_type"><option value="">All event types</option>@foreach($eventTypes as $type)<option value="{{ $type->id }}" @selected((string) request('event_type') === (string) $type->id)>{{ $type->label }}</option>@endforeach</select></label>
            <button class="inline-flex h-11 items-center rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-600 hover:bg-slate-50" type="submit">Apply</button>
            @if(request()->hasAny(['search','status','event_type']))<a class="px-2 py-3 text-xs font-bold text-emerald-700" href="{{ route('adviser.events.index') }}">Clear</a>@endif
        </form>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[850px] border-collapse">
                <thead class="bg-slate-50 text-left text-[10px] font-extrabold uppercase tracking-wider text-slate-400"><tr><th class="px-5 py-3.5">Event</th><th class="px-4 py-3.5">Schedule</th><th class="px-4 py-3.5">Location</th><th class="px-4 py-3.5">Status</th><th class="px-4 py-3.5">Event-in-Charge</th><th class="px-5 py-3.5"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($events as $event)
                    <tr class="group transition {{ $event->trashed() ? 'bg-slate-50/70 opacity-75' : 'cursor-pointer hover:bg-emerald-50/40' }}" @unless($event->trashed()) data-row-url="{{ route('adviser.events.show',$event) }}" tabindex="0" role="link" aria-label="View {{ $event->title }} details" @endunless>
                        <td class="px-5 py-4"><div class="grid min-w-48 gap-1"><strong class="text-sm text-[#121017] group-hover:text-emerald-700">{{ $event->title }}</strong><small class="max-w-sm truncate text-[10px] text-slate-400">{{ $event->description ?: 'No description' }}</small></div></td>
                        <td class="px-4 py-4"><span class="grid gap-0.5 text-xs font-semibold text-slate-600">{{ $event->start_at->format('M j, Y') }}<small class="font-medium text-slate-400">{{ $event->start_at->format('g:i A') }}</small></span></td>
                        <td class="max-w-52 px-4 py-4 text-xs text-slate-500"><span class="block truncate">{{ $event->location ?: 'Not specified' }}</span></td>
                        <td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-[10px] font-bold {{ match($event->status?->label) { 'upcoming' => 'bg-[#2F3AE0]/8 text-[#2F3AE0]', 'ongoing' => 'bg-[#C6F24E]/35 text-[#397565]', 'completed' => 'bg-[#121017]/6 text-[#121017]/55', default => 'bg-[#FF6B2C]/10 text-[#FF6B2C]' } }}">{{ ucfirst($event->status?->label ?? 'Unspecified') }}</span></td>
                        <td class="px-4 py-4"><div class="flex items-center gap-2"><div class="flex pl-1">@foreach($event->assignedUsers->take(3) as $user)<span class="-ml-1 grid h-7 w-7 place-items-center rounded-full border-2 border-white bg-emerald-100 text-[8px] font-extrabold text-emerald-800" title="{{ $user->full_name }}">{{ strtoupper(substr($user->first_name,0,1).substr($user->last_name,0,1)) }}</span>@endforeach</div><span class="text-[10px] text-slate-400">{{ $event->assignedUsers->count() ?: 'None' }}</span></div></td>
                        <td class="px-5 py-4 text-right"><div class="inline-flex gap-1.5">
                            @if($event->trashed())
                                <form method="POST" action="{{ route('adviser.events.restore',$event->id) }}">@csrf<button class="h-9 rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-600 hover:bg-white" type="submit" data-loading-text="Restoring…">Restore</button></form>
                                <form method="POST" action="{{ route('adviser.events.force-delete',$event->id) }}" data-confirm-title="Delete event permanently?" data-confirm-message="{{ $event->title }} and all assignments will be permanently removed. This cannot be undone." data-confirm-action="Delete permanently">@csrf @method('DELETE')<button class="h-9 px-2 text-xs font-bold text-red-600" type="submit" data-loading-text="Deleting…">Delete</button></form>
                            @else
                                <a class="grid h-11 w-11 place-items-center rounded-xl border border-[#397565]/25 bg-[#397565]/8 text-[#397565] shadow-sm hover:border-[#397565]/40 hover:bg-[#397565]/14" href="{{ route('adviser.events.edit',$event) }}" aria-label="Edit {{ $event->title }}" title="Edit event"><svg class="h-[18px] w-[18px] fill-none stroke-current stroke-2" viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg></a>
                                <form method="POST" action="{{ route('adviser.events.destroy',$event) }}" data-confirm-title="Archive event?" data-confirm-message="{{ $event->title }} will be hidden, but its details and assignments will be preserved." data-confirm-action="Archive">@csrf @method('DELETE')<button class="grid h-11 w-11 place-items-center rounded-xl border border-[#FF6B2C]/25 bg-[#FF6B2C]/9 text-[#d9470a] shadow-sm hover:border-[#FF6B2C]/40 hover:bg-[#FF6B2C]/15" type="submit" data-loading-text="Archiving…" aria-label="Archive {{ $event->title }}" title="Archive event"><svg class="h-[18px] w-[18px] fill-none stroke-current stroke-2" viewBox="0 0 24 24"><path d="M4 7h16v13H4V7Zm-1-4h18v4H3V3Zm6 8h6"/></svg></button></form>
                            @endif
                        </div></td>
                    </tr>
                @empty
                    <tr><td class="px-5 py-16 text-center" colspan="6"><strong class="text-sm text-slate-600">No events found</strong><p class="mt-1 text-xs text-slate-400">{{ request()->hasAny(['search','status','event_type']) ? 'Try changing your filters.' : 'Create your first event to get started.' }}</p></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($events->hasPages())<nav class="flex items-center justify-end gap-4 border-t border-slate-100 px-5 py-4 text-xs">@if($events->onFirstPage())<span class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-slate-300">Previous</span>@else<a class="rounded-lg border border-slate-200 px-3 py-2 text-slate-600" href="{{ $events->previousPageUrl() }}">Previous</a>@endif<small class="text-slate-400">Page {{ $events->currentPage() }} of {{ $events->lastPage() }}</small>@if($events->hasMorePages())<a class="rounded-lg border border-slate-200 px-3 py-2 text-slate-600" href="{{ $events->nextPageUrl() }}">Next</a>@else<span class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-slate-300">Next</span>@endif</nav>@endif
    </section>

    @include('adviser.events._create_modal')
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-row-url]').forEach((row) => {
        const open = (event) => {
            if (event.type === 'keydown' && !['Enter', ' '].includes(event.key)) return;
            if (event.target.closest('a, button, form, input, select')) return;
            event.preventDefault();
            window.location.href = row.dataset.rowUrl;
        };
        row.addEventListener('click', open);
        row.addEventListener('keydown', open);
    });

    const createPosterInput = document.querySelector('[data-create-poster-input]');
    createPosterInput?.addEventListener('change', () => {
        const file = createPosterInput.files?.[0];
        if (!file) return;
        const preview = document.querySelector('[data-create-poster-preview]');
        preview.style.backgroundImage = `linear-gradient(rgba(20,30,70,.35),rgba(20,30,70,.55)),url(${URL.createObjectURL(file)})`;
        preview.classList.add('text-white');
        preview.querySelector('strong').textContent = file.name;
        preview.querySelector('small').textContent = 'Click to replace';
    });

    @if($errors->any() && old('_form') === 'create-event' || request('create'))
        document.getElementById('create-event-dialog')?.showModal();
    @endif
</script>
@endpush
