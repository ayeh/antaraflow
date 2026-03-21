<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\Organization;
use App\Domain\Export\Models\ExportTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExportTemplate> */
class ExportTemplateFactory extends Factory
{
    protected $model = ExportTemplate::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'header_html' => null,
            'footer_html' => null,
            'css_overrides' => null,
            'primary_color' => '#'.fake()->hexColor(),
            'font_family' => fake()->randomElement(['Arial', 'Times New Roman', 'Helvetica']),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
