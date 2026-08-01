<?php

declare(strict_types=1);

namespace App\Domain\Export\Blocks\Renderers;

use App\Domain\Export\Blocks\BlockRenderer;
use App\Domain\Export\Blocks\ExportContext;
use PhpOffice\PhpWord\Element\Section;

final class SignatureRenderer implements BlockRenderer
{
    public function type(): string
    {
        return 'signature';
    }

    /** @param array<string, mixed> $settings */
    public function toHtml(array $settings, ExportContext $context): string
    {
        $columns = $settings['columns'] ?? $this->defaultColumns($context);
        $color = $context->primaryColor();

        $html = "<h2 style=\"font-size:13px;color:#{$color};font-weight:bold;border-bottom:1px solid #e5e7eb;padding-bottom:4px;margin-top:24px;margin-bottom:16px;\">Pengesahan</h2>";
        $html .= '<table style="width:100%;border-collapse:collapse;">';
        $html .= '<tr>';

        foreach ($columns as $col) {
            $label = $context->label($col['label'] ?? '') ?: ($col['label'] ?? '');
            $name = $context->tokens->resolve($col['name'] ?? '');
            $position = $context->tokens->resolve($col['position'] ?? '');
            $showDate = $col['show_date'] ?? true;

            $html .= '<td style="padding:4px 16px 4px 0;vertical-align:bottom;width:'.floor(100 / count($columns)).'%;">';
            $html .= '<div style="border-bottom:1px solid #374151;margin-top:48px;margin-bottom:4px;"></div>';
            if ($name) {
                $html .= '<div style="font-weight:bold;font-size:11px;">('.htmlspecialchars(strtoupper($name)).')</div>';
            }
            if ($position) {
                $html .= '<div style="font-size:10px;color:#374151;">'.htmlspecialchars($position).'</div>';
            }
            $html .= '<div style="font-size:10px;color:#6b7280;">'.htmlspecialchars($label).'</div>';
            if ($showDate) {
                $html .= '<div style="font-size:10px;color:#6b7280;">Tarikh: ___________________</div>';
            }
            $html .= '</td>';
        }

        $html .= '</tr></table>';

        return $html;
    }

    /** @param array<string, mixed> $settings */
    public function toWord(array $settings, ExportContext $context, Section $section): void
    {
        $columns = $settings['columns'] ?? $this->defaultColumns($context);
        $color = $context->primaryColor();

        $section->addText('Pengesahan', ['bold' => true, 'size' => 12, 'color' => $color]);
        $section->addTextBreak(2);

        $table = $section->addTable(['borderSize' => 0, 'borderColor' => 'ffffff']);
        $table->addRow();
        $cellWidth = intval(9000 / max(count($columns), 1));

        foreach ($columns as $col) {
            $label = $context->label($col['label'] ?? '') ?: ($col['label'] ?? '');
            $name = $context->tokens->resolve($col['name'] ?? '');
            $position = $context->tokens->resolve($col['position'] ?? '');
            $showDate = $col['show_date'] ?? true;

            $cell = $table->addCell($cellWidth);
            $cell->addText('_________________________________', ['size' => 11]);
            if ($name) {
                $cell->addText('('.strtoupper($name).')', ['bold' => true, 'size' => 11]);
            }
            if ($position) {
                $cell->addText($position, ['size' => 10, 'color' => '374151']);
            }
            $cell->addText($label, ['size' => 10, 'color' => '6b7280']);
            if ($showDate) {
                $cell->addText('Tarikh: ____________________', ['size' => 10, 'color' => '6b7280']);
            }
        }

        $section->addTextBreak(2);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultColumns(ExportContext $context): array
    {
        return [
            ['label' => 'prepared_by', 'name' => '{{prepared_by}}', 'position' => '', 'show_date' => true],
            ['label' => 'approved_by', 'name' => '{{chair.name}}', 'position' => '', 'show_date' => true],
        ];
    }
}
