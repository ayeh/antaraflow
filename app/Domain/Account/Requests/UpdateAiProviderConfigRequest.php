<?php

declare(strict_types=1);

namespace App\Domain\Account\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAiProviderConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'in:openai,anthropic,google,ollama'],
            'display_name' => ['required', 'string', 'max:100'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'model' => ['required', 'string', 'max:100'],
            'base_url' => ['nullable', 'url', 'max:255'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'settings' => ['nullable', 'array'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'provider.required' => 'The provider is required.',
            'provider.in' => 'The provider must be one of: openai, anthropic, google, ollama.',
            'display_name.required' => 'The display name is required.',
            'model.required' => 'The model is required.',
            'base_url.url' => 'The base URL must be a valid URL.',
        ];
    }
}
