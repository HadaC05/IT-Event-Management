<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Collection;

class StudentPortalService
{
    public function __construct(private readonly LeaderboardService $leaderboards) {}

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
        return $event ? $this->leaderboards->rankings($event) : collect();
    }
}
