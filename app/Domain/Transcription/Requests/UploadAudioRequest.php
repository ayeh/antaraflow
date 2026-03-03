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
            'audio' => ['required', 'file', 'mimes:mp3,wav,m4a,ogg,webm,mpeg,mpga', 'max:204800'],
            'language' => ['nullable', 'string', 'max:5'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'audio.required' => 'An audio file is required.',
            'audio.file' => 'The audio must be a valid file.',
            'audio.mimes' => 'The file must be an audio file (MP3, WAV, M4A, OGG, or WebM).',
            'audio.max' => 'The audio file must not exceed 200MB.',
            'language.max' => 'The language code must not exceed 5 characters.',
        ];
    }
}
