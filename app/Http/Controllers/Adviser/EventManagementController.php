<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Http\Requests\Adviser\EventRequest;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\EventStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class EventManagementController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::exists('event_statuses', 'id')],
            'timing' => ['nullable', Rule::in(['upcoming', 'ongoing', 'completed', 'archived'])],
        ]);

        $now = now();
        $events = Event::query()
            ->with(['status', 'assignedUsers.role'])
            ->when(($validated['timing'] ?? null) === 'archived', fn (Builder $query) => $query->onlyTrashed())
            ->when($validated['search'] ?? null, function (Builder $query, string $search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn (Builder $query) => $query
                    ->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('location', 'like', $term));
            })
            ->when($validated['status'] ?? null, fn (Builder $query, int|string $status) => $query->where('event_status_id', $status))
            ->when(($validated['timing'] ?? null) === 'upcoming', fn (Builder $query) => $query->where('start_at', '>', $now))
            ->when(($validated['timing'] ?? null) === 'ongoing', fn (Builder $query) => $query->where('start_at', '<=', $now)->where('end_at', '>=', $now))
            ->when(($validated['timing'] ?? null) === 'completed', fn (Builder $query) => $query->where('end_at', '<', $now))
            ->orderByRaw('CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END')
            ->orderBy('start_at')
            ->paginate(9)
            ->withQueryString();

        return view('adviser.events.index', [
            'events' => $events,
            'statuses' => EventStatus::orderBy('id')->get(),
            'assignableUsers' => $this->assignableUsers(),
        ]);
    }

    public function create(): RedirectResponse
    {
        return to_route('adviser.events.index', ['create' => 1]);
    }

    public function store(EventRequest $request): RedirectResponse
    {
        $data = $request->eventData();
        $data['event_status_id'] = EventStatus::firstOrCreate(['label' => 'active'])->id;
        $posterPath = $request->file('poster')?->store('event-posters', 'public');
        $data['poster_path'] = $posterPath;
        $data['created_by'] = $request->user()->id;

        try {
            $event = DB::transaction(function () use ($request, $data) {
                $event = Event::create($data);
                $this->syncAssignments($event, $request->input('assigned_user_ids', []), $request->user());
                $this->log($request->user(), 'event_created', "{$event->title} was created.", $event);

                return $event;
            });
        } catch (Throwable $exception) {
            if ($posterPath) {
                Storage::disk('public')->delete($posterPath);
            }

            throw $exception;
        }

        return to_route('adviser.events.index')->with('success', 'Event created successfully.');
    }

    public function show(Event $event): View
    {
        $event->load(['status', 'creator', 'assignedUsers.role', 'assignedUsers.userStatus']);

        $assignedIds = $event->assignedUsers->pluck('id');

        return view('adviser.events.show', [
            'event' => $event,
            'availableUsers' => $this->assignableUsers()->whereNotIn('id', $assignedIds),
        ]);
    }

    public function edit(Event $event): View
    {
        $event->load('assignedUsers');

        return view('adviser.events.edit', array_merge($this->formData(), ['event' => $event]));
    }

    public function update(EventRequest $request, Event $event): RedirectResponse
    {
        $data = $request->eventData();
        $oldPoster = $event->poster_path;
        $newPoster = $request->file('poster')?->store('event-posters', 'public');

        if ($newPoster) {
            $data['poster_path'] = $newPoster;
        } elseif ($request->boolean('remove_poster')) {
            $data['poster_path'] = null;
        }

        try {
            DB::transaction(function () use ($request, $event, $data) {
                $event->update($data);
                $this->syncAssignments($event, $request->input('assigned_user_ids', []), $request->user());
                $this->log($request->user(), 'event_updated', "{$event->title} was updated.", $event);
            });
        } catch (Throwable $exception) {
            if ($newPoster) {
                Storage::disk('public')->delete($newPoster);
            }

            throw $exception;
        }

        if (($newPoster || $request->boolean('remove_poster')) && $oldPoster) {
            Storage::disk('public')->delete($oldPoster);
        }

        return to_route('adviser.events.show', $event)->with('success', 'Event updated successfully.');
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        $this->log($request->user(), 'event_archived', "{$event->title} was archived.", $event);
        $event->delete();

        return to_route('adviser.events.index')->with('success', 'Event archived successfully.');
    }

    public function restore(Request $request, int $event): RedirectResponse
    {
        $event = Event::onlyTrashed()->findOrFail($event);
        $event->restore();
        $this->log($request->user(), 'event_restored', "{$event->title} was restored.", $event);

        return to_route('adviser.events.show', $event)->with('success', 'Event restored successfully.');
    }

    public function forceDelete(int $event): RedirectResponse
    {
        $event = Event::onlyTrashed()->findOrFail($event);
        $title = $event->title;
        $poster = $event->poster_path;
        $event->forceDelete();

        if ($poster) {
            Storage::disk('public')->delete($poster);
        }

        return to_route('adviser.events.index', ['timing' => 'archived'])
            ->with('success', "{$title} was permanently deleted.");
    }

    private function formData(): array
    {
        return [
            'statuses' => EventStatus::orderBy('id')->get(),
            'assignableUsers' => $this->assignableUsers(),
        ];
    }

    private function assignableUsers()
    {
        $roleIds = Role::whereIn('name', ['SBO', 'Faculty'])->pluck('id');

        return User::with('role')
            ->whereIn('role_id', $roleIds)
            ->whereHas('userStatus', fn (Builder $query) => $query->where('label', 'active'))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    private function syncAssignments(Event $event, array $userIds, User $actor): void
    {
        $before = $event->assignedUsers()->pluck('users.id');
        $after = collect($userIds)->map(fn ($id) => (int) $id)->unique()->values();
        $event->assignedUsers()->sync($after);

        User::whereIn('id', $after->diff($before))->get()->each(fn (User $user) => $this->log($actor, 'event_assigned', "{$user->full_name} was assigned to {$event->title}.", $event, $user));
        User::whereIn('id', $before->diff($after))->get()->each(fn (User $user) => $this->log($actor, 'event_unassigned', "{$user->full_name} was unassigned from {$event->title}.", $event, $user));
    }

    private function log(User $actor, string $action, string $description, Event $event, ?User $subject = null): void
    {
        ActivityLog::create([
            'actor_id' => $actor->id,
            'subject_user_id' => $subject?->id,
            'event_id' => $event->id,
            'action' => $action,
            'description' => $description,
        ]);
    }
}
