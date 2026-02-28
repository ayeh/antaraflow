<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\ApiKey;
use App\Domain\Account\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ApiKey> */
class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(2, true),
            'key' => 'ak_'.Str::random(56),
            'secret_hash' => bcrypt(Str::random(32)),
            'permissions' => ['read', 'write'],
            'last_used_at' => null,
            'expires_at' => now()->addYear(),
            'is_active' => true,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
