<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Services\MeetingService;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(MeetingService::class);
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => 'owner']);
    $this->actingAs($this->user);
});

test('can create a meeting', function () {
    $mom = $this->service->create([
        'title' => 'Team Standup',
        'summary' => 'Daily sync',
        'content' => 'Discussion points...',
    ], $this->user);

    expect($mom)->toBeInstanceOf(MinutesOfMeeting::class)
        ->and($mom->title)->toBe('Team Standup')
        ->and($mom->status)->toBe(MeetingStatus::Draft)
        ->and($mom->created_by)->toBe($this->user->id)
        ->and($mom->organization_id)->toBe($this->org->id);
});

test('can update a draft meeting', function () {
    $mom = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $updated = $this->service->update($mom, ['title' => 'Updated Title']);

    expect($updated->title)->toBe('Updated Title');
});

test('cannot edit approved meeting', function () {
    $mom = MinutesOfMeeting::factory()->approved()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    expect(fn () => $this->service->update($mom, ['title' => 'New Title']))
        ->toThrow(DomainException::class, 'Cannot edit an approved meeting.');
});

test('draft can transition to finalized', function () {
    $mom = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $finalized = $this->service->finalize($mom, $this->user);

    expect($finalized->status)->toBe(MeetingStatus::Finalized);
});

test('finalized can transition to approved', function () {
    $mom = MinutesOfMeeting::factory()->finalized()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $approved = $this->service->approve($mom, $this->user);

    expect($approved->status)->toBe(MeetingStatus::Approved);
});

test('approved cannot be finalized again', function () {
    $mom = MinutesOfMeeting::factory()->approved()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    expect(fn () => $this->service->finalize($mom, $this->user))
        ->toThrow(DomainException::class, 'Only draft or in-progress meetings can be finalized.');
});

test('finalized can revert to draft', function () {
    $mom = MinutesOfMeeting::factory()->finalized()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $reverted = $this->service->revertToDraft($mom, $this->user);

    expect($reverted->status)->toBe(MeetingStatus::Draft);
});

test('finalizing creates a version snapshot', function () {
    $mom = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'content' => 'Meeting content here',
    ]);

    $this->service->finalize($mom, $this->user);

    expect($mom->versions)->toHaveCount(1)
        ->and($mom->versions->first()->version_number)->toBe(1)
        ->and($mom->versions->first()->change_summary)->toBe('Meeting finalized')
        ->and($mom->versions->first()->snapshot['content'])->toBe('Meeting content here');
});

test('reverting creates a version snapshot', function () {
    $mom = MinutesOfMeeting::factory()->finalized()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $this->service->revertToDraft($mom, $this->user);

    expect($mom->versions)->toHaveCount(1)
        ->and($mom->versions->first()->change_summary)->toBe('Reverted to draft');
});

test('version numbers increment correctly', function () {
    $mom = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $this->service->finalize($mom, $this->user);

    $mom->update(['status' => MeetingStatus::Draft]);

    $this->service->finalize($mom->fresh(), $this->user);

    $versions = $mom->versions()->orderBy('version_number')->get();

    expect($versions)->toHaveCount(2)
        ->and($versions[0]->version_number)->toBe(1)
        ->and($versions[1]->version_number)->toBe(2);
});

test('can delete a meeting', function () {
    $mom = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $this->service->delete($mom);

    expect(MinutesOfMeeting::find($mom->id))->toBeNull()
        ->and(MinutesOfMeeting::withTrashed()->find($mom->id))->not->toBeNull();
});

test('deleting a meeting soft-deletes its action items', function () {
    $mom = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $item = \App\Domain\ActionItem\Models\ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $mom->id,
        'created_by' => $this->user->id,
    ]);

    $this->service->delete($mom);

    expect(\App\Domain\ActionItem\Models\ActionItem::find($item->id))->toBeNull()
        ->and(\App\Domain\ActionItem\Models\ActionItem::withTrashed()->find($item->id)->trashed())->toBeTrue();
});

test('a circulating meeting can revert to draft', function () {
    $mom = MinutesOfMeeting::factory()->pendingConfirmation()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $reverted = $this->service->revertToDraft($mom, $this->user);

    expect($reverted->status)->toBe(MeetingStatus::Draft);
});

