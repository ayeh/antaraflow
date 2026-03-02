<?php

declare(strict_types=1);

use App\Domain\Account\Models\ApiKey;
use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->orgOwner = User::factory()->create();
    $this->org->members()->attach($this->orgOwner->id, ['role' => 'owner']);

    $rawToken = 'test-api-key-'.uniqid();
    $this->apiKey = ApiKey::factory()->create([
        'organization_id' => $this->org->id,
        'secret_hash' => hash('sha256', $rawToken),
        'is_active' => true,
        'expires_at' => null,
    ]);
    $this->headers = ['Authorization' => 'Bearer '.$rawToken];
});

it('GET /api/v1/meetings returns meetings for the organization', function () {
    MinutesOfMeeting::factory()->count(3)->create(['organization_id' => $this->org->id]);
    MinutesOfMeeting::factory()->create(); // different org

    $response = $this->getJson('/api/v1/meetings', $this->headers);

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('GET /api/v1/meetings/{id} returns the meeting with correct structure', function () {
    $meeting = MinutesOfMeeting::factory()->create(['organization_id' => $this->org->id]);

    $response = $this->getJson("/api/v1/meetings/{$meeting->id}", $this->headers);

    $response->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('id', $meeting->id)
            ->where('title', $meeting->title)
            ->where('status', $meeting->status->value)
            ->etc()
        )
        ->assertJsonStructure([
            'id', 'title', 'meeting_date', 'location', 'duration_minutes',
            'status', 'summary', 'content', 'created_by', 'created_at', 'updated_at',
        ])
        ->assertJsonMissingPath('organization_id');
});

it('GET /api/v1/meetings/{id} returns 404 for meeting in different org', function () {
    $otherMeeting = MinutesOfMeeting::factory()->create(); // different org

    $this->getJson("/api/v1/meetings/{$otherMeeting->id}", $this->headers)
        ->assertNotFound();
});

it('GET /api/v1/meetings requires auth', function () {
    $this->getJson('/api/v1/meetings')
        ->assertUnauthorized();
});

it('GET /api/v1/meetings response includes pagination meta', function () {
    MinutesOfMeeting::factory()->count(2)->create(['organization_id' => $this->org->id]);

    $response = $this->getJson('/api/v1/meetings', $this->headers);

    $response->assertOk()
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonStructure(['meta' => ['current_page', 'last_page', 'total']]);
});

it('POST /api/v1/meetings creates a meeting', function () {
    $response = $this->postJson('/api/v1/meetings', [
        'title' => 'API Created Meeting',
        'meeting_date' => '2026-03-15',
    ], $this->headers);

    $response->assertCreated()
        ->assertJsonPath('title', 'API Created Meeting')
        ->assertJsonPath('status', 'draft');

    $this->assertDatabaseHas('minutes_of_meetings', [
        'title' => 'API Created Meeting',
        'organization_id' => $this->org->id,
    ]);
});

it('POST /api/v1/meetings validates required fields', function () {
    $this->postJson('/api/v1/meetings', [], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

it('PATCH /api/v1/meetings/{id} updates a meeting', function () {
    $meeting = MinutesOfMeeting::factory()->create(['organization_id' => $this->org->id]);

    $this->patchJson("/api/v1/meetings/{$meeting->id}", [
        'title' => 'Updated Title',
    ], $this->headers)
        ->assertOk()
        ->assertJsonPath('title', 'Updated Title');
});

it('PATCH /api/v1/meetings/{id} returns 404 for wrong org', function () {
    $otherMeeting = MinutesOfMeeting::factory()->create();

    $this->patchJson("/api/v1/meetings/{$otherMeeting->id}", [
        'title' => 'Hack',
    ], $this->headers)
        ->assertNotFound();
});

it('DELETE /api/v1/meetings/{id} deletes a meeting', function () {
    $meeting = MinutesOfMeeting::factory()->create(['organization_id' => $this->org->id]);

    $this->deleteJson("/api/v1/meetings/{$meeting->id}", [], $this->headers)
        ->assertNoContent();

    $this->assertSoftDeleted('minutes_of_meetings', ['id' => $meeting->id]);
});

it('DELETE /api/v1/meetings/{id} returns 404 for wrong org', function () {
    $otherMeeting = MinutesOfMeeting::factory()->create();

    $this->deleteJson("/api/v1/meetings/{$otherMeeting->id}", [], $this->headers)
        ->assertNotFound();
});
