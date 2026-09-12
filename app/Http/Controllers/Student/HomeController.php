<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Post;
use App\Services\StudentPortalService;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(StudentPortalService $portal): View
    {
        $user = request()->user()->loadMissing(['yearLevel', 'teams.schoolYear']);
        $events = $portal->eligibleEvents($user, true);
        $currentEvent = $events
            ->sortBy(fn (Event $event) => $event->start_at->isPast() ? 0 : $event->start_at->timestamp)
            ->first();
        $eligibleEventIds = $events->pluck('id');

        return view('student.home', [
            'user' => $user,
            'currentEvent' => $currentEvent,
            'leaderboard' => $portal->leaderboard($currentEvent)->take(4),
            'events' => $events,
            'featuredEvents' => Event::query()
                ->with(['type', 'status'])
                ->featuredForCarousel()
                ->limit(6)
                ->get(),
            'posts' => Post::approved()
                ->where(fn ($query) => $query
                    ->where('is_official', false)
                    ->orWhereNull('event_id')
                    ->orWhereIn('event_id', $eligibleEventIds))
                ->with([
                    'author.teams',
                    'event',
                    'reactions' => fn ($query) => $query->where('user_id', $user->id),
                    'comments' => fn ($query) => $query->with('author')->latest()->limit(3),
                ])
                ->withCount(['reactions', 'comments'])
                ->latest('reviewed_at')
                ->paginate(10),
            'myPosts' => $user->posts()->whereIn('status', ['pending', 'rejected'])->with('event')->latest()->get(),
            'announcements' => $currentEvent?->posts()->approved()->where('category', 'announcement')->with('author')->latest('reviewed_at')->limit(3)->get() ?? collect(),
        ]);
    }
}
