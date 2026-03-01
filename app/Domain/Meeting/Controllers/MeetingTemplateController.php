<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Controllers;

use App\Domain\Meeting\Models\MeetingTemplate;
use App\Domain\Meeting\Requests\CreateMeetingTemplateRequest;
use App\Domain\Meeting\Requests\UpdateMeetingTemplateRequest;
use App\Domain\Meeting\Services\MeetingTemplateService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class MeetingTemplateController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private MeetingTemplateService $meetingTemplateService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', MeetingTemplate::class);

        $templates = MeetingTemplate::query()
            ->with('createdBy')
            ->latest()
            ->get();

        return view('meeting-templates.index', compact('templates'));
    }

    public function create(): View
    {
        $this->authorize('create', MeetingTemplate::class);

        return view('meeting-templates.create');
    }

    public function store(CreateMeetingTemplateRequest $request): RedirectResponse
    {
        $this->authorize('create', MeetingTemplate::class);

        $data = $request->validated();
        $data['is_shared'] = $request->boolean('is_shared', true);
        $data['is_default'] = $request->boolean('is_default', false);

        $template = $this->meetingTemplateService->create($data, $request->user());

        return redirect()->route('meeting-templates.show', $template)
            ->with('success', 'Template created successfully.');
    }

    public function show(MeetingTemplate $meetingTemplate): View
    {
        $this->authorize('view', $meetingTemplate);

        $meetingTemplate->load('createdBy');

        return view('meeting-templates.show', compact('meetingTemplate'));
    }

    public function edit(MeetingTemplate $meetingTemplate): View
    {
        $this->authorize('update', $meetingTemplate);

        return view('meeting-templates.edit', compact('meetingTemplate'));
    }

    public function update(UpdateMeetingTemplateRequest $request, MeetingTemplate $meetingTemplate): RedirectResponse
    {
        $this->authorize('update', $meetingTemplate);

        $data = $request->validated();
        $data['is_shared'] = $request->boolean('is_shared', true);
        $data['is_default'] = $request->boolean('is_default', false);

        $this->meetingTemplateService->update($meetingTemplate, $data);

        return redirect()->route('meeting-templates.show', $meetingTemplate)
            ->with('success', 'Template updated successfully.');
    }

    public function destroy(MeetingTemplate $meetingTemplate): RedirectResponse
    {
        $this->authorize('delete', $meetingTemplate);

        $this->meetingTemplateService->delete($meetingTemplate);

        return redirect()->route('meeting-templates.index')
            ->with('success', 'Template deleted successfully.');
    }
}
