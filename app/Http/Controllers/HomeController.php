<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $events = Event::query()
            ->with(['type', 'status'])
            ->where('end_at', '>=', now())
            ->whereHas('status', fn ($query) => $query->where('label', 'active'))
            ->orderBy('start_at')
            ->limit(8)
            ->get();

        $calendarEvents = $events->take(3)->map(fn (Event $event) => [
            'name' => $event->type?->label ?: $event->title,
            'title' => $event->title,
            'start_at' => $event->start_at,
            'end_at' => $event->end_at,
            'location' => $event->location ?: 'CITE Campus',
            'timing' => $event->start_at->lte(now()) ? 'current' : 'upcoming',
        ]);

        if ($calendarEvents->isEmpty()) {
            $calendarEvents = collect([
                [
                    'name' => 'IT Days',
                    'title' => 'IT Days 2026',
                    'start_at' => Carbon::create(2026, 9, 12, 9),
                    'end_at' => Carbon::create(2026, 9, 16, 17),
                    'location' => 'CITE Campus',
                    'timing' => now()->between(Carbon::create(2026, 9, 12), Carbon::create(2026, 9, 16, 23, 59)) ? 'current' : 'upcoming',
                ],
                [
                    'name' => 'IT Expo',
                    'title' => 'IT Expo 2026',
                    'start_at' => Carbon::create(2026, 9, 19, 9),
                    'end_at' => Carbon::create(2026, 9, 19, 17),
                    'location' => 'CITE Multimedia Room',
                    'timing' => now()->between(Carbon::create(2026, 9, 19), Carbon::create(2026, 9, 19, 23, 59)) ? 'current' : 'upcoming',
                ],
            ]);
        }

        return view('welcome', [
            'featuredEvents' => $events->take(3),
            'upcomingEvents' => $events->take(5),
            'calendarEvents' => $calendarEvents,
        ]);
    }
}
