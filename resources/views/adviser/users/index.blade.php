@extends('layouts.adviser')

@section('title', 'User Management')

@section('content')
    <header class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="mb-2 text-xs font-extrabold uppercase tracking-[.14em] text-emerald-700">Administration</p><h1 class="text-3xl font-extrabold tracking-tight text-[#141e46] sm:text-4xl">User Management</h1><p class="mt-2 text-sm text-slate-500">Manage accounts and event responsibilities in one place.</p></div>
        <button class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 text-sm font-extrabold text-white shadow-lg shadow-emerald-600/15 transition hover:bg-emerald-700" type="button" data-dialog-open="add-user-dialog"><span class="text-xl font-normal" aria-hidden="true">+</span> Add User</button>
    </header>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <form class="flex flex-wrap items-center gap-2 border-b border-slate-100 p-4" method="GET" action="{{ route('adviser.users.index') }}">
            <label class="relative min-w-56 flex-1"><svg class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-slate-400" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><span class="sr-only">Search users</span><input class="h-11 w-full rounded-xl border border-slate-200 pl-10 pr-3 text-sm outline-none placeholder:text-slate-400 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10" name="search" value="{{ request('search') }}" placeholder="Search name, email, username or ID…"></label>
            <label><span class="sr-only">Filter by role</span><select class="h-11 min-w-36 rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-600 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10" name="role" onchange="this.form.submit()"><option value="">All roles</option>@foreach($roles as $role)<option value="{{ $role->name }}" @selected(request('role') === $role->name)>{{ $role->name }}</option>@endforeach</select></label>
            <button class="inline-flex h-11 items-center rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-600 hover:bg-slate-50" type="submit">Search</button>
            @if(request()->hasAny(['search','role']))<a class="px-2 py-3 text-xs font-bold text-emerald-700" href="{{ route('adviser.users.index') }}">Clear</a>@endif
        </form>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] border-collapse">
                <thead class="bg-slate-50 text-left text-[10px] font-extrabold uppercase tracking-wider text-slate-400"><tr><th class="px-5 py-3.5">User</th><th class="px-4 py-3.5">Role</th><th class="px-4 py-3.5">Status</th><th class="px-4 py-3.5">Assigned Events</th><th class="px-5 py-3.5"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($users as $user)
                    @php($roleTone = match($user->role?->name) { 'SBO' => 'bg-violet-50 text-violet-700', 'Faculty' => 'bg-amber-50 text-amber-700', 'Student' => 'bg-sky-50 text-sky-700', 'SBO Adviser' => 'bg-emerald-50 text-emerald-700', default => 'bg-slate-100 text-slate-600' })
                    <tr class="hover:bg-slate-50/60">
                        <td class="px-5 py-4"><div class="flex min-w-56 items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-emerald-100 text-xs font-extrabold text-emerald-800">{{ strtoupper(substr($user->first_name,0,1).substr($user->last_name,0,1)) }}</span><span class="grid min-w-0 gap-0.5"><strong class="text-xs text-slate-700">{{ $user->full_name }}</strong><small class="max-w-64 truncate text-[10px] text-slate-400">{{ $user->email }} · {{ '@'.$user->username }}</small></span></div></td>
                        <td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-[10px] font-bold {{ $roleTone }}">{{ $user->role?->name ?? 'Unassigned' }}</span></td>
                        <td class="px-4 py-4"><span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-bold {{ $user->userStatus?->label === 'inactive' ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700' }}"><i class="h-1.5 w-1.5 rounded-full {{ $user->userStatus?->label === 'inactive' ? 'bg-red-400' : 'bg-emerald-500' }}"></i>{{ ucfirst($user->userStatus?->label ?? 'Active') }}</span></td>
                        <td class="min-w-60 px-4 py-4">
                            @if(in_array($user->role?->name,['SBO','Faculty']))
                                <div class="flex flex-wrap items-center gap-1.5">@foreach($user->assignedEvents as $event)<span class="inline-flex min-h-7 items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 py-1 pl-2.5 pr-1 text-[10px] font-bold text-emerald-800" data-assignment-item>{{ $event->title }}<form method="POST" action="{{ route('adviser.users.events.destroy',[$user,$event]) }}" data-async-unassign>@csrf @method('DELETE')<button class="grid h-5 w-5 place-items-center rounded text-base text-emerald-700 hover:bg-red-50 hover:text-red-600" type="submit" aria-label="Unassign {{ $user->full_name }} from {{ $event->title }}">&times;</button></form></span>@endforeach<button class="px-1 py-1 text-[10px] font-extrabold text-emerald-700" type="button" data-dialog-open="assign-{{ $user->id }}">+ Assign Event</button></div>
                            @else<span class="text-[10px] text-slate-400">Not applicable</span>@endif
                        </td>
                        <td class="px-5 py-4 text-right">
                            @if(in_array($user->role?->name,['SBO','Faculty','Student']))
                                <div class="inline-flex gap-1.5"><button class="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700" type="button" data-dialog-open="edit-{{ $user->id }}" aria-label="Edit {{ $user->full_name }}"><svg class="h-4 w-4 fill-none stroke-current" viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg></button><form method="POST" action="{{ route('adviser.users.status',$user) }}" @if($user->userStatus?->label !== 'inactive') data-confirm-title="Deactivate user?" data-confirm-message="{{ $user->full_name }} will no longer be able to sign in until the account is activated again." data-confirm-action="Deactivate" @endif>@csrf @method('PATCH')<button class="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 {{ $user->userStatus?->label === 'inactive' ? 'text-emerald-700 hover:bg-emerald-50' : 'text-slate-500 hover:border-red-200 hover:bg-red-50 hover:text-red-600' }}" type="submit" data-loading-text="Updating…" aria-label="{{ $user->userStatus?->label === 'inactive' ? 'Activate' : 'Deactivate' }} {{ $user->full_name }}"><svg class="h-4 w-4 fill-none stroke-current" viewBox="0 0 24 24"><path d="M12 2v10m6.36-7.36a9 9 0 1 1-12.72 0"/></svg></button></form></div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-5 py-14 text-center" colspan="5"><strong class="text-sm text-slate-600">No users found</strong><p class="mt-1 text-xs text-slate-400">Try adjusting your search or role filter.</p></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())<nav class="flex items-center justify-end gap-4 border-t border-slate-100 px-5 py-4 text-xs">@if($users->onFirstPage())<span class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-slate-300">Previous</span>@else<a class="rounded-lg border border-slate-200 px-3 py-2 text-slate-600" href="{{ $users->previousPageUrl() }}">Previous</a>@endif<small class="text-slate-400">Page {{ $users->currentPage() }} of {{ $users->lastPage() }}</small>@if($users->hasMorePages())<a class="rounded-lg border border-slate-200 px-3 py-2 text-slate-600" href="{{ $users->nextPageUrl() }}">Next</a>@else<span class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-slate-300">Next</span>@endif</nav>@endif
    </section>

    <dialog class="m-auto max-h-[calc(100vh_-_2rem)] w-[min(720px,calc(100%_-_2rem))] overflow-y-auto rounded-2xl border-0 bg-white p-0 shadow-2xl backdrop:bg-[#141e46]/60 backdrop:backdrop-blur-[2px]" id="add-user-dialog">
        <form class="p-6" method="POST" action="{{ route('adviser.users.store') }}">@csrf
            <div class="mb-6 flex items-start justify-between gap-5"><div><p class="mb-2 text-[10px] font-extrabold uppercase tracking-wider text-emerald-700">New account</p><h2 class="text-2xl font-extrabold tracking-tight text-[#141e46]">Add User</h2><p class="mt-1.5 text-xs text-slate-500">Create an SBO, Faculty, or Student account.</p></div><button class="grid h-9 w-9 place-items-center rounded-lg bg-slate-100 text-xl text-slate-500" type="button" data-dialog-close aria-label="Close">&times;</button></div>
            @include('adviser.users._form')
            <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-5"><button class="inline-flex min-h-10 items-center rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-600" type="button" data-dialog-close>Cancel</button><button class="inline-flex min-h-10 items-center rounded-xl bg-emerald-600 px-4 text-sm font-bold text-white" type="submit" data-loading-text="Adding…">Add User</button></div>
        </form>
    </dialog>

    @foreach($users as $user)
        @if(in_array($user->role?->name,['SBO','Faculty','Student']))
            <dialog class="m-auto max-h-[calc(100vh_-_2rem)] w-[min(720px,calc(100%_-_2rem))] overflow-y-auto rounded-2xl border-0 bg-white p-0 shadow-2xl backdrop:bg-[#141e46]/60 backdrop:backdrop-blur-[2px]" id="edit-{{ $user->id }}"><form class="p-6" method="POST" action="{{ route('adviser.users.update',$user) }}">@csrf @method('PUT')<div class="mb-6 flex items-start justify-between gap-5"><div><p class="mb-2 text-[10px] font-extrabold uppercase tracking-wider text-emerald-700">Account details</p><h2 class="text-2xl font-extrabold tracking-tight text-[#141e46]">Edit User</h2><p class="mt-1.5 text-xs text-slate-500">Update {{ $user->full_name }}’s information.</p></div><button class="grid h-9 w-9 place-items-center rounded-lg bg-slate-100 text-xl text-slate-500" type="button" data-dialog-close>&times;</button></div>@include('adviser.users._form',['editingUser'=>$user])<div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-5"><button class="inline-flex min-h-10 items-center rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-600" type="button" data-dialog-close>Cancel</button><button class="inline-flex min-h-10 items-center rounded-xl bg-emerald-600 px-4 text-sm font-bold text-white" type="submit" data-loading-text="Saving…">Save Changes</button></div></form></dialog>
        @endif
        @if(in_array($user->role?->name,['SBO','Faculty']))
            <dialog class="m-auto w-[min(500px,calc(100%_-_2rem))] rounded-2xl border-0 bg-white p-0 shadow-2xl backdrop:bg-[#141e46]/60 backdrop:backdrop-blur-[2px]" id="assign-{{ $user->id }}"><form class="p-6" method="POST" action="{{ route('adviser.users.events.store',$user) }}">@csrf<input type="hidden" name="_form" value="assign-{{ $user->id }}"><div class="mb-6 flex items-start justify-between gap-5"><div><p class="mb-2 text-[10px] font-extrabold uppercase tracking-wider text-emerald-700">Event assignment</p><h2 class="text-2xl font-extrabold tracking-tight text-[#141e46]">Assign Event</h2><p class="mt-1.5 text-xs text-slate-500">Choose an event for {{ $user->full_name }}.</p></div><button class="grid h-9 w-9 place-items-center rounded-lg bg-slate-100 text-xl text-slate-500" type="button" data-dialog-close>&times;</button></div>
                @php($availableEvents=$events->whereNotIn('id',$user->assignedEvents->pluck('id')))
                @if($availableEvents->isEmpty())<div class="rounded-xl border border-dashed border-slate-200 px-5 py-8 text-center"><strong class="text-sm text-slate-600">No events available</strong><p class="mt-1 text-xs text-slate-400">This user is assigned to every active event, or no active events exist yet.</p></div>@else<label class="grid gap-2"><span class="text-xs font-bold text-slate-700">Available events</span><select class="h-11 rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 {{ old('_form')==='assign-'.$user->id && $errors->has('event_id') ? 'border-red-400 bg-red-50/40' : '' }}" name="event_id" required><option value="">Select an event</option>@foreach($availableEvents as $event)<option value="{{ $event->id }}">{{ $event->title }} · {{ $event->start_at->format('M j, Y') }}</option>@endforeach</select>@if(old('_form')==='assign-'.$user->id)<x-form-error name="event_id" />@endif</label><div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-5"><button class="inline-flex min-h-10 items-center rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-600" type="button" data-dialog-close>Cancel</button><button class="inline-flex min-h-10 items-center rounded-xl bg-emerald-600 px-4 text-sm font-bold text-white" type="submit" data-loading-text="Assigning…">Assign Event</button></div>@endif
            </form></dialog>
        @endif
    @endforeach
@endsection

@push('scripts')
    @if($errors->any() && old('_form'))<script>document.getElementById(@json(old('_form') === 'create' ? 'add-user-dialog' : old('_form')))?.showModal();</script>@endif
@endpush
