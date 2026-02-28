<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\AiProviderConfig;
use App\Domain\Account\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AiProviderConfig> */
class AiProviderConfigFactory extends Factory
{
    protected $model = AiProviderConfig::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'provider' => fake()->randomElement(['openai', 'anthropic', 'google']),
            'display_name' => fake()->words(2, true),
            'api_key_encrypted' => encrypt(Str::random(32)),
            'model' => fake()->randomElement(['gpt-4', 'claude-3', 'gemini-pro']),
            'base_url' => null,
            'settings' => [],
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
