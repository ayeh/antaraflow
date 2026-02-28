<?php

declare(strict_types=1);

namespace App\Domain\Attendee\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'group_id' => ['required', 'exists:attendee_groups,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'group_id.required' => 'An attendee group must be selected.',
            'group_id.exists' => 'The selected attendee group does not exist.',
        ];
    }
}
