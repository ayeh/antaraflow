<?php

declare(strict_types=1);

namespace App\Domain\ActionItem\Requests;

use App\Support\Enums\ActionItemPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateActionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', Rule::enum(ActionItemPriority::class)],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date', 'after:today'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'The action item title is required.',
            'title.max' => 'The action item title cannot exceed 255 characters.',
            'priority.Illuminate\Validation\Rules\Enum' => 'The priority must be one of: low, medium, high, critical.',
            'assigned_to.exists' => 'The selected assignee does not exist.',
            'due_date.date' => 'The due date must be a valid date.',
            'due_date.after' => 'The due date must be in the future.',
        ];
    }
}
