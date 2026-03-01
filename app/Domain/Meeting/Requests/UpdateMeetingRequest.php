<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingRequest extends FormRequest
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
            'meeting_date' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'meeting_series_id' => ['nullable', 'exists:meeting_series,id'],
            'meeting_template_id' => ['nullable', 'exists:meeting_templates,id'],
            'summary' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:mom_tags,id'],
            'allow_external_join' => ['boolean'],
            'require_rsvp' => ['boolean'],
            'auto_notify' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'The meeting title is required.',
            'title.max' => 'The meeting title cannot exceed 255 characters.',
            'meeting_date.date' => 'The meeting date must be a valid date.',
            'duration_minutes.integer' => 'The duration must be a whole number.',
            'duration_minutes.min' => 'The duration must be at least 1 minute.',
            'meeting_series_id.exists' => 'The selected meeting series does not exist.',
            'meeting_template_id.exists' => 'The selected meeting template does not exist.',
            'tags.*.exists' => 'One or more selected tags do not exist.',
        ];
    }
}
