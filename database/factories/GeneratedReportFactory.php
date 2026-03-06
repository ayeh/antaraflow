<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\Organization;
use App\Domain\Report\Models\GeneratedReport;
use App\Domain\Report\Models\ReportTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GeneratedReport> */
class GeneratedReportFactory extends Factory
{
    protected $model = GeneratedReport::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'report_template_id' => ReportTemplate::factory(),
            'organization_id' => Organization::factory(),
            'file_path' => 'reports/'.fake()->uuid().'.pdf',
            'file_size' => fake()->numberBetween(10000, 500000),
            'parameters' => null,
            'generated_at' => now(),
        ];
    }
}
