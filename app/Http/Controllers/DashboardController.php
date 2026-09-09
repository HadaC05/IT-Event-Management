<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceQrToken;
use App\Models\Event;
use App\Models\Score;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        $user = request()->user()->loadMissing('role');

        if ($user->isSboOfficer()) {
            return redirect()->route('officer.attendance.index');
        }

        if ($user->role?->name === 'Student') {
            $user->loadMissing(['studentProfile.yearLevel', 'teams.schoolYear']);
            $events = Event::query()
                ->with(['status', 'audienceTeams'])
                ->where(function ($query) {
                    $query->whereDoesntHave('status')
                        ->orWhereHas('status', fn ($query) => $query->where('label', 'active'));
                })
                ->where('end_at', '>=', now())
                ->orderBy('start_at')
                ->get()
                ->filter(fn (Event $event) => $event->expectedParticipantsQuery()->whereKey($user->id)->exists())
                ->values();

            $events->each(function (Event $event) use ($user) {
                foreach (['morning', 'afternoon'] as $session) {
                    $token = AttendanceQrToken::firstOrCreate([
                        'event_id' => $event->id,
                        'user_id' => $user->id,
                        'session' => $session,
                    ], [
                        'token' => Str::random(40),
                    ]);
                    $event->setAttribute($session.'_qr_payload', route('home', [
                        'attendance_pass' => $token->token,
                    ]));
                }
            });

            return view('student.dashboard', compact('user', 'events'));
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
            ->whereHas('event', fn ($query) => $query->whereDate('start_at', $today))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $todayPresent = (int) $todayAttendanceCounts->get('present', 0);
        $todayLate = (int) $todayAttendanceCounts->get('late', 0);
        $todayAbsent = (int) $todayAttendanceCounts->get('absent', 0);
        $todayExcused = (int) $todayAttendanceCounts->get('excused', 0);
        $todayPresentStudents = Attendance::query()
            ->whereHas('event', fn ($query) => $query->whereDate('start_at', $today))
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

        $leaderboard = Team::query()
            ->where('is_active', true)
            ->whereHas('scores')
            ->withCount('members')
            ->withSum('scores', 'points')
            ->orderByDesc('scores_sum_points')
            ->orderBy('name')
            ->limit(5)
            ->get();

        $previousPoints = null;
        $previousRank = 0;
        $leaderboard->each(function (Team $team, int $index) use (&$previousPoints, &$previousRank) {
            $points = (float) $team->scores_sum_points;
            $rank = $previousPoints !== null && $points === $previousPoints ? $previousRank : $index + 1;

            $team->setAttribute('rank', $rank);
            $previousPoints = $points;
            $previousRank = $rank;
        });

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
                ->whereDate('start_at', $today)
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
