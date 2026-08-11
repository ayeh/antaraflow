<?php

declare(strict_types=1);

namespace App\Domain\API\Requests\Mobile;

use App\Support\Enums\MeetingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'meeting_type' => ['sometimes', Rule::enum(MeetingType::class)],
            'meeting_date' => ['sometimes', 'nullable', 'date'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meeting_link' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1440'],
            'meeting_template_id' => ['sometimes', 'nullable', 'integer'],
            'meeting_series_id' => ['sometimes', 'nullable', 'integer'],
            'attendee_ids' => ['sometimes', 'array', 'max:200'],
            'attendee_ids.*' => ['integer'],
            'client_id' => ['sometimes', 'string', 'max:64'],
        ];
    }
}
