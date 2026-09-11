<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class EventPolicy
{
    public function feature(User $user, Event $event): bool
    {
        if ($user->isSboAdviser()) {
            return true;
        }

        if (! $user->isSboOfficer() || ! $user->officer_team_id) {
            return false;
        }

        return $event->expectedParticipantsQuery()
            ->whereHas('teams', fn (Builder $query) => $query->where('teams.id', $user->officer_team_id))
            ->exists();
    }
}
