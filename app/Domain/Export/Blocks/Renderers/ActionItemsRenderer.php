<?php

declare(strict_types=1);

namespace App\Domain\Export\Blocks\Renderers;

use App\Domain\Export\Blocks\BlockRenderer;
use App\Domain\Export\Blocks\ExportContext;
use PhpOffice\PhpWord\Element\Section;

final class ActionItemsRenderer implements BlockRenderer
{
    public function type(): string
    {
        return 'action_items';
    }

    /** @param array<string, mixed> $settings */
    public function toHtml(array $settings, ExportContext $context): string
    {
        $items = $context->mom->actionItems;
        if ($items->isEmpty()) {
            return '';
        }

        $color = $context->primaryColor();
        $html = "<h2 style=\"font-size:13px;color:#{$color};font-weight:bold;border-bottom:1px solid #e5e7eb;padding-bottom:4px;margin-top:16px;margin-bottom:8px;\">Tindakan Susulan</h2>";
        $html .= '<table style="width:100%;border-collapse:collapse;font-size:10px;margin-bottom:12px;">';
        $html .= '<thead><tr>';
        foreach (['Perkara', 'Tanggungjawab', 'Tarikh Siap', 'Status'] as $col) {
            $html .= "<th style=\"background:#f3f4f6;padding:5px 8px;text-align:left;border:1px solid #e5e7eb;color:#{$color};\">".htmlspecialchars($col).'</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($items as $item) {
            $assignee = $item->assignedTo?->name ?? 'Belum Ditetapkan';
            $due = $item->due_date?->format('d M Y') ?? '—';
            $status = ucfirst(str_replace('_', ' ', $item->status->value));

            $html .= '<tr>';
            $html .= '<td style="padding:5px 8px;border:1px solid #e5e7eb;">'.htmlspecialchars($item->title).'</td>';
            $html .= '<td style="padding:5px 8px;border:1px solid #e5e7eb;">'.htmlspecialchars($assignee).'</td>';
            $html .= '<td style="padding:5px 8px;border:1px solid #e5e7eb;">'.htmlspecialchars($due).'</td>';
            $html .= '<td style="padding:5px 8px;border:1px solid #e5e7eb;">'.htmlspecialchars($status).'</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /** @param array<string, mixed> $settings */
    public function toWord(array $settings, ExportContext $context, Section $section): void
    {
        $items = $context->mom->actionItems;
        if ($items->isEmpty()) {
            return;
        }

        $color = $context->primaryColor();
        $section->addText('Tindakan Susulan', ['bold' => true, 'size' => 12, 'color' => $color]);

        foreach ($items as $item) {
            $assignee = $item->assignedTo?->name ?? 'Belum Ditetapkan';
            $due = $item->due_date?->format('d M Y') ?? '—';
            $status = ucfirst(str_replace('_', ' ', $item->status->value));

            $run = $section->addTextRun();
            $run->addText('• '.$item->title, ['bold' => true, 'size' => 10]);
            $section->addText(
                "  {$assignee} | {$due} | {$status}",
                ['size' => 9, 'color' => '6b7280']
            );
        }

        $section->addTextBreak(1);
    }
}
