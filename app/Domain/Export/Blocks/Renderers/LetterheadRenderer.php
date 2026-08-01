<?php

declare(strict_types=1);

namespace App\Domain\Export\Blocks\Renderers;

use App\Domain\Export\Blocks\BlockRenderer;
use App\Domain\Export\Blocks\ExportContext;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\SimpleType\Jc;

final class LetterheadRenderer implements BlockRenderer
{
    public function type(): string
    {
        return 'letterhead';
    }

    /** @param array<string, mixed> $settings */
    public function toHtml(array $settings, ExportContext $context): string
    {
        $align = $settings['align'] ?? 'center';
        $color = $context->primaryColor();
        $html = "<div style=\"text-align:{$align};margin-bottom:12px;\">";

        if (! empty($settings['logo_path'])) {
            $url = asset('storage/'.$settings['logo_path']);
            $height = $settings['logo_height'] ?? 60;
            $html .= "<img src=\"{$url}\" style=\"max-height:{$height}px;margin-bottom:8px;display:block;margin-left:auto;margin-right:auto;\" alt=\"logo\">";
        }

        $lines = $settings['lines'] ?? [];
        foreach ($lines as $i => $line) {
            $resolved = $context->tokens->resolve($line);
            $size = $i === 0 ? '16px' : '12px';
            $weight = $i === 0 ? 'bold' : 'normal';
            $colorStyle = $i === 0 ? "color:#{$color};" : 'color:#374151;';
            $html .= "<div style=\"font-size:{$size};font-weight:{$weight};{$colorStyle}text-transform:uppercase;\">".htmlspecialchars($resolved).'</div>';
        }

        $html .= '</div>';

        if ($settings['show_rule'] ?? true) {
            $html .= "<hr style=\"border:none;border-top:2px solid #{$color};margin-bottom:16px;\">";
        }

        return $html;
    }

    /** @param array<string, mixed> $settings */
    public function toWord(array $settings, ExportContext $context, Section $section): void
    {
        $color = $context->primaryColor();
        $align = match ($settings['align'] ?? 'center') {
            'left' => Jc::START,
            'right' => Jc::END,
            default => Jc::CENTER,
        };

        if (! empty($settings['logo_path'])) {
            $path = storage_path('app/public/'.$settings['logo_path']);
            if (file_exists($path)) {
                $section->addImage($path, [
                    'width' => $settings['logo_width'] ?? 80,
                    'height' => $settings['logo_height'] ?? 40,
                    'alignment' => $align,
                    'marginTop' => 0,
                    'marginBottom' => 100,
                ]);
            }
        }

        $lines = $settings['lines'] ?? [];
        foreach ($lines as $i => $line) {
            $resolved = $context->tokens->resolve($line);
            $section->addText(
                strtoupper($resolved),
                ['bold' => $i === 0, 'size' => $i === 0 ? 14 : 11, 'color' => $i === 0 ? $color : '374151'],
                ['alignment' => $align]
            );
        }

        if ($settings['show_rule'] ?? true) {
            $section->addTextBreak(1);
        }
    }
}
