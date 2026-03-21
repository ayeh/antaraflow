<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Search\Services\AiSearchService;
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

it('returns ai search results as json', function (): void {
    $this->mock(AiSearchService::class, function ($mock): void {
        $mock->shouldReceive('search')
            ->once()
            ->andReturn([
                'answer' => 'The budget was approved.',
                'sources' => [
                    ['id' => 1, 'title' => 'Budget Meeting', 'meeting_date' => '2026-01-01', 'url' => '/meetings/1'],
                ],
            ]);
    });

    $this->actingAs($this->user)
        ->postJson(route('search.ai'), ['query' => 'budget decision'])
        ->assertOk()
        ->assertJsonStructure(['answer', 'sources'])
        ->assertJsonPath('answer', 'The budget was approved.');
});

it('validates that query must be at least 3 characters', function (): void {
    $this->actingAs($this->user)
        ->postJson(route('search.ai'), ['query' => 'ab'])
        ->assertUnprocessable();
});

it('requires authentication', function (): void {
    $this->postJson(route('search.ai'), ['query' => 'test query'])
        ->assertUnauthorized();
});
