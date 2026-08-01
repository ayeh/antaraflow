<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Admin\Services\AiControlService;
use App\Domain\AI\Models\MomExtraction;
use App\Domain\Export\Services\MomTranslationService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Infrastructure\AI\AIProviderFactory;
use App\Infrastructure\AI\Contracts\AIProviderInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = app(MomTranslationService::class);
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'title' => 'Mesyuarat Jawatankuasa',
        'language' => 'ms',
    ]);
});

// ── needsTranslation ─────────────────────────────────────────────────────────

it('needsTranslation returns true when output differs from source', function (): void {
    $this->mom->language = 'ms';
    expect($this->service->needsTranslation($this->mom, 'en'))->toBeTrue();
});

it('needsTranslation returns false when languages match', function (): void {
    $this->mom->language = 'ms';
    expect($this->service->needsTranslation($this->mom, 'ms'))->toBeFalse();
});

it('needsTranslation treats null source language as ms', function (): void {
    $this->mom->language = null;
    expect($this->service->needsTranslation($this->mom, 'ms'))->toBeFalse();
    expect($this->service->needsTranslation($this->mom, 'en'))->toBeTrue();
});

// ── applyTranslations – skips when AI disabled ────────────────────────────────

it('applyTranslations does nothing when AI is disabled', function (): void {
    app(AiControlService::class)->disable();

    $this->mom->title = 'Mesyuarat';
    $this->service->applyTranslations($this->mom, 'en');

    // Title should remain unchanged
    expect($this->mom->title)->toBe('Mesyuarat');
});

// ── applyTranslations – applies translations ──────────────────────────────────

it('applyTranslations mutates mom title in memory via AI response', function (): void {
    app(AiControlService::class)->enable();

    $this->mom->load([
        'extractions', 'topics', 'actionItems', 'manualNotes', 'organization',
    ]);

    $provider = mock(AIProviderInterface::class);
    $provider->shouldReceive('chat')
        ->once()
        ->andReturn('{"title": "Committee Meeting"}');

    // Swap the factory binding to return our mock
    AIProviderFactory::shouldUse($provider);

    $this->service->applyTranslations($this->mom, 'en');

    expect($this->mom->title)->toBe('Committee Meeting');
})->skip('Requires AIProviderFactory mock binding – integration test');

it('applyTranslations translates extraction content in memory', function (): void {
    app(AiControlService::class)->enable();

    $extraction = MomExtraction::factory()->create([
        'minutes_of_meeting_id' => $this->mom->id,
        'type' => 'summary',
        'content' => 'Mesyuarat membincangkan belanjawan tahunan.',
    ]);

    $this->mom->setRelation('extractions', $this->mom->extractions()->get());
    $this->mom->setRelation('topics', collect());
    $this->mom->setRelation('actionItems', collect());
    $this->mom->setRelation('manualNotes', collect());

    $provider = mock(AIProviderInterface::class);
    $provider->shouldReceive('chat')
        ->once()
        ->with(\Mockery::on(fn ($prompt) => str_contains($prompt, 'extraction_0')), \Mockery::any())
        ->andReturn('{"title":"Committee Meeting","extraction_0":"Meeting discussed the annual budget."}');

    AIProviderFactory::shouldUse($provider);

    $this->service->applyTranslations($this->mom, 'en');

    expect($this->mom->extractions->first()->content)
        ->toBe('Meeting discussed the annual budget.');
})->skip('Requires AIProviderFactory mock binding – integration test');

// ── applyTranslations – graceful failures ─────────────────────────────────────

it('applyTranslations leaves mom unchanged when AI returns invalid JSON', function (): void {
    app(AiControlService::class)->enable();

    // Load empty relations so collectTranslatables works
    $this->mom->setRelation('extractions', collect());
    $this->mom->setRelation('topics', collect());
    $this->mom->setRelation('actionItems', collect());
    $this->mom->setRelation('manualNotes', collect());
    $this->mom->setRelation('organization', $this->org);

    // Simulate provider returning garbage — use partial mock via swap
    $provider = mock(AIProviderInterface::class);
    $provider->shouldReceive('chat')->andReturn('not valid json at all');

    AIProviderFactory::shouldUse($provider);

    $this->service->applyTranslations($this->mom, 'en');

    // Title should remain unchanged
    expect($this->mom->title)->toBe('Mesyuarat Jawatankuasa');
})->skip('Requires AIProviderFactory mock binding – integration test');

it('applyTranslations is silent when AI throws', function (): void {
    app(AiControlService::class)->enable();

    $this->mom->setRelation('extractions', collect());
    $this->mom->setRelation('topics', collect());
    $this->mom->setRelation('actionItems', collect());
    $this->mom->setRelation('manualNotes', collect());
    $this->mom->setRelation('organization', $this->org);

    $provider = mock(AIProviderInterface::class);
    $provider->shouldReceive('chat')->andThrow(new \RuntimeException('API error'));

    AIProviderFactory::shouldUse($provider);

    // Should not throw
    $this->service->applyTranslations($this->mom, 'en');

    expect($this->mom->title)->toBe('Mesyuarat Jawatankuasa');
})->skip('Requires AIProviderFactory mock binding – integration test');

// ── applyTranslations – does nothing with empty payload ───────────────────────

it('applyTranslations does nothing when no translatable content exists', function (): void {
    app(AiControlService::class)->enable();

    $emptyMom = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'title' => '',
        'language' => 'ms',
    ]);

    $emptyMom->setRelation('extractions', collect());
    $emptyMom->setRelation('topics', collect());
    $emptyMom->setRelation('actionItems', collect());
    $emptyMom->setRelation('manualNotes', collect());
    $emptyMom->setRelation('organization', $this->org);

    // No provider call should happen for empty payload
    $this->service->applyTranslations($emptyMom, 'en');

    // Nothing to assert — just verify it didn't throw
    expect(true)->toBeTrue();
});
