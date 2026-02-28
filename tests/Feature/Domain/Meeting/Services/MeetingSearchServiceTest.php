<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomTag;
use App\Domain\Meeting\Services\MeetingSearchService;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(MeetingSearchService::class);
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => 'owner']);
    $this->actingAs($this->user);
});

test('can search meetings by title', function () {
    MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'title' => 'Weekly Standup Notes',
        'meeting_date' => now(),
    ]);

    MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'title' => 'Budget Review',
        'meeting_date' => now(),
    ]);

    $results = $this->service->search(['search' => 'Standup']);

    expect($results->total())->toBe(1)
        ->and($results->first()->title)->toBe('Weekly Standup Notes');
});

test('can filter meetings by status', function () {
    MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_date' => now(),
    ]);

    MinutesOfMeeting::factory()->finalized()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_date' => now(),
    ]);

    $results = $this->service->search(['status' => MeetingStatus::Draft->value]);

    expect($results->total())->toBe(1)
        ->and($results->first()->status)->toBe(MeetingStatus::Draft);
});

test('can filter meetings by date range', function () {
    MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_date' => '2026-01-15 10:00:00',
    ]);

    MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_date' => '2026-03-15 10:00:00',
    ]);

    $results = $this->service->search([
        'date_from' => '2026-01-01',
        'date_to' => '2026-02-01',
    ]);

    expect($results->total())->toBe(1);
});

test('can filter meetings by tags', function () {
    $tag = MomTag::factory()->create(['organization_id' => $this->org->id]);

    $taggedMom = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_date' => now(),
    ]);
    $taggedMom->tags()->attach($tag->id);

    MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_date' => now(),
    ]);

    $results = $this->service->search(['tags' => [$tag->id]]);

    expect($results->total())->toBe(1)
        ->and($results->first()->id)->toBe($taggedMom->id);
});

test('search is scoped to organization', function () {
    $otherOrg = Organization::factory()->create();
    $otherUser = User::factory()->create(['current_organization_id' => $otherOrg->id]);

    MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'title' => 'Our Meeting',
        'meeting_date' => now(),
    ]);

    MinutesOfMeeting::factory()->create([
        'organization_id' => $otherOrg->id,
        'created_by' => $otherUser->id,
        'title' => 'Their Meeting',
        'meeting_date' => now(),
    ]);

    $results = $this->service->search();

    expect($results->total())->toBe(1)
        ->and($results->first()->title)->toBe('Our Meeting');
});

test('search returns paginated results', function () {
    MinutesOfMeeting::factory()->count(20)->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_date' => now(),
    ]);

    $results = $this->service->search([], 10);

    expect($results->total())->toBe(20)
        ->and($results->perPage())->toBe(10)
        ->and($results->count())->toBe(10);
});
