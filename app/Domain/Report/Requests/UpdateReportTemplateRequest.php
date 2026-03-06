<?php

declare(strict_types=1);

namespace App\Domain\Report\Requests;

use App\Support\Enums\ReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReportTemplateRequest extends FormRequest
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
            'type' => ['required', Rule::enum(ReportType::class)],
            'filters' => ['nullable', 'array'],
            'filters.start_date' => ['nullable', 'date'],
            'filters.end_date' => ['nullable', 'date', 'after_or_equal:filters.start_date'],
            'schedule' => ['nullable', 'string', 'max:100'],
            'recipients' => ['nullable', 'array'],
            'recipients.*' => ['email'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Report template name is required.',
            'type.required' => 'Report type is required.',
            'recipients.*.email' => 'Each recipient must be a valid email address.',
        ];
    }
}
