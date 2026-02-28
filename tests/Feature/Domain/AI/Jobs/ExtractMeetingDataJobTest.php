<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\AI\Events\ExtractionCompleted;
use App\Domain\AI\Events\ExtractionFailed;
use App\Domain\AI\Jobs\ExtractMeetingDataJob;
use App\Domain\AI\Services\ExtractionService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('job calls extraction service and dispatches completed event', function () {
    Event::fake();

    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    $mockService = Mockery::mock(ExtractionService::class);
    $mockService->shouldReceive('extractAll')
        ->once()
        ->with(Mockery::on(fn ($m) => $m->id === $mom->id));

    $job = new ExtractMeetingDataJob($mom);
    $job->handle($mockService);

    Event::assertDispatched(ExtractionCompleted::class, function ($event) use ($mom) {
        return $event->meeting->id === $mom->id;
    });
});

test('job dispatches failed event on error', function () {
    Event::fake();

    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    $job = new ExtractMeetingDataJob($mom);
    $job->failed(new RuntimeException('Provider unavailable'));

    Event::assertDispatched(ExtractionFailed::class, function ($event) use ($mom) {
        return $event->meeting->id === $mom->id
            && $event->error === 'Provider unavailable';
    });
});
