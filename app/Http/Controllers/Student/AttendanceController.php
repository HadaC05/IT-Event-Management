<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AttendanceQrToken;
use App\Services\StudentPortalService;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function show(StudentPortalService $portal): View
    {
        $user = request()->user()->load(['teams', 'yearLevel']);
        $event = $portal->currentEvent($user);
        $passes = collect();

        if ($event) {
            foreach (['morning', 'afternoon'] as $session) {
                $token = AttendanceQrToken::firstOrCreate(
                    [
                        'event_id' => $event->id,
                        'user_id' => $user->id,
                        'session' => $session,
                    ],
                    ['token' => Str::random(40)],
                );

                $passes->put(
                    $session,
                    route('home', ['attendance_pass' => $token->token]),
                );
            }
        }

        $attendanceQuery = $user->attendances();
        $summary = [
            'total' => (clone $attendanceQuery)->count(),
            'present' => (clone $attendanceQuery)->where('status', 'present')->count(),
            'late' => (clone $attendanceQuery)->where('status', 'late')->count(),
            'absent' => (clone $attendanceQuery)->where('status', 'absent')->count(),
            'excused' => (clone $attendanceQuery)->where('status', 'excused')->count(),
        ];

        return view('student.attendance', [
            'user' => $user,
            'event' => $event,
            'passes' => $passes,
            'summary' => $summary,
            'currentAttendance' => $event
                ? $user->attendances()->where('event_id', $event->id)->latest('attendance_date')->first()
                : null,
            'attendances' => $user->attendances()
                ->with('event')
                ->latest('attendance_date')
                ->latest('updated_at')
                ->paginate(10),
        ]);
    }
}
