<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CirculateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body_note' => ['nullable', 'string'],
            'deadline' => ['required', 'date', 'after:today'],
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*.name' => ['required', 'string', 'max:255'],
            'recipients.*.email' => ['required', 'email'],
            'recipients.*.mom_attendee_id' => ['nullable', 'integer'],
        ];
    }
}
