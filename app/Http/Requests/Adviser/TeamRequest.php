<?php

namespace App\Http\Requests\Adviser;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSboAdviser() === true;
    }

    public function rules(): array
    {
        $team = $this->route('team');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('teams', 'name')
                    ->where(fn ($query) => $query->where('school_year_id', $this->integer('school_year_id')))
                    ->ignore($team),
            ],
            'school_year_id' => ['required', Rule::exists('school_years', 'id')],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $memberIds = collect($this->input('member_ids', []))->map(fn ($id) => (int) $id)->unique();
            if ($memberIds->isEmpty() || $validator->errors()->has('school_year_id')) {
                return;
            }

            $studentRoleId = Role::where('name', 'Student')->value('id');
            $validMembers = User::query()
                ->whereIn('id', $memberIds)
                ->where('role_id', $studentRoleId)
                ->whereHas('userStatus', fn ($query) => $query->where('label', 'active'))
                ->count();

            if ($validMembers !== $memberIds->count()) {
                $validator->errors()->add('member_ids', 'Only active student accounts can be assigned as tribe members.');

                return;
            }

            $team = $this->route('team');
            $hasConflict = Team::query()
                ->where('school_year_id', $this->integer('school_year_id'))
                ->when($team, fn ($query) => $query->whereKeyNot($team->getKey()))
                ->whereHas('members', fn ($query) => $query->whereIn('users.id', $memberIds))
                ->exists();

            if ($hasConflict) {
                $validator->errors()->add('member_ids', 'A student can belong to only one tribe in the same school year.');
            }
        });
    }
}
