<?php

declare(strict_types=1);

namespace App\Domain\Export\Blocks\Renderers;

use App\Domain\Export\Blocks\BlockRenderer;
use App\Domain\Export\Blocks\ExportContext;
use PhpOffice\PhpWord\Element\Section;

final class DividerRenderer implements BlockRenderer
{
    public function type(): string
    {
        return 'divider';
    }

    /** @param array<string, mixed> $settings */
    public function toHtml(array $settings, ExportContext $context): string
    {
        $color = $settings['color'] ?? 'e5e7eb';
        $thickness = $settings['thickness'] ?? 1;

        return "<hr style=\"border:none;border-top:{$thickness}px solid #{$color};margin:16px 0;\">";
    }

    /** @param array<string, mixed> $settings */
    public function toWord(array $settings, ExportContext $context, Section $section): void
    {
        $section->addTextBreak(1);
    }
}
