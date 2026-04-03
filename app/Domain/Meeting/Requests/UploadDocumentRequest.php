<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'document' => ['required', 'file', 'max:51200', 'mimes:pdf,doc,docx,txt,rtf,odt,pptx,xlsx,csv,png,jpg,jpeg,gif,webp'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'document.required' => 'A document file is required.',
            'document.file' => 'The uploaded file is invalid.',
            'document.max' => 'The document must not exceed 50MB.',
        ];
    }
}
