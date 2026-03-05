<?php

declare(strict_types=1);

use App\Domain\Meeting\Events\MeetingApproved;
use App\Domain\Meeting\Events\MeetingFinalized;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Services\MeetingService;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->meetingService = app(MeetingService::class);
});

it('dispatches MeetingFinalized event on finalize', function () {
    Event::fake([MeetingFinalized::class]);

    $meeting = MinutesOfMeeting::factory()->create([
        'status' => MeetingStatus::Draft,
        'created_by' => $this->user->id,
    ]);

    $this->meetingService->finalize($meeting, $this->user);

    Event::assertDispatched(MeetingFinalized::class, function ($event) use ($meeting) {
        return $event->meeting->id === $meeting->id
            && $event->finalizedBy->id === $this->user->id;
    });
});

it('dispatches MeetingApproved event on approve', function () {
    Event::fake([MeetingApproved::class]);

    $meeting = MinutesOfMeeting::factory()->create([
        'status' => MeetingStatus::Finalized,
        'created_by' => $this->user->id,
    ]);

    $this->meetingService->approve($meeting, $this->user);

    Event::assertDispatched(MeetingApproved::class, function ($event) use ($meeting) {
        return $event->meeting->id === $meeting->id
            && $event->approvedBy->id === $this->user->id;
    });
});
