<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\AI\Models\MomExtraction;
use App\Domain\AI\Models\MomTopic;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('mom extraction belongs to meeting', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    $extraction = MomExtraction::factory()->create([
        'minutes_of_meeting_id' => $mom->id,
    ]);

    expect($extraction->minutesOfMeeting->id)->toBe($mom->id);
});

test('mom topic belongs to meeting', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    $topic = MomTopic::factory()->create([
        'minutes_of_meeting_id' => $mom->id,
    ]);

    expect($topic->minutesOfMeeting->id)->toBe($mom->id);
});

test('extraction casts structured_data to array', function () {
    $extraction = MomExtraction::factory()->create([
        'structured_data' => ['key_points' => ['Point 1', 'Point 2']],
    ]);

    $extraction->refresh();

    expect($extraction->structured_data)->toBeArray()
        ->and($extraction->structured_data['key_points'])->toHaveCount(2);
});

test('topic casts related_segments to array', function () {
    $topic = MomTopic::factory()->create([
        'related_segments' => [1, 2, 3],
    ]);

    $topic->refresh();

    expect($topic->related_segments)->toBeArray()
        ->and($topic->related_segments)->toHaveCount(3);
});

test('meeting has many extractions', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    MomExtraction::factory()->count(3)->create([
        'minutes_of_meeting_id' => $mom->id,
    ]);

    expect($mom->extractions)->toHaveCount(3);
});

test('meeting has many topics', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    MomTopic::factory()->count(2)->create([
        'minutes_of_meeting_id' => $mom->id,
    ]);

    expect($mom->topics)->toHaveCount(2);
});

test('extraction casts confidence_score to float', function () {
    $extraction = MomExtraction::factory()->create([
        'confidence_score' => 0.95,
    ]);

    $extraction->refresh();

    expect($extraction->confidence_score)->toBeFloat()
        ->and($extraction->confidence_score)->toBe(0.95);
});
