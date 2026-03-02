<?php

declare(strict_types=1);

namespace App\Domain\API\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreApiMeetingRequest extends FormRequest
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
            'summary' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
        ];
    }
}
