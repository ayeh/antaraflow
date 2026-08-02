<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomGuestAccess;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MomGuestAccess>
 */
class MomGuestAccessFactory extends Factory
{
    protected $model = MomGuestAccess::class;

    /**
     * organization_id is not mass-assignable on the model — in a request it is filled
     * by the BelongsToOrganization hook from the authenticated user. Factories run
     * unguarded, so setting it here lets tests build a guest link without an auth
     * context, matching how MinutesOfMeetingFactory handles the same column.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'minutes_of_meeting_id' => MinutesOfMeeting::factory(),
            'organization_id' => Organization::factory(),
            'token' => Str::random(48),
            'label' => 'Guest Link',
            'email' => null,
            'is_active' => true,
            'expires_at' => null,
            'last_accessed_at' => null,
            'access_count' => 0,
        ];
    }

    /** A link the owner has revoked. */
    public function revoked(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /** A link whose expiry has already passed. */
    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subDay()]);
    }

    /** Still valid, but with an expiry set. */
    public function expiringIn(int $days = 7): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->addDays($days)]);
    }

    /** Attach to an existing meeting, keeping the organisation in sync with it. */
    public function forMeeting(MinutesOfMeeting $meeting): static
    {
        return $this->state(fn (): array => [
            'minutes_of_meeting_id' => $meeting->id,
            'organization_id' => $meeting->organization_id,
        ]);
    }
}
