<?php

namespace App\Http\Requests\Adviser;

use App\Models\Attendance;
use App\Models\ScoreCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReportRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['type' => $this->input('type', 'attendance')]);
    }

    public function authorize(): bool
    {
        return $this->user()?->isSboAdviser() === true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['attendance', 'participation', 'scores', 'rankings'])],
            'event_id' => ['nullable', 'integer', Rule::exists('events', 'id')->whereNull('deleted_at')],
            'school_year_id' => ['nullable', 'integer', 'exists:school_years,id'],
            'category_id' => ['nullable', 'integer', 'exists:score_categories,id'],
            'status' => ['nullable', Rule::in(Attendance::STATUSES)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $categoryId = $this->integer('category_id');
            $eventId = $this->integer('event_id');

            if (! $categoryId) {
                return;
            }

            if (! $eventId || ! ScoreCategory::whereKey($categoryId)->where('event_id', $eventId)->exists()) {
                $validator->errors()->add('category_id', 'Choose a scoring criterion from the selected event.');
            }
        });
    }
}
