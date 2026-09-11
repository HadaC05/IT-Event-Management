<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Post;
use App\Models\SboOfficerAssignment;
use App\Models\Team;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function show(): View
    {
        $user = request()->user()->load('teams');
        $team = $user->teams->first();
        if ($team) {
            $team->load(['members.yearLevel', 'scores.category', 'scores.event', 'schoolYear']);
        }
        $ranked = Team::where('is_active', true)->withSum('scores', 'points')->orderByDesc('scores_sum_points')->get();
        $rankIndex = $team ? $ranked->search(fn ($item) => $item->id === $team->id) : false;

        return view('student.team', ['user' => $user, 'team' => $team, 'rank' => $rankIndex === false ? null : $rankIndex + 1,
            'leaders' => $team ? SboOfficerAssignment::with('officerAccount')->where('team_id', $team->id)->where('status', 'Active')->get() : collect(),
            'announcements' => $team ? Post::approved()->where('category', 'announcement')->whereHas('author.teams', fn ($q) => $q->where('teams.id', $team->id))->latest('reviewed_at')->take(5)->get() : collect(),
            'activities' => $team ? Event::where('end_at', '>=', now())->whereHas('audienceTeams', fn ($q) => $q->where('teams.id', $team->id))->orderBy('start_at')->take(5)->get() : collect()]);
    }
}
