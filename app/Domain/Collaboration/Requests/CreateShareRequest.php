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

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $orgId = $this->user()->current_organization_id;

        return [
            'shared_with_user_id' => [
                'nullable',
                'exists:users,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($orgId): void {
                    if ($value === null) {
                        return;
                    }
                    $belongsToOrg = \App\Domain\Account\Models\Organization::find($orgId)
                        ?->members()
                        ->where('users.id', $value)
                        ->exists();
                    if (! $belongsToOrg) {
                        $fail('The selected user does not belong to your organization.');
                    }
                },
            ],
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
