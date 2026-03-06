<?php

declare(strict_types=1);

namespace App\Domain\Account\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubOrganizationRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:1000'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Organization name is required.',
            'owner_name.required' => 'The sub-organization owner name is required.',
            'owner_email.required' => 'The sub-organization owner email is required.',
            'owner_email.email' => 'Please provide a valid email address for the owner.',
        ];
    }
}
