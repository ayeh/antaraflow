<?php

declare(strict_types=1);

namespace App\Domain\API\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreApiCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
        ];
    }
}
