<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\ResellerSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ResellerSetting> */
class ResellerSettingFactory extends Factory
{
    protected $model = ResellerSetting::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'subdomain' => fake()->unique()->slug(2),
            'custom_domain' => null,
            'is_reseller' => false,
            'allowed_plans' => null,
            'commission_rate' => 0,
            'max_sub_organizations' => null,
            'branding_overrides' => null,
        ];
    }

    public function reseller(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_reseller' => true,
            'commission_rate' => fake()->randomFloat(2, 5, 30),
            'max_sub_organizations' => fake()->numberBetween(5, 100),
        ]);
    }

    public function withBranding(): static
    {
        return $this->state(fn (array $attributes) => [
            'branding_overrides' => [
                'app_name' => fake()->company(),
                'primary_color' => fake()->hexColor(),
                'logo_url' => fake()->imageUrl(200, 60),
            ],
        ]);
    }

    public function withCustomDomain(): static
    {
        return $this->state(fn (array $attributes) => [
            'custom_domain' => fake()->unique()->domainName(),
        ]);
    }
}
