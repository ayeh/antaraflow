<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MeetingTemplate;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Manager->value]);
});

test('user can list templates', function () {
    MeetingTemplate::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'name' => 'My Template',
    ]);

    $response = $this->actingAs($this->user)->get(route('meeting-templates.index'));

    $response->assertSuccessful();
    $response->assertSee('My Template');
});

test('manager can create a template', function () {
    $response = $this->actingAs($this->user)->post(route('meeting-templates.store'), [
        'name' => 'New Template',
        'structure' => ['sections' => [['title' => 'Agenda', 'type' => 'text']]],
        'is_shared' => true,
        'is_default' => false,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('meeting_templates', [
        'name' => 'New Template',
        'organization_id' => $this->org->id,
    ]);
});

test('manager can update a template', function () {
    $template = MeetingTemplate::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->put(route('meeting-templates.update', $template), [
        'name' => 'Updated Template',
        'structure' => ['sections' => []],
        'is_shared' => true,
        'is_default' => false,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('meeting_templates', [
        'id' => $template->id,
        'name' => 'Updated Template',
    ]);
});

test('manager can delete a template', function () {
    $template = MeetingTemplate::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->delete(route('meeting-templates.destroy', $template));

    $response->assertRedirect(route('meeting-templates.index'));
    $this->assertDatabaseMissing('meeting_templates', ['id' => $template->id, 'deleted_at' => null]);
});

test('viewer cannot create a template', function () {
    $viewer = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($viewer, ['role' => UserRole::Viewer->value]);

    $response = $this->actingAs($viewer)->post(route('meeting-templates.store'), [
        'name' => 'Should Fail',
        'structure' => ['sections' => []],
    ]);

    $response->assertForbidden();
});
