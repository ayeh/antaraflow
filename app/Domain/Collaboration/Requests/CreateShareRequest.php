<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'shared_with_user_id' => ['nullable', 'exists:users,id'],
            'permission' => ['required', 'in:view,comment,edit'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'is_link_share' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'permission.required' => 'A permission level is required.',
            'permission.in' => 'The permission must be one of: view, comment, edit.',
            'expires_at.date' => 'The expiration date must be a valid date.',
            'expires_at.after' => 'The expiration date must be in the future.',
            'shared_with_user_id.exists' => 'The selected user does not exist.',
        ];
    }
}
