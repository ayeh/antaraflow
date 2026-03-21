<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Models\MomReaction;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user->id, ['role' => 'member']);
    $this->meeting = MinutesOfMeeting::factory()->for($this->org)->create();
    $this->comment = Comment::factory()->create([
        'organization_id' => $this->org->id,
        'user_id' => $this->user->id,
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $this->meeting->id,
    ]);
});

it('adds a reaction to a comment', function (): void {
    $this->actingAs($this->user)
        ->postJson(route('comments.reactions.toggle', $this->comment), ['emoji' => '👍'])
        ->assertOk()
        ->assertJsonFragment(['action' => 'added', 'emoji' => '👍']);

    expect(MomReaction::where('comment_id', $this->comment->id)->count())->toBe(1);
});

it('removes reaction if already exists', function (): void {
    MomReaction::create(['comment_id' => $this->comment->id, 'user_id' => $this->user->id, 'emoji' => '👍']);

    $this->actingAs($this->user)
        ->postJson(route('comments.reactions.toggle', $this->comment), ['emoji' => '👍'])
        ->assertOk()
        ->assertJsonFragment(['action' => 'removed']);

    expect(MomReaction::count())->toBe(0);
});
