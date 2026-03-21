<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Analytics\Models\AnalyticsEvent;
use App\Domain\Analytics\Services\AnalyticsEventService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('tracks an analytics event', function (): void {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->for($org)->create();

    AnalyticsEventService::track('meeting.viewed', $meeting, $user);

    expect(AnalyticsEvent::where('event_type', 'meeting.viewed')->count())->toBe(1);
});

it('tracks event with properties', function (): void {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->for($org)->create();

    AnalyticsEventService::track('export.downloaded', $meeting, $user, ['format' => 'pdf']);

    $event = AnalyticsEvent::where('event_type', 'export.downloaded')->first();
    expect($event->properties)->toBe(['format' => 'pdf']);
});
