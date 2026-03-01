<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Manager->value]);
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);
});

test('authenticated user can add a comment to a meeting', function () {
    $response = $this->actingAs($this->user)->post(route('meetings.comments.store', $this->meeting), [
        'body' => 'This is a test comment.',
    ]);

    $response->assertRedirect(route('meetings.show', $this->meeting));
    $this->assertDatabaseHas('comments', [
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $this->meeting->id,
        'user_id' => $this->user->id,
        'body' => 'This is a test comment.',
    ]);
});

test('authenticated user can reply to a comment', function () {
    $parentComment = Comment::factory()->create([
        'organization_id' => $this->org->id,
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $this->meeting->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->post(route('meetings.comments.store', $this->meeting), [
        'body' => 'This is a reply.',
        'parent_id' => $parentComment->id,
    ]);

    $response->assertRedirect(route('meetings.show', $this->meeting));
    $this->assertDatabaseHas('comments', [
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $this->meeting->id,
        'user_id' => $this->user->id,
        'body' => 'This is a reply.',
        'parent_id' => $parentComment->id,
    ]);
});

test('user can delete their own comment', function () {
    $comment = Comment::factory()->create([
        'organization_id' => $this->org->id,
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $this->meeting->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->delete(route('comments.destroy', $comment));

    $response->assertRedirect();
    $this->assertSoftDeleted('comments', ['id' => $comment->id]);
});

test('user cannot delete another user comment', function () {
    $viewerUser = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($viewerUser, ['role' => UserRole::Viewer->value]);

    $comment = Comment::factory()->create([
        'organization_id' => $this->org->id,
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $this->meeting->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($viewerUser)->delete(route('comments.destroy', $comment));

    $response->assertForbidden();
    $this->assertNotSoftDeleted('comments', ['id' => $comment->id]);
});

test('unauthenticated user cannot post comments', function () {
    $response = $this->post(route('meetings.comments.store', $this->meeting), [
        'body' => 'Unauthorized comment.',
    ]);

    $response->assertRedirect(route('login'));
});
