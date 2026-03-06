<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateResolutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'mover_id' => ['nullable', 'exists:mom_attendees,id'],
            'seconder_id' => ['nullable', 'exists:mom_attendees,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'The resolution title is required.',
            'title.max' => 'The resolution title cannot exceed 255 characters.',
            'mover_id.exists' => 'The selected mover is not a valid attendee.',
            'seconder_id.exists' => 'The selected seconder is not a valid attendee.',
        ];
    }
}
