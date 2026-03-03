<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Services\MeetingService;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
    $this->service = app()->make(MeetingService::class);
});

it('create() sets status to Draft', function () {
    $meeting = $this->service->create([
        'title' => 'Test Meeting',
        'meeting_date' => now(),
    ], $this->user);

    expect($meeting->status)->toBe(MeetingStatus::Draft);
});

it('create() sets created_by to the given user', function () {
    $meeting = $this->service->create([
        'title' => 'Test Meeting',
        'meeting_date' => now(),
    ], $this->user);

    expect($meeting->created_by)->toBe($this->user->id);
});

it('create() sets organization_id from the user', function () {
    $meeting = $this->service->create([
        'title' => 'Test Meeting',
        'meeting_date' => now(),
    ], $this->user);

    expect($meeting->organization_id)->toBe($this->org->id);
});

it('create() creates a join setting record when join settings are provided', function () {
    $meeting = $this->service->create([
        'title' => 'Test Meeting',
        'meeting_date' => now(),
        'allow_external_join' => false,
        'require_rsvp' => false,
        'auto_notify' => true,
    ], $this->user);

    expect($meeting->joinSetting)->not->toBeNull();
});

it('create() does not create join setting when no join setting keys are provided', function () {
    $meeting = $this->service->create([
        'title' => 'Test Meeting',
        'meeting_date' => now(),
    ], $this->user);

    expect($meeting->joinSetting)->toBeNull();
});

it('update() throws DomainException on approved meeting', function () {
    $meeting = MinutesOfMeeting::factory()->approved()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    expect(fn () => $this->service->update($meeting, ['title' => 'New Title']))
        ->toThrow(\DomainException::class, 'Cannot edit an approved meeting.');
});

it('update() persists changes to a draft meeting', function () {
    $meeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'title' => 'Original Title',
    ]);

    $updated = $this->service->update($meeting, ['title' => 'Updated Title']);

    expect($updated->title)->toBe('Updated Title');
    expect($meeting->fresh()->title)->toBe('Updated Title');
});

it('finalize() transitions Draft to Finalized', function () {
    $meeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $finalized = $this->service->finalize($meeting, $this->user);

    expect($finalized->status)->toBe(MeetingStatus::Finalized);
    expect($meeting->fresh()->status)->toBe(MeetingStatus::Finalized);
});

it('finalize() transitions InProgress to Finalized', function () {
    $meeting = MinutesOfMeeting::factory()->inProgress()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $finalized = $this->service->finalize($meeting, $this->user);

    expect($finalized->status)->toBe(MeetingStatus::Finalized);
});

it('finalize() throws DomainException on already-finalized meeting', function () {
    $meeting = MinutesOfMeeting::factory()->finalized()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    expect(fn () => $this->service->finalize($meeting, $this->user))
        ->toThrow(\DomainException::class, 'Only draft or in-progress meetings can be finalized.');
});

it('finalize() throws DomainException on approved meeting', function () {
    $meeting = MinutesOfMeeting::factory()->approved()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    expect(fn () => $this->service->finalize($meeting, $this->user))
        ->toThrow(\DomainException::class, 'Only draft or in-progress meetings can be finalized.');
});

it('finalize() creates a version snapshot', function () {
    $meeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $this->service->finalize($meeting, $this->user);

    expect($meeting->versions()->count())->toBe(1);
});

it('approve() transitions Finalized to Approved', function () {
    $meeting = MinutesOfMeeting::factory()->finalized()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $approved = $this->service->approve($meeting, $this->user);

    expect($approved->status)->toBe(MeetingStatus::Approved);
    expect($meeting->fresh()->status)->toBe(MeetingStatus::Approved);
});

it('approve() throws DomainException on a non-finalized meeting', function () {
    $meeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    expect(fn () => $this->service->approve($meeting, $this->user))
        ->toThrow(\DomainException::class, 'Only finalized meetings can be approved.');
});

it('revertToDraft() transitions Finalized back to Draft', function () {
    $meeting = MinutesOfMeeting::factory()->finalized()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $reverted = $this->service->revertToDraft($meeting, $this->user);

    expect($reverted->status)->toBe(MeetingStatus::Draft);
    expect($meeting->fresh()->status)->toBe(MeetingStatus::Draft);
});

it('revertToDraft() throws DomainException on a non-finalized meeting', function () {
    $meeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    expect(fn () => $this->service->revertToDraft($meeting, $this->user))
        ->toThrow(\DomainException::class, 'Only finalized meetings can be reverted to draft.');
});

it('revertToDraft() throws DomainException on an approved meeting', function () {
    $meeting = MinutesOfMeeting::factory()->approved()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    expect(fn () => $this->service->revertToDraft($meeting, $this->user))
        ->toThrow(\DomainException::class);

    expect($meeting->fresh()->status)->toBe(MeetingStatus::Approved);
});

it('delete() soft-deletes the meeting', function () {
    $meeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $this->service->delete($meeting);

    expect(MinutesOfMeeting::withoutGlobalScopes()->find($meeting->id)->deleted_at)->not->toBeNull();
});
