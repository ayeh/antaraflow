<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\UserDevice;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Admin\Models\PlatformSetting;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Infrastructure\Notifications\Push\PushMessage;
use App\Infrastructure\Notifications\Push\PushSender;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create(['name' => 'Antara']);
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);

    $this->meeting = MinutesOfMeeting::createForOrganization($this->org->id, [
        'title' => 'Board meeting',
        'created_by' => $this->user->id,
    ]);
});

describe('bootstrap', function () {
    test('one call returns identity, tenant, plan and badge counts', function () {
        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/mobile/v1/bootstrap');

        $response->assertOk()
            ->assertJsonPath('user.id', $this->user->id)
            ->assertJsonPath('organization.name', 'Antara')
            ->assertJsonStructure([
                'user', 'organization', 'subscription' => ['features', 'limits', 'usage'],
                'capabilities', 'unread' => ['notifications', 'action_items_due', 'pending_approvals'],
                'realtime', 'server_time',
            ]);
    });

    test('overdue action items are counted for the badge', function () {
        ActionItem::createForOrganization($this->org->id, [
            'minutes_of_meeting_id' => $this->meeting->id,
            'title' => 'Overdue task',
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'due_date' => now()->subDay(),
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/mobile/v1/bootstrap')
            ->assertOk()
            ->assertJsonPath('unread.action_items_due', 1);
    });
});

describe('idempotency', function () {
    test('a repeated create with the same key returns the first result once', function () {
        $key = (string) Str::uuid();

        $first = $this->actingAs($this->user, 'sanctum')
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/mobile/v1/meetings', ['title' => 'Standup']);

        $second = $this->actingAs($this->user, 'sanctum')
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/mobile/v1/meetings', ['title' => 'Standup']);

        $first->assertCreated();
        $second->assertCreated();

        expect($second->json('id'))->toBe($first->json('id'));
        expect($second->headers->get('Idempotency-Replayed'))->toBe('true');
        expect(MinutesOfMeeting::query()->where('title', 'Standup')->count())->toBe(1);
    });

    test('different keys create separate records', function () {
        foreach ([Str::uuid(), Str::uuid()] as $key) {
            $this->actingAs($this->user, 'sanctum')
                ->withHeader('Idempotency-Key', (string) $key)
                ->postJson('/api/mobile/v1/meetings', ['title' => 'Standup'])
                ->assertCreated();
        }

        expect(MinutesOfMeeting::query()->where('title', 'Standup')->count())->toBe(2);
    });

    test('a failed request does not burn the key', function () {
        $key = (string) Str::uuid();

        $this->actingAs($this->user, 'sanctum')
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/mobile/v1/meetings', ['title' => ''])
            ->assertStatus(422);

        $this->actingAs($this->user, 'sanctum')
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/mobile/v1/meetings', ['title' => 'Corrected'])
            ->assertCreated();
    });
});

describe('client version gate', function () {
    test('a build below the configured floor is blocked with an upgrade code', function () {
        PlatformSetting::setValue('mobile_min_version_ios', '2.0.0');

        $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Client-Version', 'ios/1.4.2 (build 210)')
            ->getJson('/api/mobile/v1/meetings')
            ->assertStatus(426)
            ->assertJsonPath('code', 'CLIENT_UPGRADE_REQUIRED')
            ->assertJsonPath('minimum_version', '2.0.0');
    });

    test('a current build passes and is told the floor', function () {
        PlatformSetting::setValue('mobile_min_version_ios', '2.0.0');

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Client-Version', 'ios/2.1.0 (build 300)')
            ->getJson('/api/mobile/v1/meetings');

        $response->assertOk();
        expect($response->headers->get('X-Min-Client-Version'))->toBe('2.0.0');
    });

    test('no configured floor lets any build through', function () {
        $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Client-Version', 'android/0.1.0')
            ->getJson('/api/mobile/v1/meetings')
            ->assertOk();
    });
});

describe('sign-in desk', function () {
    test('opening the desk hands back both the lobby screen and the scan target', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/mobile/v1/meetings/{$this->meeting->id}/qr-registration");

        $response->assertCreated();

        $token = $this->meeting->qrRegistrationTokens()->sole();

        expect($response->json('lobby_url'))->toBe(route('qr-registration.lobby', $token->token))
            ->and($response->json('qr_payload'))->toBe(route('qr-registration.form', $token->token))
            ->and($response->json('join_code'))->toHaveLength(6);
    });

    test('a link made without a date dies at the end of the sitting day', function () {
        $meeting = MinutesOfMeeting::createForOrganization($this->org->id, [
            'title' => 'Audit committee',
            'created_by' => $this->user->id,
            'meeting_date' => now()->addDays(3)->setTime(14, 0),
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/mobile/v1/meetings/{$meeting->id}/qr-registration")
            ->assertCreated()
            ->assertJsonPath(
                'expires_at',
                now()->addDays(3)->endOfDay()->toIso8601String(),
            );
    });

    test('a sitting already in the past still gets a link that works today', function () {
        $meeting = MinutesOfMeeting::createForOrganization($this->org->id, [
            'title' => 'Ran late',
            'created_by' => $this->user->id,
            'meeting_date' => now()->subWeek(),
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/mobile/v1/meetings/{$meeting->id}/qr-registration")
            ->assertCreated()
            ->assertJsonPath('expires_at', now()->endOfDay()->toIso8601String());
    });

    test('an explicit expiry is honoured over the default', function () {
        $chosen = now()->addHours(2)->startOfMinute();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/mobile/v1/meetings/{$this->meeting->id}/qr-registration", [
                'expires_at' => $chosen->toIso8601String(),
            ])
            ->assertCreated()
            ->assertJsonPath('expires_at', $chosen->toIso8601String());
    });

    test('opening a second desk closes the first', function () {
        $acting = $this->actingAs($this->user, 'sanctum');

        $first = $acting->postJson("/api/mobile/v1/meetings/{$this->meeting->id}/qr-registration")
            ->json('token');

        $acting->postJson("/api/mobile/v1/meetings/{$this->meeting->id}/qr-registration")
            ->assertCreated();

        expect($this->meeting->qrRegistrationTokens()->where('token', $first)->sole()->is_active)
            ->toBeFalse();
    });
});

describe('attendance scan', function () {
    test('scanning a valid code checks the person in', function () {
        $token = $this->meeting->qrRegistrationTokens()->create([
            'token' => Str::random(64),
            'join_code' => 'ABC123',
            'is_active' => true,
            'required_fields' => ['name'],
            'registrations_count' => 0,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/mobile/v1/attendance/scan', ['token' => $token->token]);

        $response->assertCreated()
            ->assertJsonPath('already_registered', false)
            ->assertJsonPath('attendee.is_present', true)
            ->assertJsonPath('meeting.id', $this->meeting->id);

        expect($token->fresh()->registrations_count)->toBe(1);
    });

    test('scanning twice reads as already checked in rather than an error', function () {
        $token = $this->meeting->qrRegistrationTokens()->create([
            'token' => Str::random(64),
            'join_code' => 'ABC124',
            'is_active' => true,
            'required_fields' => ['name'],
            'registrations_count' => 0,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/mobile/v1/attendance/scan', ['token' => $token->token])
            ->assertCreated();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/mobile/v1/attendance/scan', ['token' => $token->token])
            ->assertOk()
            ->assertJsonPath('already_registered', true);

        expect($this->meeting->attendees()->count())->toBe(1);
    });

    test('an expired code is refused', function () {
        $token = $this->meeting->qrRegistrationTokens()->create([
            'token' => Str::random(64),
            'join_code' => 'ABC125',
            'is_active' => true,
            'expires_at' => now()->subMinute(),
            'required_fields' => ['name'],
            'registrations_count' => 0,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/mobile/v1/attendance/scan', ['token' => $token->token])
            ->assertStatus(410)
            ->assertJsonPath('code', 'QR_TOKEN_EXPIRED');
    });

    test('an unknown code is refused', function () {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/mobile/v1/attendance/scan', ['token' => Str::random(64)])
            ->assertStatus(404)
            ->assertJsonPath('code', 'QR_TOKEN_INVALID');
    });

    test('a full code is refused', function () {
        $token = $this->meeting->qrRegistrationTokens()->create([
            'token' => Str::random(64),
            'join_code' => 'ABC126',
            'is_active' => true,
            'max_attendees' => 1,
            'registrations_count' => 1,
            'required_fields' => ['name'],
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/mobile/v1/attendance/scan', ['token' => $token->token])
            ->assertStatus(410)
            ->assertJsonPath('code', 'QR_TOKEN_FULL');
    });
});

describe('push notifications', function () {
    test('an assigned action item reaches every registered device', function () {
        $assignee = User::factory()->create(['current_organization_id' => $this->org->id]);
        $this->org->members()->attach($assignee, ['role' => UserRole::Member->value]);

        UserDevice::query()->create([
            'user_id' => $assignee->id,
            'device_id' => 'device-1',
            'push_token' => 'token-1',
            'platform' => 'ios',
        ]);

        $sent = [];
        $this->app->instance(PushSender::class, new class($sent) implements PushSender
        {
            public function __construct(public array &$sent) {}

            public function send(UserDevice $device, PushMessage $message): bool
            {
                $this->sent[] = [$device->device_id, $message];

                return true;
            }
        });

        $this->actingAs($this->user, 'sanctum')->postJson('/api/mobile/v1/action-items', [
            'minutes_of_meeting_id' => $this->meeting->id,
            'title' => 'Draft the budget paper',
            'assigned_to' => $assignee->id,
        ])->assertCreated();

        expect($sent)->toHaveCount(1);
        expect($sent[0][0])->toBe('device-1');
        expect($sent[0][1]->deepLink)->toContain('antaraflow://action-items/');
    });

    test('a dead token is dropped rather than retried forever', function () {
        $assignee = User::factory()->create(['current_organization_id' => $this->org->id]);
        $this->org->members()->attach($assignee, ['role' => UserRole::Member->value]);

        $device = UserDevice::query()->create([
            'user_id' => $assignee->id,
            'device_id' => 'device-dead',
            'push_token' => 'token-dead',
            'platform' => 'android',
        ]);

        $this->app->instance(PushSender::class, new class implements PushSender
        {
            public function send(UserDevice $device, PushMessage $message): bool
            {
                return false;
            }
        });

        $this->actingAs($this->user, 'sanctum')->postJson('/api/mobile/v1/action-items', [
            'minutes_of_meeting_id' => $this->meeting->id,
            'title' => 'Something',
            'assigned_to' => $assignee->id,
        ])->assertCreated();

        $device->refresh();
        expect($device->push_token)->toBeNull()
            ->and($device->revoked_at)->not->toBeNull();
    });

    test('no push is attempted when the person has no device', function () {
        $assignee = User::factory()->create(['current_organization_id' => $this->org->id]);
        $this->org->members()->attach($assignee, ['role' => UserRole::Member->value]);

        $attempts = 0;
        $this->app->instance(PushSender::class, new class($attempts) implements PushSender
        {
            public function __construct(public int &$attempts) {}

            public function send(UserDevice $device, PushMessage $message): bool
            {
                $this->attempts++;

                return true;
            }
        });

        $this->actingAs($this->user, 'sanctum')->postJson('/api/mobile/v1/action-items', [
            'minutes_of_meeting_id' => $this->meeting->id,
            'title' => 'Something',
            'assigned_to' => $assignee->id,
        ])->assertCreated();

        expect($attempts)->toBe(0);
    });
});

describe('device registration', function () {
    test('registering the same device twice updates rather than duplicates', function () {
        foreach (['token-a', 'token-b'] as $pushToken) {
            $this->actingAs($this->user, 'sanctum')->postJson('/api/mobile/v1/devices', [
                'device_id' => 'device-x',
                'push_token' => $pushToken,
                'platform' => 'android',
                'app_version' => '1.0.0',
            ])->assertOk();
        }

        $devices = UserDevice::query()->where('device_id', 'device-x')->get();

        expect($devices)->toHaveCount(1);
        expect($devices->first()->push_token)->toBe('token-b');
    });
});

describe('mom numbering', function () {
    test('a sitting filed from the phone gets a number, like one filed on the web', function () {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/mobile/v1/meetings', ['title' => 'Board meeting'])
            ->assertCreated()
            ->assertJsonPath('mom_number', 'MOM-'.date('Y').'-000001');
    });

    test('numbers continue the organisation series rather than restarting', function () {
        MinutesOfMeeting::createForOrganization($this->org->id, [
            'title' => 'Filed on the web',
            'created_by' => $this->user->id,
            'mom_number' => 'MOM-'.date('Y').'-000007',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/mobile/v1/meetings', ['title' => 'Filed on the phone'])
            ->assertCreated()
            ->assertJsonPath('mom_number', 'MOM-'.date('Y').'-000008');
    });

    test('each organisation counts on its own', function () {
        $other = Organization::factory()->create();
        MinutesOfMeeting::createForOrganization($other->id, [
            'title' => 'Somebody else',
            'created_by' => $this->user->id,
            'mom_number' => 'MOM-'.date('Y').'-000042',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/mobile/v1/meetings', ['title' => 'Ours'])
            ->assertCreated()
            ->assertJsonPath('mom_number', 'MOM-'.date('Y').'-000001');
    });

    test('two sittings filed back to back do not collide on the unique index', function () {
        $acting = $this->actingAs($this->user, 'sanctum');

        $first = $acting->postJson('/api/mobile/v1/meetings', ['title' => 'One'])
            ->assertCreated()->json('mom_number');

        $second = $acting->postJson('/api/mobile/v1/meetings', ['title' => 'Two'])
            ->assertCreated()->json('mom_number');

        expect($first)->not->toBe($second);
    });
});

describe('circulations', function () {
    /*
     * A circulation the secretary pulled back (by reverting the MOM to draft)
     * must disappear from the phone and refuse a late response — otherwise the
     * app keeps collecting confirmations for minutes that are being rewritten.
     */
    beforeEach(function () {
        $this->circulation = \App\Domain\Meeting\Models\MomCirculation::createForOrganization($this->org->id, [
            'minutes_of_meeting_id' => $this->meeting->id,
            'sent_by' => $this->user->id,
            'round' => 1,
            'subject' => 'Board minutes',
            'deadline_at' => now()->addDays(3),
            'status' => 'open',
        ]);

        $this->recipient = \App\Domain\Meeting\Models\MomCirculationRecipient::create([
            'mom_circulation_id' => $this->circulation->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'token' => Str::random(64),
        ]);
    });

    test('an open circulation is listed as pending', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/mobile/v1/circulations/pending')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    test('a cancelled circulation drops off the pending list', function () {
        $this->circulation->update(['status' => 'cancelled', 'closed_at' => now()]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/mobile/v1/circulations/pending')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    test('responding to a cancelled circulation is refused', function () {
        $this->circulation->update(['status' => 'cancelled', 'closed_at' => now()]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/mobile/v1/circulations/{$this->recipient->id}/respond", [
                'decision' => 'confirmed',
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CIRCULATION_CLOSED');

        expect($this->recipient->fresh()->response)->toBeNull();
    });
});
