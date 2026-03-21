<?php

declare(strict_types=1);

namespace App\Domain\API\Requests\V1;

use App\Support\Enums\WebhookEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $validEvents = array_column(WebhookEvent::cases(), 'value');

        return [
            'url' => ['required', 'url', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in($validEvents)],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
