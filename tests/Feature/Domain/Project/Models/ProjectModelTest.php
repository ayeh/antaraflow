<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Project\Models\Project;
use App\Domain\Project\Models\ProjectMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('project can be created with factory', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $project = Project::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    expect($project)->toBeInstanceOf(Project::class)
        ->and($project->name)->toBeString()
        ->and($project->is_active)->toBeTrue();
});

test('project belongs to organization', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $project = Project::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    expect($project->organization->id)->toBe($org->id);
});

test('project belongs to creator', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $project = Project::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    expect($project->createdBy->id)->toBe($user->id);
});

test('project has members relationship', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $project = Project::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    $member = User::factory()->create(['current_organization_id' => $org->id]);
    $project->members()->attach($member, ['role' => 'member']);

    expect($project->members)->toHaveCount(1)
        ->and($project->members->first()->pivot->role)->toBe('member');
});

test('project has project members relationship', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $project = Project::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    $member = User::factory()->create(['current_organization_id' => $org->id]);
    $project->members()->attach($member, ['role' => 'lead']);

    expect($project->projectMembers)->toHaveCount(1)
        ->and($project->projectMembers->first())->toBeInstanceOf(ProjectMember::class)
        ->and($project->projectMembers->first()->role)->toBe('lead');
});

test('project has meetings relationship', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $project = Project::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    expect($project->meetings())->toBeInstanceOf(
        \Illuminate\Database\Eloquent\Relations\HasMany::class
    );
});

test('project casts settings to array', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $project = Project::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
        'settings' => ['key' => 'value'],
    ]);

    expect($project->settings)->toBeArray()
        ->and($project->settings['key'])->toBe('value');
});

test('project member belongs to project and user', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $project = Project::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    $member = User::factory()->create(['current_organization_id' => $org->id]);
    $project->members()->attach($member, ['role' => 'viewer']);

    $projectMember = ProjectMember::first();

    expect($projectMember->project->id)->toBe($project->id)
        ->and($projectMember->user->id)->toBe($member->id);
});
