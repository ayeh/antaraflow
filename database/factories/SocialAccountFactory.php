<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SocialAccount> */
class SocialAccountFactory extends Factory
{
    protected $model = SocialAccount::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => fake()->randomElement(['google', 'microsoft', 'github']),
            'provider_id' => (string) fake()->unique()->numberBetween(100000, 999999),
            'provider_email' => fake()->safeEmail(),
            'avatar_url' => fake()->imageUrl(),
        ];
    }

    public function google(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'google',
        ]);
    }

    public function microsoft(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'microsoft',
        ]);
    }

    public function github(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'github',
        ]);
    }
}
