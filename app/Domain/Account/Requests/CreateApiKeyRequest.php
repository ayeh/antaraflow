<?php

declare(strict_types=1);

namespace App\Domain\Account\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', 'in:read,write,delete'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => __('The API key name is required.'),
            'permissions.required' => __('At least one permission is required.'),
            'permissions.min' => __('At least one permission must be selected.'),
            'permissions.*.in' => __('Permissions must be one of: read, write, delete.'),
            'expires_at.after' => __('The expiration date must be after today.'),
        ];
    }
}
