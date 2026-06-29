<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\AI\Mail\AgendaEmail;
use App\Domain\AI\Models\MomTopic;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
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
        'status' => MeetingStatus::Draft,
    ]);

    MomTopic::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'title' => 'Review previous action items',
        'sort_order' => 1,
    ]);

    config(['ai.default' => 'openai']);
    config(['ai.providers.openai.api_key' => 'test-key']);
    config(['ai.providers.openai.model' => 'gpt-4o']);
});

test('user can generate agenda email preview', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'subject' => 'Agenda: Upcoming Meeting',
                'body' => 'Dear team, please review the agenda below.',
            ])]]],
        ]),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('meetings.agenda-email.generate', $this->meeting));

    $response->assertSuccessful();
    $response->assertSee('Send Agenda');
});

test('agenda context includes the meeting topics', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'subject' => 'Agenda',
                'body' => 'Please prepare.',
            ])]]],
        ]),
    ]);

    $this->actingAs($this->user)
        ->get(route('meetings.agenda-email.generate', $this->meeting))
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);
        foreach ($body['messages'] ?? [] as $msg) {
            if (str_contains($msg['content'] ?? '', 'Review previous action items')) {
                return true;
            }
        }

        return false;
    });
});

test('user can send agenda email', function () {
    Mail::fake();

    $response = $this->actingAs($this->user)
        ->post(route('meetings.agenda-email.send', $this->meeting), [
            'subject' => 'Agenda: Project Sync',
            'body' => 'Please review the agenda and come prepared.',
            'recipients' => ['alice@example.com', 'bob@example.com'],
        ]);

    $response->assertRedirect(route('meetings.show', $this->meeting));
    $response->assertSessionHas('success');

    Mail::assertSent(AgendaEmail::class, function ($mail) {
        return $mail->hasTo('alice@example.com')
            && $mail->hasTo('bob@example.com')
            && $mail->emailSubject === 'Agenda: Project Sync';
    });
});

test('agenda email body renders markdown as html', function () {
    $mailable = new AgendaEmail(
        emailSubject: 'Agenda: Project Sync',
        emailBody: "Please review the agenda:\n\n1. Item one\n2. Item two",
        meetingTitle: 'Project Sync',
        meetingDate: 'July 1, 2026',
    );

    $rendered = $mailable->render();

    expect($rendered)
        ->toContain('<li>Item one</li>')
        ->toContain('July 1, 2026')
        ->not->toContain('1. Item one');
});

test('send validation requires recipients', function () {
    $response = $this->actingAs($this->user)
        ->post(route('meetings.agenda-email.send', $this->meeting), [
            'subject' => 'Agenda',
            'body' => 'Agenda body',
            'recipients' => [],
        ]);

    $response->assertSessionHasErrors('recipients');
});

test('send validation requires valid emails', function () {
    $response = $this->actingAs($this->user)
        ->post(route('meetings.agenda-email.send', $this->meeting), [
            'subject' => 'Agenda',
            'body' => 'Agenda body',
            'recipients' => ['not-an-email'],
        ]);

    $response->assertSessionHasErrors('recipients.0');
});

test('send agenda button visible when meeting has agenda', function () {
    $response = $this->actingAs($this->user)
        ->get(route('meetings.show', $this->meeting));

    $response->assertSuccessful();
    $response->assertSee('Send Agenda');
});

test('send agenda button not visible without an agenda', function () {
    $meetingNoAgenda = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'status' => MeetingStatus::Draft,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('meetings.show', $meetingNoAgenda));

    $response->assertSuccessful();
    $response->assertDontSee('Send Agenda');
});
