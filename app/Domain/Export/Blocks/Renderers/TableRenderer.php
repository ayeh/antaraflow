<?php

declare(strict_types=1);

namespace App\Domain\Export\Blocks\Renderers;

use App\Domain\Export\Blocks\BlockRenderer;
use App\Domain\Export\Blocks\ExportContext;
use PhpOffice\PhpWord\Element\Section;

final class TableRenderer implements BlockRenderer
{
    public function type(): string
    {
        return 'table';
    }

    /** @param array<string, mixed> $settings */
    public function toHtml(array $settings, ExportContext $context): string
    {
        $headers = (array) ($settings['headers'] ?? []);
        $rows = (array) ($settings['rows'] ?? []);
        $color = $context->primaryColor();

        if (empty($rows)) {
            return '';
        }

        $html = '<table style="width:100%;border-collapse:collapse;font-size:10px;margin-bottom:12px;">';

        if ($headers) {
            $html .= '<thead><tr>';
            foreach ($headers as $h) {
                $html .= "<th style=\"background:#f3f4f6;padding:5px 8px;text-align:left;border:1px solid #e5e7eb;color:#{$color};\">".htmlspecialchars($h).'</th>';
            }
            $html .= '</tr></thead>';
        }

        $html .= '<tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ((array) $row as $cell) {
                $resolved = $context->tokens->resolve((string) $cell);
                $html .= '<td style="padding:5px 8px;border:1px solid #e5e7eb;">'.nl2br(htmlspecialchars($resolved)).'</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    /** @param array<string, mixed> $settings */
    public function toWord(array $settings, ExportContext $context, Section $section): void
    {
        $headers = (array) ($settings['headers'] ?? []);
        $rows = (array) ($settings['rows'] ?? []);
        $color = $context->primaryColor();

        if (empty($rows)) {
            return;
        }

        $colCount = max(count($headers), count(reset($rows) ?: []));
        $cellWidth = intval(9000 / max($colCount, 1));

        $table = $section->addTable(['borderSize' => 6, 'borderColor' => 'e5e7eb']);

        if ($headers) {
            $table->addRow();
            foreach ($headers as $h) {
                $cell = $table->addCell($cellWidth, ['bgColor' => 'f3f4f6']);
                $cell->addText($h, ['bold' => true, 'size' => 9, 'color' => $color]);
            }
        }

        foreach ($rows as $row) {
            $table->addRow();
            foreach ((array) $row as $cell) {
                $resolved = $context->tokens->resolve((string) $cell);
                $c = $table->addCell($cellWidth);
                $c->addText($resolved, ['size' => 9]);
            }
        }

        $section->addTextBreak(1);
    }
}
