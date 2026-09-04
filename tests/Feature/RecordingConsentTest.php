<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\RecordingConsent;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);

    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'title' => 'Consent Test Meeting',
    ]);
});

test('a guest cannot record consent', function () {
    $this->postJson(route('meetings.recording-consent.store', $this->meeting), [
        'notice_version' => 'v1',
        'acknowledged' => true,
    ])->assertUnauthorized();

    expect(RecordingConsent::query()->count())->toBe(0);
});

test('an authenticated member records consent as a per-meeting audit row', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('meetings.recording-consent.store', $this->meeting), [
            'notice_version' => 'v1',
            'includes_tab_audio' => true,
            'acknowledged' => true,
        ]);

    $response->assertCreated()->assertJsonPath('consented', true);

    $consent = RecordingConsent::query()->first();

    expect($consent)->not->toBeNull()
        ->and($consent->minutes_of_meeting_id)->toBe($this->meeting->id)
        ->and($consent->organization_id)->toBe($this->org->id)
        ->and($consent->acknowledged_by)->toBe($this->user->id)
        ->and($consent->notice_version)->toBe('v1')
        ->and($consent->includes_tab_audio)->toBeTrue()
        ->and($consent->acknowledged_at)->not->toBeNull();

    expect($this->meeting->fresh()->hasRecordingConsent())->toBeTrue();
});

test('consent requires the acknowledgement checkbox', function () {
    $this->actingAs($this->user)
        ->postJson(route('meetings.recording-consent.store', $this->meeting), [
            'notice_version' => 'v1',
        ])->assertJsonValidationErrorFor('acknowledged');

    expect(RecordingConsent::query()->count())->toBe(0);
});

test('a meeting has no consent until one is recorded', function () {
    expect($this->meeting->hasRecordingConsent())->toBeFalse();
});
