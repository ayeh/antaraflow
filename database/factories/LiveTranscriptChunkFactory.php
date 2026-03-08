<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\LiveMeeting\Enums\ChunkStatus;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LiveTranscriptChunk> */
class LiveTranscriptChunkFactory extends Factory
{
    protected $model = LiveTranscriptChunk::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $startTime = fake()->randomFloat(2, 0, 3600);

        return [
            'live_meeting_session_id' => LiveMeetingSession::factory(),
            'chunk_number' => fake()->numberBetween(1, 100),
            'audio_file_path' => null,
            'text' => fake()->sentence(),
            'speaker' => fake()->name(),
            'start_time' => $startTime,
            'end_time' => $startTime + fake()->randomFloat(2, 5, 30),
            'confidence' => fake()->randomFloat(2, 0.7, 1.0),
            'status' => ChunkStatus::Pending,
            'error_message' => null,
        ];
    }

    public function processing(): static
    {
        return $this->state(['status' => ChunkStatus::Processing]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => ChunkStatus::Completed,
            'text' => fake()->sentence(),
            'confidence' => fake()->randomFloat(2, 0.85, 1.0),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => ChunkStatus::Failed,
            'error_message' => 'Transcription failed: '.fake()->sentence(),
        ]);
    }
}
