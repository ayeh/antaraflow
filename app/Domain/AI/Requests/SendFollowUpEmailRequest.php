<?php

declare(strict_types=1);

namespace App\Domain\AI\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendFollowUpEmailRequest extends FormRequest
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
            'body' => ['required', 'string'],
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*' => ['required', 'email'],
        ];
    }
}
