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
        $prefix = Str::random(8);
        $fullKey = 'af_'.$prefix.'_'.Str::random(32);

        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(2, true),
            'key' => $prefix,
            'secret_hash' => hash('sha256', $fullKey),
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
