<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadAudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'audio' => ['required', 'file', 'mimetypes:audio/*', 'max:102400'],
            'language' => ['nullable', 'string', 'max:5'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'audio.required' => 'An audio file is required.',
            'audio.file' => 'The audio must be a valid file.',
            'audio.mimetypes' => 'The file must be an audio file.',
            'audio.max' => 'The audio file must not exceed 100MB.',
            'language.max' => 'The language code must not exceed 5 characters.',
        ];
    }
}
