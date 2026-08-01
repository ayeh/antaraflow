<?php

declare(strict_types=1);

namespace App\Domain\Export\Blocks\Renderers;

use App\Domain\Export\Blocks\BlockRenderer;
use App\Domain\Export\Blocks\ExportContext;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\SimpleType\Jc;

final class FooterRenderer implements BlockRenderer
{
    public function type(): string
    {
        return 'footer';
    }

    /** @param array<string, mixed> $settings */
    public function toHtml(array $settings, ExportContext $context): string
    {
        $text = $context->tokens->resolve($settings['text'] ?? '');
        if ($text === '') {
            $text = $context->label('generated_by').' '.now()->translatedFormat('d F Y g:i A');
        }

        $align = $settings['align'] ?? 'center';

        return "<div style=\"text-align:{$align};font-size:9px;color:#9ca3af;border-top:1px solid #e5e7eb;padding-top:8px;margin-top:24px;\">".htmlspecialchars($text).'</div>';
    }

    /** @param array<string, mixed> $settings */
    public function toWord(array $settings, ExportContext $context, Section $section): void
    {
        $text = $context->tokens->resolve($settings['text'] ?? '');
        if ($text === '') {
            $text = $context->label('generated_by').' '.now()->translatedFormat('d F Y g:i A');
        }

        $align = match ($settings['align'] ?? 'center') {
            'left' => Jc::START,
            'right' => Jc::END,
            default => Jc::CENTER,
        };

        $section->addText($text, ['size' => 9, 'color' => '9ca3af'], ['alignment' => $align]);
    }
}
