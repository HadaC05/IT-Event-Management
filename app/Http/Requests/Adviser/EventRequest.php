<?php

namespace App\Http\Requests\Adviser;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\EventConflictDetector;
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

        if (! $this->isMethod('POST')) {
            $data['event_status_id'] = $validated['event_status_id'];
        }

        return $data;
    }
}
