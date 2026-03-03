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
            'title' => ['sometimes', 'string', 'max:255'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'meeting_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'location' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'in:ms,en'],
            'prepared_by' => ['nullable', 'string', 'max:255'],
            'share_with_client' => ['nullable', 'boolean'],
            'summary' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:mom_tags,id'],
            'allow_external_join' => ['nullable', 'boolean'],
            'require_rsvp' => ['nullable', 'boolean'],
            'auto_notify' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.max' => 'The meeting title cannot exceed 255 characters.',
            'meeting_date.date' => 'The meeting date must be a valid date.',
            'start_time.date_format' => 'The start time must be in HH:MM format.',
            'end_time.date_format' => 'The end time must be in HH:MM format.',
            'end_time.after' => 'The end time must be after the start time.',
            'project_id.exists' => 'The selected project does not exist.',
            'prepared_by.max' => 'The prepared by field cannot exceed 255 characters.',
            'language.in' => 'The language must be either Malay (ms) or English (en).',
            'tags.*.exists' => 'One or more selected tags do not exist.',
        ];
    }
}
