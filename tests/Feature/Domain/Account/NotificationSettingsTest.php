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
    // Stored canonically now. The form still posts `mention_in_comment`; the
    // controller maps it, so one spelling reaches the database and the mobile
    // screen sees the same preference the web screen set.
    expect($prefs['mention']['email'])->toBeTrue();
    expect($prefs['action_item_assigned']['email'])->toBeFalse();
});
