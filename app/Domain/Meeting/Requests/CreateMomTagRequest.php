<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;
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
                Rule::unique('mom_tags')->where('organization_id', $orgId)->ignore($this->route('momTag')),
            ],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => 'A tag with this name already exists.',
            'color.regex' => 'Color must be a valid hex color (e.g. #A855F7).',
        ];
    }
}
