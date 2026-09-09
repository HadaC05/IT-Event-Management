<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\AttendanceQrToken;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request, ?Event $event = null): View
    {
        $officer = $request->user()->loadMissing('officerTeam');
        $events = Event::query()
            ->where(function (Builder $query) {
                $query->whereDoesntHave('status')->orWhereHas('status', fn (Builder $query) => $query->where('label', 'active'));
            })
            ->where('end_at', '>=', now()->copy()->subDay())
            ->orderByRaw('CASE WHEN start_at <= ? AND end_at >= ? THEN 0 WHEN start_at > ? THEN 1 ELSE 2 END', [now(), now(), now()])
            ->orderBy('start_at')
            ->get()
            ->filter(fn (Event $candidate) => $officer->officerTeam && $candidate->expectedParticipantsQuery()
                ->whereHas('teams', fn (Builder $query) => $query->where('teams.id', $officer->officer_team_id))
                ->exists())
            ->values();

        if ($event && ! $events->contains('id', $event->id)) {
            abort(403, 'This event does not include students from your assigned tribe.');
        }

        $event ??= $events->first();
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'unmarked', 'complete'])],
        ]);

        $participants = collect();
        $records = collect();
        $activeSlot = null;
        $slots = [];

        if ($event && $officer->officerTeam) {
            $slots = $event->attendanceSlots();
            $activeSlot = $this->activeSlot($event);
            $participantQuery = $event->expectedParticipantsQuery()
                ->whereHas('teams', fn (Builder $query) => $query->where('teams.id', $officer->officer_team_id))
                ->when($validated['search'] ?? null, function (Builder $query, string $search) {
                    $term = '%'.addcslashes($search, '%_\\').'%';
                    $query->where(fn (Builder $query) => $query
                        ->where('first_name', 'like', $term)
                        ->orWhere('middle_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('id_number', 'like', $term)
                        ->orWhereHas('studentProfile', fn (Builder $profile) => $profile
                            ->where('first_name', 'like', $term)->orWhere('middle_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)->orWhere('student_id', 'like', $term)));
                });

            if (($validated['status'] ?? 'all') === 'unmarked' && $activeSlot) {
                $column = $activeSlot['key'].'_at';
                $participantQuery->whereDoesntHave('attendances', fn (Builder $query) => $query
                    ->where('event_id', $event->id)->whereNotNull($column));
            }

            if (($validated['status'] ?? 'all') === 'complete') {
                $participantQuery->whereHas('attendances', fn (Builder $query) => $query
                    ->where('event_id', $event->id)
                    ->whereNotNull('morning_in_at')->whereNotNull('morning_out_at')
                    ->whereNotNull('afternoon_in_at')->whereNotNull('afternoon_out_at'));
            }

            $participants = $participantQuery->orderBy('last_name')->orderBy('first_name')->paginate(50)->withQueryString();
            $records = $event->attendances()->whereIn('user_id', $participants->getCollection()->pluck('id'))->get()->keyBy('user_id');
        }

        return view('officer.attendance', compact('officer', 'events', 'event', 'participants', 'records', 'slots', 'activeSlot'));
    }

    public function scan(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'id_number' => ['nullable', 'string', 'max:255', 'required_without:qr_content'],
            'qr_content' => ['nullable', 'string', 'max:512', 'required_without:id_number'],
        ]);
        abort_if($event->status?->label === 'inactive', 403, 'Attendance scanning is disabled for this event.');
        $officer = $request->user()->loadMissing('officerTeam');
        abort_unless($officer->officerTeam, 403, 'An adviser must assign your account to a tribe first.');

        $qrToken = null;
        if (filled($data['qr_content'] ?? null)) {
            $tokenValue = null;
            if (str_starts_with($data['qr_content'], 'CITEATT:')) {
                $tokenValue = substr($data['qr_content'], 8);
            } elseif (filter_var($data['qr_content'], FILTER_VALIDATE_URL)) {
                parse_str((string) parse_url($data['qr_content'], PHP_URL_QUERY), $query);
                $tokenValue = $query['attendance_pass'] ?? null;
            }

            if (! is_string($tokenValue) || ! preg_match('/^[A-Za-z0-9]{40}$/', $tokenValue)) {
                return back()->with('error', 'This attendance QR code is invalid. Ask the student to open it again.');
            }

            $qrToken = AttendanceQrToken::query()
                ->where('token', $tokenValue)
                ->where('event_id', $event->id)
                ->first();
            if (! $qrToken) {
                return back()->with('error', 'This QR code does not belong to the selected event.');
            }
        }

        $student = $event->expectedParticipantsQuery()
            ->when(
                $qrToken,
                fn (Builder $query) => $query->whereKey($qrToken->user_id),
                fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->where('id_number', $data['id_number'])
                    ->orWhereHas('studentProfile', fn (Builder $profile) => $profile->where('student_id', $data['id_number']))),
            )
            ->whereHas('teams', fn (Builder $query) => $query->where('teams.id', $officer->officer_team_id))
            ->first();

        if (! $student) {
            return back()->with('error', 'Student number not found in '.$officer->officerTeam->name.' for this event.')->withInput();
        }

        $slot = $this->activeSlot($event);
        if (! $slot) {
            return back()->with('warning', 'No attendance scan window is open right now. Check the event schedule.');
        }
        if ($qrToken && ! str_starts_with($slot['key'], $qrToken->session.'_')) {
            return back()->with('error', 'Open the student’s '.ucfirst(strtok($slot['key'], '_')).' QR code for this checkpoint.');
        }

        $column = $slot['key'].'_at';
        $alreadyMarked = false;

        DB::transaction(function () use ($event, $student, $officer, $slot, $column, &$alreadyMarked) {
            $attendance = Attendance::firstOrNew(['event_id' => $event->id, 'user_id' => $student->id]);
            if ($attendance->{$column}) {
                $alreadyMarked = true;

                return;
            }

            $attendance->{$column} = now();
            $attendance->checked_in_at ??= now();
            $attendance->status = 'present';
            $attendance->recorded_by = $officer->id;
            $attendance->save();

            ActivityLog::create([
                'actor_id' => $officer->id,
                'subject_user_id' => $student->id,
                'student_profile_id' => $student->student_profile_id,
                'event_id' => $event->id,
                'action' => 'officer_attendance_scanned',
                'acting_role' => $officer->role?->name,
                'description' => "{$student->full_name} was recorded for {$slot['label']} by {$officer->full_name}.",
            ]);
        });

        return back()->with($alreadyMarked ? 'info' : 'success', $alreadyMarked
            ? "{$student->full_name} is already marked for {$slot['label']}."
            : "{$student->full_name} — {$slot['label']} recorded at ".now()->format('g:i A').'.');
    }

    private function activeSlot(Event $event): ?array
    {
        $now = now();
        if ($now->lt($event->start_at) || $now->gt($event->end_at)) {
            return null;
        }

        $slots = $event->attendanceSlots();
        $active = null;
        foreach ($slots as $slot) {
            if ($now->gte($slot['at'])) {
                $active = $slot;
            }
        }

        return $active;
    }
}
