<?php

declare(strict_types=1);

namespace App\Domain\Export\Controllers;

use App\Domain\Export\Models\ExportTemplate;
use App\Domain\Export\Requests\StoreExportTemplateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExportTemplateController
{
    public function index(Request $request): View
    {
        $templates = ExportTemplate::query()
            ->where('organization_id', $request->user()->current_organization_id)
            ->latest()
            ->get();

        return view('settings.export-templates.index', compact('templates'));
    }

    public function create(): View
    {
        return view('settings.export-templates.create');
    }

    public function store(StoreExportTemplateRequest $request): RedirectResponse
    {
        $orgId = $request->user()->current_organization_id;

        if ($request->boolean('is_default')) {
            ExportTemplate::query()->where('organization_id', $orgId)->update(['is_default' => false]);
        }

        ExportTemplate::create(array_merge(
            $request->validated(),
            ['organization_id' => $orgId, 'is_default' => $request->boolean('is_default')]
        ));

        return redirect()->route('settings.export-templates.index')->with('success', 'Template created.');
    }

    public function edit(Request $request, ExportTemplate $exportTemplate): View
    {
        abort_unless(
            $exportTemplate->organization_id === $request->user()->current_organization_id,
            403
        );

        return view('settings.export-templates.edit', ['template' => $exportTemplate]);
    }

    public function update(StoreExportTemplateRequest $request, ExportTemplate $exportTemplate): RedirectResponse
    {
        abort_unless(
            $exportTemplate->organization_id === $request->user()->current_organization_id,
            403
        );

        $orgId = $request->user()->current_organization_id;

        if ($request->boolean('is_default')) {
            ExportTemplate::query()
                ->where('organization_id', $orgId)
                ->where('id', '!=', $exportTemplate->id)
                ->update(['is_default' => false]);
        }

        $exportTemplate->update(array_merge(
            $request->validated(),
            ['is_default' => $request->boolean('is_default')]
        ));

        return redirect()->route('settings.export-templates.index')->with('success', 'Template updated.');
    }

    public function destroy(Request $request, ExportTemplate $exportTemplate): RedirectResponse
    {
        abort_unless(
            $exportTemplate->organization_id === $request->user()->current_organization_id,
            403
        );

        $exportTemplate->delete();

        return redirect()->route('settings.export-templates.index')->with('success', 'Template deleted.');
    }
}
