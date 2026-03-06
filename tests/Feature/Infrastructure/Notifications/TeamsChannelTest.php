<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Notifications\MeetingFinalizedNotification;
use App\Infrastructure\Notifications\Channels\TeamsChannel;
use App\Infrastructure\Notifications\Messages\TeamsMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('teams message builds adaptive card payload', function () {
    $message = (new TeamsMessage)
        ->title('Test Title')
        ->content('Test content')
        ->fact('Key', 'Value')
        ->action('Click Me', 'https://example.com');

    $payload = $message->toAdaptiveCard();

    expect($payload)->toHaveKey('type', 'message')
        ->and($payload['attachments'])->toHaveCount(1)
        ->and($payload['attachments'][0]['contentType'])->toBe('application/vnd.microsoft.card.adaptive')
        ->and($payload['attachments'][0]['content']['body'][0]['text'])->toBe('Test Title')
        ->and($payload['attachments'][0]['content']['body'][1]['text'])->toBe('Test content')
        ->and($payload['attachments'][0]['content']['body'][2]['facts'][0]['title'])->toBe('Key')
        ->and($payload['attachments'][0]['content']['actions'][0]['title'])->toBe('Click Me');
});

test('teams channel sends notification to webhook url', function () {
    Http::fake(['*' => Http::response('', 200)]);

    $organization = Organization::factory()->create([
        'teams_webhook_url' => 'https://outlook.office.com/webhook/test-webhook',
    ]);

    $user = User::factory()->create(['current_organization_id' => $organization->id]);
    $meeting = MinutesOfMeeting::factory()->create(['organization_id' => $organization->id]);

    $notification = new MeetingFinalizedNotification($meeting, $user);

    $channel = new TeamsChannel;
    $channel->send($user, $notification);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'outlook.office.com/webhook')
            && $request['type'] === 'message'
            && isset($request['attachments']);
    });
});

test('teams channel does not send when no webhook url configured', function () {
    Http::fake();

    $organization = Organization::factory()->create(['teams_webhook_url' => null]);
    $user = User::factory()->create(['current_organization_id' => $organization->id]);
    $meeting = MinutesOfMeeting::factory()->create(['organization_id' => $organization->id]);

    $notification = new MeetingFinalizedNotification($meeting, $user);

    $channel = new TeamsChannel;
    $channel->send($user, $notification);

    Http::assertNothingSent();
});

test('teams channel is included in via when org has webhook', function () {
    $organization = Organization::factory()->create([
        'teams_webhook_url' => 'https://outlook.office.com/webhook/test',
    ]);
    $user = User::factory()->create(['current_organization_id' => $organization->id]);
    $meeting = MinutesOfMeeting::factory()->create(['organization_id' => $organization->id]);

    $notification = new MeetingFinalizedNotification($meeting, $user);

    expect($notification->via($user))->toContain('teams');
});

test('teams channel is not included in via when org has no webhook', function () {
    $organization = Organization::factory()->create(['teams_webhook_url' => null]);
    $user = User::factory()->create(['current_organization_id' => $organization->id]);
    $meeting = MinutesOfMeeting::factory()->create(['organization_id' => $organization->id]);

    $notification = new MeetingFinalizedNotification($meeting, $user);

    expect($notification->via($user))->not->toContain('teams');
});

test('organization has teams webhook helper works', function () {
    $orgWithWebhook = Organization::factory()->create([
        'teams_webhook_url' => 'https://outlook.office.com/webhook/test',
    ]);
    $orgWithoutWebhook = Organization::factory()->create(['teams_webhook_url' => null]);

    expect($orgWithWebhook->hasTeamsWebhook())->toBeTrue()
        ->and($orgWithoutWebhook->hasTeamsWebhook())->toBeFalse();
});
