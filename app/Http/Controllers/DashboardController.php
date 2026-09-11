<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Student\HomeController as StudentHomeController;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\Score;
use App\Models\Team;
use App\Models\User;
use App\Services\LeaderboardService;
use App\Services\StudentPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(LeaderboardService $leaderboards): View|RedirectResponse
    {
        $user = request()->user()->loadMissing('role');

        if ($user->isSboOfficer()) {
            return redirect()->route('officer.attendance.index');
        }

        if ($user->role?->name === 'Student') {
            return app(StudentHomeController::class)(app(StudentPortalService::class));
        }

        if (! $user->isSboAdviser()) {
            return view('dashboard');
        }

        $now = now();
        $today = $now->toDateString();
        $totalUsers = User::count();
        $totalSbo = User::whereHas('role', fn ($query) => $query->whereIn('name', ['SBO', 'SBO Officer']))->count();
        $totalFaculty = User::whereHas('role', fn ($query) => $query->where('name', 'Faculty'))->count();
        $totalStudents = User::whereHas('role', fn ($query) => $query->where('name', 'Student'))->count();
        $todayAttendanceCounts = Attendance::query()
            ->where(fn ($query) => $query->whereDate('attendance_date', $today)
                ->orWhere(fn ($query) => $query->whereNull('attendance_date')
                    ->whereHas('event', fn ($query) => $query->whereDate('start_at', $today))))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $todayPresent = (int) $todayAttendanceCounts->get('present', 0);
        $todayLate = (int) $todayAttendanceCounts->get('late', 0);
        $todayAbsent = (int) $todayAttendanceCounts->get('absent', 0);
        $todayExcused = (int) $todayAttendanceCounts->get('excused', 0);
        $todayPresentStudents = Attendance::query()
            ->where(fn ($query) => $query->whereDate('attendance_date', $today)
                ->orWhere(fn ($query) => $query->whereNull('attendance_date')
                    ->whereHas('event', fn ($query) => $query->whereDate('start_at', $today))))
            ->where('status', 'present')
            ->distinct()
            ->count('user_id');
        $todayMarked = $todayPresent + $todayLate + $todayAbsent + $todayExcused;
        $todayAttended = $todayPresent + $todayLate;
        $todayRate = $todayMarked > 0 ? round(($todayAttended / $todayMarked) * 100, 1) : null;

        $previousAttendanceEvent = Event::query()
            ->where('start_at', '<', $now->copy()->startOfDay())
            ->whereHas('attendances')
            ->withCount([
                'attendances as attendance_marked_count',
                'attendances as attended_count' => fn ($query) => $query->whereIn('status', Attendance::ATTENDED_STATUSES),
            ])
            ->latest('start_at')
            ->first();
        $previousRate = $previousAttendanceEvent && $previousAttendanceEvent->attendance_marked_count > 0
            ? round(($previousAttendanceEvent->attended_count / $previousAttendanceEvent->attendance_marked_count) * 100, 1)
            : null;

        $leaderboard = $leaderboards->rankings()->where('has_score', true)->take(5);

        return view('adviser.dashboard', [
            'stats' => [
                'total_users' => $totalUsers,
                'sbo' => $totalSbo,
                'faculty' => $totalFaculty,
                'students' => $totalStudents,
                'attendance_rate' => $todayRate,
                'students_present' => $todayPresentStudents,
                'upcoming_events' => Event::where('start_at', '>', $now)->count(),
                'points_awarded' => (float) Score::sum('points'),
                'ranked_teams' => Team::where('is_active', true)->whereHas('scores')->count(),
            ],
            'todayAttendance' => [
                'marked' => $todayMarked,
                'attended' => $todayAttended,
                'present' => $todayPresent,
                'late' => $todayLate,
                'absent' => $todayAbsent,
                'excused' => $todayExcused,
                'rate' => $todayRate,
            ],
            'attendanceTrend' => $todayRate !== null && $previousRate !== null ? round($todayRate - $previousRate, 1) : null,
            'previousAttendanceEvent' => $previousAttendanceEvent,
            'todayEvents' => Event::query()
                ->with('assignedUsers')
                ->where('start_at', '<=', now()->endOfDay())
                ->where('end_at', '>=', now()->startOfDay())
                ->orderBy('start_at')
                ->get(),
            'upcomingEvents' => Event::query()
                ->where('start_at', '>', $now)
                ->orderBy('start_at')
                ->limit(3)
                ->get(),
            'recentAttendances' => Attendance::query()
                ->with(['user', 'event'])
                ->orderByRaw('COALESCE(checked_in_at, updated_at) DESC')
                ->limit(6)
                ->get(),
            'leaderboard' => $leaderboard,
            'startingTomorrow' => Event::whereBetween('start_at', [
                now()->addDay()->startOfDay(),
                now()->addDay()->endOfDay(),
            ])->orderBy('start_at')->first(),
        ]);
    }
}
