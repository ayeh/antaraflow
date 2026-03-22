<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Member->value]);
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'title' => 'Board Meeting',
        'mom_number' => 'MOM-001',
    ]);
});

test('authenticated user can download meeting as json', function () {
    $response = $this->actingAs($this->user)->get(route('meetings.export.json', $this->meeting));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/json');
});

test('json export response filename contains meeting number', function () {
    $response = $this->actingAs($this->user)->get(route('meetings.export.json', $this->meeting));

    $response->assertSuccessful();
    $contentDisposition = $response->headers->get('content-disposition');
    expect($contentDisposition)->toContain('MOM-001');
});

test('json export contains expected meeting fields', function () {
    $response = $this->actingAs($this->user)->get(route('meetings.export.json', $this->meeting));

    $response->assertSuccessful();
    $data = json_decode($response->getContent(), true);

    expect($data)->toHaveKeys(['id', 'mom_number', 'title', 'meeting_date', 'location', 'status', 'attendees', 'topics', 'action_items', 'decisions', 'summary', 'exported_at']);
    expect($data['title'])->toBe('Board Meeting');
    expect($data['mom_number'])->toBe('MOM-001');
});

test('json export contains attendees, topics, action items, and decisions arrays', function () {
    $response = $this->actingAs($this->user)->get(route('meetings.export.json', $this->meeting));

    $response->assertSuccessful();
    $data = json_decode($response->getContent(), true);

    expect($data['attendees'])->toBeArray();
    expect($data['topics'])->toBeArray();
    expect($data['action_items'])->toBeArray();
    expect($data['decisions'])->toBeArray();
});

test('cross org user cannot access json export', function () {
    $otherOrg = Organization::factory()->create();
    $otherUser = User::factory()->create(['current_organization_id' => $otherOrg->id]);
    $otherOrg->members()->attach($otherUser, ['role' => UserRole::Member->value]);

    $response = $this->actingAs($otherUser)->get(route('meetings.export.json', $this->meeting));

    $response->assertNotFound();
});

test('unauthenticated user cannot download json export', function () {
    $response = $this->get(route('meetings.export.json', $this->meeting));

    $response->assertRedirect(route('login'));
});
