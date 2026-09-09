<?php

namespace App\Http\Requests\Adviser;

use App\Models\AttendanceSessionMode;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\EventConflictDetector;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSboAdviser() === true;
    }

    public function rules(): array
    {
        return [
            'event_type_id' => ['nullable', 'integer', Rule::exists('event_types', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['required', 'string', 'max:255'],
            'audience_type' => ['required', Rule::in(['all_students', 'selected_tribes', 'selected_year_levels', 'specific_students'])],
            'tribe_ids' => ['nullable', 'required_if:audience_type,selected_tribes', 'array', 'min:1'],
            'tribe_ids.*' => ['integer', 'distinct', Rule::exists('teams', 'id')],
            'year_level_ids' => ['nullable', 'required_if:audience_type,selected_year_levels', 'array', 'min:1'],
            'year_level_ids.*' => ['integer', 'distinct', Rule::exists('year_levels', 'id')],
            'participant_ids' => ['nullable', 'required_if:audience_type,specific_students', 'array', 'min:1'],
            'participant_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'end_time' => ['required', 'date_format:H:i'],
            'attendance_days' => ['nullable', 'array', 'max:31'],
            'attendance_days.*' => ['array'],
            'attendance_days.*.date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'distinct'],
            'attendance_days.*.attendance_session_mode_id' => ['required', 'integer', Rule::exists('attendance_session_modes', 'id')],
            'attendance_days.*.morning_in' => ['nullable', 'date_format:H:i'],
            'attendance_days.*.morning_out' => ['nullable', 'date_format:H:i'],
            'attendance_days.*.afternoon_in' => ['nullable', 'date_format:H:i'],
            'attendance_days.*.afternoon_out' => ['nullable', 'date_format:H:i'],
            'event_status_id' => ['nullable', Rule::exists('event_statuses', 'id')],
            'poster' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_poster' => ['nullable', 'boolean'],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_user_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
            'acknowledge_conflicts' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $defaults = [];

        if (is_array($this->input('attendance_days'))) {
            $defaults['attendance_days'] = collect($this->input('attendance_days'))->map(function ($day, $key) {
                if (! is_array($day)) {
                    return $day;
                }

                $modeCode = $day['mode'] ?? (filled($day['afternoon_in'] ?? null) ? 'split' : (filled($day['morning_in'] ?? null) ? 'single' : 'none'));
                if (! isset($day['attendance_session_mode_id']) && $modeCode) {
                    $legacyCode = match ($modeCode) {
                        'single' => AttendanceSessionMode::WHOLE_DAY,
                        'split' => AttendanceSessionMode::TWO_SESSIONS,
                        default => AttendanceSessionMode::NONE,
                    };
                    $day['attendance_session_mode_id'] = AttendanceSessionMode::where('code', $legacyCode)->value('id');
                }
                unset($day['mode']);

                if (! isset($day['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $key)) {
                    return ['date' => $key, ...$day];
                }

                return $day;
            })->values()->all();
        }

        if (is_array($this->input('assigned_user_ids'))) {
            $defaults['assigned_user_ids'] = array_values(array_filter($this->input('assigned_user_ids'), fn ($id) => filled($id)));
        }

        if (! $this->filled('audience_type')) {
            $defaults['audience_type'] = 'all_students';
        }

        if (! $this->filled('end_date') && $this->filled('start_date')) {
            $defaults['end_date'] = $this->input('start_date');
        }

        if ($defaults !== []) {
            $this->merge($defaults);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $start = null;
            $end = null;
            if (! $validator->errors()->hasAny(['start_date', 'start_time', 'end_date', 'end_time'])) {
                $start = Carbon::createFromFormat('Y-m-d H:i', "{$this->start_date} {$this->start_time}");
                $end = Carbon::createFromFormat('Y-m-d H:i', "{$this->end_date} {$this->end_time}");

                if ($end->lessThanOrEqualTo($start)) {
                    $validator->errors()->add('end_time', 'The event must end after it starts.');
                }
                if ($start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) > 30) {
                    $validator->errors()->add('end_date', 'An event schedule cannot exceed 31 days.');
                }
            }

            if ($start && $end && is_array($this->input('attendance_days'))) {
                $submittedDates = collect($this->input('attendance_days'))->pluck('date')->filter()->sort()->values();
                if ($submittedDates->isEmpty()) {
                    $validator->errors()->add('attendance_days', 'Add at least one event day.');
                } elseif ($submittedDates->first() !== $start->toDateString() || $submittedDates->last() !== $end->toDateString()) {
                    $validator->errors()->add('attendance_days', 'The event dates do not match the first and last daily schedules.');
                }

                foreach ($this->input('attendance_days') as $index => $day) {
                    $date = $day['date'] ?? "day {$index}";
                    $mode = AttendanceSessionMode::find($day['attendance_session_mode_id'] ?? null)?->code ?? AttendanceSessionMode::NONE;
                    $fields = ['morning_in', 'morning_out', 'afternoon_in', 'afternoon_out'];
                    $provided = collect($fields)->filter(fn (string $field) => filled($day[$field] ?? null));
                    $requiredCount = $mode === AttendanceSessionMode::TWO_SESSIONS ? 4 : ($mode === AttendanceSessionMode::WHOLE_DAY ? 2 : 0);
                    if ($provided->count() !== $requiredCount) {
                        $message = match ($mode) {
                            AttendanceSessionMode::TWO_SESSIONS => 'Enter all four morning and afternoon times for this day.',
                            AttendanceSessionMode::WHOLE_DAY => 'Enter one time in and one time out for this day.',
                            default => 'Remove checkpoint times when attendance scanning is off.',
                        };
                        $validator->errors()->add("attendance_days.{$index}.morning_in", $message);

                        continue;
                    }
                    if ($requiredCount > 0) {
                        $activeFields = $mode === AttendanceSessionMode::TWO_SESSIONS ? $fields : ['morning_in', 'morning_out'];
                        $times = collect($activeFields)->map(fn (string $field) => Carbon::createFromFormat('H:i', $day[$field]));
                        if (! $times->every(fn (Carbon $time, int $index) => $index === 0 || $time->gt($times[$index - 1]))) {
                            $validator->errors()->add("attendance_days.{$index}.morning_in", 'Time in/out checkpoints must be in chronological order.');
                        }
                        $firstCheckpoint = Carbon::parse($date.' '.$day['morning_in']);
                        $lastCheckpoint = Carbon::parse($date.' '.($mode === AttendanceSessionMode::TWO_SESSIONS ? $day['afternoon_out'] : $day['morning_out']));
                        if ($firstCheckpoint->lt($start) || $lastCheckpoint->gt($end)) {
                            $validator->errors()->add("attendance_days.{$index}.morning_in", 'This day’s times must stay within the overall event duration.');
                        }
                    }
                }
            }

            $ids = collect($this->input('assigned_user_ids', []))->map(fn ($id) => (int) $id)->unique();

            if ($ids->isNotEmpty()) {
                $allowedRoleIds = Role::whereIn('name', ['SBO Adviser', 'Faculty'])->pluck('id');
                $validCount = User::whereIn('id', $ids)
                    ->whereIn('role_id', $allowedRoleIds)
                    ->whereHas('userStatus', fn ($query) => $query->where('label', 'active'))
                    ->count();

                if ($validCount !== $ids->count()) {
                    $validator->errors()->add('assigned_user_ids', 'Only active SBO Adviser and Faculty users can be Event-in-Charge.');
                }
            }

            if ($this->input('audience_type') === 'selected_tribes') {
                $tribeIds = collect($this->input('tribe_ids', []))->map(fn ($id) => (int) $id)->unique();
                if ($tribeIds->isNotEmpty() && Team::whereIn('id', $tribeIds)->where('is_active', true)->count() !== $tribeIds->count()) {
                    $validator->errors()->add('tribe_ids', 'Only active tribes can be selected for an event.');
                }
            }

            if ($this->input('audience_type') === 'specific_students') {
                $participantIds = collect($this->input('participant_ids', []))->map(fn ($id) => (int) $id)->unique();
                $studentRoleId = Role::where('name', 'Student')->value('id');
                $validParticipants = User::whereIn('id', $participantIds)->where('role_id', $studentRoleId)->whereHas('userStatus', fn ($query) => $query->where('label', 'active'))->count();
                if ($participantIds->isNotEmpty() && $validParticipants !== $participantIds->count()) {
                    $validator->errors()->add('participant_ids', 'Only active students can be selected as participants.');
                }
            }

            if ($start && $end && $end->greaterThan($start) && ! $this->boolean('acknowledge_conflicts') && ! $validator->errors()->hasAny(['location', 'assigned_user_ids', 'assigned_user_ids.*'])) {
                $event = $this->route('event');
                $conflicts = app(EventConflictDetector::class)->detect(
                    $start,
                    $end,
                    $this->string('location')->toString(),
                    $ids->all(),
                    $event?->id,
                );

                if ($conflicts['location'] !== []) {
                    $conflict = $conflicts['location'][0];
                    $validator->errors()->add('location', "Possible conflict: {$conflict['title']} already uses this location ({$conflict['schedule']}). Confirm the warning to continue.");
                }

                if ($conflicts['people'] !== []) {
                    $conflict = $conflicts['people'][0];
                    $person = $conflict['people'][0] ?? 'This person';
                    $validator->errors()->add('assigned_user_ids', "Possible conflict: {$person} is assigned to {$conflict['title']} ({$conflict['schedule']}). Confirm the warning to continue.");
                }
            }
        });
    }

    public function eventData(): array
    {
        $validated = $this->validated();

        $data = [
            'title' => $validated['title'],
            'event_type_id' => $validated['event_type_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'location' => $validated['location'],
            'audience_type' => $validated['audience_type'],
            'start_at' => Carbon::createFromFormat('Y-m-d H:i', "{$validated['start_date']} {$validated['start_time']}"),
            'end_at' => Carbon::createFromFormat('Y-m-d H:i', "{$validated['end_date']} {$validated['end_time']}"),
        ];

        return $data;
    }

    public function attendanceScheduleData(): array
    {
        $days = $this->validated('attendance_days');
        if (is_array($days)) {
            $modes = AttendanceSessionMode::query()->pluck('code', 'id');
            return collect($days)->map(function (array $day) use ($modes) {
                $mode = $modes[$day['attendance_session_mode_id']] ?? AttendanceSessionMode::NONE;

                return [
                    'schedule_date' => $day['date'],
                    'attendance_session_mode_id' => $day['attendance_session_mode_id'],
                    'whole_day_in_time' => $mode === AttendanceSessionMode::WHOLE_DAY ? $day['morning_in'] : null,
                    'whole_day_out_time' => $mode === AttendanceSessionMode::WHOLE_DAY ? $day['morning_out'] : null,
                    'morning_in_time' => $mode === AttendanceSessionMode::TWO_SESSIONS ? ($day['morning_in'] ?? null) : null,
                    'morning_out_time' => $mode === AttendanceSessionMode::TWO_SESSIONS ? ($day['morning_out'] ?? null) : null,
                    'afternoon_in_time' => $mode === AttendanceSessionMode::TWO_SESSIONS ? ($day['afternoon_in'] ?? null) : null,
                    'afternoon_out_time' => $mode === AttendanceSessionMode::TWO_SESSIONS ? ($day['afternoon_out'] ?? null) : null,
                ];
            })->values()->all();
        }

        $start = Carbon::parse($this->eventData()['start_at'])->startOfDay();
        $end = Carbon::parse($this->eventData()['end_at'])->startOfDay();

        $noneModeId = AttendanceSessionMode::where('code', AttendanceSessionMode::NONE)->value('id');
        return collect(CarbonPeriod::create($start, $end))->map(function (Carbon $date) use ($noneModeId) {
            return [
                'schedule_date' => $date->toDateString(),
                'attendance_session_mode_id' => $noneModeId,
                'whole_day_in_time' => null,
                'whole_day_out_time' => null,
                'morning_in_time' => null,
                'morning_out_time' => null,
                'afternoon_in_time' => null,
                'afternoon_out_time' => null,
            ];
        })->all();
    }
}
