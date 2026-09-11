<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\StudentPortalService;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(StudentPortalService $portal): View
    {
        $user = request()->user()->loadMissing(['yearLevel', 'teams.schoolYear']);
        $currentEvent = $portal->currentEvent($user);

        return view('student.home', [
            'user' => $user,
            'currentEvent' => $currentEvent,
            'leaderboard' => $portal->leaderboard($currentEvent)->take(4),
            'events' => $portal->eligibleEvents($user, true),
            'posts' => Post::approved()->with(['author.teams', 'event'])->latest('reviewed_at')->paginate(10),
            'myPosts' => $user->posts()->whereIn('status', ['pending', 'rejected'])->with('event')->latest()->get(),
            'announcements' => $currentEvent?->posts()->approved()->where('category', 'announcement')->with('author')->latest('reviewed_at')->limit(3)->get() ?? collect(),
        ]);
    }
}
