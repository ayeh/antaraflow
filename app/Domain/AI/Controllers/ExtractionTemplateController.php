<?php

declare(strict_types=1);

namespace App\Domain\AI\Controllers;

use App\Domain\AI\Models\ExtractionTemplate;
use App\Domain\AI\Requests\CreateExtractionTemplateRequest;
use App\Domain\AI\Requests\UpdateExtractionTemplateRequest;
use App\Support\Enums\ExtractionType;
use App\Support\Enums\MeetingType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ExtractionTemplateController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('viewAny', ExtractionTemplate::class);

        $templates = ExtractionTemplate::query()
            ->with('createdBy')
            ->orderBy('extraction_type')
            ->orderBy('sort_order')
            ->get();

        return view('extraction-templates.index', compact('templates'));
    }

    public function create(): View
    {
        $this->authorize('create', ExtractionTemplate::class);

        $meetingTypes = MeetingType::cases();
        $extractionTypes = ExtractionType::cases();

        return view('extraction-templates.create', compact('meetingTypes', 'extractionTypes'));
    }

    public function store(CreateExtractionTemplateRequest $request): RedirectResponse
    {
        $this->authorize('create', ExtractionTemplate::class);

        $data = $request->validated();
        $data['organization_id'] = $request->user()->current_organization_id;
        $data['created_by'] = $request->user()->id;
        $data['is_active'] = $request->boolean('is_active', true);

        ExtractionTemplate::query()->create($data);

        return redirect()->route('extraction-templates.index')
            ->with('success', 'Extraction template created successfully.');
    }

    public function edit(ExtractionTemplate $extractionTemplate): View
    {
        $this->authorize('update', $extractionTemplate);

        $meetingTypes = MeetingType::cases();
        $extractionTypes = ExtractionType::cases();

        return view('extraction-templates.edit', compact('extractionTemplate', 'meetingTypes', 'extractionTypes'));
    }

    public function update(UpdateExtractionTemplateRequest $request, ExtractionTemplate $extractionTemplate): RedirectResponse
    {
        $this->authorize('update', $extractionTemplate);

        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        $extractionTemplate->update($data);

        return redirect()->route('extraction-templates.index')
            ->with('success', 'Extraction template updated successfully.');
    }

    public function destroy(ExtractionTemplate $extractionTemplate): RedirectResponse
    {
        $this->authorize('delete', $extractionTemplate);

        $extractionTemplate->delete();

        return redirect()->route('extraction-templates.index')
            ->with('success', 'Extraction template deleted.');
    }
}
