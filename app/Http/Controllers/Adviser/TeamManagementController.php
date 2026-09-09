<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Http\Requests\Adviser\TeamRequest;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamManagementController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'school_year' => ['nullable', Rule::exists('school_years', 'id')],
        ]);

        $teams = Team::query()
            ->with(['schoolYear', 'members' => fn ($query) => $query->limit(5)])
            ->withCount('members')
            ->withSum('scores', 'points')
            ->when($validated['search'] ?? null, function (Builder $query, string $search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', $term)
                    ->orWhereHas('members', fn (Builder $query) => $query
                        ->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)));
            })
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'))
            ->when($validated['school_year'] ?? null, fn (Builder $query, int|string $schoolYear) => $query->where('school_year_id', $schoolYear))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(9)
            ->withQueryString();

        $activeStudents = User::query()
            ->where('role_id', Role::where('name', 'Student')->value('id'))
            ->whereHas('userStatus', fn (Builder $query) => $query->where('label', 'active'));

        $schoolYears = SchoolYear::orderByDesc('label')->get();

        return view('adviser.teams.index', [
            'teams' => $teams,
            'schoolYears' => $schoolYears,
            'teamSummary' => [
                'total' => Team::count(),
                'active' => Team::where('is_active', true)->count(),
                'students' => (clone $activeStudents)->count(),
                'assigned' => (clone $activeStudents)->whereHas('teams')->count(),
                'unassigned' => (clone $activeStudents)->whereDoesntHave('teams')->count(),
            ],
        ]);
    }

    public function create(): RedirectResponse
    {
        return to_route('adviser.teams.index', ['create' => 1]);
    }

    public function store(TeamRequest $request): RedirectResponse
    {
        [$data] = $this->teamData($request);

        $team = DB::transaction(function () use ($request, $data) {
            $team = Team::create($data);
            $this->log($request, 'team_created', "{$team->name} was created without assigned members.");

            return $team;
        });

        return to_route('adviser.teams.index')->with('success', "{$team->name} was created successfully.");
    }

    public function randomize(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('randomize', [
            'school_year_id' => ['required', Rule::exists('school_years', 'id')],
        ]);

        $result = DB::transaction(function () use ($request, $validated) {
            $schoolYear = SchoolYear::query()->lockForUpdate()->findOrFail($validated['school_year_id']);

            if ($schoolYear->teams_randomized_at) {
                return ['error' => "Students for SY {$schoolYear->label} have already been randomized and cannot be randomized again."];
            }

            $activeTeams = Team::query()
                ->where('school_year_id', $schoolYear->id)
                ->where('is_active', true)
                ->orderBy('id')
                ->get();

            if ($activeTeams->count() < 2) {
                return ['error' => 'Create at least two active tribes for this school year before randomizing students.'];
            }

            $studentIds = User::query()
                ->where('role_id', Role::where('name', 'Student')->value('id'))
                ->whereHas('userStatus', fn (Builder $query) => $query->where('label', 'active'))
                ->pluck('id')
                ->shuffle()
                ->values();

            if ($studentIds->isEmpty()) {
                return ['error' => 'There are no active students to distribute.'];
            }

            Team::query()
                ->where('school_year_id', $schoolYear->id)
                ->get()
                ->each(fn (Team $team) => $team->members()->detach($studentIds->all()));

            $assignments = $activeTeams->mapWithKeys(fn (Team $team) => [$team->id => []])->all();
            foreach ($studentIds as $index => $studentId) {
                $teamId = $activeTeams[$index % $activeTeams->count()]->id;
                $assignments[$teamId][] = $studentId;
            }

            foreach ($activeTeams as $team) {
                $team->members()->attach($assignments[$team->id]);
            }

            $schoolYear->update(['teams_randomized_at' => now()]);

            $this->log(
                $request,
                'team_members_randomized',
                $studentIds->count()." active students were distributed across {$activeTeams->count()} tribes for SY {$schoolYear->label}."
            );

            return [
                'school_year_id' => $schoolYear->id,
                'student_count' => $studentIds->count(),
                'team_count' => $activeTeams->count(),
            ];
        });

        if (isset($result['error'])) {
            return back()->withErrors(['randomize' => $result['error']], 'randomize');
        }

        return to_route('adviser.teams.index', ['school_year' => $result['school_year_id']])
            ->with('success', $result['student_count']." students were randomly and evenly distributed across {$result['team_count']} tribes. This distribution is now locked.");
    }

    public function edit(Team $team): View
    {
        $team->load(['members', 'schoolYear']);

        return view('adviser.teams.edit', array_merge($this->formData(), ['team' => $team]));
    }

    public function update(TeamRequest $request, Team $team): RedirectResponse
    {
        [$data, $memberIds] = $this->teamData($request);

        DB::transaction(function () use ($request, $team, $data, $memberIds) {
            $team->update($data);
            $team->members()->sync($memberIds);
            $this->log($request, 'team_updated', "{$team->name} was updated with ".count($memberIds).' members.');
        });

        return to_route('adviser.teams.index')->with('success', "{$team->name} was updated successfully.");
    }

    public function toggleStatus(Request $request, Team $team): RedirectResponse
    {
        $team->update(['is_active' => ! $team->is_active]);
        $state = $team->is_active ? 'activated' : 'deactivated';
        $this->log($request, 'team_status_changed', "{$team->name} was {$state}.");

        return back()->with('success', "{$team->name} was {$state}.");
    }

    private function formData(): array
    {
        return [
            'schoolYears' => SchoolYear::orderByDesc('label')->get(),
            'students' => User::query()
                ->with(['teams.schoolYear', 'yearLevel'])
                ->where('role_id', Role::where('name', 'Student')->value('id'))
                ->whereHas('userStatus', fn (Builder $query) => $query->where('label', 'active'))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
        ];
    }

    private function teamData(TeamRequest $request): array
    {
        $validated = $request->validated();
        $memberIds = collect($validated['member_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        unset($validated['member_ids']);

        return [$validated, $memberIds];
    }

    private function log(Request $request, string $action, string $description): void
    {
        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'action' => $action,
            'description' => $description,
        ]);
    }
}
