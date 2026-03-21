<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Analytics\Models\AnalyticsEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create an analytics event', function (): void {
    $org = Organization::factory()->create();

    $event = AnalyticsEvent::create([
        'organization_id' => $org->id,
        'event_type' => 'meeting.viewed',
        'occurred_at' => now(),
    ]);

    expect($event->event_type)->toBe('meeting.viewed');
    expect(AnalyticsEvent::count())->toBe(1);
});
