<?php

namespace App\Http\Requests\Adviser;

use App\Models\ScoreCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ScoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSboAdviser() === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    public function rules(): array
    {
        $event = $this->route('event');
        $category = $this->route('scoreCategory');

        return [
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('score_categories', 'name')
                    ->where('event_id', $event?->id)
                    ->ignore($category?->id),
            ],
            'max_points' => ['required', 'numeric', 'gt:0', 'max:1000000', 'decimal:0,2'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var ScoreCategory|null $category */
            $category = $this->route('scoreCategory');
            if (! $category || ! is_numeric($this->input('max_points'))) {
                return;
            }

            $highestScore = (float) ($category->scores()->max('points') ?? 0);
            if ((float) $this->input('max_points') < $highestScore) {
                $validator->errors()->add('max_points', "Maximum points cannot be lower than the existing score of {$highestScore}.");
            }
        });
    }
}
