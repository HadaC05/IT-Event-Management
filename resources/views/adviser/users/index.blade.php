@extends('layouts.adviser')

@section('title', 'User Management')

@section('content')
    <header class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="mb-2 text-xs font-extrabold uppercase tracking-[.14em] text-emerald-700">Administration</p>
            <h1 class="text-3xl font-extrabold tracking-tight text-[#141e46] sm:text-4xl">User Management</h1>
            <p class="mt-2 text-sm text-slate-500">Manage access, roles, and event responsibilities.</p>
        </div>
        <button class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 text-sm font-extrabold text-white shadow-lg shadow-emerald-600/15 transition hover:bg-emerald-700" type="button" data-dialog-open="add-user-dialog"><span class="text-xl font-normal" aria-hidden="true">+</span>Add User</button>
    </header>

    <section class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-label="Account overview">
        <div class="grid divide-y divide-slate-100 md:grid-cols-[1.3fr_repeat(5,1fr)] md:divide-x md:divide-y-0">
            <div class="bg-[#141e46] p-5 text-white md:p-6">
                <p class="text-xs font-extrabold uppercase tracking-[.14em] text-emerald-300">Account Overview</p>
                <div class="mt-2 flex items-baseline gap-2"><strong class="text-3xl font-black">{{ number_format($userSummary['total']) }}</strong><span class="text-xs font-semibold text-white/50">Total Users</span></div>
            </div>
            @foreach ([['students', 'Students'], ['faculty', 'Faculty'], ['sbo', 'SBO'], ['active', 'Active'], ['inactive', 'Inactive']] as [$key, $label])
                <div class="flex items-center justify-between gap-4 px-5 py-4 md:block md:p-6">
                    <span class="inline-flex items-center gap-2 text-sm font-semibold {{ $key === 'active' ? 'text-emerald-700' : ($key === 'inactive' ? 'text-rose-700' : 'text-slate-500') }}">
                        @if (in_array($key, ['active', 'inactive']))<i class="h-2 w-2 rounded-full {{ $key === 'active' ? 'bg-emerald-500' : 'bg-rose-500' }}" aria-hidden="true"></i>@endif
                        {{ $label }}
                    </span>
                    <strong class="text-2xl font-extrabold text-[#141e46] md:mt-2 md:block">{{ number_format($userSummary[$key]) }}</strong>
                </div>
            @endforeach
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 px-5 py-5 sm:px-6">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between"><div><h2 class="text-lg font-extrabold tracking-tight text-[#141e46]">User Directory</h2><p class="mt-1 text-xs text-slate-500">{{ number_format($users->total()) }} {{ Str::plural('account', $users->total()) }} found</p></div>@if(request()->hasAny(['search', 'role', 'status']))<a class="mt-2 text-xs font-extrabold text-emerald-700 sm:mt-0" href="{{ route('adviser.users.index') }}">Clear all filters</a>@endif</div>

            <form class="mt-5 grid gap-2 md:grid-cols-[minmax(260px,1fr)_170px_170px_auto]" method="GET" action="{{ route('adviser.users.index') }}">
                <label class="relative"><svg class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-slate-400" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><span class="sr-only">Search users</span><input class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-3 text-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-500/10" name="search" value="{{ request('search') }}" placeholder="Search name, email, username or ID…"></label>
                <label><span class="sr-only">Filter by role</span><select class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-600 outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-500/10" name="role"><option value="">All roles</option>@foreach($roles as $role)<option value="{{ $role->name }}" @selected(request('role') === $role->name)>{{ $role->name }}</option>@endforeach</select></label>
                <label><span class="sr-only">Filter by status</span><select class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-600 outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-500/10" name="status"><option value="">Any status</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></select></label>
                <button class="inline-flex h-11 items-center justify-center rounded-xl bg-[#141e46] px-5 text-sm font-extrabold text-white transition hover:bg-slate-800" type="submit">Apply</button>
            </form>
        </header>

        <div class="hidden overflow-x-auto lg:block">
            <table class="w-full min-w-[980px] border-collapse">
                <thead class="bg-slate-50 text-left text-xs font-extrabold uppercase tracking-wider text-slate-400"><tr><th class="px-6 py-3.5">User</th><th class="px-4 py-3.5">Role</th><th class="px-4 py-3.5">Status</th><th class="px-4 py-3.5">Event Responsibilities</th><th class="px-6 py-3.5 text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $user)
                        @php
                            $manageable = in_array($user->role?->name, ['SBO', 'Faculty', 'Student']);
                            $statusLabel = $user->userStatus?->label;
                            $statusTone = match($statusLabel) { 'active' => 'text-emerald-700', 'inactive' => 'text-rose-700', default => 'text-slate-500' };
                            $statusDot = match($statusLabel) { 'active' => 'bg-emerald-500', 'inactive' => 'bg-rose-500', default => 'bg-slate-400' };
                        @endphp
                        <tr class="transition hover:bg-slate-50/70">
                            <td class="px-6 py-5"><div class="flex min-w-64 items-center gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[#141e46] text-xs font-extrabold text-[#fff5e0]">{{ strtoupper(substr($user->first_name, 0, 1).substr($user->last_name, 0, 1)) }}</span><span class="grid min-w-0 gap-0.5"><strong class="truncate text-sm text-slate-700">{{ $user->full_name }}</strong><small class="truncate text-xs text-slate-400">{{ $user->email }}</small><small class="truncate text-xs text-slate-400">{{ '@'.$user->username }}@if($user->id_number) · {{ $user->id_number }}@endif</small></span></div></td>
                            <td class="px-4 py-5"><span class="inline-flex rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-600">{{ $user->role?->name ?? 'Unassigned' }}</span></td>
                            <td class="px-4 py-5"><span class="inline-flex items-center gap-2 text-xs font-bold {{ $statusTone }}"><i class="h-2 w-2 rounded-full {{ $statusDot }}" aria-hidden="true"></i>{{ ucfirst($statusLabel ?? 'Unassigned') }}</span></td>
                            <td class="min-w-72 px-4 py-5">
                                @if(in_array($user->role?->name, ['SBO', 'Faculty']))
                                    <div class="flex flex-wrap items-center gap-2">
                                        @foreach($user->assignedEvents as $event)
                                            <span class="inline-flex min-h-8 items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 py-1 pl-2.5 pr-1 text-xs font-bold text-emerald-800" data-assignment-item>{{ $event->title }}<form method="POST" action="{{ route('adviser.users.events.destroy', [$user, $event]) }}" data-async-unassign>@csrf @method('DELETE')<button class="grid h-6 w-6 place-items-center rounded text-base text-emerald-700 hover:bg-rose-50 hover:text-rose-600" type="submit" aria-label="Unassign {{ $user->full_name }} from {{ $event->title }}">&times;</button></form></span>
                                        @endforeach
                                        <button class="rounded-lg px-2 py-1.5 text-xs font-extrabold text-emerald-700 hover:bg-emerald-50" type="button" data-dialog-open="assign-{{ $user->id }}">+ Assign Event</button>
                                    </div>
                                @elseif($user->role?->name === 'Student')
                                    <span class="text-xs text-slate-400">Student participant</span>
                                @else
                                    <span class="text-xs text-slate-400">Protected account</span>
                                @endif
                            </td>
                            <td class="px-6 py-5 text-right">
                                @if($manageable)
                                    <div class="inline-flex items-center gap-2"><button class="inline-flex min-h-9 items-center rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-600 hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700" type="button" data-dialog-open="edit-{{ $user->id }}">Edit</button><form method="POST" action="{{ route('adviser.users.status', $user) }}" @if($user->userStatus?->label !== 'inactive') data-confirm-title="Deactivate user?" data-confirm-message="{{ $user->full_name }} will no longer be able to sign in until the account is activated again." data-confirm-action="Deactivate" @endif>@csrf @method('PATCH')<button class="inline-flex min-h-9 items-center rounded-lg px-3 text-xs font-bold {{ $user->userStatus?->label === 'inactive' ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'text-rose-600 hover:bg-rose-50' }}" type="submit" data-loading-text="Updating…">{{ $user->userStatus?->label === 'inactive' ? 'Activate' : 'Deactivate' }}</button></form></div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td class="px-6 py-16 text-center" colspan="5"><strong class="text-sm text-slate-600">No users found</strong><p class="mt-1 text-xs text-slate-400">Try adjusting your search, role, or status filter.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="divide-y divide-slate-100 lg:hidden">
            @forelse($users as $user)
                @php
                    $manageable = in_array($user->role?->name, ['SBO', 'Faculty', 'Student']);
                    $statusLabel = $user->userStatus?->label;
                    $statusTone = match($statusLabel) { 'active' => 'text-emerald-700', 'inactive' => 'text-rose-700', default => 'text-slate-500' };
                    $statusDot = match($statusLabel) { 'active' => 'bg-emerald-500', 'inactive' => 'bg-rose-500', default => 'bg-slate-400' };
                @endphp
                <article class="p-5">
                    <div class="flex items-start gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[#141e46] text-xs font-extrabold text-[#fff5e0]">{{ strtoupper(substr($user->first_name, 0, 1).substr($user->last_name, 0, 1)) }}</span><div class="min-w-0 flex-1"><strong class="block truncate text-sm text-slate-700">{{ $user->full_name }}</strong><p class="mt-0.5 truncate text-xs text-slate-400">{{ $user->email }}</p><div class="mt-2 flex flex-wrap items-center gap-2"><span class="rounded-lg border border-slate-200 px-2 py-1 text-xs font-bold text-slate-600">{{ $user->role?->name ?? 'Unassigned' }}</span><span class="inline-flex items-center gap-1.5 text-xs font-bold {{ $statusTone }}"><i class="h-2 w-2 rounded-full {{ $statusDot }}"></i>{{ ucfirst($statusLabel ?? 'Unassigned') }}</span></div></div></div>
                    @if(in_array($user->role?->name, ['SBO', 'Faculty']))<div class="mt-4 flex flex-wrap gap-2">@foreach($user->assignedEvents as $event)<span class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 py-1 pl-2.5 pr-1 text-xs font-bold text-emerald-800" data-assignment-item>{{ $event->title }}<form method="POST" action="{{ route('adviser.users.events.destroy', [$user, $event]) }}" data-async-unassign>@csrf @method('DELETE')<button class="grid h-6 w-6 place-items-center rounded text-base text-emerald-700 hover:bg-rose-50 hover:text-rose-600" type="submit" aria-label="Unassign {{ $user->full_name }} from {{ $event->title }}">&times;</button></form></span>@endforeach<button class="rounded-lg px-2.5 py-1.5 text-xs font-extrabold text-emerald-700" type="button" data-dialog-open="assign-{{ $user->id }}">+ Assign Event</button></div>@endif
                    @if($manageable)<div class="mt-4 flex gap-2 border-t border-slate-100 pt-4"><button class="inline-flex min-h-9 flex-1 items-center justify-center rounded-lg border border-slate-200 text-xs font-bold text-slate-600" type="button" data-dialog-open="edit-{{ $user->id }}">Edit account</button><form class="flex-1" method="POST" action="{{ route('adviser.users.status', $user) }}" @if($user->userStatus?->label !== 'inactive') data-confirm-title="Deactivate user?" data-confirm-message="{{ $user->full_name }} will no longer be able to sign in until the account is activated again." data-confirm-action="Deactivate" @endif>@csrf @method('PATCH')<button class="min-h-9 w-full rounded-lg text-xs font-bold {{ $user->userStatus?->label === 'inactive' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}" type="submit" data-loading-text="Updating…">{{ $user->userStatus?->label === 'inactive' ? 'Activate' : 'Deactivate' }}</button></form></div>@endif
                </article>
            @empty
                <div class="px-6 py-16 text-center"><strong class="text-sm text-slate-600">No users found</strong><p class="mt-1 text-xs text-slate-400">Try adjusting your filters.</p></div>
            @endforelse
        </div>

        @if($users->hasPages())
            <nav class="flex flex-col items-center justify-between gap-3 border-t border-slate-100 px-5 py-4 text-xs sm:flex-row"><small class="text-slate-400">Showing {{ $users->firstItem() }}–{{ $users->lastItem() }} of {{ $users->total() }}</small><div class="flex items-center gap-2">@if($users->onFirstPage())<span class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-slate-300">Previous</span>@else<a class="rounded-lg border border-slate-200 px-3 py-2 font-bold text-slate-600 hover:bg-slate-50" href="{{ $users->previousPageUrl() }}">Previous</a>@endif<span class="px-2 text-slate-400">{{ $users->currentPage() }} / {{ $users->lastPage() }}</span>@if($users->hasMorePages())<a class="rounded-lg border border-slate-200 px-3 py-2 font-bold text-slate-600 hover:bg-slate-50" href="{{ $users->nextPageUrl() }}">Next</a>@else<span class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-slate-300">Next</span>@endif</div></nav>
        @endif
    </section>

    <dialog class="m-auto max-h-[calc(100vh_-_2rem)] w-[min(760px,calc(100%_-_2rem))] overflow-y-auto rounded-2xl border-0 bg-white p-0 shadow-2xl backdrop:bg-[#141e46]/60 backdrop:backdrop-blur-[2px]" id="add-user-dialog">
        <form method="POST" action="{{ route('adviser.users.store') }}">@csrf
            <header class="border-b border-slate-100 border-t-4 border-t-emerald-500 px-6 py-5"><div class="flex items-start justify-between gap-5"><div><p class="mb-2 text-xs font-extrabold uppercase tracking-[.14em] text-emerald-700">New Account</p><h2 class="text-2xl font-extrabold tracking-tight text-[#141e46]">Add User</h2><p class="mt-1.5 text-sm text-slate-500">Create an SBO, Faculty, or Student account.</p></div><button class="grid h-9 w-9 place-items-center rounded-lg bg-slate-100 text-xl text-slate-500 hover:bg-slate-200" type="button" data-dialog-close aria-label="Close">&times;</button></div></header>
            <div class="p-6">@include('adviser.users._form')</div>
            <footer class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/60 px-6 py-4"><button class="inline-flex min-h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-600" type="button" data-dialog-close>Cancel</button><button class="inline-flex min-h-10 items-center rounded-xl bg-emerald-600 px-5 text-sm font-bold text-white" type="submit" data-loading-text="Adding…">Add User</button></footer>
        </form>
    </dialog>

    @foreach($users as $user)
        @if(in_array($user->role?->name, ['SBO', 'Faculty', 'Student']))
            <dialog class="m-auto max-h-[calc(100vh_-_2rem)] w-[min(760px,calc(100%_-_2rem))] overflow-y-auto rounded-2xl border-0 bg-white p-0 shadow-2xl backdrop:bg-[#141e46]/60 backdrop:backdrop-blur-[2px]" id="edit-{{ $user->id }}"><form method="POST" action="{{ route('adviser.users.update', $user) }}">@csrf @method('PUT')<header class="border-b border-slate-100 border-t-4 border-t-emerald-500 px-6 py-5"><div class="flex items-start justify-between gap-5"><div><p class="mb-2 text-xs font-extrabold uppercase tracking-[.14em] text-emerald-700">Account Details</p><h2 class="text-2xl font-extrabold tracking-tight text-[#141e46]">Edit User</h2><p class="mt-1.5 text-sm text-slate-500">Update {{ $user->full_name }}’s information and access.</p></div><button class="grid h-9 w-9 place-items-center rounded-lg bg-slate-100 text-xl text-slate-500 hover:bg-slate-200" type="button" data-dialog-close aria-label="Close">&times;</button></div></header><div class="p-6">@include('adviser.users._form', ['editingUser' => $user])</div><footer class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/60 px-6 py-4"><button class="inline-flex min-h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-600" type="button" data-dialog-close>Cancel</button><button class="inline-flex min-h-10 items-center rounded-xl bg-emerald-600 px-5 text-sm font-bold text-white" type="submit" data-loading-text="Saving…">Save Changes</button></footer></form></dialog>
        @endif
        @if(in_array($user->role?->name, ['SBO', 'Faculty']))
            <dialog class="m-auto w-[min(520px,calc(100%_-_2rem))] rounded-2xl border-0 bg-white p-0 shadow-2xl backdrop:bg-[#141e46]/60 backdrop:backdrop-blur-[2px]" id="assign-{{ $user->id }}"><form method="POST" action="{{ route('adviser.users.events.store', $user) }}">@csrf<input type="hidden" name="_form" value="assign-{{ $user->id }}"><header class="border-b border-slate-100 border-t-4 border-t-emerald-500 px-6 py-5"><div class="flex items-start justify-between gap-5"><div><p class="mb-2 text-xs font-extrabold uppercase tracking-[.14em] text-emerald-700">Event Responsibility</p><h2 class="text-2xl font-extrabold tracking-tight text-[#141e46]">Assign Event</h2><p class="mt-1.5 text-sm text-slate-500">Choose an event for {{ $user->full_name }}.</p></div><button class="grid h-9 w-9 place-items-center rounded-lg bg-slate-100 text-xl text-slate-500 hover:bg-slate-200" type="button" data-dialog-close aria-label="Close">&times;</button></div></header><div class="p-6">
                @php($availableEvents = $events->whereNotIn('id', $user->assignedEvents->pluck('id')))
                @if($availableEvents->isEmpty())<div class="rounded-xl border border-dashed border-slate-200 px-5 py-8 text-center"><strong class="text-sm text-slate-600">No events available</strong><p class="mt-1 text-xs text-slate-400">This user is assigned to every active event, or no active events exist yet.</p></div>@else<label class="grid gap-2"><span class="text-sm font-bold text-slate-700">Available event</span><select class="h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm outline-none transition focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-500/10 {{ old('_form') === 'assign-'.$user->id && $errors->has('event_id') ? 'border-red-400 bg-red-50/40' : '' }}" name="event_id" required><option value="">Select an event</option>@foreach($availableEvents as $event)<option value="{{ $event->id }}">{{ $event->title }} · {{ $event->start_at->format('M j, Y') }}</option>@endforeach</select>@if(old('_form') === 'assign-'.$user->id)<x-form-error name="event_id" />@endif</label>@endif
            </div>@unless($availableEvents->isEmpty())<footer class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/60 px-6 py-4"><button class="inline-flex min-h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-600" type="button" data-dialog-close>Cancel</button><button class="inline-flex min-h-10 items-center rounded-xl bg-emerald-600 px-5 text-sm font-bold text-white" type="submit" data-loading-text="Assigning…">Assign Event</button></footer>@endunless</form></dialog>
        @endif
    @endforeach
@endsection

@push('scripts')
    @if($errors->any() && old('_form'))<script>document.getElementById(@json(old('_form') === 'create' ? 'add-user-dialog' : old('_form')))?.showModal();</script>@endif
@endpush
