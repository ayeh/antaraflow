<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingSeriesRequest extends FormRequest
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
            'recurrence_pattern' => ['required', 'string', 'in:weekly,biweekly,monthly'],
            'recurrence_config' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Series name is required.',
            'recurrence_pattern.required' => 'Recurrence pattern is required.',
            'recurrence_pattern.in' => 'Recurrence pattern must be weekly, biweekly, or monthly.',
        ];
    }
}
