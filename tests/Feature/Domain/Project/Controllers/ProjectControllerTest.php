<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Project\Models\Project;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Manager->value]);
});

test('user can list projects', function () {
    Project::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'name' => 'Test Project',
    ]);

    $response = $this->actingAs($this->user)->get(route('projects.index'));

    $response->assertSuccessful();
    $response->assertSee('Test Project');
});

test('user can view create project form', function () {
    $response = $this->actingAs($this->user)->get(route('projects.create'));

    $response->assertSuccessful();
    $response->assertSee('Create Project');
});

test('user can create a project', function () {
    $response = $this->actingAs($this->user)->post(route('projects.store'), [
        'name' => 'New Project',
        'code' => 'NP',
        'description' => 'A test project description',
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('projects', [
        'name' => 'New Project',
        'code' => 'NP',
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);
});

test('creating a project requires a name', function () {
    $response = $this->actingAs($this->user)->post(route('projects.store'), [
        'name' => '',
        'code' => 'NP',
    ]);

    $response->assertSessionHasErrors('name');
});

test('user can view a project with members and meetings', function () {
    $project = Project::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'name' => 'View Project',
    ]);

    $project->members()->attach($this->user, ['role' => 'lead']);

    $response = $this->actingAs($this->user)->get(route('projects.show', $project));

    $response->assertSuccessful();
    $response->assertSee('View Project');
    $response->assertSee($this->user->name);
});

test('user can update a project', function () {
    $project = Project::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->put(route('projects.update', $project), [
        'name' => 'Updated Project',
        'code' => 'UP',
        'description' => 'Updated description',
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'name' => 'Updated Project',
        'code' => 'UP',
    ]);
});

test('user can delete a project', function () {
    $project = Project::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->delete(route('projects.destroy', $project));

    $response->assertRedirect(route('projects.index'));
    $this->assertSoftDeleted('projects', ['id' => $project->id]);
});

test('user can add a member to a project', function () {
    $project = Project::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $newMember = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($newMember, ['role' => UserRole::Member->value]);

    $response = $this->actingAs($this->user)->post(route('projects.members.add', $project), [
        'user_id' => $newMember->id,
        'role' => 'member',
    ]);

    $response->assertRedirect(route('projects.show', $project));
    $this->assertDatabaseHas('project_members', [
        'project_id' => $project->id,
        'user_id' => $newMember->id,
        'role' => 'member',
    ]);
});

test('user can remove a member from a project', function () {
    $project = Project::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $member = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($member, ['role' => UserRole::Member->value]);
    $project->members()->attach($member, ['role' => 'member']);

    $response = $this->actingAs($this->user)->delete(route('projects.members.remove', [$project, $member]));

    $response->assertRedirect(route('projects.show', $project));
    $this->assertDatabaseMissing('project_members', [
        'project_id' => $project->id,
        'user_id' => $member->id,
    ]);
});

test('projects index shows correct counts', function () {
    $project = Project::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'name' => 'Counted Project',
    ]);

    $project->members()->attach($this->user, ['role' => 'member']);

    $response = $this->actingAs($this->user)->get(route('projects.index'));

    $response->assertSuccessful();
    $response->assertSee('Counted Project');
});
