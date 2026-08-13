<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\AI\Jobs\ExtractMeetingDataJob;
use App\Domain\AI\Models\MomExtraction;
use App\Domain\AI\Services\ExtractionService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);

    $this->meeting = MinutesOfMeeting::createForOrganization($this->org->id, [
        'title' => 'Board meeting',
        'created_by' => $this->user->id,
    ]);
});

/** extractAll reaches an AI provider, so the extraction it would have written is placed directly. */
function placeExtraction(MinutesOfMeeting $meeting, array $items): void
{
    MomExtraction::query()->create([
        'minutes_of_meeting_id' => $meeting->id,
        'type' => 'action_items',
        'content' => 'irrelevant to this path',
        'structured_data' => $items,
        'provider' => 'test',
        'model' => 'test',
    ]);
}

test('a recording that produced action items puts them on the Tasks tab', function () {
    placeExtraction($this->meeting, [
        ['title' => 'Laksanakan projek AKB', 'description' => 'At MBPJ', 'priority' => 'high'],
        ['title' => 'Perkukuh mitigasi banjir', 'description' => 'Hotspots', 'priority' => 'medium'],
    ]);

    // What the job does after extraction, which until now it did not.
    app(ExtractionService::class)->createActionItemRecords($this->meeting, $this->user);

    $items = ActionItem::query()->where('minutes_of_meeting_id', $this->meeting->id)->get();

    expect($items)->toHaveCount(2)
        ->and($items->pluck('title')->all())->toContain('Laksanakan projek AKB')
        ->and($items->first()->metadata['ai_generated'])->toBeTrue();
});

test('the job itself does both halves, not just the extraction', function () {
    placeExtraction($this->meeting, [
        ['title' => 'Follow up on the audit', 'priority' => 'high'],
    ]);

    // extractAll returns early on an empty transcript, so the job runs through
    // to the part being tested without reaching a provider.
    (new ExtractMeetingDataJob($this->meeting))->handle(app(ExtractionService::class));

    expect(ActionItem::query()->where('minutes_of_meeting_id', $this->meeting->id)->count())
        ->toBe(1);
});

test('running twice does not double the list', function () {
    placeExtraction($this->meeting, [['title' => 'Only once', 'priority' => 'low']]);

    $service = app(ExtractionService::class);
    $service->createActionItemRecords($this->meeting, $this->user);
    $service->createActionItemRecords($this->meeting, $this->user);

    expect(ActionItem::query()->where('minutes_of_meeting_id', $this->meeting->id)->count())
        ->toBe(1);
});

test('an item a person typed themselves is not swept away by a re-run', function () {
    ActionItem::createForOrganization($this->org->id, [
        'minutes_of_meeting_id' => $this->meeting->id,
        'title' => 'Written by hand',
        'created_by' => $this->user->id,
        'status' => 'open',
    ]);

    placeExtraction($this->meeting, [['title' => 'From the recording', 'priority' => 'low']]);
    app(ExtractionService::class)->createActionItemRecords($this->meeting, $this->user);

    expect(ActionItem::query()->where('minutes_of_meeting_id', $this->meeting->id)->pluck('title')->all())
        ->toContain('Written by hand')
        ->toContain('From the recording');
});
