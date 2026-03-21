<?php

declare(strict_types=1);

namespace App\Domain\Account\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'mention_in_comment' => ['nullable', 'array'],
            'mention_in_comment.email' => ['nullable', 'boolean'],
            'mention_in_comment.in_app' => ['nullable', 'boolean'],
            'action_item_assigned' => ['nullable', 'array'],
            'action_item_assigned.email' => ['nullable', 'boolean'],
            'action_item_assigned.in_app' => ['nullable', 'boolean'],
            'meeting_finalized' => ['nullable', 'array'],
            'meeting_finalized.email' => ['nullable', 'boolean'],
            'meeting_finalized.in_app' => ['nullable', 'boolean'],
            'action_item_overdue' => ['nullable', 'array'],
            'action_item_overdue.email' => ['nullable', 'boolean'],
            'action_item_overdue.in_app' => ['nullable', 'boolean'],
        ];
    }
}
