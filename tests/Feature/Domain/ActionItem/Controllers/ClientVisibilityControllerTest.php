<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);
});

it('can toggle action item client visibility to true', function (): void {
    $item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'client_visible' => false,
    ]);

    $this->actingAs($this->user)
        ->patchJson(route('action-items.toggle-visibility', $item))
        ->assertOk()
        ->assertJson(['client_visible' => true]);

    expect($item->fresh()->client_visible)->toBeTrue();
});

it('toggles action item client visibility back to false on second call', function (): void {
    $item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'client_visible' => true,
    ]);

    $this->actingAs($this->user)
        ->patchJson(route('action-items.toggle-visibility', $item))
        ->assertOk()
        ->assertJson(['client_visible' => false]);

    expect($item->fresh()->client_visible)->toBeFalse();
});

it('returns 401 for unauthenticated action item visibility toggle', function (): void {
    $item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
    ]);

    $this->patchJson(route('action-items.toggle-visibility', $item))
        ->assertUnauthorized();
});

it('can toggle comment client visibility to true', function (): void {
    $comment = Comment::factory()->create([
        'organization_id' => $this->org->id,
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $this->meeting->id,
        'user_id' => $this->user->id,
        'client_visible' => false,
    ]);

    $this->actingAs($this->user)
        ->patchJson(route('comments.toggle-visibility', $comment))
        ->assertOk()
        ->assertJson(['client_visible' => true]);

    expect($comment->fresh()->client_visible)->toBeTrue();
});

it('toggles comment client visibility back to false on second call', function (): void {
    $comment = Comment::factory()->create([
        'organization_id' => $this->org->id,
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $this->meeting->id,
        'user_id' => $this->user->id,
        'client_visible' => true,
    ]);

    $this->actingAs($this->user)
        ->patchJson(route('comments.toggle-visibility', $comment))
        ->assertOk()
        ->assertJson(['client_visible' => false]);

    expect($comment->fresh()->client_visible)->toBeFalse();
});

it('returns 404 when toggling comment visibility from a different organization', function (): void {
    $otherOrg = Organization::factory()->create();
    $comment = Comment::factory()->create([
        'organization_id' => $otherOrg->id,
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $this->meeting->id,
        'user_id' => $this->user->id,
        'client_visible' => false,
    ]);

    // The org-scoped model binding returns 404 for records belonging to other orgs,
    // which prevents org enumeration attacks.
    $this->actingAs($this->user)
        ->patchJson(route('comments.toggle-visibility', $comment))
        ->assertNotFound();
});

it('returns 401 for unauthenticated comment visibility toggle', function (): void {
    $comment = Comment::factory()->create([
        'organization_id' => $this->org->id,
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $this->meeting->id,
        'user_id' => $this->user->id,
    ]);

    $this->patchJson(route('comments.toggle-visibility', $comment))
        ->assertUnauthorized();
});
