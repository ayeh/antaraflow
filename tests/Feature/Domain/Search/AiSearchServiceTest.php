<?php

declare(strict_types=1);

use App\Domain\Search\Services\AiSearchService;
use App\Domain\Search\Services\GlobalSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('returns cached response and skips search when cache is warm', function (): void {
    $org = \App\Domain\Account\Models\Organization::factory()->create();
    $cacheKey = 'ai_search:'.$org->id.':'.md5('test query');

    Cache::put($cacheKey, ['answer' => 'Cached answer', 'sources' => []], 3600);

    // Mock GlobalSearchService — it should NOT be called
    $mock = \Mockery::mock(GlobalSearchService::class);
    $mock->shouldNotReceive('search');
    app()->instance(GlobalSearchService::class, $mock);

    $result = app(AiSearchService::class)->search('test query', $org->id);

    expect($result['answer'])->toBe('Cached answer');
});

it('returns no meetings found when search yields no results', function (): void {
    $org = \App\Domain\Account\Models\Organization::factory()->create();
    // No meetings created, so search returns empty

    $result = app(AiSearchService::class)->search('nonexistent xyzabc123', $org->id);

    expect($result['answer'])->toContain('No relevant meetings');
    expect($result['sources'])->toBeArray()->toBeEmpty();
});

it('returns helpful message when no AI provider is configured', function (): void {
    $org = \App\Domain\Account\Models\Organization::factory()->create();
    $meeting = \App\Domain\Meeting\Models\MinutesOfMeeting::factory()
        ->for($org)
        ->create(['title' => 'Budget Meeting', 'summary' => 'Discussed budget']);

    // No AiProviderConfig created for this org
    $result = app(AiSearchService::class)->search('budget', $org->id);

    expect($result['answer'])->toContain('not configured');
    expect($result['sources'])->toBeArray();
});
