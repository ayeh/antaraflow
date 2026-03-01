<?php

declare(strict_types=1);

namespace App\Domain\Attendee\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendeeGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'default_members' => ['nullable', 'array'],
            'default_members.*.name' => ['required_with:default_members.*', 'string', 'max:255'],
            'default_members.*.email' => ['required_with:default_members.*', 'email', 'max:255'],
            'default_members.*.role' => ['nullable', 'string', 'max:50'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Group name is required.',
            'default_members.*.name.required_with' => 'Member name is required.',
            'default_members.*.email.required_with' => 'Member email is required.',
            'default_members.*.email.email' => 'Member email must be valid.',
        ];
    }
}
