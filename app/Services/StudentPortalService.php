<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

class StudentPortalService
{
    public function eligibleEvents(User $user, bool $futureOnly = false): Collection
    {
        $events = Event::query()
            ->with(['status', 'type', 'audienceTeams', 'attendanceSchedules.attendanceSessionMode'])
            ->when($futureOnly, fn ($query) => $query
                ->where('end_at', '>=', now())
                ->where(fn ($query) => $query
                    ->whereDoesntHave('status')
                    ->orWhereHas('status', fn ($query) => $query->whereIn('label', ['upcoming', 'ongoing']))))
            ->orderBy('start_at')
            ->get();

        return $events->filter(fn (Event $event) => $event->expectedParticipantsQuery()->whereKey($user->id)->exists())->values();
    }

    public function currentEvent(User $user): ?Event
    {
        return $this->eligibleEvents($user, true)
            ->sortBy(fn (Event $event) => $event->start_at->isPast() ? 0 : $event->start_at->timestamp)
            ->first();
    }

    public function leaderboard(?Event $event): Collection
    {
        if (! $event) {
            return collect();
        }

        $teams = Team::query()
            ->where('is_active', true)
            ->with(['scores' => fn ($query) => $query->where('event_id', $event->id)->with('category')])
            ->get();

        return $teams->map(function (Team $team) {
            $team->setAttribute('total_score', (float) $team->scores->sum('points'));

            return $team;
        })->sortByDesc('total_score')->values()->each(fn (Team $team, int $index) => $team->setAttribute('rank', $index + 1));
    }
}
