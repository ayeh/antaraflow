<?php

declare(strict_types=1);

use App\Domain\Search\Services\AiSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('returns cached response when available', function (): void {
    $org = \App\Domain\Account\Models\Organization::factory()->create();
    $cacheKey = 'ai_search:'.$org->id.':'.md5('test query');

    Cache::put($cacheKey, [
        'answer' => 'Cached answer',
        'sources' => [],
    ], 3600);

    $result = app(AiSearchService::class)->search('test query', $org->id);

    expect($result['answer'])->toBe('Cached answer');
    expect($result['sources'])->toBeArray();
});

it('returns no meetings found when search yields no results', function (): void {
    $org = \App\Domain\Account\Models\Organization::factory()->create();
    // No meetings created, so search returns empty

    $result = app(AiSearchService::class)->search('nonexistent xyzabc123', $org->id);

    expect($result['answer'])->toContain('No relevant meetings');
    expect($result['sources'])->toBeArray()->toBeEmpty();
});
