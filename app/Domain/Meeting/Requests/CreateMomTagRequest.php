<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CreateMomTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $orgId = $this->user()->current_organization_id;

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('mom_tags')->where('organization_id', $orgId),
            ],
            'slug' => [
                Rule::unique('mom_tags')->where('organization_id', $orgId),
            ],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) $this->input('name', '')),
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => __('A tag with this name already exists.'),
            'slug.unique' => __('A tag with a similar name (same slug) already exists.'),
            'color.regex' => __('Color must be a valid hex color (e.g. #A855F7).'),
        ];
    }
}
