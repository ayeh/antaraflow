<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\Organization;
use App\Domain\Report\Models\ReportTemplate;
use App\Models\User;
use App\Support\Enums\ReportType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReportTemplate> */
class ReportTemplateFactory extends Factory
{
    protected $model = ReportTemplate::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true),
            'type' => fake()->randomElement(ReportType::cases()),
            'filters' => null,
            'schedule' => null,
            'recipients' => null,
            'is_active' => true,
            'last_generated_at' => null,
            'created_by' => User::factory(),
        ];
    }

    public function monthlySummary(): static
    {
        return $this->state(['type' => ReportType::MonthlySummary]);
    }

    public function actionItemStatus(): static
    {
        return $this->state(['type' => ReportType::ActionItemStatus]);
    }

    public function attendanceReport(): static
    {
        return $this->state(['type' => ReportType::AttendanceReport]);
    }

    public function governanceCompliance(): static
    {
        return $this->state(['type' => ReportType::GovernanceCompliance]);
    }

    public function scheduled(string $schedule = '0 * * * *'): static
    {
        return $this->state(['schedule' => $schedule]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function withRecipients(array $emails = ['test@example.com']): static
    {
        return $this->state(['recipients' => $emails]);
    }
}
