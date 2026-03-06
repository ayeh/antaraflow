<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Webhook\Models\WebhookDelivery;
use App\Domain\Webhook\Models\WebhookEndpoint;
use App\Support\Enums\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WebhookDelivery> */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'webhook_endpoint_id' => WebhookEndpoint::factory(),
            'event' => WebhookEvent::MeetingFinalized->value,
            'payload' => ['event' => 'meeting.finalized', 'data' => []],
            'response_status' => 200,
            'response_body' => 'OK',
            'attempt' => 1,
            'successful' => true,
        ];
    }

    public function failed(): static
    {
        return $this->state([
            'response_status' => 500,
            'response_body' => 'Internal Server Error',
            'successful' => false,
        ]);
    }
}
