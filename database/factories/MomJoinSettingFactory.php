<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Attendee\Models\MomJoinSetting;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MomJoinSetting> */
class MomJoinSettingFactory extends Factory
{
    protected $model = MomJoinSetting::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'minutes_of_meeting_id' => MinutesOfMeeting::factory(),
            'allow_external_join' => false,
            'require_rsvp' => false,
            'auto_notify' => true,
            'notification_config' => null,
        ];
    }
}
