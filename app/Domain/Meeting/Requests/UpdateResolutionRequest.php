<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Requests;

use App\Support\Enums\ResolutionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateResolutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'mover_id' => ['nullable', 'exists:mom_attendees,id'],
            'seconder_id' => ['nullable', 'exists:mom_attendees,id'],
            'status' => ['sometimes', Rule::enum(ResolutionStatus::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => __('The resolution title is required.'),
            'title.max' => __('The resolution title cannot exceed 255 characters.'),
            'mover_id.exists' => __('The selected mover is not a valid attendee.'),
            'seconder_id.exists' => __('The selected seconder is not a valid attendee.'),
            'status.enum' => __('The selected status is invalid.'),
        ];
    }
}
