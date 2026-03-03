<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Admin\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::query()->firstOrCreate(
            ['email' => 'admin@antaraflow.test'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
            ],
        );
    }
}
