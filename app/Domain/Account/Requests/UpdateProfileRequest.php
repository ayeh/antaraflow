<?php

declare(strict_types=1);

namespace App\Domain\Account\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->user()->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'timezone' => ['required', 'string', 'max:100'],
            'language' => ['required', 'string', 'max:10'],
            'preferences' => ['nullable', 'array'],
            'preferences.theme' => ['nullable', 'string', Rule::in(['light', 'dark', 'system'])],
            'preferences.default_meeting_duration' => ['nullable', 'integer', 'min:5', 'max:480'],
            'preferences.notifications' => ['nullable', 'array'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Your name is required.',
            'email.required' => 'Your email address is required.',
            'email.unique' => 'This email address is already in use.',
        ];
    }
}
