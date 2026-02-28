<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\ActionItem\Models\ActionItemHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ActionItemHistory> */
class ActionItemHistoryFactory extends Factory
{
    protected $model = ActionItemHistory::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'action_item_id' => ActionItem::factory(),
            'changed_by' => User::factory(),
            'field_changed' => fake()->randomElement(['status', 'priority', 'assigned_to', 'due_date']),
            'old_value' => fake()->word(),
            'new_value' => fake()->word(),
            'comment' => null,
        ];
    }
}
