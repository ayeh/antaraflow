<?php

declare(strict_types=1);

namespace App\Domain\ActionItem\Requests;

use App\Support\Enums\ActionItemPriority;
use App\Support\Enums\ActionItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkActionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
            'action' => ['required', 'in:status,priority,delete'],
            'value' => array_filter([
                Rule::requiredIf(fn () => $this->input('action') !== 'delete'),
                'nullable',
                'string',
                $this->input('action') === 'status' ? Rule::enum(ActionItemStatus::class) : null,
                $this->input('action') === 'priority' ? Rule::enum(ActionItemPriority::class) : null,
            ]),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'ids.required' => 'At least one item must be selected.',
            'ids.min' => 'At least one item must be selected.',
            'action.in' => 'The action must be status, priority, or delete.',
            'value.required_if' => 'A value is required for status and priority actions.',
        ];
    }
}
