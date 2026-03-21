<?php

declare(strict_types=1);

namespace App\Domain\Export\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendEmailDistributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('recipients_raw')) {
            $emails = preg_split('/[\n,]+/', $this->input('recipients_raw', ''));
            $emails = array_filter(array_map('trim', $emails));
            $this->merge(['recipients' => array_values($emails)]);
        }
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'body_note' => ['nullable', 'string'],
            'export_format' => ['required', 'in:pdf,docx'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'recipients.required' => 'At least one recipient is required.',
            'recipients.*.email' => 'Each recipient must be a valid email address.',
            'subject.required' => 'The email subject is required.',
            'export_format.required' => 'The export format is required.',
            'export_format.in' => 'The export format must be either PDF or DOCX.',
        ];
    }
}
