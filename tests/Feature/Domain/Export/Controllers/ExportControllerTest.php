<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Member->value]);
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);
});

test('user can export meeting as pdf', function () {
    $response = $this->actingAs($this->user)->get(route('meetings.export.pdf', $this->meeting));

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/pdf');
});

test('user can export meeting action items as csv', function () {
    $response = $this->actingAs($this->user)->get(route('meetings.export.csv', $this->meeting));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

test('user can export meeting as word document', function () {
    $response = $this->actingAs($this->user)->get(route('meetings.export.word', $this->meeting));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('wordprocessingml');
});

test('unauthenticated user cannot export', function () {
    $response = $this->get(route('meetings.export.pdf', $this->meeting));

    $response->assertRedirect(route('login'));
});
