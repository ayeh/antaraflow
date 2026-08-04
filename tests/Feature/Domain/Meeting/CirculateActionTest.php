<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomCirculation;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('secretary can circulate a finalized meeting', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $org->members()->attach($user, ['role' => UserRole::Owner->value]);

    $meeting = MinutesOfMeeting::factory()->finalized()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->post(route('meetings.circulate', $meeting), [
        'subject' => 'Sila sahkan minit mesyuarat',
        'deadline' => now()->addDays(3)->toDateString(),
        'recipients' => [
            ['name' => 'Alice', 'email' => 'alice@example.com'],
            ['name' => 'Bob', 'email' => 'bob@example.com'],
        ],
    ]);

    $response->assertRedirect();
    expect(MomCirculation::count())->toBe(1);
    expect($meeting->fresh()->status)->toBe(MeetingStatus::PendingConfirmation);
});

test('circulate requires at least one recipient', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $org->members()->attach($user, ['role' => UserRole::Owner->value]);

    $meeting = MinutesOfMeeting::factory()->finalized()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->post(route('meetings.circulate', $meeting), [
        'subject' => 'Test',
        'deadline' => now()->addDays(3)->toDateString(),
        'recipients' => [],
    ]);

    $response->assertSessionHasErrors('recipients');
});
