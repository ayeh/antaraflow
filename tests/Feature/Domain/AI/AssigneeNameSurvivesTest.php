<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\AI\Models\MomExtraction;
use App\Domain\AI\Services\ExtractionService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create([
        'name' => 'Nurul Aina',
        'current_organization_id' => $this->org->id,
    ]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);

    $this->meeting = MinutesOfMeeting::createForOrganization($this->org->id, [
        'title' => 'Mesyuarat Jawatankuasa',
        'created_by' => $this->user->id,
    ]);
});

function extractionWith(MinutesOfMeeting $meeting, array $items): void
{
    MomExtraction::query()->create([
        'minutes_of_meeting_id' => $meeting->id,
        'type' => 'action_items',
        'content' => 'text form, not used by this path',
        'structured_data' => $items,
        'provider' => 'test',
        'model' => 'test',
    ]);
}

test('an agency named in the minutes is kept, not thrown away', function () {
    extractionWith($this->meeting, [
        ['title' => 'Laksanakan projek AKB', 'assignee' => 'MBPJ', 'priority' => 'high'],
    ]);

    app(ExtractionService::class)->createActionItemRecords($this->meeting, $this->user);

    $item = ActionItem::query()->sole();

    expect($item->assignee_name)->toBe('MBPJ')
        // Still nobody's personal task — MBPJ has no account.
        ->and($item->assigned_to)->toBeNull();
});

test('a colleague in the room is still linked, and named', function () {
    $this->meeting->attendees()->create([
        'user_id' => $this->user->id,
        'name' => $this->user->name,
        'email' => $this->user->email,
    ]);

    extractionWith($this->meeting, [
        ['title' => 'Draft the paper', 'assignee' => 'Nurul Aina', 'priority' => 'medium'],
    ]);

    app(ExtractionService::class)->createActionItemRecords($this->meeting, $this->user);

    $item = ActionItem::query()->sole();

    expect($item->assigned_to)->toBe($this->user->id)
        // Both: the link drives "my tasks", the name is what the minutes said.
        ->and($item->assignee_name)->toBe('Nurul Aina');
});

test('minutes that named nobody leave it empty rather than inventing one', function () {
    extractionWith($this->meeting, [
        ['title' => 'Someone should look into this', 'priority' => 'low'],
    ]);

    app(ExtractionService::class)->createActionItemRecords($this->meeting, $this->user);

    expect(ActionItem::query()->sole()->assignee_name)->toBeNull();
});

test('a blank assignee is treated as none', function () {
    extractionWith($this->meeting, [
        ['title' => 'Padded', 'assignee' => '   ', 'priority' => 'low'],
    ]);

    app(ExtractionService::class)->createActionItemRecords($this->meeting, $this->user);

    expect(ActionItem::query()->sole()->assignee_name)->toBeNull();
});

test('markup in an extracted name does not reach the record', function () {
    extractionWith($this->meeting, [
        ['title' => 'Tagged', 'assignee' => '<b>MBPJ</b>', 'priority' => 'low'],
    ]);

    app(ExtractionService::class)->createActionItemRecords($this->meeting, $this->user);

    expect(ActionItem::query()->sole()->assignee_name)->toBe('MBPJ');
});

test('the app is told the name', function () {
    extractionWith($this->meeting, [
        ['title' => 'Laksanakan projek AKB', 'assignee' => 'MBPJ', 'priority' => 'high'],
    ]);

    app(ExtractionService::class)->createActionItemRecords($this->meeting, $this->user);

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/mobile/v1/action-items?unassigned=1')
        ->assertOk()
        ->assertJsonPath('data.0.assignee_name', 'MBPJ');
});

test('a model saying it found nobody is not stored as a name', function () {
    extractionWith($this->meeting, [
        ['title' => 'One', 'assignee' => 'Tidak dinyatakan'],
        ['title' => 'Two', 'assignee' => 'Not specified'],
        ['title' => 'Three', 'assignee' => 'N/A'],
        ['title' => 'Four', 'assignee' => '-'],
        ['title' => 'Five', 'assignee' => 'TBD'],
        ['title' => 'Six', 'assignee' => 'tiada.'],
    ]);

    app(ExtractionService::class)->createActionItemRecords($this->meeting, $this->user);

    expect(ActionItem::query()->whereNotNull('assignee_name')->count())->toBe(0);
});

test('a real name that merely resembles one of them is kept', function () {
    extractionWith($this->meeting, [
        ['title' => 'Keep this', 'assignee' => 'Nadia'],
        ['title' => 'And this', 'assignee' => 'Unassigned Holdings Sdn Bhd'],
    ]);

    app(ExtractionService::class)->createActionItemRecords($this->meeting, $this->user);

    expect(ActionItem::query()->pluck('assignee_name')->filter()->values()->all())
        ->toBe(['Nadia', 'Unassigned Holdings Sdn Bhd']);
});
