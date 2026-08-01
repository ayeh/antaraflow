<?php

declare(strict_types=1);

namespace App\Domain\Export\Blocks\Renderers;

use App\Domain\Export\Blocks\BlockRenderer;
use App\Domain\Export\Blocks\ExportContext;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\SimpleType\Jc;

final class ImageRenderer implements BlockRenderer
{
    public function type(): string
    {
        return 'image';
    }

    /** @param array<string, mixed> $settings */
    public function toHtml(array $settings, ExportContext $context): string
    {
        $path = $settings['path'] ?? '';
        if (! $path) {
            return '';
        }

        $url = asset('storage/'.$path);
        $maxWidth = $settings['max_width'] ?? '100%';
        $align = $settings['align'] ?? 'center';
        $caption = $settings['caption'] ?? '';

        $html = "<div style=\"text-align:{$align};margin:12px 0;\">";
        $html .= "<img src=\"{$url}\" style=\"max-width:{$maxWidth};height:auto;\" alt=\"".htmlspecialchars($caption).'">';
        if ($caption) {
            $html .= '<div style="font-size:10px;color:#6b7280;margin-top:4px;">'.htmlspecialchars($caption).'</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /** @param array<string, mixed> $settings */
    public function toWord(array $settings, ExportContext $context, Section $section): void
    {
        $path = $settings['path'] ?? '';
        if (! $path) {
            return;
        }

        $absolutePath = storage_path('app/public/'.$path);
        if (! file_exists($absolutePath)) {
            return;
        }

        $align = match ($settings['align'] ?? 'center') {
            'left' => Jc::START,
            'right' => Jc::END,
            default => Jc::CENTER,
        };

        $section->addImage($absolutePath, [
            'width' => $settings['width'] ?? 400,
            'alignment' => $align,
        ]);

        if (! empty($settings['caption'])) {
            $section->addText($settings['caption'], ['size' => 9, 'italic' => true, 'color' => '6b7280'], ['alignment' => $align]);
        }

        $section->addTextBreak(1);
    }
}
