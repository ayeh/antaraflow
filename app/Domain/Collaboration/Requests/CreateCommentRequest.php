<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCommentRequest extends FormRequest
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
            'parent_id' => ['nullable', 'exists:comments,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'body.required' => 'A comment body is required.',
            'body.max' => 'The comment may not be greater than 2000 characters.',
            'parent_id.exists' => 'The parent comment does not exist.',
        ];
    }
}
