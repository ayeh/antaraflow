<?php

declare(strict_types=1);

namespace App\Domain\API\Requests\V1;

use App\Support\Enums\ActionItemPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiActionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'minutes_of_meeting_id' => ['required', 'integer'],
            'priority' => ['nullable', Rule::enum(ActionItemPriority::class)],
            'due_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
