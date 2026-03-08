<?php

declare(strict_types=1);

use App\Domain\AI\Models\MeetingPrepBrief;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('belongs to a meeting', function () {
    $brief = MeetingPrepBrief::factory()->create();
    expect($brief->meeting)->toBeInstanceOf(MinutesOfMeeting::class);
});

it('belongs to an attendee', function () {
    $brief = MeetingPrepBrief::factory()->create();
    expect($brief->attendee)->toBeInstanceOf(MomAttendee::class);
});

it('optionally belongs to a user', function () {
    $user = User::factory()->create();
    $brief = MeetingPrepBrief::factory()->create(['user_id' => $user->id]);
    expect($brief->user)->toBeInstanceOf(User::class);
});

it('casts content and summary_highlights as arrays', function () {
    $brief = MeetingPrepBrief::factory()->create([
        'content' => ['executive_summary' => 'Test summary'],
        'summary_highlights' => ['highlight 1', 'highlight 2'],
    ]);
    $fresh = $brief->fresh();
    expect($fresh->content)->toBeArray()
        ->and($fresh->content['executive_summary'])->toBe('Test summary')
        ->and($fresh->summary_highlights)->toBeArray()
        ->and($fresh->summary_highlights)->toHaveCount(2);
});

it('casts date fields as datetime', function () {
    $brief = MeetingPrepBrief::factory()->create([
        'generated_at' => now(),
        'viewed_at' => now(),
        'email_sent_at' => now(),
    ]);
    expect($brief->generated_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($brief->viewed_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($brief->email_sent_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('marks as viewed', function () {
    $brief = MeetingPrepBrief::factory()->create(['viewed_at' => null]);
    $brief->markAsViewed();
    expect($brief->fresh()->viewed_at)->not->toBeNull();
});

it('tracks sections read', function () {
    $brief = MeetingPrepBrief::factory()->create(['sections_read' => null]);
    $brief->markSectionRead('executive_summary');
    $brief->markSectionRead('action_items');
    $fresh = $brief->fresh();
    expect($fresh->sections_read)->toContain('executive_summary')
        ->and($fresh->sections_read)->toContain('action_items');
});
