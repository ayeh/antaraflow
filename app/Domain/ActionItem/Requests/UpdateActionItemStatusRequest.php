<?php

declare(strict_types=1);

namespace App\Domain\ActionItem\Requests;

use App\Support\Enums\ActionItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateActionItemStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ActionItemStatus::class)],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'status.required' => 'A status is required.',
            'status.Illuminate\Validation\Rules\Enum' => 'The selected status is not valid.',
            'comment.max' => 'The note cannot exceed 500 characters.',
        ];
    }
}
