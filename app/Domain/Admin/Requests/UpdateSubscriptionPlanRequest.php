<?php

declare(strict_types=1);

namespace App\Domain\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('subscription_plans', 'slug')->ignore($this->route('plan'))],
            'description' => ['nullable', 'string'],
            'price_monthly' => ['required', 'numeric', 'min:0'],
            'price_yearly' => ['required', 'numeric', 'min:0'],
            'features' => ['required', 'array'],
            'features.*' => ['boolean'],
            'max_users' => ['required', 'integer', 'min:-1'],
            'max_meetings_per_month' => ['required', 'integer', 'min:-1'],
            'max_audio_minutes_per_month' => ['required', 'integer', 'min:-1'],
            'max_storage_mb' => ['required', 'integer', 'min:-1'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'The plan name is required.',
            'slug.required' => 'The plan slug is required.',
            'slug.unique' => 'This slug is already taken.',
            'price_monthly.required' => 'Monthly price is required.',
            'price_yearly.required' => 'Yearly price is required.',
        ];
    }
}
