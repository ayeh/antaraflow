<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\AI\Models\MomAiConversation;
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
    ]);

    config(['ai.default' => 'openai']);
    config(['ai.providers.openai.api_key' => 'test-key']);
    config(['ai.providers.openai.model' => 'gpt-4o']);
});

test('user can view chat history', function () {
    MomAiConversation::factory()->userRole()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'user_id' => $this->user->id,
        'message' => 'Hello AI',
    ]);

    MomAiConversation::factory()->assistantRole()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'user_id' => $this->user->id,
        'message' => 'Hello human',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('meetings.chat.index', $this->meeting));

    $response->assertSuccessful();
    $response->assertViewHas('history');
    $response->assertViewHas('meeting');
});

test('user can send chat message', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'AI response']]],
        ]),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('meetings.chat.store', $this->meeting), [
            'message' => 'What were the action items?',
        ]);

    $response->assertStatus(201);
    $response->assertJsonStructure(['message', 'role', 'created_at']);
    $response->assertJson(['role' => 'assistant']);

    $this->assertDatabaseHas('mom_ai_conversations', [
        'minutes_of_meeting_id' => $this->meeting->id,
        'user_id' => $this->user->id,
        'role' => 'user',
        'message' => 'What were the action items?',
    ]);

    $this->assertDatabaseHas('mom_ai_conversations', [
        'minutes_of_meeting_id' => $this->meeting->id,
        'user_id' => $this->user->id,
        'role' => 'assistant',
    ]);
});

test('guest cannot access chat', function () {
    $response = $this->get(route('meetings.chat.index', $this->meeting));
    $response->assertRedirect(route('login'));

    $response = $this->postJson(route('meetings.chat.store', $this->meeting), [
        'message' => 'Hello',
    ]);
    $response->assertUnauthorized();
});

test('chat message requires message field', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('meetings.chat.store', $this->meeting), []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['message']);
});

test('chat message has max length', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('meetings.chat.store', $this->meeting), [
            'message' => str_repeat('a', 5001),
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['message']);
});
