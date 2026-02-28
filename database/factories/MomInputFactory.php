<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomInput;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Support\Enums\InputType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MomInput> */
class MomInputFactory extends Factory
{
    protected $model = MomInput::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'minutes_of_meeting_id' => MinutesOfMeeting::factory(),
            'type' => InputType::Audio,
            'source_type' => AudioTranscription::class,
            'source_id' => AudioTranscription::factory(),
            'sort_order' => 0,
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(['is_primary' => true]);
    }

    public function forAudio(AudioTranscription $transcription): static
    {
        return $this->state([
            'type' => InputType::Audio,
            'source_type' => AudioTranscription::class,
            'source_id' => $transcription->id,
        ]);
    }
}
