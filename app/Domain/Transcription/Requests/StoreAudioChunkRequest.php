<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAudioChunkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'chunk' => ['required', 'file', 'max:51200'],
            'session_id' => ['required', 'string', 'uuid'],
            'chunk_index' => ['required', 'integer', 'min:0'],
            'mime_type' => ['required', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'chunk.required' => __('An audio chunk file is required.'),
            'chunk.file' => __('The chunk must be a valid file.'),
            'chunk.max' => __('The audio chunk must not exceed 50MB.'),
            'session_id.required' => __('A session ID is required.'),
            'session_id.uuid' => __('The session ID must be a valid UUID.'),
            'chunk_index.required' => __('A chunk index is required.'),
            'chunk_index.integer' => __('The chunk index must be an integer.'),
            'chunk_index.min' => __('The chunk index must be at least 0.'),
            'mime_type.required' => __('A MIME type is required.'),
        ];
    }
}
