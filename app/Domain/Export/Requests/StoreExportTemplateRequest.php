<?php

declare(strict_types=1);

namespace App\Domain\Export\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExportTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'header_html' => ['nullable', 'string'],
            'footer_html' => ['nullable', 'string'],
            'css_overrides' => ['nullable', 'string'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'font_family' => ['nullable', 'string', 'max:100'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'The template name is required.',
            'name.max' => 'The template name cannot exceed 255 characters.',
            'primary_color.max' => 'The primary color value cannot exceed 20 characters.',
            'font_family.max' => 'The font family cannot exceed 100 characters.',
        ];
    }
}
