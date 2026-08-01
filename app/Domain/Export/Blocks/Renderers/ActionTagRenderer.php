<?php

declare(strict_types=1);

namespace App\Domain\Export\Blocks\Renderers;

use App\Domain\Export\Blocks\BlockRenderer;
use App\Domain\Export\Blocks\ExportContext;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\SimpleType\Jc;

final class ActionTagRenderer implements BlockRenderer
{
    public function type(): string
    {
        return 'action_tag';
    }

    /** @param array<string, mixed> $settings */
    public function toHtml(array $settings, ExportContext $context): string
    {
        $tag = $context->tokens->resolve($settings['tag'] ?? $context->label('noted'));
        $color = $context->primaryColor();
        $align = $settings['align'] ?? 'right';

        return "<div style=\"text-align:{$align};font-weight:bold;font-size:11px;color:#{$color};margin:4px 0 8px 0;\">".htmlspecialchars($tag).'</div>';
    }

    /** @param array<string, mixed> $settings */
    public function toWord(array $settings, ExportContext $context, Section $section): void
    {
        $tag = $context->tokens->resolve($settings['tag'] ?? $context->label('noted'));
        $color = $context->primaryColor();
        $align = match ($settings['align'] ?? 'right') {
            'left' => Jc::START,
            'center' => Jc::CENTER,
            default => Jc::END,
        };

        $section->addText($tag, ['bold' => true, 'size' => 10, 'color' => $color], ['alignment' => $align]);
    }
}
