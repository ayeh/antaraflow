<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
});

test('authenticated user sees dashboard with stats', function () {
    MinutesOfMeeting::factory()->count(3)->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    ActionItem::factory()->open()->create([
        'organization_id' => $this->org->id,
        'assigned_to' => $this->user->id,
        'minutes_of_meeting_id' => MinutesOfMeeting::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->user->id,
        ]),
    ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertSuccessful();
    $response->assertSee('Dashboard');
    $response->assertSee('Total Meetings');
    $response->assertSee('Pending Actions');
    $response->assertSee('Overdue Actions');
});

test('guest is redirected from dashboard', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});
