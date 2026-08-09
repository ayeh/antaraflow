<?php

declare(strict_types=1);

namespace App\Domain\API\Requests\Mobile;

use App\Support\Enums\DevicePlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:120'],
            'device_id' => ['required', 'string', 'max:128'],
            'platform' => ['required', Rule::enum(DevicePlatform::class)],
            'push_token' => ['sometimes', 'nullable', 'string', 'max:512'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'device_id.required' => __('A device identifier is required.'),
            'platform.required' => __('The device platform is required.'),
        ];
    }
}
