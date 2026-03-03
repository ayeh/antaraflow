<?php

declare(strict_types=1);

namespace App\Domain\Project\Services;

use App\Domain\Project\Models\Project;
use App\Models\User;

class ProjectService
{
    /** @param array<string, mixed> $data */
    public function create(array $data, User $user): Project
    {
        return Project::create([
            'organization_id' => $user->current_organization_id,
            'created_by' => $user->id,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(Project $project, array $data): Project
    {
        $project->update($data);

        return $project->fresh();
    }

    public function delete(Project $project): void
    {
        $project->delete();
    }

    public function addMember(Project $project, User $user, string $role = 'member'): void
    {
        $project->members()->syncWithoutDetaching([
            $user->id => ['role' => $role],
        ]);
    }

    public function removeMember(Project $project, User $user): void
    {
        $project->members()->detach($user->id);
    }
}
