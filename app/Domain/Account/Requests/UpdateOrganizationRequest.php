<?php

declare(strict_types=1);

namespace App\Domain\Account\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
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
            'slug' => ['required', 'alpha_dash', Rule::unique('organizations', 'slug')->ignore($this->route('organization'))],
            'description' => ['nullable', 'string'],
            'timezone' => ['nullable', 'string'],
            'language' => ['nullable', 'string', 'max:5'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => __('The organization name is required.'),
            'slug.required' => __('The organization slug is required.'),
            'slug.unique' => __('This slug is already taken.'),
            'slug.alpha_dash' => __('The slug may only contain letters, numbers, dashes, and underscores.'),
        ];
    }
}
