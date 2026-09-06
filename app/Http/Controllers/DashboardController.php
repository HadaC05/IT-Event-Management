<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = request()->user()->loadMissing('role');

        if (! $user->isSboAdviser()) {
            return view('dashboard');
        }

        $now = now();

        return view('adviser.dashboard', [
            'stats' => [
                'total_users' => User::count(),
                'sbo' => User::whereHas('role', fn ($query) => $query->where('name', 'SBO'))->count(),
                'faculty' => User::whereHas('role', fn ($query) => $query->where('name', 'Faculty'))->count(),
                'students' => User::whereHas('role', fn ($query) => $query->where('name', 'Student'))->count(),
                'events' => Event::count(),
                'upcoming' => Event::where('start_at', '>', $now)->count(),
                'ongoing' => Event::where('start_at', '<=', $now)
                    ->where('end_at', '>=', $now)
                    ->count(),
            ],
            'activities' => ActivityLog::with(['actor', 'subjectUser', 'event'])
                ->latest()
                ->limit(8)
                ->get(),
            'startingTomorrow' => Event::whereBetween('start_at', [
                now()->addDay()->startOfDay(),
                now()->addDay()->endOfDay(),
            ])->orderBy('start_at')->first(),
        ]);
    }
}
