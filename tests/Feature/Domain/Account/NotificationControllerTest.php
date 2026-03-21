<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns notification unread list and count as json', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('notifications.unread'))
        ->assertOk()
        ->assertJsonStructure(['notifications', 'count']);
});

it('marks all notifications as read', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('notifications.read-all'))
        ->assertRedirect();
});

it('marks a single notification as read', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('notifications.read', ['id' => 'non-existent-id']))
        ->assertRedirect();
});

it('returns unread count via unread endpoint', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->getJson(route('notifications.unread'))
        ->assertOk()
        ->assertJsonFragment(['count' => 0]);

    expect($response->json('count'))->toBe(0);
});
