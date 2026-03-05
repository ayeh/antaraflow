<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Project\Models\Project;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->org = Organization::factory()->create();
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
    $this->user->update(['current_organization_id' => $this->org->id]);
});

it('returns search results grouped by entity type', function () {
    MinutesOfMeeting::factory()->create([
        'title' => 'Budget Review Meeting',
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    Project::factory()->create([
        'name' => 'Budget Project',
        'organization_id' => $this->org->id,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('search', ['q' => 'budget']))
        ->assertOk()
        ->assertJsonStructure(['meetings', 'action_items', 'projects'])
        ->assertJsonPath('meetings.0.title', 'Budget Review Meeting');
});

it('requires minimum 2 characters', function () {
    $this->actingAs($this->user)
        ->getJson(route('search', ['q' => 'a']))
        ->assertUnprocessable();
});

it('scopes results to current organization', function () {
    $otherOrg = Organization::factory()->create();

    MinutesOfMeeting::factory()->create([
        'title' => 'Other Org Meeting',
        'organization_id' => $otherOrg->id,
    ]);

    MinutesOfMeeting::factory()->create([
        'title' => 'My Org Meeting',
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('search', ['q' => 'meeting']))
        ->assertOk();

    $meetings = $response->json('meetings');
    expect($meetings)->toHaveCount(1);
    expect($meetings[0]['title'])->toBe('My Org Meeting');
});

it('requires authentication', function () {
    $this->getJson(route('search', ['q' => 'test']))
        ->assertUnauthorized();
});

it('returns empty results when no matches found', function () {
    $this->actingAs($this->user)
        ->getJson(route('search', ['q' => 'nonexistent']))
        ->assertOk()
        ->assertJsonPath('meetings', [])
        ->assertJsonPath('action_items', [])
        ->assertJsonPath('projects', []);
});

it('rejects queries exceeding 100 characters', function () {
    $longQuery = str_repeat('a', 101);

    $this->actingAs($this->user)
        ->getJson(route('search', ['q' => $longQuery]))
        ->assertUnprocessable();
});
