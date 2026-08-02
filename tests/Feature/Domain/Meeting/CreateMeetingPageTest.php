<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Models\User;
use App\Support\Enums\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create(['timezone' => 'Asia/Kuala_Lumpur']);
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
});

test('date default follows the organisation timezone, not the app timezone', function () {
    // 19:20 UTC is already the next calendar day in Kuala Lumpur (UTC+8).
    $this->travelTo(CarbonImmutable::parse('2026-08-02 19:20:00', 'UTC'));

    $defaults = $this->actingAs($this->user)
        ->get(route('meetings.create'))
        ->assertSuccessful()
        ->viewData('defaults');

    expect($defaults['today'])->toBe('2026-08-03')
        ->and($defaults['tomorrow'])->toBe('2026-08-04');
});

test('suggested start time is rounded up to the next half hour', function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-02 06:40:00', 'UTC')); // 14:40 in KL

    $defaults = $this->actingAs($this->user)
        ->get(route('meetings.create'))
        ->assertSuccessful()
        ->viewData('defaults');

    expect($defaults['now_time'])->toBe('14:40')
        ->and($defaults['start_time'])->toBe('15:00')
        ->and($defaults['end_time'])->toBe('16:00');
});

test('a time already on the half hour is not pushed forward', function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-02 06:30:00', 'UTC')); // 14:30 in KL

    $defaults = $this->actingAs($this->user)
        ->get(route('meetings.create'))
        ->assertSuccessful()
        ->viewData('defaults');

    expect($defaults['start_time'])->toBe('14:30')
        ->and($defaults['end_time'])->toBe('15:30');
});

test('suggested start time rolls past midnight correctly', function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-02 15:45:00', 'UTC')); // 23:45 in KL

    $defaults = $this->actingAs($this->user)
        ->get(route('meetings.create'))
        ->assertSuccessful()
        ->viewData('defaults');

    expect($defaults['start_time'])->toBe('00:00')
        ->and($defaults['end_time'])->toBe('01:00');
});

test('the form still posts every field the create request accepts', function () {
    $html = $this->actingAs($this->user)
        ->get(route('meetings.create'))
        ->assertSuccessful()
        ->getContent();

    $fields = ['title', 'meeting_date', 'start_time', 'end_time', 'location',
        'meeting_link', 'project_id', 'prepared_by', 'language'];

    foreach ($fields as $field) {
        expect($html)->toContain('name="'.$field.'"');
    }
});

test('the form offers no client-sharing control', function () {
    // Sharing happens at the Finalize step via a guest link, which is the only
    // mechanism that actually grants access. Offering a toggle here would be
    // asking the user to decide disclosure before any minutes exist.
    $html = $this->actingAs($this->user)
        ->get(route('meetings.create'))
        ->assertSuccessful()
        ->getContent();

    foreach (['share_with_client', 'Share with Client', 'type="checkbox"'] as $needle) {
        expect($html)->not->toContain($needle);
    }
});

test('validation failure returns to the form with the typed title intact', function () {
    $this->actingAs($this->user)
        ->from(route('meetings.create'))
        ->post(route('meetings.store'), [
            'title' => 'Budget Review',
            'meeting_date' => '2026-04-01',
            'prepared_by' => 'Noor Ariff',
            'start_time' => '14:00',
            'end_time' => '13:00',
        ])
        ->assertRedirect(route('meetings.create'))
        ->assertSessionHasErrors('end_time');

    $this->actingAs($this->user)
        ->get(route('meetings.create'))
        ->assertSuccessful()
        ->assertSee('Budget Review', false);
});
