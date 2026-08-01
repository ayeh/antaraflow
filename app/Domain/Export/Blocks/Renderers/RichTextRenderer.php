<?php

declare(strict_types=1);

namespace App\Domain\Export\Blocks\Renderers;

use App\Domain\Export\Blocks\BlockRenderer;
use App\Domain\Export\Blocks\ExportContext;
use PhpOffice\PhpWord\Element\Section;

final class RichTextRenderer implements BlockRenderer
{
    public function type(): string
    {
        return 'richtext';
    }

    /** @param array<string, mixed> $settings */
    public function toHtml(array $settings, ExportContext $context): string
    {
        $content = $settings['content'] ?? '';
        $resolved = $context->tokens->resolve($content);

        // If source is 'manual_notes', pull from the MOM.
        if (($settings['source'] ?? '') === 'manual_notes' && $context->mom->manualNotes->isNotEmpty()) {
            $color = $context->primaryColor();
            $html = "<h2 style=\"font-size:13px;color:#{$color};font-weight:bold;border-bottom:1px solid #e5e7eb;padding-bottom:4px;margin-top:16px;margin-bottom:8px;\">Nota Manual</h2>";
            foreach ($context->mom->manualNotes as $note) {
                $author = $note->createdBy?->name ?? 'Unknown';
                $date = $note->created_at?->format('d M Y g:i A') ?? '';
                $html .= "<div style=\"font-size:10px;color:#6b7280;font-style:italic;margin-top:8px;\">{$author} — {$date}</div>";
                $html .= '<div style="font-size:11px;">'.nl2br(htmlspecialchars(strip_tags($note->content ?? ''))).'</div>';
            }

            return $html;
        }

        if ($resolved === '') {
            return '';
        }

        return '<div style="font-size:11px;margin-bottom:12px;">'.nl2br(htmlspecialchars($resolved)).'</div>';
    }

    /** @param array<string, mixed> $settings */
    public function toWord(array $settings, ExportContext $context, Section $section): void
    {
        if (($settings['source'] ?? '') === 'manual_notes' && $context->mom->manualNotes->isNotEmpty()) {
            $color = $context->primaryColor();
            $section->addText('Nota Manual', ['bold' => true, 'size' => 12, 'color' => $color]);

            foreach ($context->mom->manualNotes as $note) {
                $author = $note->createdBy?->name ?? 'Unknown';
                $date = $note->created_at?->format('d M Y g:i A') ?? '';
                $section->addText("{$author} — {$date}", ['size' => 9, 'italic' => true, 'color' => '6b7280']);
                $section->addText(strip_tags($note->content ?? ''), ['size' => 11]);
                $section->addTextBreak(1);
            }

            return;
        }

        $content = $context->tokens->resolve($settings['content'] ?? '');
        if ($content !== '') {
            $section->addText($content, ['size' => 11]);
        }
    }
}
