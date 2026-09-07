<?php

namespace App\Http\Requests\Adviser;

use App\Models\Event;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ScoreEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSboAdviser() === true;
    }

    public function rules(): array
    {
        return [
            'scores' => ['required', 'array'],
            'scores.*' => ['array'],
            'scores.*.*' => ['nullable', 'numeric', 'min:0', 'max:1000000', 'decimal:0,2'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('scores')) {
                return;
            }

            /** @var Event|null $event */
            $event = $this->route('event');
            if (! $event) {
                return;
            }

            $submitted = collect($this->input('scores', []));
            $categories = $event->scoreCategories()->get()->keyBy('id');
            $categoryIds = $submitted->keys()->map(fn ($id) => (int) $id);

            if ($categoryIds->count() !== $categoryIds->filter()->count() || $categoryIds->diff($categories->keys())->isNotEmpty()) {
                $validator->errors()->add('scores', 'Scores can only be recorded in this event’s categories.');

                return;
            }

            $allowedTeamIds = Team::query()
                ->where('is_active', true)
                ->orWhereHas('scores', fn ($query) => $query->where('event_id', $event->id))
                ->pluck('id');

            foreach ($submitted as $categoryId => $teamScores) {
                if (! is_array($teamScores)) {
                    continue;
                }

                $teamIds = collect(array_keys($teamScores))->map(fn ($id) => (int) $id);
                if ($teamIds->count() !== $teamIds->filter()->count() || $teamIds->diff($allowedTeamIds)->isNotEmpty()) {
                    $validator->errors()->add('scores', 'Scores can only be recorded for available tribes.');

                    return;
                }

                $maximum = (float) $categories->get((int) $categoryId)?->max_points;
                foreach ($teamScores as $teamId => $points) {
                    if ($points !== null && $points !== '' && is_numeric($points) && (float) $points > $maximum) {
                        $validator->errors()->add("scores.{$categoryId}.{$teamId}", "The score may not exceed {$maximum} points.");
                    }
                }
            }
        });
    }
}
