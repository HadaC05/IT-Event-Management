<?php

namespace App\Http\Requests\Adviser;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSboAdviser() === true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:3000'],
            'event_id' => ['nullable', 'integer', Rule::exists('events', 'id')->whereNull('deleted_at')],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4096,max_height=4096'],
            'remove_image' => ['nullable', 'boolean'],
            'intent' => ['required', Rule::in(['draft', 'publish'])],
        ];
    }
}
