<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\SchoolYear;
use App\Models\ScoreCategory;
use App\Services\LeaderboardService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LeaderboardController extends Controller
{
    public function __invoke(Request $request, LeaderboardService $leaderboards): View
    {
        $validated = $request->validate([
            'event_id' => ['nullable', 'integer', Rule::exists('events', 'id')->whereNull('deleted_at')],
            'school_year_id' => ['nullable', 'integer', 'exists:school_years,id'],
            'category_id' => ['nullable', 'integer', 'exists:score_categories,id'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $event = isset($validated['event_id']) ? Event::findOrFail($validated['event_id']) : null;
        $schoolYear = isset($validated['school_year_id']) ? SchoolYear::findOrFail($validated['school_year_id']) : null;
        $category = isset($validated['category_id']) ? ScoreCategory::findOrFail($validated['category_id']) : null;

        if ($category && (! $event || $category->event_id !== $event->id)) {
            throw ValidationException::withMessages([
                'category_id' => 'Choose a scoring category from the selected event.',
            ]);
        }

        $rankings = $leaderboards->rankings($event, $schoolYear, $category);
        $ranked = $rankings->where('has_score', true)->values();
        $search = trim($validated['search'] ?? '');
        $visibleRankings = $rankings
            ->when($search !== '', fn ($teams) => $teams->filter(
                fn ($team) => str_contains(mb_strtolower($team->name), mb_strtolower($search))
            ))
            ->values();

        return view('adviser.leaderboard.index', [
            'rankings' => $visibleRankings,
            'podium' => $ranked->take(3),
            'events' => Event::query()->whereHas('scoreCategories')->orderByDesc('start_at')->get(['id', 'title', 'start_at']),
            'schoolYears' => SchoolYear::query()->orderByDesc('label')->get(),
            'categories' => $event?->scoreCategories()->get() ?? collect(),
            'selectedEvent' => $event,
            'selectedSchoolYear' => $schoolYear,
            'selectedCategory' => $category,
            'summary' => [
                'ranked_teams' => $ranked->count(),
                'eligible_teams' => $rankings->count(),
                'points' => (float) $ranked->sum('total_score'),
                'events' => $ranked->flatMap->scores->pluck('event_id')->unique()->count(),
                'leader' => $ranked->first(),
                'lead' => $ranked->count() > 1
                    ? (float) max(0, (float) $ranked->first()->total_score - (float) $ranked->get(1)->total_score)
                    : null,
            ],
        ]);
    }
}
