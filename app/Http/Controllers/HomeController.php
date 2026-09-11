<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventStatusSynchronizer;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(EventStatusSynchronizer $statusSynchronizer): View
    {
        $statusSynchronizer->sync();

        $events = Event::query()
            ->with(['type', 'status'])
            ->where('end_at', '>=', now())
            ->whereHas('status', fn ($query) => $query->whereIn('label', ['upcoming', 'ongoing']))
            ->orderBy('start_at')
            ->limit(8)
            ->get();

        $featuredEvents = Event::query()
            ->with(['type', 'status'])
            ->featuredForCarousel()
            ->limit(6)
            ->get();

        $calendarEvents = $events->take(3)->map(fn (Event $event) => [
            'name' => $event->type?->label ?: $event->title,
            'title' => $event->title,
            'start_at' => $event->start_at,
            'end_at' => $event->end_at,
            'location' => $event->location ?: 'CITE Campus',
            'timing' => $event->start_at->lte(now()) ? 'current' : 'upcoming',
        ]);

        return view('welcome', [
            'featuredEvents' => $featuredEvents,
            'upcomingEvents' => $events->take(5),
            'calendarEvents' => $calendarEvents,
        ]);
    }
}
