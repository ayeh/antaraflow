<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\Organization;
use App\Domain\Webhook\Models\WebhookEndpoint;
use App\Models\User;
use App\Support\Enums\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<WebhookEndpoint> */
class WebhookEndpointFactory extends Factory
{
    protected $model = WebhookEndpoint::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'url' => fake()->url(),
            'secret' => Str::random(32),
            'events' => [WebhookEvent::MeetingFinalized->value],
            'is_active' => true,
            'failure_count' => 0,
            'description' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /** @param list<WebhookEvent> $events */
    public function forEvents(array $events): static
    {
        return $this->state([
            'events' => array_map(fn ($e) => $e->value, $events),
        ]);
    }
}
