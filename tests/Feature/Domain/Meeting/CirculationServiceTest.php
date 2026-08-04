<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomCirculation;
use App\Domain\Meeting\Services\CirculationService;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('circulate creates circulation and recipient records', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $this->actingAs($user);

    $meeting = MinutesOfMeeting::factory()->finalized()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    $service = app(CirculationService::class);

    $recipients = [
        ['name' => 'Nor Khamisah', 'email' => 'khamisah@example.com'],
        ['name' => 'Irfan Hakim', 'email' => 'irfan@example.com'],
    ];

    $circulation = $service->circulate(
        meeting: $meeting,
        sentBy: $user,
        recipients: $recipients,
        subject: 'Sila sahkan minit mesyuarat',
        deadline: now()->addDays(3),
    );

    expect($circulation)->toBeInstanceOf(MomCirculation::class);
    expect($circulation->status)->toBe('open');
    expect($circulation->round)->toBe(1);
    expect($circulation->recipients)->toHaveCount(2);
    expect($meeting->fresh()->status)->toBe(MeetingStatus::PendingConfirmation);
});

test('each recipient gets a unique token', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $this->actingAs($user);

    $meeting = MinutesOfMeeting::factory()->finalized()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    $service = app(CirculationService::class);

    $recipients = [
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        ['name' => 'Bob', 'email' => 'bob@example.com'],
    ];

    $circulation = $service->circulate(
        meeting: $meeting,
        sentBy: $user,
        recipients: $recipients,
        subject: 'Test',
        deadline: now()->addDays(3),
    );

    $tokens = $circulation->recipients->pluck('token');
    expect($tokens->unique())->toHaveCount(2);
    expect($tokens->first())->toHaveLength(64);
});
