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
            /*
             * mp4 is accepted as a container, not as video: the picker offers it
             * anyway (macOS maps .mp4 to the same UTI as .m4a under accept="audio/*"),
             * and a screen or Zoom recording carries the same AAC speech an .m4a
             * would. ProcessTranscriptionJob strips the video track before anything
             * is sent on, so only the audio ever leaves the server.
             */
            'audio' => ['required', 'file', 'mimes:mp3,wav,m4a,ogg,webm,mpeg,mpga,mp4', 'max:512000'],
            'language' => ['nullable', 'string', 'max:5'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'audio.required' => __('An audio file is required.'),
            'audio.file' => __('The audio must be a valid file.'),
            'audio.mimes' => __('The file must be an audio or video recording (MP3, WAV, M4A, OGG, WebM, or MP4).'),
            'audio.max' => __('The audio file must not exceed 500MB.'),
            'language.max' => __('The language code must not exceed 5 characters.'),
        ];
    }
}
