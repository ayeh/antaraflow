<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RenameSpeakerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via policy
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'old_speaker' => ['required', 'string', 'max:100'],
            'new_speaker' => ['required', 'string', 'max:100'],
        ];
    }
}
