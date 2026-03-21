<?php

declare(strict_types=1);

use App\Domain\Account\Models\UserSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create user settings with defaults', function (): void {
    $user = User::factory()->create();

    $settings = UserSettings::create([
        'user_id' => $user->id,
    ]);

    $settings->refresh();

    expect($settings->timezone)->toBe('UTC');
    expect($settings->two_factor_enabled)->toBeFalse();
});
