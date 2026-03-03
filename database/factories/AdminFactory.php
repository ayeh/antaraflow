<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Admin\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Admin> */
class AdminFactory extends Factory
{
    protected $model = Admin::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'remember_token' => null,
        ];
    }
}
