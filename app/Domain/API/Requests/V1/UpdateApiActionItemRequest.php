<?php

declare(strict_types=1);

namespace App\Domain\API\Requests\V1;

use App\Support\Enums\ActionItemPriority;
use App\Support\Enums\ActionItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApiActionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'priority' => ['sometimes', 'nullable', Rule::enum(ActionItemPriority::class)],
            'status' => ['sometimes', Rule::enum(ActionItemStatus::class)],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
