<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\StudentPortalService;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(StudentPortalService $portal): View
    {
        $user = request()->user()->load('teams');
        $eligibleEvents = $portal->eligibleEvents($user);

        return view('student.events', [
            'user' => $user,
            'events' => $eligibleEvents
                ->filter(fn ($event) => $event->end_at->gte(now()) && in_array($event->status?->label, ['upcoming', 'ongoing'], true))
                ->values(),
            'pastEvents' => $eligibleEvents
                ->filter(fn ($event) => $event->end_at->lt(now()) || $event->status?->label === 'completed')
                ->sortByDesc('end_at')
                ->values(),
        ]);
    }
}
