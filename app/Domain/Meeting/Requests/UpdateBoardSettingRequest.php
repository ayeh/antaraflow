<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBoardSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'quorum_type' => ['required', 'string', 'in:percentage,count'],
            'quorum_value' => ['required', 'integer', 'min:1', 'max:100'],
            'require_chair' => ['nullable', 'boolean'],
            'require_secretary' => ['nullable', 'boolean'],
            'voting_enabled' => ['nullable', 'boolean'],
            'chair_casting_vote' => ['nullable', 'boolean'],
            'block_finalization_without_quorum' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'quorum_type.required' => __('The quorum type is required.'),
            'quorum_type.in' => __('The quorum type must be either percentage or count.'),
            'quorum_value.required' => __('The quorum value is required.'),
            'quorum_value.integer' => __('The quorum value must be a whole number.'),
            'quorum_value.min' => __('The quorum value must be at least 1.'),
            'quorum_value.max' => __('The quorum value cannot exceed 100.'),
        ];
    }
}
