<?php

declare(strict_types=1);

use App\Domain\Account\Models\UserSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can view notification settings page', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('settings.notifications'))->assertOk();
});

it('can update notification preferences', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('settings.notifications.update'), [
            'mention_in_comment' => ['email' => true, 'in_app' => true],
            'action_item_assigned' => ['email' => false, 'in_app' => true],
        ])
        ->assertRedirect();

    $prefs = UserSettings::where('user_id', $user->id)->value('notification_preferences');
    expect($prefs['mention_in_comment']['email'])->toBeTrue();
    expect($prefs['action_item_assigned']['email'])->toBeFalse();
});
