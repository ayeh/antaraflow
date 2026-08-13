<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\UserDevice;
use App\Domain\Account\Support\NotificationPreferences;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\ActionItem\Notifications\ActionItemAssignedNotification;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);

    $this->meeting = MinutesOfMeeting::createForOrganization($this->org->id, [
        'title' => 'Board meeting',
        'created_by' => $this->user->id,
    ]);

    // An active device, so push is a channel that would otherwise be chosen.
    UserDevice::query()->create([
        'user_id' => $this->user->id,
        'device_id' => 'test-device',
        'platform' => 'ios',
        'push_token' => 'token-123',
        'is_active' => true,
    ]);

    $this->item = ActionItem::createForOrganization($this->org->id, [
        'minutes_of_meeting_id' => $this->meeting->id,
        'title' => 'Do the thing',
        'created_by' => $this->user->id,
        'assigned_to' => $this->user->id,
    ]);
});

describe('channels actually follow the preference', function () {
    test('by default a notification reaches mail, the database and the phone', function () {
        $channels = (new ActionItemAssignedNotification($this->item))->via($this->user);

        expect($channels)->toContain('mail')
            ->and($channels)->toContain('database')
            ->and($channels)->toContain('push');
    });

    test('switching email off drops the mail channel and nothing else', function () {
        $this->user->settings()->create([
            'notification_preferences' => NotificationPreferences::merge(
                $this->user,
                ['action_item_assigned' => ['email' => false]],
            ),
        ]);

        $channels = (new ActionItemAssignedNotification($this->item))->via($this->user->fresh());

        expect($channels)->not->toContain('mail')
            ->and($channels)->toContain('database')
            ->and($channels)->toContain('push');
    });

    test('switching push off drops the phone but keeps the record', function () {
        $this->user->settings()->create([
            'notification_preferences' => NotificationPreferences::merge(
                $this->user,
                ['action_item_assigned' => ['push' => false]],
            ),
        ]);

        $channels = (new ActionItemAssignedNotification($this->item))->via($this->user->fresh());

        expect($channels)->not->toContain('push')
            // The in-app list is the record of what happened. Silencing a phone
            // must not put a hole in it.
            ->and($channels)->toContain('database')
            ->and($channels)->toContain('mail');
    });

    test('silencing everything still writes the entry to the record', function () {
        $this->user->settings()->create([
            'notification_preferences' => NotificationPreferences::merge(
                $this->user,
                ['action_item_assigned' => ['push' => false, 'email' => false]],
            ),
        ]);

        expect((new ActionItemAssignedNotification($this->item))->via($this->user->fresh()))
            ->toBe(['database']);
    });

    test('a preference for one kind does not silence another', function () {
        $this->user->settings()->create([
            'notification_preferences' => NotificationPreferences::merge(
                $this->user,
                ['mention' => ['push' => false, 'email' => false]],
            ),
        ]);

        expect((new ActionItemAssignedNotification($this->item))->via($this->user->fresh()))
            ->toContain('mail');
    });
});

describe('the two clients stop overwriting each other', function () {
    test('a mobile save does not blank out what the web set', function () {
        $this->user->settings()->create([
            'notification_preferences' => ['action_item_assigned' => ['push' => true, 'email' => false]],
        ]);

        $this->actingAs($this->user->fresh(), 'sanctum')
            ->patchJson('/api/mobile/v1/settings/notifications', [
                'preferences' => ['action_item_assigned' => ['push' => false]],
            ])
            ->assertOk()
            ->assertJsonPath('data.action_item_assigned.push', false)
            ->assertJsonPath('data.action_item_assigned.email', false);
    });

    test('a mobile save leaves the other kinds alone', function () {
        $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/mobile/v1/settings/notifications', [
                'preferences' => ['stale_decision' => ['email' => false]],
            ])
            ->assertOk()
            ->assertJsonPath('data.stale_decision.email', false)
            ->assertJsonPath('data.action_item_assigned.email', true)
            ->assertJsonPath('data.extraction_completed.push', true);
    });

    test('the old web key for a mention is read as the same preference', function () {
        $this->user->settings()->create([
            'notification_preferences' => ['mention_in_comment' => ['email' => false, 'in_app' => true]],
        ]);

        expect(NotificationPreferences::allows($this->user->fresh(), 'mention', 'email'))->toBeFalse();
    });
});

describe('the kinds that could not be silenced before', function () {
    test('every kind the server sends now has a preference key', function () {
        $keys = array_keys(NotificationPreferences::defaults());

        expect($keys)->toContain('extraction_completed')
            ->and($keys)->toContain('meeting_starting')
            ->and($keys)->toContain('stale_decision')
            ->and($keys)->toContain('action_item_overdue')
            ->and($keys)->toContain('processing_failed');
    });

    test('the endpoint hands the app all eleven', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/mobile/v1/settings/notifications')
            ->assertOk()
            ->assertJsonCount(11, 'data');
    });
});
