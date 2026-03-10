<?php

declare(strict_types=1);

namespace App\Domain\Account\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $hourlyRates = $this->input('settings.hourly_rates', []);

        if (is_array($hourlyRates)) {
            $casted = [];
            foreach ($hourlyRates as $role => $rate) {
                $casted[$role] = is_numeric($rate) ? (float) $rate : null;
            }

            $this->merge([
                'settings' => array_merge($this->input('settings', []), ['hourly_rates' => $casted]),
            ]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'timezone' => ['required', 'string', 'max:100'],
            'language' => ['required', 'string', 'max:10'],
            'teams_webhook_url' => ['nullable', 'url', 'max:2048'],
            'settings' => ['nullable', 'array'],
            'settings.hourly_rates' => ['nullable', 'array'],
            'settings.hourly_rates.admin' => ['nullable', 'numeric', 'min:0'],
            'settings.hourly_rates.manager' => ['nullable', 'numeric', 'min:0'],
            'settings.hourly_rates.member' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Organization name is required.',
        ];
    }
}
