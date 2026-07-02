<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\OrganizationInvitation;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrganizationInvitation> */
class OrganizationInvitationFactory extends Factory
{
    /** @var class-string<OrganizationInvitation> */
    protected $model = OrganizationInvitation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => UserRole::Member,
            'token' => OrganizationInvitation::generateToken(),
            'invited_by_user_id' => User::factory(),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => now(),
        ]);
    }
}
