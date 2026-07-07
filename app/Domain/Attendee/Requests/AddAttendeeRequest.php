<?php

declare(strict_types=1);

namespace App\Domain\Attendee\Requests;

use App\Support\Enums\AttendeeRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddAttendeeRequest extends FormRequest
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
            'email' => ['nullable', 'email'],
            'role' => ['nullable', Rule::in(array_column(AttendeeRole::cases(), 'value'))],
            'is_external' => ['nullable', 'boolean'],
            'is_present' => ['nullable', 'boolean'],
            'department' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => __('The attendee name is required.'),
            'name.max' => __('The attendee name cannot exceed 255 characters.'),
            'email.email' => __('Please provide a valid email address.'),
            'role.in' => __('The selected role is invalid.'),
            'department.max' => __('The department name cannot exceed 255 characters.'),
        ];
    }
}
