<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\AI\Mail\FollowUpMeetingEmail;
use App\Domain\AI\Models\MomExtraction;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Manager->value]);

    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'content' => 'We discussed the Q4 roadmap.',
    ]);

    MomExtraction::query()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'type' => 'summary',
        'content' => 'Discussed Q4 roadmap priorities.',
        'provider' => 'openai',
        'model' => 'gpt-4o',
    ]);

    config(['ai.default' => 'openai']);
    config(['ai.providers.openai.api_key' => 'test-key']);
    config(['ai.providers.openai.model' => 'gpt-4o']);
});

test('user can generate follow-up email preview', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'subject' => 'Follow-up: Q4 Roadmap Meeting',
                'body' => 'Dear team, thank you for attending.',
            ])]]],
        ]),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('meetings.follow-up-email.generate', $this->meeting));

    $response->assertSuccessful();
    $response->assertSee('Follow-up Email');
    $response->assertSee('Send Email');
});

test('user can send follow-up email', function () {
    Mail::fake();

    $response = $this->actingAs($this->user)
        ->post(route('meetings.follow-up-email.send', $this->meeting), [
            'subject' => 'Follow-up: Q4 Roadmap',
            'body' => 'Thank you for attending the meeting.',
            'recipients' => ['alice@example.com', 'bob@example.com'],
        ]);

    $response->assertRedirect(route('meetings.show', $this->meeting));
    $response->assertSessionHas('success');

    Mail::assertSent(FollowUpMeetingEmail::class, function ($mail) {
        return $mail->hasTo('alice@example.com')
            && $mail->hasTo('bob@example.com')
            && $mail->emailSubject === 'Follow-up: Q4 Roadmap';
    });
});

test('send validation requires recipients', function () {
    $response = $this->actingAs($this->user)
        ->post(route('meetings.follow-up-email.send', $this->meeting), [
            'subject' => 'Test',
            'body' => 'Test body',
            'recipients' => [],
        ]);

    $response->assertSessionHasErrors('recipients');
});

test('send validation requires valid emails', function () {
    $response = $this->actingAs($this->user)
        ->post(route('meetings.follow-up-email.send', $this->meeting), [
            'subject' => 'Test',
            'body' => 'Test body',
            'recipients' => ['not-an-email'],
        ]);

    $response->assertSessionHasErrors('recipients.0');
});

test('send validation requires subject and body', function () {
    $response = $this->actingAs($this->user)
        ->post(route('meetings.follow-up-email.send', $this->meeting), [
            'subject' => '',
            'body' => '',
            'recipients' => ['test@example.com'],
        ]);

    $response->assertSessionHasErrors(['subject', 'body']);
});

test('follow-up email button visible on meeting with extractions', function () {
    $response = $this->actingAs($this->user)
        ->get(route('meetings.show', $this->meeting));

    $response->assertSuccessful();
    $response->assertSee('Follow-up Email');
});

test('follow-up email button not visible on meeting without extractions', function () {
    $meetingNoExtractions = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('meetings.show', $meetingNoExtractions));

    $response->assertSuccessful();
    $response->assertDontSee('Follow-up Email');
});
