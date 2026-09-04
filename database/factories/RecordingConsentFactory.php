<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\RecordingConsent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RecordingConsent> */
class RecordingConsentFactory extends Factory
{
    protected $model = RecordingConsent::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'minutes_of_meeting_id' => MinutesOfMeeting::factory(),
            'acknowledged_by' => User::factory(),
            'notice_version' => 'v1',
            'includes_tab_audio' => fake()->boolean(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'acknowledged_at' => now(),
        ];
    }

    public function withTabAudio(): static
    {
        return $this->state(['includes_tab_audio' => true]);
    }
}
