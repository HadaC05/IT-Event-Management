<?php

namespace App\Http\Requests\Adviser;

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
            'start_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'end_time' => ['required', 'date_format:H:i'],
            'morning_in_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'morning_out_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'afternoon_in_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'afternoon_out_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'attendance_days' => ['nullable', 'array', 'max:31'],
            'attendance_days.*' => ['array'],
            'attendance_days.*.date' => ['required', 'date_format:Y-m-d', 'distinct'],
            'attendance_days.*.mode' => ['required', Rule::in(['none', 'single', 'split'])],
            'attendance_days.*.morning_in' => ['nullable', 'date_format:H:i'],
            'attendance_days.*.morning_out' => ['nullable', 'date_format:H:i'],
            'attendance_days.*.afternoon_in' => ['nullable', 'date_format:H:i'],
            'attendance_days.*.afternoon_out' => ['nullable', 'date_format:H:i'],
            'event_status_id' => [Rule::requiredIf(! $this->isMethod('POST')), 'nullable', Rule::exists('event_statuses', 'id')],
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
                if (is_array($day) && ! isset($day['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $key)) {
                    $mode = filled($day['afternoon_in'] ?? null) ? 'split' : (filled($day['morning_in'] ?? null) ? 'single' : 'none');

                    return ['date' => $key, 'mode' => $mode, ...$day];
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

            $checkpointFields = ['morning_in_at', 'morning_out_at', 'afternoon_in_at', 'afternoon_out_at'];
            $providedCheckpoints = collect($checkpointFields)->filter(fn (string $field) => $this->filled($field));
            if ($providedCheckpoints->isNotEmpty() && $providedCheckpoints->count() !== count($checkpointFields)) {
                $validator->errors()->add('morning_in_at', 'Set all four attendance checkpoints, or leave all four blank.');
            } elseif ($providedCheckpoints->count() === count($checkpointFields) && ! $validator->errors()->hasAny($checkpointFields)) {
                $checkpoints = collect($checkpointFields)->mapWithKeys(fn (string $field) => [
                    $field => Carbon::createFromFormat('Y-m-d\\TH:i', $this->input($field)),
                ]);
                if (! $checkpoints->values()->every(fn (Carbon $value, int $index) => $index === 0 || $value->gt($checkpoints->values()[$index - 1]))) {
                    $validator->errors()->add('morning_in_at', 'Attendance checkpoints must follow this order: morning in, morning out, afternoon in, afternoon out.');
                }
                if ($start && $end && ($checkpoints->first()->lt($start) || $checkpoints->last()->gt($end))) {
                    $validator->errors()->add('morning_in_at', 'Attendance checkpoints must be within the event start and end time.');
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
                    $mode = $day['mode'] ?? 'none';
                    $fields = ['morning_in', 'morning_out', 'afternoon_in', 'afternoon_out'];
                    $provided = collect($fields)->filter(fn (string $field) => filled($day[$field] ?? null));
                    $requiredCount = $mode === 'split' ? 4 : ($mode === 'single' ? 2 : 0);
                    if ($provided->count() !== $requiredCount) {
                        $message = match ($mode) {
                            'split' => 'Enter all four morning and afternoon times for this day.',
                            'single' => 'Enter one time in and one time out for this day.',
                            default => 'Remove checkpoint times when attendance scanning is off.',
                        };
                        $validator->errors()->add("attendance_days.{$index}.morning_in", $message);

                        continue;
                    }
                    if ($requiredCount > 0) {
                        $activeFields = $mode === 'split' ? $fields : ['morning_in', 'morning_out'];
                        $times = collect($activeFields)->map(fn (string $field) => Carbon::createFromFormat('H:i', $day[$field]));
                        if (! $times->every(fn (Carbon $time, int $index) => $index === 0 || $time->gt($times[$index - 1]))) {
                            $validator->errors()->add("attendance_days.{$index}.morning_in", 'Time in/out checkpoints must be in chronological order.');
                        }
                        $firstCheckpoint = Carbon::parse($date.' '.$day['morning_in']);
                        $lastCheckpoint = Carbon::parse($date.' '.($mode === 'split' ? $day['afternoon_out'] : $day['morning_out']));
                        if ($firstCheckpoint->lt($start) || $lastCheckpoint->gt($end)) {
                            $validator->errors()->add("attendance_days.{$index}.morning_in", 'This day’s times must stay within the overall event duration.');
                        }
                    }
                }
            }

            $ids = collect($this->input('assigned_user_ids', []))->map(fn ($id) => (int) $id)->unique();

            if ($ids->isNotEmpty()) {
                $allowedRoleIds = Role::whereIn('name', ['SBO Adviser', 'SBO', 'Faculty'])->pluck('id');
                $validCount = User::whereIn('id', $ids)
                    ->whereIn('role_id', $allowedRoleIds)
                    ->whereHas('userStatus', fn ($query) => $query->where('label', 'active'))
                    ->count();

                if ($validCount !== $ids->count()) {
                    $validator->errors()->add('assigned_user_ids', 'Only active SBO Adviser, SBO, and Faculty users can be Event-in-Charge.');
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
                $validParticipants = User::whereIn('id', $participantIds)
                    ->where('role_id', $studentRoleId)
                    ->whereHas('userStatus', fn ($query) => $query->where('label', 'active'))
                    ->count();

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
            'description' => $validated['description'] ?? null,
            'location' => $validated['location'],
            'audience_type' => $validated['audience_type'],
            'start_at' => Carbon::createFromFormat('Y-m-d H:i', "{$validated['start_date']} {$validated['start_time']}"),
            'end_at' => Carbon::createFromFormat('Y-m-d H:i', "{$validated['end_date']} {$validated['end_time']}"),
        ];

        foreach (['morning_in_at', 'morning_out_at', 'afternoon_in_at', 'afternoon_out_at'] as $field) {
            $data[$field] = filled($validated[$field] ?? null)
                ? Carbon::createFromFormat('Y-m-d\\TH:i', $validated[$field])
                : null;
        }

        if (! $this->isMethod('POST')) {
            $data['event_status_id'] = $validated['event_status_id'];
        }

        return $data;
    }

    public function attendanceScheduleData(): array
    {
        $days = $this->validated('attendance_days');
        if (is_array($days)) {
            return collect($days)->map(fn (array $day) => [
                'schedule_date' => $day['date'],
                'session_mode' => $day['mode'],
                'morning_in_time' => $day['morning_in'] ?? null,
                'morning_out_time' => $day['morning_out'] ?? null,
                'afternoon_in_time' => $day['afternoon_in'] ?? null,
                'afternoon_out_time' => $day['afternoon_out'] ?? null,
            ])->values()->all();
        }

        $start = Carbon::parse($this->eventData()['start_at'])->startOfDay();
        $end = Carbon::parse($this->eventData()['end_at'])->startOfDay();

        return collect(CarbonPeriod::create($start, $end))->map(function (Carbon $date, int $index) {
            $legacy = $index === 0 ? collect(['morning_in', 'morning_out', 'afternoon_in', 'afternoon_out'])
                ->mapWithKeys(fn (string $key) => [$key.'_time' => $this->filled($key.'_at') ? Carbon::parse($this->input($key.'_at'))->format('H:i') : null])
                ->all() : [
                    'morning_in_time' => null, 'morning_out_time' => null,
                    'afternoon_in_time' => null, 'afternoon_out_time' => null,
                ];

            return [
                'schedule_date' => $date->toDateString(),
                'session_mode' => filled($legacy['afternoon_in_time']) ? 'split' : (filled($legacy['morning_in_time']) ? 'single' : 'none'),
                ...$legacy,
            ];
        })->all();
    }
}
