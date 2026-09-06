<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventAssignmentController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        $this->ensureAssignable($user);
        $validated = $request->validate(['event_id' => ['required', 'exists:events,id']]);
        $event = Event::findOrFail($validated['event_id']);

        if ($event->status?->label === 'inactive') {
            throw ValidationException::withMessages(['event_id' => 'Inactive events cannot receive new assignments.']);
        }

        if ($user->assignedEvents()->whereKey($event->id)->exists()) {
            return back()->with('info', "{$user->full_name} is already assigned to {$event->title}.");
        }

        DB::transaction(function () use ($request, $user, $event) {
            $user->assignedEvents()->attach($event);
            ActivityLog::create([
                'actor_id' => $request->user()->id,
                'subject_user_id' => $user->id,
                'event_id' => $event->id,
                'action' => 'event_assigned',
                'description' => "{$user->full_name} was assigned to {$event->title}.",
            ]);
        });

        return back()->with('success', "{$user->full_name} was assigned to {$event->title}.");
    }

    public function storeForEvent(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);
        $user = User::findOrFail($validated['user_id']);
        $this->ensureAssignable($user);

        if ($event->status?->label === 'inactive') {
            throw ValidationException::withMessages(['user_id' => 'Users cannot be assigned while this event is inactive.']);
        }

        if ($event->assignedUsers()->whereKey($user->id)->exists()) {
            return back()->with('info', "{$user->full_name} is already assigned to {$event->title}.");
        }

        DB::transaction(function () use ($request, $user, $event) {
            $event->assignedUsers()->attach($user);
            ActivityLog::create([
                'actor_id' => $request->user()->id,
                'subject_user_id' => $user->id,
                'event_id' => $event->id,
                'action' => 'event_assigned',
                'description' => "{$user->full_name} was assigned to {$event->title}.",
            ]);
        });

        return back()->with('success', "{$user->full_name} was assigned to {$event->title}.");
    }

    public function destroy(Request $request, User $user, Event $event): JsonResponse|RedirectResponse
    {
        $this->ensureAssignable($user);

        if (! $user->assignedEvents()->whereKey($event->id)->exists()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'That assignment was already removed.'], 409);
            }

            return back()->with('info', 'That assignment was already removed.');
        }

        DB::transaction(function () use ($request, $user, $event) {
            $user->assignedEvents()->detach($event);
            ActivityLog::create([
                'actor_id' => $request->user()->id,
                'subject_user_id' => $user->id,
                'event_id' => $event->id,
                'action' => 'event_unassigned',
                'description' => "{$user->full_name} was unassigned from {$event->title}.",
            ]);
        });

        $message = "{$user->full_name} was unassigned from {$event->title}.";

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'undo_url' => route('adviser.users.events.restore', [$user, $event]),
            ]);
        }

        return back()->with('success', $message);
    }

    public function restore(Request $request, User $user, Event $event): JsonResponse|RedirectResponse
    {
        $this->ensureAssignable($user);

        if ($user->assignedEvents()->whereKey($event->id)->exists()) {
            $message = "{$user->full_name} is already assigned to {$event->title}.";

            return $request->expectsJson()
                ? response()->json(['message' => $message])
                : back()->with('info', $message);
        }

        DB::transaction(function () use ($request, $user, $event) {
            $user->assignedEvents()->attach($event);
            ActivityLog::create([
                'actor_id' => $request->user()->id,
                'subject_user_id' => $user->id,
                'event_id' => $event->id,
                'action' => 'event_assignment_restored',
                'description' => "{$user->full_name}'s assignment to {$event->title} was restored.",
            ]);
        });

        $message = "{$user->full_name} was reassigned to {$event->title}.";

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back()->with('success', $message);
    }

    private function ensureAssignable(User $user): void
    {
        abort_unless(in_array($user->role?->name, ['SBO', 'Faculty'], true), 422, 'Only SBO and Faculty users can be assigned to events.');
    }
}
