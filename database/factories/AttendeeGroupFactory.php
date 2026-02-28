<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\Organization;
use App\Domain\Attendee\Models\AttendeeGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttendeeGroup> */
class AttendeeGroupFactory extends Factory
{
    protected $model = AttendeeGroup::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'default_members' => [
                ['name' => fake()->name(), 'email' => fake()->safeEmail(), 'role' => 'participant'],
                ['name' => fake()->name(), 'email' => fake()->safeEmail(), 'role' => 'participant'],
            ],
        ];
    }
}
