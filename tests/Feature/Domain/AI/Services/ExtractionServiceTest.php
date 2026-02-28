<?php

declare(strict_types=1);

use App\Domain\Account\Models\AiProviderConfig;
use App\Domain\Account\Models\Organization;
use App\Domain\AI\Models\MomExtraction;
use App\Domain\AI\Models\MomTopic;
use App\Domain\AI\Services\ExtractionService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);

    config(['ai.default' => 'openai']);
    config(['ai.providers.openai.api_key' => 'test-key']);
    config(['ai.providers.openai.model' => 'gpt-4o']);
});

test('extraction service generates summary for meeting', function () {
    Http::fake([
        'api.openai.com/*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => json_encode([
                'summary' => 'Meeting discussed project timelines.',
                'key_points' => '- Timeline review\n- Budget approval',
                'confidence_score' => 0.92,
            ])]]]])
            ->push(['choices' => [['message' => ['content' => json_encode([
                ['title' => 'Review code', 'priority' => 'high'],
            ])]]]])
            ->push(['choices' => [['message' => ['content' => json_encode([
                ['decision' => 'Use Laravel', 'context' => 'Framework choice'],
            ])]]]])
            ->push(['choices' => [['message' => ['content' => json_encode([
                ['title' => 'Project Timeline', 'description' => 'Discussed deadlines'],
            ])]]]]),
    ]);

    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'content' => 'We discussed the project timeline and budget.',
    ]);

    $service = app(ExtractionService::class);
    $service->extractAll($mom);

    $this->assertDatabaseHas('mom_extractions', [
        'minutes_of_meeting_id' => $mom->id,
        'type' => 'summary',
        'provider' => 'openai',
        'model' => 'gpt-4o',
    ]);

    $summary = MomExtraction::query()
        ->where('minutes_of_meeting_id', $mom->id)
        ->where('type', 'summary')
        ->first();

    expect($summary->content)->toBe('Meeting discussed project timelines.')
        ->and($summary->confidence_score)->toBe(0.92);
});

test('extraction service creates action items extraction', function () {
    Http::fake([
        'api.openai.com/*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => json_encode([
                'summary' => 'Summary',
                'key_points' => '- Point',
                'confidence_score' => 0.9,
            ])]]]])
            ->push(['choices' => [['message' => ['content' => json_encode([
                ['title' => 'Review PR #42', 'assignee' => 'Alice', 'priority' => 'high'],
                ['title' => 'Update docs', 'priority' => 'medium'],
            ])]]]])
            ->push(['choices' => [['message' => ['content' => '[]']]]])
            ->push(['choices' => [['message' => ['content' => '[]']]]]),
    ]);

    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'content' => 'Alice should review PR #42. Also update the docs.',
    ]);

    $service = app(ExtractionService::class);
    $service->extractAll($mom);

    $extraction = MomExtraction::query()
        ->where('minutes_of_meeting_id', $mom->id)
        ->where('type', 'action_items')
        ->first();

    expect($extraction)->not->toBeNull()
        ->and($extraction->structured_data)->toHaveCount(2)
        ->and($extraction->structured_data[0]['title'])->toBe('Review PR #42');
});

test('extraction service uses org provider config when available', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::sequence()
            ->push(['content' => [['type' => 'text', 'text' => json_encode([
                'summary' => 'Anthropic summary',
                'key_points' => '- Point',
                'confidence_score' => 0.88,
            ])]]])
            ->push(['content' => [['type' => 'text', 'text' => '[]']]])
            ->push(['content' => [['type' => 'text', 'text' => '[]']]])
            ->push(['content' => [['type' => 'text', 'text' => '[]']]]),
    ]);

    AiProviderConfig::factory()->create([
        'organization_id' => $this->org->id,
        'provider' => 'anthropic',
        'api_key_encrypted' => 'org-anthropic-key',
        'model' => 'claude-sonnet-4-20250514',
        'is_default' => true,
        'is_active' => true,
    ]);

    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'content' => 'Discussion content here.',
    ]);

    $service = app(ExtractionService::class);
    $service->extractAll($mom);

    $this->assertDatabaseHas('mom_extractions', [
        'minutes_of_meeting_id' => $mom->id,
        'type' => 'summary',
        'provider' => 'anthropic',
    ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.anthropic.com'));
});

test('extraction service falls back to default config', function () {
    Http::fake([
        'api.openai.com/*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => json_encode([
                'summary' => 'Default provider summary',
                'key_points' => '',
                'confidence_score' => 0.8,
            ])]]]])
            ->push(['choices' => [['message' => ['content' => '[]']]]])
            ->push(['choices' => [['message' => ['content' => '[]']]]])
            ->push(['choices' => [['message' => ['content' => '[]']]]]),
    ]);

    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'content' => 'Some meeting content.',
    ]);

    $service = app(ExtractionService::class);
    $service->extractAll($mom);

    $this->assertDatabaseHas('mom_extractions', [
        'minutes_of_meeting_id' => $mom->id,
        'provider' => 'openai',
    ]);
});

test('extraction service skips when no text available', function () {
    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'content' => null,
    ]);

    $service = app(ExtractionService::class);
    $service->extractAll($mom);

    expect(MomExtraction::query()->where('minutes_of_meeting_id', $mom->id)->count())->toBe(0);
});

test('extraction service creates topics', function () {
    Http::fake([
        'api.openai.com/*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => json_encode([
                'summary' => 'Summary',
                'key_points' => '',
                'confidence_score' => 0.9,
            ])]]]])
            ->push(['choices' => [['message' => ['content' => '[]']]]])
            ->push(['choices' => [['message' => ['content' => '[]']]]])
            ->push(['choices' => [['message' => ['content' => json_encode([
                ['title' => 'Budget Review', 'description' => 'Reviewed Q4 budget', 'duration_minutes' => 15],
                ['title' => 'Hiring Plan', 'description' => 'New positions discussion', 'duration_minutes' => 20],
            ])]]]]),
    ]);

    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'content' => 'Budget and hiring discussion.',
    ]);

    $service = app(ExtractionService::class);
    $service->extractAll($mom);

    expect(MomTopic::query()->where('minutes_of_meeting_id', $mom->id)->count())->toBe(2);

    $this->assertDatabaseHas('mom_topics', [
        'minutes_of_meeting_id' => $mom->id,
        'title' => 'Budget Review',
        'sort_order' => 0,
    ]);

    $this->assertDatabaseHas('mom_topics', [
        'minutes_of_meeting_id' => $mom->id,
        'title' => 'Hiring Plan',
        'sort_order' => 1,
    ]);
});
