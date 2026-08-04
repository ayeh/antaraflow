<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Services\MeetingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(MeetingService::class);
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => 'owner']);
    $this->actingAs($this->user);
});

test('meeting cannot be updated while pending confirmation', function () {
    $mom = MinutesOfMeeting::factory()->pendingConfirmation()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    expect(fn () => $this->service->update($mom, ['title' => 'New Title']))
        ->toThrow(DomainException::class, 'Cannot edit a meeting that is pending confirmation.');
});

test('meeting can be updated when finalized', function () {
    $mom = MinutesOfMeeting::factory()->finalized()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $updated = $this->service->update($mom, ['title' => 'New Title']);

    expect($updated->title)->toBe('New Title');
});
