<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\AuditLog;
use App\Domain\Account\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AuditLog> */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $organization = Organization::factory();

        return [
            'organization_id' => $organization,
            'user_id' => User::factory(),
            'action' => fake()->randomElement(['created', 'updated', 'deleted']),
            'auditable_type' => Organization::class,
            'auditable_id' => $organization,
            'old_values' => null,
            'new_values' => ['name' => fake()->company()],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
