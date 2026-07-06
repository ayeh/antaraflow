<?php

declare(strict_types=1);

namespace App\Domain\Account\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileSettingsRequest extends FormRequest
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
            'timezone' => ['nullable', 'string', 'max:100'],
            'locale' => ['nullable', 'string', Rule::in(array_keys(config('locales.supported', [])))],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
