<?php

declare(strict_types=1);

namespace App\Domain\ActionItem\Requests;

use App\Support\Enums\ActionItemPriority;
use App\Support\Enums\ActionItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateActionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['sometimes', Rule::enum(ActionItemPriority::class)],
            'status' => ['sometimes', Rule::enum(ActionItemStatus::class)],
            'assigned_to' => ['nullable', Rule::exists('organization_user', 'user_id')->where('organization_id', $this->user()->current_organization_id)],
            'due_date' => ['nullable', 'date'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.max' => 'The action item title cannot exceed 255 characters.',
            'priority.Illuminate\Validation\Rules\Enum' => 'The priority must be one of: low, medium, high, critical.',
            'status.Illuminate\Validation\Rules\Enum' => 'The status is not valid.',
            'assigned_to.exists' => 'The selected assignee does not exist.',
            'due_date.date' => 'The due date must be a valid date.',
        ];
    }
}
