<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Http\Requests\Adviser\ScoreCategoryRequest;
use App\Http\Requests\Adviser\ScoreEntriesRequest;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Score;
use App\Models\ScoreCategory;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ScoreManagementController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'timing' => ['nullable', Rule::in(['upcoming', 'ongoing', 'completed'])],
        ]);
        $now = now();

        $events = Event::query()
            ->withCount(['scoreCategories', 'scores'])
            ->withSum('scores', 'points')
            ->addSelect([
                'scored_teams_count' => Score::query()
                    ->selectRaw('count(distinct team_id)')
                    ->whereColumn('event_id', 'events.id')
                    ->whereNotNull('score_category_id'),
            ])
            ->when($validated['search'] ?? null, function (Builder $query, string $search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn (Builder $query) => $query->where('title', 'like', $term)->orWhere('location', 'like', $term));
            })
            ->when(($validated['timing'] ?? null) === 'upcoming', fn (Builder $query) => $query->where('start_at', '>', $now))
            ->when(($validated['timing'] ?? null) === 'ongoing', fn (Builder $query) => $query->where('start_at', '<=', $now)->where('end_at', '>=', $now))
            ->when(($validated['timing'] ?? null) === 'completed', fn (Builder $query) => $query->where('end_at', '<', $now))
            ->orderByDesc('start_at')
            ->paginate(10)
            ->withQueryString();

        return view('adviser.scores.index', [
            'events' => $events,
            'summary' => [
                'events' => Event::count(),
                'configured' => Event::whereHas('scoreCategories')->count(),
                'results' => Score::query()->whereNotNull('score_category_id')->get(['event_id', 'team_id'])->unique(fn (Score $score) => $score->event_id.'-'.$score->team_id)->count(),
                'points' => (float) Score::sum('points'),
            ],
        ]);
    }

    public function show(Event $event): View
    {
        $categories = $event->scoreCategories()->withCount('scores')->get();
        $teams = Team::query()
            ->with('schoolYear')
            ->where(fn (Builder $query) => $query
                ->where('is_active', true)
                ->orWhereHas('scores', fn (Builder $query) => $query->where('event_id', $event->id)))
            ->withSum(['scores as event_score_total' => fn (Builder $query) => $query->where('event_id', $event->id)], 'points')
            ->orderByDesc('event_score_total')
            ->orderBy('name')
            ->get();

        $previousPoints = null;
        $previousRank = 0;
        $teams->each(function (Team $team, int $index) use (&$previousPoints, &$previousRank) {
            $points = (float) ($team->event_score_total ?? 0);
            $rank = $previousPoints !== null && abs($points - $previousPoints) < 0.00001 ? $previousRank : $index + 1;
            $team->setAttribute('rank', $rank);
            $previousPoints = $points;
            $previousRank = $rank;
        });

        $scores = $event->scores()
            ->whereNotNull('score_category_id')
            ->get()
            ->keyBy(fn (Score $score) => $score->score_category_id.'-'.$score->team_id);
        $possibleEntries = $categories->count() * $teams->count();

        return view('adviser.scores.show', [
            'event' => $event,
            'categories' => $categories,
            'teams' => $teams,
            'scores' => $scores,
            'summary' => [
                'categories' => $categories->count(),
                'teams' => $teams->count(),
                'entries' => $scores->count(),
                'completion' => $possibleEntries > 0 ? round(($scores->count() / $possibleEntries) * 100, 1) : null,
                'total_points' => (float) $event->scores()->sum('points'),
                'maximum' => (float) $categories->sum('max_points'),
            ],
            'eventOptions' => Event::query()->orderByDesc('start_at')->get(['id', 'title', 'start_at']),
        ]);
    }

    public function storeCategory(ScoreCategoryRequest $request, Event $event): RedirectResponse
    {
        $event->scoreCategories()->create([
            ...$request->validated(),
            'sort_order' => ((int) $event->scoreCategories()->max('sort_order')) + 1,
        ]);

        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'event_id' => $event->id,
            'action' => 'score_category_created',
            'description' => "A scoring category was added to {$event->title}.",
        ]);

        return back()->with('success', 'Scoring category added.');
    }

    public function updateCategory(ScoreCategoryRequest $request, Event $event, ScoreCategory $scoreCategory): RedirectResponse
    {
        $this->ensureCategoryBelongsToEvent($scoreCategory, $event);
        $scoreCategory->update($request->validated());

        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'event_id' => $event->id,
            'action' => 'score_category_updated',
            'description' => "The {$scoreCategory->name} scoring category was updated for {$event->title}.",
        ]);

        return back()->with('success', 'Scoring category updated.');
    }

    public function destroyCategory(Request $request, Event $event, ScoreCategory $scoreCategory): RedirectResponse
    {
        $this->ensureCategoryBelongsToEvent($scoreCategory, $event);
        $name = $scoreCategory->name;
        $scoreCategory->delete();

        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'event_id' => $event->id,
            'action' => 'score_category_deleted',
            'description' => "The {$name} scoring category was removed from {$event->title}.",
        ]);

        return back()->with('success', "{$name} and its score entries were removed.");
    }

    public function update(ScoreEntriesRequest $request, Event $event): RedirectResponse
    {
        $changed = 0;

        DB::transaction(function () use ($request, $event, &$changed) {
            foreach ($request->validated('scores') as $categoryId => $teamScores) {
                foreach ($teamScores as $teamId => $points) {
                    $score = Score::query()
                        ->where('event_id', $event->id)
                        ->where('team_id', $teamId)
                        ->where('score_category_id', $categoryId)
                        ->first();

                    if ($points === null || $points === '') {
                        if ($score) {
                            $score->delete();
                            $changed++;
                        }

                        continue;
                    }

                    if (! $score || abs((float) $score->points - (float) $points) > 0.00001) {
                        $changed++;
                    }

                    $score ??= new Score([
                        'event_id' => $event->id,
                        'team_id' => $teamId,
                        'score_category_id' => $categoryId,
                    ]);
                    $score->points = $points;
                    $score->recorded_by = $request->user()->id;
                    $score->save();
                }
            }

            if ($changed > 0) {
                ActivityLog::create([
                    'actor_id' => $request->user()->id,
                    'event_id' => $event->id,
                    'action' => 'scores_updated',
                    'description' => "Scores for {$event->title} were updated ({$changed} entries changed).",
                ]);
            }
        });

        return back()->with($changed > 0 ? 'success' : 'info', $changed > 0 ? "Scores saved. {$changed} entries changed." : 'Scores are already up to date.');
    }

    private function ensureCategoryBelongsToEvent(ScoreCategory $category, Event $event): void
    {
        abort_unless($category->event_id === $event->id, 404);
    }
}
