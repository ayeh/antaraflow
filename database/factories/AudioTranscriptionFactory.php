<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Models\User;
use App\Support\Enums\TranscriptionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AudioTranscription> */
class AudioTranscriptionFactory extends Factory
{
    protected $model = AudioTranscription::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'minutes_of_meeting_id' => MinutesOfMeeting::factory(),
            'uploaded_by' => User::factory(),
            'original_filename' => fake()->word().'.mp3',
            'file_path' => 'organizations/1/audio/'.fake()->uuid().'.mp3',
            'mime_type' => 'audio/mpeg',
            'file_size' => fake()->numberBetween(1024, 10485760),
            'language' => 'en',
            'status' => TranscriptionStatus::Pending,
        ];
    }

    public function processing(): static
    {
        return $this->state([
            'status' => TranscriptionStatus::Processing,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => TranscriptionStatus::Completed,
            'full_text' => fake()->paragraphs(3, true),
            'confidence_score' => fake()->randomFloat(2, 0.8, 1.0),
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => TranscriptionStatus::Failed,
            'error_message' => 'Transcription failed: '.fake()->sentence(),
            'retry_count' => fake()->numberBetween(1, 3),
        ]);
    }
}
