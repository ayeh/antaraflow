<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Calendar\Models\CalendarConnection;
use App\Domain\Calendar\Providers\GoogleCalendarProvider;
use App\Domain\Calendar\Providers\OutlookCalendarProvider;
use App\Domain\Calendar\Services\CalendarSyncService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->org = Organization::factory()->create();
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
    $this->user->update(['current_organization_id' => $this->org->id]);
});

it('shows calendar connections page', function () {
    $this->actingAs($this->user)
        ->get(route('calendar.connections'))
        ->assertOk()
        ->assertSee('Calendar Connections');
});

it('redirects to Google OAuth for connection', function () {
    $this->actingAs($this->user)
        ->get(route('calendar.connect', 'google'))
        ->assertRedirect();
});

it('redirects to Outlook OAuth for connection', function () {
    $this->actingAs($this->user)
        ->get(route('calendar.connect', 'outlook'))
        ->assertRedirect();
});

it('rejects invalid provider', function () {
    $this->actingAs($this->user)
        ->get(route('calendar.connect', 'invalid'))
        ->assertNotFound();
});

it('resolves correct provider from CalendarSyncService', function () {
    $service = app(CalendarSyncService::class);

    expect($service->resolveProvider('google'))->toBeInstanceOf(GoogleCalendarProvider::class)
        ->and($service->resolveProvider('outlook'))->toBeInstanceOf(OutlookCalendarProvider::class);
});

it('throws exception for unknown provider', function () {
    $service = app(CalendarSyncService::class);

    $service->resolveProvider('yahoo');
})->throws(\InvalidArgumentException::class, 'Unknown calendar provider: yahoo');

it('can disconnect a calendar connection', function () {
    $connection = CalendarConnection::create([
        'user_id' => $this->user->id,
        'organization_id' => $this->org->id,
        'provider' => 'google',
        'access_token' => 'test-token',
        'is_active' => true,
    ]);

    $this->actingAs($this->user)
        ->delete(route('calendar.disconnect', $connection))
        ->assertRedirect(route('calendar.connections'));

    $this->assertDatabaseMissing('calendar_connections', ['id' => $connection->id]);
});

it('prevents disconnecting another users connection', function () {
    $otherUser = User::factory()->create();

    $connection = CalendarConnection::create([
        'user_id' => $otherUser->id,
        'organization_id' => $this->org->id,
        'provider' => 'google',
        'access_token' => 'test-token',
        'is_active' => true,
    ]);

    $this->actingAs($this->user)
        ->delete(route('calendar.disconnect', $connection))
        ->assertForbidden();
});

it('syncs meeting to calendar via service', function () {
    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events' => Http::response([
            'id' => 'google-event-123',
        ]),
    ]);

    CalendarConnection::create([
        'user_id' => $this->user->id,
        'organization_id' => $this->org->id,
        'provider' => 'google',
        'access_token' => 'test-token',
        'is_active' => true,
        'token_expires_at' => now()->addHour(),
    ]);

    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $service = app(CalendarSyncService::class);
    $service->syncToCalendar($meeting);

    $meeting->refresh();

    expect($meeting->calendar_event_id)->toBe('google-event-123')
        ->and($meeting->calendar_provider)->toBe('google')
        ->and($meeting->calendar_synced_at)->not->toBeNull();
});

it('updates existing calendar event on re-sync', function () {
    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events/existing-event-456' => Http::response([], 200),
    ]);

    CalendarConnection::create([
        'user_id' => $this->user->id,
        'organization_id' => $this->org->id,
        'provider' => 'google',
        'access_token' => 'test-token',
        'is_active' => true,
        'token_expires_at' => now()->addHour(),
    ]);

    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'calendar_event_id' => 'existing-event-456',
        'calendar_provider' => 'google',
    ]);

    $service = app(CalendarSyncService::class);
    $service->syncToCalendar($meeting);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'existing-event-456')
            && $request->method() === 'PUT';
    });
});

it('handles webhook from Google', function () {
    $this->postJson(route('calendar.webhook', 'google'), [], [
        'X-Goog-Channel-ID' => 'test-channel',
        'X-Goog-Resource-ID' => 'test-resource',
        'X-Goog-Resource-State' => 'sync',
    ])->assertOk()
        ->assertJson(['status' => 'ok']);
});

it('handles webhook from Outlook', function () {
    $this->postJson(route('calendar.webhook', 'outlook'), [
        'value' => [
            [
                'changeType' => 'updated',
                'resource' => '/me/events/abc',
                'subscriptionId' => 'sub-123',
            ],
        ],
    ])->assertOk()
        ->assertJson(['status' => 'ok']);
});

it('skips deletion when meeting has no calendar event', function () {
    Http::fake();

    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'calendar_event_id' => null,
        'calendar_provider' => null,
    ]);

    $service = app(CalendarSyncService::class);
    $service->deleteFromCalendar($meeting);

    Http::assertNothingSent();
});

it('deletes calendar event from provider', function () {
    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events/event-to-delete' => Http::response([], 204),
    ]);

    CalendarConnection::create([
        'user_id' => $this->user->id,
        'organization_id' => $this->org->id,
        'provider' => 'google',
        'access_token' => 'test-token',
        'is_active' => true,
        'token_expires_at' => now()->addHour(),
    ]);

    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'calendar_event_id' => 'event-to-delete',
        'calendar_provider' => 'google',
    ]);

    $service = app(CalendarSyncService::class);
    $service->deleteFromCalendar($meeting);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'event-to-delete')
            && $request->method() === 'DELETE';
    });
});

it('shows connected status for existing connections', function () {
    CalendarConnection::create([
        'user_id' => $this->user->id,
        'organization_id' => $this->org->id,
        'provider' => 'google',
        'access_token' => 'test-token',
        'is_active' => true,
    ]);

    $this->actingAs($this->user)
        ->get(route('calendar.connections'))
        ->assertOk()
        ->assertSee('Connected')
        ->assertSee('Disconnect');
});
