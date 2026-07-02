<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\AI\Jobs\GenerateProactiveInsightsJob;
use App\Domain\AI\Models\ProactiveInsight;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('generates insights without an authenticated user, as when run from a queue worker', function () {
    $org = Organization::factory()->create();
    $meeting = MinutesOfMeeting::factory()->create(['organization_id' => $org->id]);
    $assignee = User::factory()->create(['current_organization_id' => $org->id]);

    ActionItem::factory()->overdue()->count(3)->create([
        'organization_id' => $org->id,
        'minutes_of_meeting_id' => $meeting->id,
        'assigned_to' => $assignee->id,
    ]);

    expect(auth()->check())->toBeFalse();

    app(GenerateProactiveInsightsJob::class)->handle(app(\App\Domain\AI\Services\MemoAdvisorService::class));

    $insight = ProactiveInsight::where('type', 'overdue_pattern')->first();

    expect($insight)->not->toBeNull()
        ->and($insight->organization_id)->toBe($org->id);
});
