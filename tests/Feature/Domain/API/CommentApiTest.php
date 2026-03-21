<?php

declare(strict_types=1);

use App\Domain\Account\Models\ApiKey;
use App\Domain\Account\Models\Organization;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
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
    $this->meeting = MinutesOfMeeting::factory()->create(['organization_id' => $this->org->id]);
});

it('GET /api/v1/meetings/{meeting}/comments returns comments for the meeting', function (): void {
    Comment::factory()->count(3)->create([
        'organization_id' => $this->org->id,
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $this->meeting->id,
    ]);

    $this->getJson("/api/v1/meetings/{$this->meeting->id}/comments", $this->headers)
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('GET /api/v1/meetings/{meeting}/comments returns 404 for meeting in different org', function (): void {
    $otherMeeting = MinutesOfMeeting::factory()->create(); // different org

    $this->getJson("/api/v1/meetings/{$otherMeeting->id}/comments", $this->headers)
        ->assertNotFound();
});

it('GET /api/v1/meetings/{meeting}/comments requires auth', function (): void {
    $this->getJson("/api/v1/meetings/{$this->meeting->id}/comments")
        ->assertUnauthorized();
});

it('GET /api/v1/meetings/{meeting}/comments returns correct structure', function (): void {
    Comment::factory()->create([
        'organization_id' => $this->org->id,
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $this->meeting->id,
    ]);

    $this->getJson("/api/v1/meetings/{$this->meeting->id}/comments", $this->headers)
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'body', 'user', 'commentable_type', 'commentable_id', 'created_at'],
            ],
        ]);
});

it('GET /api/v1/meetings/{meeting}/comments does not return comments from other meetings', function (): void {
    Comment::factory()->count(2)->create([
        'organization_id' => $this->org->id,
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $this->meeting->id,
    ]);
    $otherMeeting = MinutesOfMeeting::factory()->create(['organization_id' => $this->org->id]);
    Comment::factory()->count(3)->create([
        'organization_id' => $this->org->id,
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $otherMeeting->id,
    ]);

    $this->getJson("/api/v1/meetings/{$this->meeting->id}/comments", $this->headers)
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('POST /api/v1/meetings/{meeting}/comments creates a comment', function (): void {
    $this->postJson("/api/v1/meetings/{$this->meeting->id}/comments", [
        'body' => 'Test comment from API',
    ], $this->headers)
        ->assertCreated()
        ->assertJsonPath('body', 'Test comment from API');

    $this->assertDatabaseHas('comments', [
        'body' => 'Test comment from API',
        'organization_id' => $this->org->id,
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $this->meeting->id,
    ]);
});

it('POST /api/v1/meetings/{meeting}/comments validates required body', function (): void {
    $this->postJson("/api/v1/meetings/{$this->meeting->id}/comments", [], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body']);
});

it('POST /api/v1/meetings/{meeting}/comments returns 404 for meeting in different org', function (): void {
    $otherMeeting = MinutesOfMeeting::factory()->create(); // different org

    $this->postJson("/api/v1/meetings/{$otherMeeting->id}/comments", [
        'body' => 'Hack',
    ], $this->headers)
        ->assertNotFound();
});

it('PUT /api/v1/comments/{comment} updates a comment', function (): void {
    $comment = Comment::factory()->create([
        'organization_id' => $this->org->id,
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $this->meeting->id,
    ]);

    $this->putJson("/api/v1/comments/{$comment->id}", [
        'body' => 'Updated body',
    ], $this->headers)
        ->assertOk()
        ->assertJsonPath('body', 'Updated body');
});

it('PUT /api/v1/comments/{comment} returns 404 for comment in different org', function (): void {
    $comment = Comment::factory()->create(); // different org

    $this->putJson("/api/v1/comments/{$comment->id}", [
        'body' => 'Hack',
    ], $this->headers)
        ->assertNotFound();
});

it('DELETE /api/v1/comments/{comment} deletes a comment', function (): void {
    $comment = Comment::factory()->create([
        'organization_id' => $this->org->id,
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $this->meeting->id,
    ]);

    $this->deleteJson("/api/v1/comments/{$comment->id}", [], $this->headers)
        ->assertNoContent();

    $this->assertSoftDeleted('comments', ['id' => $comment->id]);
});

it('DELETE /api/v1/comments/{comment} returns 404 for comment in different org', function (): void {
    $comment = Comment::factory()->create(); // different org

    $this->deleteJson("/api/v1/comments/{$comment->id}", [], $this->headers)
        ->assertNotFound();
});
