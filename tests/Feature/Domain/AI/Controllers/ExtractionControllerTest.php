<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\AI\Jobs\ExtractMeetingDataJob;
use App\Domain\AI\Models\MomExtraction;
use App\Domain\AI\Models\MomTopic;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);
});

test('user can trigger extraction', function () {
    Queue::fake();

    $response = $this->actingAs($this->user)
        ->post(route('meetings.extract', $this->meeting));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'AI extraction started.');

    Queue::assertPushed(ExtractMeetingDataJob::class, function ($job) {
        return $job->meeting->id === $this->meeting->id;
    });
});

test('user can view extractions', function () {
    MomExtraction::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'type' => 'summary',
    ]);

    MomTopic::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('meetings.extractions.index', $this->meeting));

    $response->assertSuccessful();
    $response->assertViewHas('extractions');
    $response->assertViewHas('topics');
});

test('guest cannot trigger extraction', function () {
    $response = $this->post(route('meetings.extract', $this->meeting));

    $response->assertRedirect(route('login'));
});

test('guest cannot view extractions', function () {
    $response = $this->get(route('meetings.extractions.index', $this->meeting));

    $response->assertRedirect(route('login'));
});
