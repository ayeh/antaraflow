<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\AI\Models\MomTopic;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user->id, ['role' => UserRole::Owner->value]);
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'title' => 'Q1 Review',
    ]);
});

it('can duplicate a meeting', function (): void {
    $this->actingAs($this->user)
        ->post(route('meetings.duplicate', $this->meeting))
        ->assertRedirect();

    expect(MinutesOfMeeting::where('organization_id', $this->org->id)->count())->toBe(2);

    $duplicate = MinutesOfMeeting::where('title', 'like', '%Copy%')->first();
    expect($duplicate)->not->toBeNull();
    expect($duplicate->title)->toBe('Q1 Review (Copy)');
});

it('duplicate is set to draft status', function (): void {
    $this->actingAs($this->user)
        ->post(route('meetings.duplicate', $this->meeting));

    $duplicate = MinutesOfMeeting::where('title', 'like', '%Copy%')->first();
    expect($duplicate)->not->toBeNull();
    expect($duplicate->status)->toBe(MeetingStatus::Draft);
});

it('duplicate has today as meeting date', function (): void {
    $this->actingAs($this->user)
        ->post(route('meetings.duplicate', $this->meeting));

    $duplicate = MinutesOfMeeting::where('title', 'like', '%Copy%')->first();
    expect($duplicate)->not->toBeNull();
    expect($duplicate->meeting_date->toDateString())->toBe(today()->toDateString());
});

it('copies attendees to the duplicate', function (): void {
    MomAttendee::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $this->actingAs($this->user)
        ->post(route('meetings.duplicate', $this->meeting));

    $duplicate = MinutesOfMeeting::where('title', 'like', '%Copy%')->first();
    expect($duplicate->attendees()->where('name', 'John Doe')->exists())->toBeTrue();
});

it('copies topics to the duplicate', function (): void {
    MomTopic::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'title' => 'Budget Review',
    ]);

    $this->actingAs($this->user)
        ->post(route('meetings.duplicate', $this->meeting));

    $duplicate = MinutesOfMeeting::where('title', 'like', '%Copy%')->first();
    expect($duplicate->topics()->where('title', 'Budget Review')->exists())->toBeTrue();
});

it('cannot duplicate a meeting from another organization', function (): void {
    $otherOrg = Organization::factory()->create();
    $otherMeeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $otherOrg->id,
    ]);

    $this->actingAs($this->user)
        ->post(route('meetings.duplicate', $otherMeeting))
        ->assertNotFound();
});
