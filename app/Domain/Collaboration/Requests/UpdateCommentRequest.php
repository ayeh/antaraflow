<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'body.required' => 'A comment body is required.',
            'body.max' => 'The comment may not be greater than 2000 characters.',
        ];
    }
}
