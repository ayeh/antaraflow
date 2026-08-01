<?php

declare(strict_types=1);

namespace App\Domain\Export\Blocks\Renderers;

use App\Domain\Export\Blocks\BlockRenderer;
use App\Domain\Export\Blocks\ExportContext;
use PhpOffice\PhpWord\Element\Section;

final class MetaRenderer implements BlockRenderer
{
    public function type(): string
    {
        return 'meta';
    }

    /** @param array<string, mixed> $settings */
    public function toHtml(array $settings, ExportContext $context): string
    {
        $fields = $settings['fields'] ?? $this->defaultFields($context);
        $separator = $settings['separator'] ?? ':';
        $color = $context->primaryColor();

        $html = '<table style="width:100%;margin-bottom:12px;font-size:11px;border-collapse:collapse;">';
        foreach ($fields as $field) {
            $label = $context->label($field['label'] ?? '') ?: ($field['label'] ?? '');
            $value = $context->tokens->resolve($field['token'] ?? '');
            if ($value === '') {
                continue;
            }
            $html .= '<tr>';
            $html .= "<td style=\"width:150px;font-weight:bold;color:#{$color};padding:1px 8px 1px 0;vertical-align:top;\">".htmlspecialchars($label)." {$separator}</td>";
            $html .= '<td style="padding:1px 0;color:#374151;">'.htmlspecialchars($value).'</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';
        $html .= '<hr style="border:none;border-top:1px solid #e5e7eb;margin-bottom:16px;">';

        return $html;
    }

    /** @param array<string, mixed> $settings */
    public function toWord(array $settings, ExportContext $context, Section $section): void
    {
        $fields = $settings['fields'] ?? $this->defaultFields($context);
        $color = $context->primaryColor();
        $separator = $settings['separator'] ?? ':';

        foreach ($fields as $field) {
            $label = $context->label($field['label'] ?? '') ?: ($field['label'] ?? '');
            $value = $context->tokens->resolve($field['token'] ?? '');
            if ($value === '') {
                continue;
            }

            $run = $section->addTextRun();
            $run->addText($label.' '.$separator.' ', ['bold' => true, 'size' => 10, 'color' => $color]);
            $run->addText($value, ['size' => 10, 'color' => '374151']);
        }

        $section->addTextBreak(1);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function defaultFields(ExportContext $context): array
    {
        return [
            ['label' => 'date',     'token' => '{{meeting_date|d F Y (l)}}'],
            ['label' => 'time',     'token' => '{{start_time}} hingga {{end_time}}'],
            ['label' => 'venue',    'token' => '{{location}}'],
        ];
    }
}
