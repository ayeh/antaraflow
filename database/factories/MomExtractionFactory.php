<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\AI\Models\MomExtraction;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MomExtraction> */
class MomExtractionFactory extends Factory
{
    protected $model = MomExtraction::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'minutes_of_meeting_id' => MinutesOfMeeting::factory(),
            'type' => fake()->randomElement(['summary', 'action_items', 'decisions', 'topics']),
            'content' => fake()->paragraphs(2, true),
            'structured_data' => ['key_points' => fake()->sentences(3)],
            'provider' => fake()->randomElement(['openai', 'anthropic', 'google']),
            'model' => fake()->randomElement(['gpt-4o', 'claude-sonnet-4-20250514', 'gemini-2.0-flash']),
            'confidence_score' => fake()->randomFloat(2, 0.7, 1.0),
            'token_usage' => fake()->numberBetween(100, 5000),
        ];
    }

    public function summary(): static
    {
        return $this->state(['type' => 'summary']);
    }

    public function actionItems(): static
    {
        return $this->state(['type' => 'action_items']);
    }

    public function decisions(): static
    {
        return $this->state(['type' => 'decisions']);
    }
}
