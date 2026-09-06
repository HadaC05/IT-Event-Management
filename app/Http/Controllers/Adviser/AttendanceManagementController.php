<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Http\Requests\Adviser\AttendanceBulkRequest;
use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceManagementController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'timing' => ['nullable', Rule::in(['upcoming', 'ongoing', 'completed'])],
        ]);
        $now = now();

        $events = Event::query()
            ->withCount([
                'attendances',
                'attendances as attended_count' => fn (Builder $query) => $query->whereIn('status', Attendance::ATTENDED_STATUSES),
                'attendances as present_count' => fn (Builder $query) => $query->where('status', 'present'),
                'attendances as late_count' => fn (Builder $query) => $query->where('status', 'late'),
                'attendances as absent_count' => fn (Builder $query) => $query->where('status', 'absent'),
                'attendances as excused_count' => fn (Builder $query) => $query->where('status', 'excused'),
            ])
            ->when($validated['search'] ?? null, function (Builder $query, string $search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn (Builder $query) => $query
                    ->where('title', 'like', $term)
                    ->orWhere('location', 'like', $term));
            })
            ->when(($validated['timing'] ?? null) === 'upcoming', fn (Builder $query) => $query->where('start_at', '>', $now))
            ->when(($validated['timing'] ?? null) === 'ongoing', fn (Builder $query) => $query->where('start_at', '<=', $now)->where('end_at', '>=', $now))
            ->when(($validated['timing'] ?? null) === 'completed', fn (Builder $query) => $query->where('end_at', '<', $now))
            ->orderByDesc('start_at')
            ->paginate(10)
            ->withQueryString();

        $events->through(function (Event $event) {
            $expected = $event->expectedParticipants()->count();
            $event->setAttribute('expected_count', $expected);
            $event->setAttribute('coverage_rate', $expected > 0 ? min(100, round(($event->attendances_count / $expected) * 100, 1)) : null);
            $event->setAttribute('attendance_rate', $event->attendances_count > 0 ? round(($event->attended_count / $event->attendances_count) * 100, 1) : null);

            return $event;
        });

        return view('adviser.attendance.index', [
            'events' => $events,
            'summary' => [
                'events' => Event::count(),
                'tracked' => Attendance::query()->distinct()->count('event_id'),
                'records' => Attendance::count(),
                'attended' => Attendance::whereIn('status', Attendance::ATTENDED_STATUSES)->count(),
            ],
        ]);
    }

    public function show(Request $request, Event $event): View
    {
        $event->load(['attendances.user', 'audienceTeams', 'audienceYearLevels']);
        $expectedParticipants = $event->expectedParticipants()->load(['teams.schoolYear', 'yearLevel']);
        $recordedParticipants = User::query()
            ->with(['teams.schoolYear', 'yearLevel'])
            ->whereIn('id', $event->attendances->pluck('user_id'))
            ->get();
        $records = $event->attendances->keyBy('user_id');
        $participants = $expectedParticipants
            ->merge($recordedParticipants)
            ->unique('id')
            ->sortBy(fn ($user) => mb_strtolower($user->last_name.' '.$user->first_name))
            ->values();
        $counts = collect(Attendance::STATUSES)->mapWithKeys(fn ($status) => [$status => $event->attendances->where('status', $status)->count()]);
        $recorded = $counts->sum();
        $attended = $counts->only(Attendance::ATTENDED_STATUSES)->sum();

        return view('adviser.attendance.show', [
            'event' => $event,
            'participants' => $participants,
            'records' => $records,
            'counts' => $counts,
            'summary' => [
                'expected' => $expectedParticipants->count(),
                'recorded' => $recorded,
                'unrecorded' => max(0, $expectedParticipants->count() - $recorded),
                'rate' => $recorded > 0 ? round(($attended / $recorded) * 100, 1) : null,
            ],
            'expectedParticipantIds' => $expectedParticipants->pluck('id'),
            'eventOptions' => Event::query()->orderByDesc('start_at')->get(['id', 'title', 'start_at']),
        ]);
    }

    public function update(AttendanceBulkRequest $request, Event $event): RedirectResponse
    {
        $changed = 0;

        DB::transaction(function () use ($request, $event, &$changed) {
            foreach ($request->validated('records') as $userId => $record) {
                $status = $record['status'] ?? null;
                $attendance = Attendance::where('event_id', $event->id)->where('user_id', $userId)->first();

                if (! $status) {
                    if ($attendance) {
                        $attendance->delete();
                        $changed++;
                    }

                    continue;
                }

                $attendance ??= new Attendance(['event_id' => $event->id, 'user_id' => $userId]);
                if (! $attendance->exists || $attendance->status !== $status) {
                    $changed++;
                }
                $attendance->status = $status;
                $attendance->recorded_by = $request->user()->id;
                $attendance->checked_in_at = in_array($status, Attendance::ATTENDED_STATUSES, true)
                    ? ($attendance->checked_in_at ?? now())
                    : null;
                $attendance->save();
            }

            if ($changed > 0) {
                ActivityLog::create([
                    'actor_id' => $request->user()->id,
                    'event_id' => $event->id,
                    'action' => 'attendance_updated',
                    'description' => "Attendance for {$event->title} was updated ({$changed} records changed).",
                ]);
            }
        });

        $message = $changed > 0 ? "Attendance saved. {$changed} records changed." : 'Attendance is already up to date.';

        return back()->with($changed > 0 ? 'success' : 'info', $message);
    }
}
