<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecordingConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'notice_version' => ['required', 'string', 'max:32'],
            'includes_tab_audio' => ['nullable', 'boolean'],
            'acknowledged' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'acknowledged.accepted' => __('Please confirm you will inform all participants before recording.'),
            'notice_version.required' => __('The recording notice version is required.'),
        ];
    }
}
