<?php

namespace App\Services;

use App\Models\Event;
use App\Models\SchoolYear;
use App\Models\ScoreCategory;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LeaderboardService
{
    public function rankings(
        ?Event $event = null,
        ?SchoolYear $schoolYear = null,
        ?ScoreCategory $category = null,
    ): Collection {
        $teams = Team::query()
            ->with(['schoolYear', 'scores' => function ($query) use ($event, $category) {
                $query
                    ->when($event, fn (Builder $query) => $query->where('event_id', $event->id))
                    ->when($category, fn (Builder $query) => $query->where('score_category_id', $category->id));
            }])
            ->withCount('members')
            ->when($schoolYear, fn (Builder $query) => $query->where('school_year_id', $schoolYear->id))
            ->when(
                $event,
                fn (Builder $query) => $this->limitToEventTeams($query, $event),
                fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->where('is_active', true)
                    ->orWhereHas('scores')),
            )
            ->get()
            ->each(function (Team $team) {
                $team->setAttribute('total_score', (float) $team->scores->sum('points'));
                $team->setAttribute('score_entries_count', $team->scores->count());
                $team->setAttribute('scored_events_count', $team->scores->pluck('event_id')->unique()->count());
                $team->setAttribute('last_scored_at', $team->scores->max('updated_at'));
                $team->setAttribute('has_score', $team->scores->isNotEmpty());
            })
            ->sort(function (Team $left, Team $right) {
                if ($left->has_score !== $right->has_score) {
                    return $left->has_score ? -1 : 1;
                }

                $points = $right->total_score <=> $left->total_score;

                return $points !== 0 ? $points : strcasecmp($left->name, $right->name);
            })
            ->values();

        $previousPoints = null;
        $previousRank = null;

        return $teams->each(function (Team $team, int $index) use (&$previousPoints, &$previousRank) {
            if (! $team->has_score) {
                $team->setAttribute('rank', null);

                return;
            }

            $rank = $previousPoints !== null && abs($team->total_score - $previousPoints) < 0.00001
                ? $previousRank
                : $index + 1;

            $team->setAttribute('rank', $rank);
            $previousPoints = $team->total_score;
            $previousRank = $rank;
        });
    }

    private function limitToEventTeams(Builder $query, Event $event): Builder
    {
        return $query->where(function (Builder $query) use ($event) {
            $query
                ->where(function (Builder $query) use ($event) {
                    $query->where('is_active', true);

                    match ($event->audience_type) {
                        'selected_tribes' => $query->whereIn('teams.id', $event->audienceTeams()->pluck('teams.id')),
                        'selected_year_levels' => $query->whereHas('members', fn (Builder $query) => $query
                            ->whereIn('year_level', $event->audienceYearLevels()->pluck('year_levels.id'))),
                        'specific_students' => $query->whereHas('members', fn (Builder $query) => $query
                            ->whereIn('users.id', $event->participants()->pluck('users.id'))),
                        default => null,
                    };
                })
                ->orWhereHas('scores', fn (Builder $query) => $query->where('event_id', $event->id));
        });
    }
}
