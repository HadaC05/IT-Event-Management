<?php

namespace App\Http\Requests\Adviser;

use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
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
            'start_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'end_time' => ['required', 'date_format:H:i'],
            'event_status_id' => [Rule::requiredIf(! $this->isMethod('POST')), 'nullable', Rule::exists('event_statuses', 'id')],
            'poster' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_poster' => ['nullable', 'boolean'],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_user_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $validator->errors()->hasAny(['start_date', 'start_time', 'end_date', 'end_time'])) {
                $start = Carbon::createFromFormat('Y-m-d H:i', "{$this->start_date} {$this->start_time}");
                $end = Carbon::createFromFormat('Y-m-d H:i', "{$this->end_date} {$this->end_time}");

                if ($end->lessThanOrEqualTo($start)) {
                    $validator->errors()->add('end_time', 'The event must end after it starts.');
                }
            }

            $ids = collect($this->input('assigned_user_ids', []))->map(fn ($id) => (int) $id)->unique();
            if ($ids->isEmpty()) {
                return;
            }

            $allowedRoleIds = Role::whereIn('name', ['SBO', 'Faculty'])->pluck('id');
            $validCount = User::whereIn('id', $ids)
                ->whereIn('role_id', $allowedRoleIds)
                ->whereHas('userStatus', fn ($query) => $query->where('label', 'active'))
                ->count();

            if ($validCount !== $ids->count()) {
                $validator->errors()->add('assigned_user_ids', 'Only active SBO and Faculty users can be Event-in-Charge.');
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
            'start_at' => Carbon::createFromFormat('Y-m-d H:i', "{$validated['start_date']} {$validated['start_time']}"),
            'end_at' => Carbon::createFromFormat('Y-m-d H:i', "{$validated['end_date']} {$validated['end_time']}"),
        ];

        if (! $this->isMethod('POST')) {
            $data['event_status_id'] = $validated['event_status_id'];
        }

        return $data;
    }
}
