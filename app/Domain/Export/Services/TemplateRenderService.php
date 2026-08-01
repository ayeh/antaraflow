<?php

declare(strict_types=1);

namespace App\Domain\Export\Services;

use App\Domain\Export\Blocks\BlockRegistry;
use App\Domain\Export\Blocks\ExportContext;
use App\Domain\Export\Models\ExportTemplate;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

final class TemplateRenderService
{
    public function __construct(private readonly BlockRegistry $registry) {}

    /**
     * Resolve the best template for a MOM:
     *   1. Template pinned on the MOM itself
     *   2. Organisation default
     *   3. System preset
     */
    public function resolveTemplate(MinutesOfMeeting $mom): ExportTemplate
    {
        if ($mom->export_template_id) {
            $pinned = ExportTemplate::withoutGlobalScopes()->find($mom->export_template_id);
            if ($pinned) {
                return $pinned;
            }
        }

        $orgDefault = ExportTemplate::withoutGlobalScopes()
            ->where('organization_id', $mom->organization_id)
            ->where('is_default', true)
            ->where('is_system', false)
            ->first();

        if ($orgDefault) {
            return $orgDefault;
        }

        $preset = ExportTemplate::withoutGlobalScopes()
            ->where('is_system', true)
            ->where('meeting_type', $mom->meeting_type?->value)
            ->first()
            ?? ExportTemplate::withoutGlobalScopes()->where('is_system', true)->first();

        // Absolute fallback: return a minimal in-memory template with no blocks
        // so the services can still emit something rather than crashing.
        return $preset ?? $this->minimalTemplate();
    }

    /**
     * Render all blocks to an HTML string for PDF generation or browser preview.
     */
    public function renderHtml(
        MinutesOfMeeting $mom,
        ExportTemplate $template,
        string $outputLanguage = 'ms',
    ): string {
        $ctx = $this->makeContext($mom, $template, $outputLanguage);
        $blocks = $this->blocks($template);
        $font = $ctx->pageSetup['font'] ?? 'Arial';
        $size = $ctx->pageSetup['size'] ?? 11;

        $css = <<<CSS
            body{margin:32px;font-family:'{$font}',Arial,sans-serif;font-size:{$size}px;color:#1f2937;line-height:1.6;}
            h1{font-size:16px;}h2{font-size:13px;}
            ol,ul{margin:0;padding-left:20px;}
            table{width:100%;border-collapse:collapse;}
            @page{size:A4;margin:25mm 20mm 20mm 25mm;}
            CSS;

        if ($template->css_overrides) {
            $css .= "\n".$template->css_overrides;
        }

        $body = '';
        foreach ($blocks as $block) {
            $type = $block['type'] ?? '';
            if (! $this->registry->has($type)) {
                continue;
            }
            $body .= $this->registry->get($type)->toHtml($block['settings'] ?? [], $ctx);
        }

        return <<<HTML
            <!DOCTYPE html>
            <html lang="{$outputLanguage}">
            <head><meta charset="UTF-8"><title>{$mom->title}</title>
            <style>{$css}</style></head>
            <body>{$body}</body>
            </html>
            HTML;
    }

    /**
     * Render all blocks into a PhpWord document.
     */
    public function renderWord(
        MinutesOfMeeting $mom,
        ExportTemplate $template,
        string $outputLanguage = 'ms',
    ): PhpWord {
        $ctx = $this->makeContext($mom, $template, $outputLanguage);
        $blocks = $this->blocks($template);
        $font = $ctx->pageSetup['font'] ?? 'Arial';

        $word = new PhpWord;
        $word->setDefaultFontName($font);
        $word->setDefaultFontSize($ctx->pageSetup['size'] ?? 11);

        $section = $word->addSection([
            'marginTop' => $ctx->pageSetup['margin_top'] ?? 1440,
            'marginBottom' => $ctx->pageSetup['margin_bottom'] ?? 1440,
            'marginLeft' => $ctx->pageSetup['margin_left'] ?? 1800,
            'marginRight' => $ctx->pageSetup['margin_right'] ?? 1440,
        ]);

        foreach ($blocks as $block) {
            $type = $block['type'] ?? '';
            if (! $this->registry->has($type)) {
                continue;
            }
            $this->registry->get($type)->toWord($block['settings'] ?? [], $ctx, $section);
        }

        // Generated-by footer
        $section->addTextBreak(2);
        $section->addText(
            $ctx->label('generated_by').' '.now()->translatedFormat('d F Y g:i A'),
            ['size' => 9, 'color' => '9ca3af'],
            ['alignment' => Jc::CENTER]
        );

        return $word;
    }

    private function makeContext(
        MinutesOfMeeting $mom,
        ExportTemplate $template,
        string $outputLanguage,
    ): ExportContext {
        $mom->loadMissing([
            'createdBy',
            'attendees.user',
            'actionItems.assignedTo',
            'extractions',
            'manualNotes.createdBy',
            'topics',
            'organization',
            'series',
        ]);

        return new ExportContext($mom, $template, $outputLanguage);
    }

    /** @return array<int, array<string, mixed>> */
    private function blocks(ExportTemplate $template): array
    {
        if (is_array($template->blocks) && count($template->blocks) > 0) {
            return $template->blocks;
        }

        // Legacy template with no blocks: emit a sensible default so existing
        // templates still produce output.
        return $this->legacyFallbackBlocks($template);
    }

    /** @return array<int, array<string, mixed>> */
    private function legacyFallbackBlocks(ExportTemplate $template): array
    {
        $blocks = [];

        if ($template->logo_path || $template->header_html) {
            $blocks[] = ['type' => 'letterhead', 'settings' => [
                'logo_path' => $template->logo_path,
                'lines' => ['{{organization.name}}'],
            ]];
        }

        $blocks[] = ['type' => 'title',     'settings' => ['text' => '{{title}}']];
        $blocks[] = ['type' => 'meta',      'settings' => []];
        $blocks[] = ['type' => 'attendees', 'settings' => []];
        $blocks[] = ['type' => 'agenda',    'settings' => []];
        $blocks[] = ['type' => 'action_items', 'settings' => []];
        $blocks[] = ['type' => 'signature', 'settings' => []];
        $blocks[] = ['type' => 'footer',    'settings' => []];

        return $blocks;
    }

    private function minimalTemplate(): ExportTemplate
    {
        $t = new ExportTemplate;
        $t->name = 'Default';
        $t->is_system = true;
        $t->blocks = null;

        return $t;
    }
}
