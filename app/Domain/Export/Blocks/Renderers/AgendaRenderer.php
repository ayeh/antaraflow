<?php

declare(strict_types=1);

namespace App\Domain\Export\Blocks\Renderers;

use App\Domain\AI\Models\MomExtraction;
use App\Domain\Export\Blocks\BlockRenderer;
use App\Domain\Export\Blocks\ExportContext;
use PhpOffice\PhpWord\Element\Section;

final class AgendaRenderer implements BlockRenderer
{
    public function type(): string
    {
        return 'agenda';
    }

    /** @param array<string, mixed> $settings */
    public function toHtml(array $settings, ExportContext $context): string
    {
        $color = $context->primaryColor();
        $source = $settings['source'] ?? 'topics';

        $html = "<h2 style=\"font-size:13px;color:#{$color};font-weight:bold;border-bottom:1px solid #e5e7eb;padding-bottom:4px;margin-top:16px;margin-bottom:8px;\">Agenda</h2>";

        if ($source === 'topics') {
            $context->numbering->reset();
            foreach ($context->mom->topics->sortBy('sort_order') as $topic) {
                $num = $context->numbering->next(0);
                $html .= '<div style="margin-bottom:6px;">';
                $html .= "<span style=\"font-weight:bold;color:#{$color};\">{$num}</span> ";
                $html .= '<span style="font-weight:bold;">'.htmlspecialchars($topic->title).'</span>';
                if ($topic->description) {
                    $html .= '<div style="padding-left:32px;font-size:10px;color:#6b7280;">'.htmlspecialchars($topic->description).'</div>';
                }
                $html .= '</div>';
            }
        }

        // Also render structured extraction content (decisions, discussed points) per block config
        $types = (array) ($settings['extraction_types'] ?? ['summary', 'topics', 'decisions', 'risks']);
        $byType = $context->mom->extractions->groupBy('type');

        foreach ($types as $type) {
            /** @var MomExtraction|null $extraction */
            $extraction = $byType->get($type)?->sortByDesc('created_at')->first();
            if (! $extraction) {
                continue;
            }

            $sectionLabel = match ($type) {
                'summary' => 'Rumusan',
                'topics' => 'Perkara yang Dibincangkan',
                'decisions' => 'Keputusan',
                'risks' => 'Risiko & Isu',
                default => ucfirst($type),
            };

            $html .= "<h2 style=\"font-size:13px;color:#{$color};font-weight:bold;border-bottom:1px solid #e5e7eb;padding-bottom:4px;margin-top:16px;margin-bottom:8px;\">".htmlspecialchars($sectionLabel).'</h2>';

            if ($extraction->content) {
                $html .= '<p style="font-size:11px;margin-bottom:8px;">'.nl2br(htmlspecialchars($extraction->content)).'</p>';
            }

            if (! empty($extraction->structured_data) && ! isset($extraction->structured_data['custom_template'])) {
                $html .= '<ul style="padding-left:20px;margin:0 0 8px 0;">';
                foreach ($extraction->structured_data as $item) {
                    if (is_array($item)) {
                        $title = $item['title'] ?? $item['decision'] ?? $item['risk'] ?? $item['topic'] ?? '';
                        $desc = $item['description'] ?? $item['rationale'] ?? $item['context'] ?? $item['summary'] ?? '';
                        $html .= '<li style="margin-bottom:4px;font-size:11px;"><strong>'.htmlspecialchars($title).'</strong>';
                        if ($desc) {
                            $html .= '<div style="color:#6b7280;font-size:10px;">'.htmlspecialchars($desc).'</div>';
                        }
                        $html .= '</li>';
                    } else {
                        $html .= '<li style="font-size:11px;">'.htmlspecialchars((string) $item).'</li>';
                    }
                }
                $html .= '</ul>';
            }
        }

        return $html;
    }

    /** @param array<string, mixed> $settings */
    public function toWord(array $settings, ExportContext $context, Section $section): void
    {
        $color = $context->primaryColor();
        $source = $settings['source'] ?? 'topics';

        if ($source === 'topics' && $context->mom->topics->isNotEmpty()) {
            $section->addText('Agenda', ['bold' => true, 'size' => 12, 'color' => $color]);
            $context->numbering->reset();

            foreach ($context->mom->topics->sortBy('sort_order') as $topic) {
                $num = $context->numbering->next(0);
                $run = $section->addTextRun();
                $run->addText($num.' ', ['bold' => true, 'size' => 11, 'color' => $color]);
                $run->addText($topic->title, ['bold' => true, 'size' => 11]);
                if ($topic->description) {
                    $section->addText($topic->description, ['size' => 10, 'color' => '6b7280'], ['indent' => 720]);
                }
            }
            $section->addTextBreak(1);
        }

        $types = (array) ($settings['extraction_types'] ?? ['summary', 'topics', 'decisions', 'risks']);
        $byType = $context->mom->extractions->groupBy('type');

        foreach ($types as $type) {
            $extraction = $byType->get($type)?->sortByDesc('created_at')->first();
            if (! $extraction) {
                continue;
            }

            $sectionLabel = match ($type) {
                'summary' => 'Rumusan',
                'topics' => 'Perkara yang Dibincangkan',
                'decisions' => 'Keputusan',
                'risks' => 'Risiko & Isu',
                default => ucfirst($type),
            };

            $section->addText($sectionLabel, ['bold' => true, 'size' => 12, 'color' => $color]);

            if ($extraction->content) {
                $section->addText($extraction->content, ['size' => 11]);
            }

            if (! empty($extraction->structured_data) && ! isset($extraction->structured_data['custom_template'])) {
                foreach ($extraction->structured_data as $item) {
                    if (is_array($item)) {
                        $title = $item['title'] ?? $item['decision'] ?? $item['risk'] ?? $item['topic'] ?? '';
                        $desc = $item['description'] ?? $item['rationale'] ?? $item['context'] ?? $item['summary'] ?? '';
                        $section->addText('• '.$title, ['bold' => true, 'size' => 11]);
                        if ($desc) {
                            $section->addText('  '.$desc, ['size' => 10, 'color' => '6b7280']);
                        }
                    } else {
                        $section->addText('• '.(string) $item, ['size' => 11]);
                    }
                }
            }

            $section->addTextBreak(1);
        }
    }
}
