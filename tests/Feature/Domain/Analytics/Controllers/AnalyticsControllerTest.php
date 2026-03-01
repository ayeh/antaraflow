<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Manager->value]);
});

test('authenticated user can view analytics page', function () {
    $response = $this->actingAs($this->user)->get(route('analytics.index'));

    $response->assertOk();
    $response->assertViewIs('analytics.index');
    $response->assertViewHas('meetingStats');
    $response->assertViewHas('actionStats');
    $response->assertViewHas('participationStats');
    $response->assertViewHas('aiStats');
});

test('authenticated user can fetch analytics data as json', function () {
    $response = $this->actingAs($this->user)->get(route('analytics.data'));

    $response->assertOk();
    $response->assertJsonStructure(['meetings', 'actions', 'participation', 'ai']);
});

test('authenticated user can filter analytics by date range', function () {
    $startDate = now()->subMonths(3)->startOfMonth()->toDateString();
    $endDate = now()->endOfMonth()->toDateString();

    $response = $this->actingAs($this->user)->get(route('analytics.data', [
        'start_date' => $startDate,
        'end_date' => $endDate,
    ]));

    $response->assertOk();
    $response->assertJsonStructure(['meetings', 'actions', 'participation', 'ai']);
});

test('unauthenticated user cannot view analytics', function () {
    $response = $this->get(route('analytics.index'));

    $response->assertRedirect(route('login'));
});
