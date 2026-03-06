<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\Organization;
use App\Domain\AI\Models\ExtractionTemplate;
use App\Support\Enums\ExtractionType;
use App\Support\Enums\MeetingType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExtractionTemplate> */
class ExtractionTemplateFactory extends Factory
{
    protected $model = ExtractionTemplate::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true),
            'meeting_type' => fake()->randomElement(MeetingType::cases()),
            'extraction_type' => fake()->randomElement(ExtractionType::cases()),
            'prompt_template' => "Extract information from the following meeting transcript:\n\n{transcript}",
            'system_message' => 'You are an expert meeting analyst.',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function forType(ExtractionType $type): self
    {
        return $this->state(['extraction_type' => $type]);
    }

    public function forMeetingType(MeetingType $type): self
    {
        return $this->state(['meeting_type' => $type]);
    }

    public function wildcard(): self
    {
        return $this->state(['meeting_type' => null]);
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }
}
