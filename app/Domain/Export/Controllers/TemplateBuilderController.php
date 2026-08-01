<?php

declare(strict_types=1);

namespace App\Domain\Export\Controllers;

use App\Domain\Export\Blocks\BlockRegistry;
use App\Domain\Export\Models\ExportTemplate;
use App\Domain\Export\Services\TemplateRenderService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TemplateBuilderController extends Controller
{
    public function __construct(
        private readonly BlockRegistry $registry,
        private readonly TemplateRenderService $renderer,
    ) {}

    public function show(Request $request, ExportTemplate $exportTemplate): View|RedirectResponse
    {
        $this->authorizeAccess($request, $exportTemplate);

        // System templates are read-only; offer to duplicate first.
        if ($exportTemplate->is_system) {
            return redirect()
                ->route('settings.export-templates.index')
                ->with('info', __('System templates are read-only. Duplicate it first to customise.'));
        }

        $sampleMom = $this->sampleMom($request);

        return view('settings.export-templates.builder', [
            'template' => $exportTemplate,
            'blockTypes' => $this->blockTypeMeta(),
            'sampleMomId' => $sampleMom?->id,
            'systemPresets' => ExportTemplate::withoutGlobalScopes()
                ->where('is_system', true)
                ->get(['id', 'name']),
        ]);
    }

    public function saveBlocks(Request $request, ExportTemplate $exportTemplate): JsonResponse
    {
        $this->authorizeAccess($request, $exportTemplate);

        $validated = $request->validate([
            'blocks' => ['present', 'array'],
            'blocks.*.id' => ['required', 'string'],
            'blocks.*.type' => ['required', 'string'],
            'blocks.*.settings' => ['nullable', 'array'],
            'labels' => ['nullable', 'array'],
            'page_setup' => ['nullable', 'array'],
            'glossary' => ['nullable', 'array'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'font_family' => ['nullable', 'string', 'max:100'],
        ]);

        $exportTemplate->update($validated);

        return response()->json(['ok' => true, 'saved_at' => now()->toIso8601String()]);
    }

    public function preview(Request $request, ExportTemplate $exportTemplate): Response
    {
        $this->authorizeAccess($request, $exportTemplate);

        // Accept unsaved blocks from the builder (POST form with blocks_json or array input).
        if ($request->has('blocks_json')) {
            $decoded = json_decode($request->input('blocks_json'), true);
            if (is_array($decoded)) {
                $exportTemplate->blocks = $decoded;
            }
        } elseif ($request->has('blocks') && is_array($request->input('blocks'))) {
            $exportTemplate->blocks = $request->input('blocks');
        }

        $mom = $this->sampleMom($request);

        if (! $mom) {
            return response(
                '<html><body style="font-family:Arial;padding:32px;color:#6b7280;font-size:13px;">Tiada minit mesyuarat untuk pratonton. Buat satu terlebih dahulu.</body></html>',
                200,
                ['Content-Type' => 'text/html']
            );
        }

        $language = $request->input('language', $mom->output_language ?? $mom->language ?? 'ms');
        $html = $this->renderer->renderHtml($mom, $exportTemplate, $language);

        return response($html, 200, ['Content-Type' => 'text/html']);
    }

    public function uploadLogo(Request $request, ExportTemplate $exportTemplate): JsonResponse
    {
        $this->authorizeAccess($request, $exportTemplate);

        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
        ]);

        // Remove old logo if it exists and is not a system asset.
        if ($exportTemplate->logo_path && Storage::disk('public')->exists($exportTemplate->logo_path)) {
            Storage::disk('public')->delete($exportTemplate->logo_path);
        }

        $path = $request->file('logo')->store('export-template-logos', 'public');
        $exportTemplate->update(['logo_path' => $path]);

        return response()->json([
            'ok' => true,
            'logo_url' => asset('storage/'.$path),
            'logo_path' => $path,
        ]);
    }

    public function duplicate(Request $request, ExportTemplate $exportTemplate): RedirectResponse
    {
        $this->authorizeAccess($request, $exportTemplate, allowSystem: true);

        $orgId = $request->user()->current_organization_id;

        $copy = $exportTemplate->replicate(['organization_id', 'is_system', 'created_by', 'is_default', 'deleted_at']);
        $copy->name = $exportTemplate->name.' (Salinan)';
        $copy->organization_id = $orgId;
        $copy->is_system = false;
        $copy->is_default = false;
        $copy->created_by = $request->user()->id;
        $copy->save();

        return redirect()
            ->route('settings.export-templates.builder', $copy)
            ->with('success', __('Template duplicated. You can now customise it.'));
    }

    // -------------------------------------------------------------------------

    private function authorizeAccess(Request $request, ExportTemplate $exportTemplate, bool $allowSystem = false): void
    {
        if ($exportTemplate->is_system && ! $allowSystem) {
            return; // show() handles the redirect; other methods should not reach here for system templates
        }

        abort_unless(
            $exportTemplate->is_system
            || $exportTemplate->organization_id === $request->user()->current_organization_id,
            403
        );
    }

    private function sampleMom(Request $request): ?MinutesOfMeeting
    {
        if ($request->has('mom_id')) {
            return MinutesOfMeeting::find($request->integer('mom_id'));
        }

        return MinutesOfMeeting::withoutGlobalScopes()
            ->where('organization_id', $request->user()->current_organization_id)
            ->with(['createdBy', 'attendees.user', 'actionItems.assignedTo', 'extractions', 'manualNotes', 'topics', 'organization', 'series'])
            ->latest()
            ->first();
    }

    /**
     * Metadata used by the builder UI palette.
     *
     * @return array<int, array<string, string>>
     */
    private function blockTypeMeta(): array
    {
        return [
            ['type' => 'letterhead',   'label' => 'Letterhead',         'icon' => '🏛️',  'description' => 'Logo + nama organisasi'],
            ['type' => 'title',        'label' => 'Tajuk',              'icon' => '📋',  'description' => 'Tajuk mesyuarat'],
            ['type' => 'meta',         'label' => 'Maklumat Mesyuarat', 'icon' => '📅',  'description' => 'Tarikh, masa, tempat'],
            ['type' => 'attendees',    'label' => 'Kehadiran',          'icon' => '👥',  'description' => 'Hadir / Turut Hadir / Tidak Hadir'],
            ['type' => 'agenda',       'label' => 'Agenda & Kandungan', 'icon' => '📝',  'description' => 'Topik, ringkasan AI, keputusan, risiko'],
            ['type' => 'action_items', 'label' => 'Tindakan Susulan',   'icon' => '✅',  'description' => 'Jadual tindakan susulan'],
            ['type' => 'richtext',     'label' => 'Teks Bebas',         'icon' => '✍️',  'description' => 'Teks statik atau nota manual'],
            ['type' => 'action_tag',   'label' => 'Tag Tindakan',       'icon' => '🏷️',  'description' => 'Makluman / Tindakan : X'],
            ['type' => 'table',        'label' => 'Jadual',             'icon' => '📊',  'description' => 'Jadual data tersuai'],
            ['type' => 'image',        'label' => 'Imej',               'icon' => '🖼️',  'description' => 'Imej atau diagram'],
            ['type' => 'signature',    'label' => 'Tandatangan',        'icon' => '✍️',  'description' => 'Blok pengesahan dua lajur'],
            ['type' => 'divider',      'label' => 'Pemisah',            'icon' => '➖',  'description' => 'Garisan pemisah'],
            ['type' => 'pagebreak',    'label' => 'Pecahan Halaman',    'icon' => '📄',  'description' => 'Halaman baru (PDF/DOCX)'],
            ['type' => 'footer',       'label' => 'Footer',             'icon' => '🔻',  'description' => 'Teks footer + cap dijana'],
        ];
    }
}
