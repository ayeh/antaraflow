<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MeetingSeries;
use App\Domain\Meeting\Models\MeetingTemplate;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomTag;
use App\Domain\Meeting\Models\MomVersion;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('minutes of meeting can be created with factory', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    expect($mom)->toBeInstanceOf(MinutesOfMeeting::class)
        ->and($mom->title)->toBeString()
        ->and($mom->status)->toBe(MeetingStatus::Draft);
});

test('minutes of meeting belongs to organization', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    expect($mom->organization->id)->toBe($org->id);
});

test('minutes of meeting has many versions', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    MomVersion::factory()->create([
        'minutes_of_meeting_id' => $mom->id,
        'created_by' => $user->id,
        'version_number' => 1,
    ]);

    MomVersion::factory()->create([
        'minutes_of_meeting_id' => $mom->id,
        'created_by' => $user->id,
        'version_number' => 2,
    ]);

    expect($mom->versions)->toHaveCount(2);
});

test('minutes of meeting belongs to series', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $series = MeetingSeries::factory()->create(['organization_id' => $org->id]);

    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
        'meeting_series_id' => $series->id,
    ]);

    expect($mom->series->id)->toBe($series->id);
});

test('minutes of meeting has many tags', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    $tag1 = MomTag::factory()->create(['organization_id' => $org->id]);
    $tag2 = MomTag::factory()->create(['organization_id' => $org->id]);

    $mom->tags()->attach([$tag1->id, $tag2->id]);

    expect($mom->tags)->toHaveCount(2);
});

test('meeting series has many meetings', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $series = MeetingSeries::factory()->create(['organization_id' => $org->id]);

    MinutesOfMeeting::factory()->count(3)->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
        'meeting_series_id' => $series->id,
    ]);

    $this->actingAs($user);

    expect($series->meetings)->toHaveCount(3);
});

test('mom version belongs to meeting', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    $version = MomVersion::factory()->create([
        'minutes_of_meeting_id' => $mom->id,
        'created_by' => $user->id,
    ]);

    expect($version->minutesOfMeeting->id)->toBe($mom->id);
});

test('meeting template belongs to creator', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $template = MeetingTemplate::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    expect($template->createdBy->id)->toBe($user->id);
});
