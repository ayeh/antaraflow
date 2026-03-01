<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\Organization;
use App\Domain\Collaboration\Models\MeetingShare;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\SharePermission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<MeetingShare> */
class MeetingShareFactory extends Factory
{
    protected $model = MeetingShare::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'minutes_of_meeting_id' => MinutesOfMeeting::factory(),
            'shared_with_user_id' => User::factory(),
            'shared_by_user_id' => User::factory(),
            'permission' => fake()->randomElement(SharePermission::cases()),
            'share_token' => Str::random(64),
        ];
    }

    public function guestLink(): static
    {
        return $this->state([
            'shared_with_user_id' => null,
            'share_token' => Str::random(64),
        ]);
    }
}
