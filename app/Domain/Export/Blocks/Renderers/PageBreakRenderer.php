<?php

declare(strict_types=1);

namespace App\Domain\Export\Blocks\Renderers;

use App\Domain\Export\Blocks\BlockRenderer;
use App\Domain\Export\Blocks\ExportContext;
use PhpOffice\PhpWord\Element\Section;

final class PageBreakRenderer implements BlockRenderer
{
    public function type(): string
    {
        return 'pagebreak';
    }

    /** @param array<string, mixed> $settings */
    public function toHtml(array $settings, ExportContext $context): string
    {
        // In HTML preview, show a visual page-break indicator.
        return '<div style="border-top:2px dashed #d1d5db;margin:24px 0;text-align:center;"><span style="font-size:9px;color:#9ca3af;background:#fff;padding:0 8px;">— page break —</span></div>';
    }

    /** @param array<string, mixed> $settings */
    public function toWord(array $settings, ExportContext $context, Section $section): void
    {
        $section->addPageBreak();
    }
}
