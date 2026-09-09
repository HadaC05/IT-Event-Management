<?php

namespace App\Http\Requests\Adviser;

use App\Models\Attendance;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AttendanceBulkRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('attendance_date') && $this->route('event')) {
            $event = $this->route('event');
            $this->merge([
                'attendance_date' => $event->attendanceSchedules()->value('schedule_date') ?? $event->start_at->toDateString(),
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->isSboAdviser() === true;
    }

    public function rules(): array
    {
        return [
            'attendance_date' => ['required', 'date_format:Y-m-d'],
            'records' => ['required', 'array'],
            'records.*' => ['array:status'],
            'records.*.status' => ['nullable', Rule::in(Attendance::STATUSES)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('records')) {
                return;
            }

            /** @var Event|null $event */
            $event = $this->route('event');
            if (! $event) {
                return;
            }

            $hasSchedules = $event->attendanceSchedules()->exists();
            if (($hasSchedules && ! $event->attendanceSchedules()->whereDate('schedule_date', $this->input('attendance_date'))->exists())
                || (! $hasSchedules && $this->input('attendance_date') !== $event->start_at->toDateString())) {
                $validator->errors()->add('attendance_date', 'Choose a valid date from this event schedule.');
            }

            $submittedUserIds = collect(array_keys($this->input('records', [])))->map(fn ($id) => (int) $id);
            $allowedUserIds = $event->expectedParticipantsQuery()->pluck('users.id')
                ->merge($event->attendances()->pluck('user_id'))
                ->unique();

            if ($submittedUserIds->diff($allowedUserIds)->isNotEmpty()) {
                $validator->errors()->add('records', 'Attendance can only be recorded for this event’s expected participants.');
            }
        });
    }
}
