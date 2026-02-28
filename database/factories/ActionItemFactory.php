<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\ActionItemPriority;
use App\Support\Enums\ActionItemStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ActionItem> */
class ActionItemFactory extends Factory
{
    protected $model = ActionItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'minutes_of_meeting_id' => MinutesOfMeeting::factory(),
            'assigned_to' => User::factory(),
            'created_by' => User::factory(),
            'carried_from_id' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'priority' => ActionItemPriority::Medium,
            'status' => ActionItemStatus::Open,
            'due_date' => fake()->dateTimeBetween('+1 day', '+30 days'),
            'completed_at' => null,
            'metadata' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(['status' => ActionItemStatus::Open]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => ActionItemStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state([
            'status' => ActionItemStatus::Open,
            'due_date' => fake()->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }

    public function highPriority(): static
    {
        return $this->state(['priority' => ActionItemPriority::High]);
    }
}