test('reverting a circulating meeting closes its live circulations', function () {
    $mom = MinutesOfMeeting::factory()->pendingConfirmation()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $open = \App\Domain\Meeting\Models\MomCirculation::createForOrganization($this->org->id, [
        'minutes_of_meeting_id' => $mom->id,
        'sent_by' => $this->user->id,
        'round' => 1,
        'subject' => 'Round 1',
        'deadline_at' => now()->addDays(3),
        'status' => 'open',
    ]);

    $held = \App\Domain\Meeting\Models\MomCirculation::createForOrganization($this->org->id, [
        'minutes_of_meeting_id' => $mom->id,
        'sent_by' => $this->user->id,
        'round' => 2,
        'subject' => 'Held for secretary',
        'deadline_at' => now()->subDay(),
        'status' => 'awaiting_secretary',
    ]);

    $this->service->revertToDraft($mom, $this->user);

    expect($open->fresh()->status)->toBe('cancelled')
        ->and($open->fresh()->closed_at)->not->toBeNull()
        ->and($held->fresh()->status)->toBe('cancelled');
});

test('reverting does not reopen circulations already closed', function () {
    $mom = MinutesOfMeeting::factory()->pendingConfirmation()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $settled = \App\Domain\Meeting\Models\MomCirculation::createForOrganization($this->org->id, [
        'minutes_of_meeting_id' => $mom->id,
        'sent_by' => $this->user->id,
        'round' => 1,
        'subject' => 'Superseded',
        'deadline_at' => now()->subWeek(),
        'status' => 'closed_amended',
    ]);

    $this->service->revertToDraft($mom, $this->user);

    expect($settled->fresh()->status)->toBe('closed_amended');
});

test('a draft meeting cannot be reverted', function () {
    $mom = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    expect(fn () => $this->service->revertToDraft($mom, $this->user))
        ->toThrow(DomainException::class);
});

test('an in-progress meeting cannot be reverted', function () {
    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'status' => MeetingStatus::InProgress,
    ]);

    expect(fn () => $this->service->revertToDraft($mom, $this->user))
        ->toThrow(DomainException::class);
});

/*
 * Restoring an old version used to POST at the revert endpoint, which ignored
 * the version entirely — the button read "Restore" but only flipped the status
 * to draft and dropped the version you picked.
 */
test('restoring a version brings back its content', function () {
    $mom = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'title' => 'Original title',
        'summary' => 'Original summary',
        'content' => 'Original content',
    ]);

    $versionService = app(\App\Domain\Meeting\Services\VersionService::class);
    $version = $versionService->createVersion($mom, $this->user, 'Before the rewrite');

    $mom->update([
        'title' => 'Rewritten title',
        'summary' => 'Rewritten summary',
        'content' => 'Rewritten content',
    ]);

    $restored = $versionService->restoreVersion($mom->fresh(), $version, $this->user);

    expect($restored->title)->toBe('Original title')
        ->and($restored->summary)->toBe('Original summary')
        ->and($restored->content)->toBe('Original content');
});

test('restoring keeps the superseded text as a new version', function () {
    $mom = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'content' => 'First draft',
    ]);

    $versionService = app(\App\Domain\Meeting\Services\VersionService::class);
    $version = $versionService->createVersion($mom, $this->user, 'First draft');

    $mom->update(['content' => 'Second draft']);
    $versionService->restoreVersion($mom->fresh(), $version, $this->user);

    $versions = $mom->versions()->orderBy('version_number')->get();

    expect($versions)->toHaveCount(2)
        ->and($versions[1]->change_summary)->toBe("Restored from version {$version->version_number}")
        ->and($versions[1]->snapshot['content'])->toBe('Second draft');
});

test('restoring does not resurrect the snapshot status', function () {
    $mom = MinutesOfMeeting::factory()->finalized()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'content' => 'Finalized text',
    ]);

    $versionService = app(\App\Domain\Meeting\Services\VersionService::class);
    $version = $versionService->createVersion($mom, $this->user, 'While finalized');

    $this->service->revertToDraft($mom, $this->user);

    $restored = $versionService->restoreVersion($mom->fresh(), $version, $this->user);

    expect($restored->status)->toBe(MeetingStatus::Draft)
        ->and($restored->content)->toBe('Finalized text');
});
