<?php

declare(strict_types=1);

namespace App\Domain\Export\Blocks\Renderers;

use App\Domain\Export\Blocks\BlockRenderer;
use App\Domain\Export\Blocks\ExportContext;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpWord\Element\Section;

final class AttendeesRenderer implements BlockRenderer
{
    public function type(): string
    {
        return 'attendees';
    }

    /** @param array<string, mixed> $settings */
    public function toHtml(array $settings, ExportContext $context): string
    {
        $groups = $settings['groups'] ?? ['present', 'also_present', 'absent'];
        $color = $context->primaryColor();

        $attendees = $context->mom->attendees->sortBy('sort_order');
        $html = '';

        foreach ($groups as $group) {
            $members = $attendees->filter(fn ($a) => $a->attendance_group === $group);
            if ($members->isEmpty()) {
                continue;
            }

            $groupLabel = $context->label($group);
            $html .= "<h2 style=\"font-size:13px;color:#{$color};font-weight:bold;border-bottom:1px solid #e5e7eb;padding-bottom:4px;margin-top:16px;margin-bottom:8px;\">".htmlspecialchars($groupLabel).' :</h2>';
            $html .= $this->renderGroup($members, $context, $settings);
        }

        return $html;
    }

    /** @param array<string, mixed> $settings */
    public function toWord(array $settings, ExportContext $context, Section $section): void
    {
        $groups = $settings['groups'] ?? ['present', 'also_present', 'absent'];
        $color = $context->primaryColor();

        $attendees = $context->mom->attendees->sortBy('sort_order');

        foreach ($groups as $group) {
            $members = $attendees->filter(fn ($a) => $a->attendance_group === $group);
            if ($members->isEmpty()) {
                continue;
            }

            $section->addText($context->label($group).' :', [
                'bold' => true, 'size' => 12, 'color' => $color,
            ]);

            $i = 1;
            foreach ($members as $attendee) {
                $name = $attendee->user?->name ?? $attendee->name ?? 'Unknown';
                $position = $attendee->position ?? '';
                $orgUnit = $attendee->organization_unit ?? '';
                $annotation = $attendee->annotation ? ' '.$attendee->annotation : '';

                $run = $section->addTextRun(['indent' => 360]);
                $run->addText($i.'.  ', ['size' => 10]);

                if ($position) {
                    $run->addText($position."\n", ['size' => 10]);
                }
                if ($orgUnit) {
                    $run->addText($orgUnit."\n", ['size' => 10]);
                }
                $run->addText('('.$name.')'.$annotation, ['bold' => true, 'size' => 10]);

                if ($group === 'absent' && $attendee->absence_reason) {
                    $run->addText(' - '.$attendee->absence_reason, ['size' => 10, 'italic' => true, 'color' => '6b7280']);
                }

                $i++;
            }

            $section->addTextBreak(1);
        }
    }

    private function renderGroup(Collection $members, ExportContext $context, array $settings): string
    {
        $html = '<ol style="list-style:decimal;padding-left:24px;margin:0 0 12px 0;">';
        foreach ($members as $attendee) {
            $name = $attendee->user?->name ?? $attendee->name ?? 'Unknown';
            $position = $attendee->position ? '<div style="font-size:10px;color:#374151;">'.htmlspecialchars($attendee->position).'</div>' : '';
            $orgUnit = $attendee->organization_unit ? '<div style="font-size:10px;color:#374151;">'.htmlspecialchars($attendee->organization_unit).'</div>' : '';
            $annotation = $attendee->annotation ? ' <span style="font-style:italic;font-size:10px;color:#6b7280;">'.htmlspecialchars($attendee->annotation).'</span>' : '';
            $absenceNote = ($attendee->attendance_group === 'absent' && $attendee->absence_reason)
                ? ' <span style="font-size:10px;color:#6b7280;font-style:italic;">- '.htmlspecialchars($attendee->absence_reason).'</span>'
                : '';

            $html .= '<li style="margin-bottom:8px;">';
            $html .= $position.$orgUnit;
            $html .= '<strong>('.htmlspecialchars($name).')</strong>'.$annotation.$absenceNote;
            $html .= '</li>';
        }
        $html .= '</ol>';

        return $html;
    }
}
