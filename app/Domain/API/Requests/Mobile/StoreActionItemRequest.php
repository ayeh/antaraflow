<?php

declare(strict_types=1);

namespace App\Domain\API\Requests\Mobile;

use App\Support\Enums\ActionItemPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'minutes_of_meeting_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'priority' => ['sometimes', Rule::enum(ActionItemPriority::class)],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'assigned_to' => ['sometimes', 'nullable', 'integer'],
            'client_id' => ['sometimes', 'string', 'max:64'],
        ];
    }
}
