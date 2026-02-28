<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Transcription\Models\AudioTranscription;
use App\Domain\Transcription\Models\TranscriptionSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TranscriptionSegment> */
class TranscriptionSegmentFactory extends Factory
{
    protected $model = TranscriptionSegment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $startTime = fake()->randomFloat(2, 0, 300);

        return [
            'audio_transcription_id' => AudioTranscription::factory(),
            'text' => fake()->sentence(),
            'speaker' => 'Speaker '.fake()->numberBetween(1, 5),
            'start_time' => $startTime,
            'end_time' => $startTime + fake()->randomFloat(2, 1, 10),
            'confidence' => fake()->randomFloat(2, 0.7, 1.0),
            'sequence_order' => fake()->numberBetween(0, 100),
            'is_edited' => false,
        ];
    }

    public function edited(): static
    {
        return $this->state(['is_edited' => true]);
    }
}
