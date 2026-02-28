<?php

declare(strict_types=1);

use App\Domain\Account\Models\AiProviderConfig;
use App\Domain\Account\Models\Organization;
use App\Domain\AI\Models\MomAiConversation;
use App\Domain\AI\Services\ChatService;
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
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'title' => 'Sprint Planning',
        'summary' => 'Discussed sprint goals',
        'content' => 'We planned the upcoming sprint features.',
    ]);

    config(['ai.default' => 'openai']);
    config(['ai.providers.openai.api_key' => 'test-key']);
    config(['ai.providers.openai.model' => 'gpt-4o']);
});

test('can send message and receive AI response', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'AI response about the sprint']]],
        ]),
    ]);

    $service = app(ChatService::class);
    $response = $service->sendMessage($this->meeting, $this->user, 'What were the key decisions?');

    expect($response)->toBeInstanceOf(MomAiConversation::class)
        ->and($response->role)->toBe('assistant')
        ->and($response->message)->toBe('AI response about the sprint')
        ->and($response->provider)->toBe('openai');

    $conversations = MomAiConversation::query()
        ->where('minutes_of_meeting_id', $this->meeting->id)
        ->get();

    expect($conversations)->toHaveCount(2)
        ->and($conversations->first()->role)->toBe('user')
        ->and($conversations->first()->message)->toBe('What were the key decisions?')
        ->and($conversations->last()->role)->toBe('assistant');
});

test('chat includes meeting context', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Context-aware response']]],
        ]),
    ]);

    $service = app(ChatService::class);
    $service->sendMessage($this->meeting, $this->user, 'Summarize this meeting');

    Http::assertSent(function ($request) {
        $body = $request->body();

        return str_contains($body, 'Sprint Planning')
            && str_contains($body, 'Discussed sprint goals');
    });
});

test('chat history is preserved per user', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Response']]],
        ]),
    ]);

    $otherUser = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($otherUser, ['role' => UserRole::Member->value]);

    $service = app(ChatService::class);
    $service->sendMessage($this->meeting, $this->user, 'User 1 message');
    $service->sendMessage($this->meeting, $otherUser, 'User 2 message');

    $user1History = $service->getHistory($this->meeting, $this->user);
    $user2History = $service->getHistory($this->meeting, $otherUser);

    expect($user1History)->toHaveCount(2)
        ->and($user2History)->toHaveCount(2)
        ->and($user1History->first()->message)->toBe('User 1 message')
        ->and($user2History->first()->message)->toBe('User 2 message');
});

test('chat uses org provider config when available', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'Anthropic response']],
        ]),
    ]);

    AiProviderConfig::factory()->create([
        'organization_id' => $this->org->id,
        'provider' => 'anthropic',
        'api_key_encrypted' => 'org-anthropic-key',
        'model' => 'claude-sonnet-4-20250514',
        'is_default' => true,
        'is_active' => true,
    ]);

    $service = app(ChatService::class);
    $response = $service->sendMessage($this->meeting, $this->user, 'Hello');

    expect($response->provider)->toBe('anthropic');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.anthropic.com'));
});

test('chat falls back to default config when no org config exists', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Default response']]],
        ]),
    ]);

    $service = app(ChatService::class);
    $response = $service->sendMessage($this->meeting, $this->user, 'Hello');

    expect($response->provider)->toBe('openai');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.openai.com'));
});
