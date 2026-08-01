<?php

declare(strict_types=1);

namespace App\Domain\Export\Blocks\Renderers;

use App\Domain\Export\Blocks\BlockRenderer;
use App\Domain\Export\Blocks\ExportContext;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\SimpleType\Jc;

final class TitleRenderer implements BlockRenderer
{
    public function type(): string
    {
        return 'title';
    }

    /** @param array<string, mixed> $settings */
    public function toHtml(array $settings, ExportContext $context): string
    {
        $text = $context->tokens->resolve($settings['text'] ?? '{{title}}');
        $align = $settings['align'] ?? 'center';
        $color = $context->primaryColor();

        return "<h1 style=\"text-align:{$align};color:#{$color};font-size:16px;font-weight:bold;text-transform:uppercase;margin-bottom:4px;\">".htmlspecialchars($text).'</h1>';
    }

    /** @param array<string, mixed> $settings */
    public function toWord(array $settings, ExportContext $context, Section $section): void
    {
        $text = $context->tokens->resolve($settings['text'] ?? '{{title}}');
        $color = $context->primaryColor();
        $align = match ($settings['align'] ?? 'center') {
            'left' => Jc::START,
            'right' => Jc::END,
            default => Jc::CENTER,
        };

        $section->addText(strtoupper($text), [
            'bold' => true,
            'size' => 14,
            'color' => $color,
        ], ['alignment' => $align]);
    }
}
