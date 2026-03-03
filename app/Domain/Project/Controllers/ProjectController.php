<?php

declare(strict_types=1);

namespace App\Domain\Project\Controllers;

use App\Domain\Project\Models\Project;
use App\Domain\Project\Requests\CreateProjectRequest;
use App\Domain\Project\Requests\UpdateProjectRequest;
use App\Domain\Project\Services\ProjectService;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ProjectController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private ProjectService $projectService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Project::class);

        $projects = Project::query()
            ->withCount(['meetings', 'members'])
            ->latest()
            ->paginate(20);

        return view('projects.index', compact('projects'));
    }

    public function create(): View
    {
        $this->authorize('create', Project::class);

        return view('projects.create');
    }

    public function store(CreateProjectRequest $request): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        $project = $this->projectService->create($data, $request->user());

        return redirect()->route('projects.show', $project)
            ->with('success', 'Project created successfully.');
    }

    public function show(Project $project): View
    {
        $this->authorize('view', $project);

        $project->load(['createdBy', 'members', 'meetings' => fn ($q) => $q->latest()->limit(10)]);

        $orgMembers = User::query()
            ->whereHas('organizations', fn ($q) => $q->where('organization_id', $project->organization_id))
            ->orderBy('name')
            ->get();

        return view('projects.show', compact('project', 'orgMembers'));
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('projects.edit', compact('project'));
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        $this->projectService->update($project, $data);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Project updated successfully.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $this->projectService->delete($project);

        return redirect()->route('projects.index')
            ->with('success', 'Project deleted successfully.');
    }

    public function addMember(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['nullable', 'string', 'max:50'],
        ]);

        $user = User::findOrFail($request->input('user_id'));
        $this->projectService->addMember($project, $user, $request->input('role', 'member'));

        return redirect()->route('projects.show', $project)
            ->with('success', 'Member added successfully.');
    }

    public function removeMember(Project $project, User $user): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->projectService->removeMember($project, $user);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Member removed successfully.');
    }
}
