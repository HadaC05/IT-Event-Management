<?php

namespace App\Http\Requests\Adviser;

use App\Models\Attendance;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AttendanceBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSboAdviser() === true;
    }

    public function rules(): array
    {
        return [
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

            $submittedUserIds = collect(array_keys($this->input('records', [])))->map(fn ($id) => (int) $id);
            $allowedUserIds = $event->expectedParticipants()->pluck('id')
                ->merge($event->attendances()->pluck('user_id'))
                ->unique();

            if ($submittedUserIds->diff($allowedUserIds)->isNotEmpty()) {
                $validator->errors()->add('records', 'Attendance can only be recorded for this event’s expected participants.');
            }
        });
    }
}
