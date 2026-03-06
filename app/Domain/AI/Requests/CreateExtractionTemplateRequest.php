<?php

declare(strict_types=1);

namespace App\Domain\AI\Requests;

use App\Support\Enums\ExtractionType;
use App\Support\Enums\MeetingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateExtractionTemplateRequest extends FormRequest
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
            'meeting_type' => ['nullable', Rule::enum(MeetingType::class)],
            'extraction_type' => ['required', Rule::enum(ExtractionType::class)],
            'prompt_template' => ['required', 'string', 'min:10'],
            'system_message' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Template name is required.',
            'extraction_type.required' => 'Extraction type is required.',
            'prompt_template.required' => 'Prompt template is required.',
            'prompt_template.min' => 'Prompt template must be at least 10 characters.',
        ];
    }
}
