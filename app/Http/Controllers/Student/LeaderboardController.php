<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\StudentPortalService;
use Illuminate\View\View;

class LeaderboardController extends Controller
{
    public function index(StudentPortalService $portal): View
    {
        $user = request()->user()->load('teams');
        $event = $portal->currentEvent($user);

        return view('student.leaderboard', ['user' => $user, 'event' => $event, 'teams' => $portal->leaderboard($event), 'categories' => $event?->scoreCategories()->get() ?? collect()]);
    }
}
