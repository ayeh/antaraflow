<?php

declare(strict_types=1);

namespace App\Domain\Account\Requests;

use App\Domain\Account\Models\Organization;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var Organization $organization */
        $organization = $this->route('organization');

        return [
            'email' => [
                'required',
                'email',
                Rule::unique('organization_invitations', 'email')
                    ->where('organization_id', $organization->id)
                    ->whereNull('accepted_at'),
                function (string $attribute, mixed $value, \Closure $fail) use ($organization): void {
                    $alreadyMember = $organization->members()
                        ->where('email', $value)
                        ->exists();

                    if ($alreadyMember) {
                        $fail('This person is already a member of the organization.');
                    }
                },
            ],
            'role' => ['required', Rule::in(array_column(UserRole::cases(), 'value'))],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required' => 'An email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'An invitation has already been sent to this email address.',
            'role.required' => 'A role is required.',
            'role.in' => 'The selected role is invalid.',
        ];
    }
}
