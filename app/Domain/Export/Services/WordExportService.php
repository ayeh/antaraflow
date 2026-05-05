<?php

declare(strict_types=1);

namespace App\Domain\Export\Services;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WordExportService
{
    public function export(MinutesOfMeeting $meeting): StreamedResponse
    {
        $meeting->load(['createdBy', 'attendees.user', 'actionItems.assignedTo', 'extractions', 'manualNotes', 'topics']);

        $phpWord = new PhpWord;
        $phpWord->getDefaultFontName('Arial');

        $section = $phpWord->addSection();

        $titleStyle = ['bold' => true, 'size' => 18, 'color' => '1e1b4b'];
        $headingStyle = ['bold' => true, 'size' => 13, 'color' => '4c1d95'];
        $labelStyle = ['bold' => true, 'size' => 10, 'color' => '374151'];
        $infoStyle = ['size' => 10, 'color' => '6b7280'];
        $bodyStyle = ['size' => 11];
        $bulletStyle = ['size' => 11];

        // Title
        $section->addText($meeting->title, $titleStyle);
        $section->addTextBreak();

        // Meeting info
        if ($meeting->mom_number) {
            $section->addText('Ref No: '.$meeting->mom_number, $labelStyle);
        }
        if ($meeting->meeting_date) {
            $time = '';
            if ($meeting->start_time) {
                $time = ' | '.\Carbon\Carbon::parse($meeting->start_time)->format('g:i A');
                if ($meeting->end_time) {
                    $time .= ' – '.\Carbon\Carbon::parse($meeting->end_time)->format('g:i A');
                }
            }
            $section->addText('Tarikh: '.$meeting->meeting_date->format('d F Y').$time, $infoStyle);
        }
        if ($meeting->location) {
            $section->addText('Tempat: '.$meeting->location, $infoStyle);
        }
        if ($meeting->duration_minutes) {
            $section->addText('Tempoh: '.$meeting->duration_minutes.' minit', $infoStyle);
        }
        if ($meeting->createdBy) {
            $section->addText('Pengerusi: '.$meeting->createdBy->name, $infoStyle);
        }
        if ($meeting->prepared_by) {
            $section->addText('Disediakan oleh: '.$meeting->prepared_by, $infoStyle);
        }
        $section->addText('Status: '.ucfirst(str_replace('_', ' ', $meeting->status->value)), $infoStyle);
        $section->addTextBreak();

        // Attendees
        if ($meeting->attendees->isNotEmpty()) {
            $section->addText('Senarai Peserta', $headingStyle);
            foreach ($meeting->attendees as $attendee) {
                $name = $attendee->user?->name ?? $attendee->name ?? 'Unknown';
                $present = $attendee->is_present ? ' (Hadir)' : ' (Tidak Hadir)';
                $section->addText('• '.$name.$present, $bulletStyle);
            }
            $section->addTextBreak();
        }

        // Agenda
        if ($meeting->topics->isNotEmpty()) {
            $section->addText('Agenda', $headingStyle);
            foreach ($meeting->topics->sortBy('sort_order') as $i => $item) {
                $duration = $item->duration_minutes ? ' ['.$item->duration_minutes.' min]' : '';
                $section->addText(($i + 1).'. '.$item->title.$duration, ['bold' => true, 'size' => 11]);
                if ($item->description) {
                    $section->addText('   '.$item->description, $infoStyle);
                }
            }
            $section->addTextBreak();
        }

        $extractionsByType = $meeting->extractions->groupBy('type');
        $summary = $extractionsByType->get('summary')?->sortByDesc('created_at')->first();
        $decisions = $extractionsByType->get('decisions')?->sortByDesc('created_at')->first();
        $topics = $extractionsByType->get('topics')?->sortByDesc('created_at')->first();
        $risks = $extractionsByType->get('risks')?->sortByDesc('created_at')->first();

        // Rumusan
        if ($summary?->content) {
            $section->addText('Rumusan', $headingStyle);
            $section->addText($summary->content, $bodyStyle);
            if (! empty($summary->structured_data['key_points'])) {
                $keyPoints = is_array($summary->structured_data['key_points'])
                    ? $summary->structured_data['key_points']
                    : array_filter(explode("\n", $summary->structured_data['key_points']));
                $section->addText('Perkara Utama', ['bold' => true, 'size' => 11]);
                foreach ($keyPoints as $point) {
                    $section->addText('• '.trim($point), $bulletStyle);
                }
            }
            $section->addTextBreak();
        }

        // Nota Mesyuarat
        if ($meeting->content) {
            $section->addText('Nota Mesyuarat', $headingStyle);
            $section->addText(strip_tags($meeting->content), $bodyStyle);
            $section->addTextBreak();
        }

        // Nota Manual
        if ($meeting->manualNotes->isNotEmpty()) {
            $section->addText('Nota Manual', $headingStyle);
            foreach ($meeting->manualNotes as $note) {
                $author = $note->createdBy?->name ?? 'Unknown';
                $date = $note->created_at?->format('d M Y g:i A') ?? '';
                $section->addText("{$author} — {$date}", ['size' => 10, 'italic' => true, 'color' => '6b7280']);
                $section->addText(strip_tags($note->content ?? ''), $bodyStyle);
                $section->addTextBreak();
            }
        }

        // Perkara yang Dibincangkan
        if ($topics) {
            $section->addText('Perkara yang Dibincangkan', $headingStyle);
            $items = (! empty($topics->structured_data) && ! isset($topics->structured_data['custom_template']))
                ? $topics->structured_data
                : [];
            if ($items) {
                foreach ($items as $item) {
                    if (is_array($item)) {
                        $title = $item['title'] ?? $item['topic'] ?? '';
                        $desc = $item['description'] ?? $item['summary'] ?? '';
                        $section->addText('• '.$title, ['bold' => true, 'size' => 11]);
                        if ($desc) {
                            $section->addText('  '.$desc, $infoStyle);
                        }
                    } else {
                        $section->addText('• '.(string) $item, $bulletStyle);
                    }
                }
            } elseif ($topics->content) {
                $section->addText($topics->content, $bodyStyle);
            }
            $section->addTextBreak();
        }

        // Keputusan
        if ($decisions) {
            $section->addText('Keputusan', $headingStyle);
            $items = (! empty($decisions->structured_data) && ! isset($decisions->structured_data['custom_template']))
                ? $decisions->structured_data
                : [];
            if ($items) {
                foreach ($items as $item) {
                    if (is_array($item)) {
                        $text = $item['title'] ?? $item['decision'] ?? '';
                        $context = $item['description'] ?? $item['rationale'] ?? $item['context'] ?? '';
                        $section->addText('• '.$text, ['bold' => true, 'size' => 11]);
                        if ($context) {
                            $section->addText('  '.$context, $infoStyle);
                        }
                    } else {
                        $section->addText('• '.(string) $item, $bulletStyle);
                    }
                }
            } elseif ($decisions->content) {
                $section->addText($decisions->content, $bodyStyle);
            }
            $section->addTextBreak();
        }

        // Risiko & Isu
        if ($risks) {
            $section->addText('Risiko & Isu', $headingStyle);
            $items = (! empty($risks->structured_data) && ! isset($risks->structured_data['custom_template']))
                ? $risks->structured_data
                : [];
            if ($items) {
                foreach ($items as $item) {
                    if (is_array($item)) {
                        $text = $item['title'] ?? $item['risk'] ?? '';
                        $mitigation = $item['description'] ?? $item['mitigation'] ?? '';
                        $section->addText('• '.$text, ['bold' => true, 'size' => 11]);
                        if ($mitigation) {
                            $section->addText('  '.$mitigation, $infoStyle);
                        }
                    } else {
                        $section->addText('• '.(string) $item, $bulletStyle);
                    }
                }
            } elseif ($risks->content) {
                $section->addText($risks->content, $bodyStyle);
            }
            $section->addTextBreak();
        }

        // Tindakan Susulan
        if ($meeting->actionItems->isNotEmpty()) {
            $section->addText('Tindakan Susulan', $headingStyle);
            foreach ($meeting->actionItems as $item) {
                $assignee = $item->assignedTo?->name ?? 'Belum Ditetapkan';
                $priority = ucfirst($item->priority?->value ?? '');
                $status = ucfirst(str_replace('_', ' ', $item->status->value));
                $due = $item->due_date?->format('d M Y') ?? '—';
                $section->addText('• '.$item->title, ['bold' => true, 'size' => 11]);
                $section->addText("  Tanggungjawab: {$assignee} | Keutamaan: {$priority} | Status: {$status} | Tarikh Siap: {$due}", $infoStyle);
            }
            $section->addTextBreak();
        }

        // Mesyuarat Seterusnya
        $section->addText('Mesyuarat Seterusnya', $headingStyle);
        $section->addText('Tarikh & Masa : _______________________________________________', $infoStyle);
        $section->addText('Tempat        : _______________________________________________', $infoStyle);
        $section->addTextBreak();

        // Pengesahan / Signature
        $section->addText('Pengesahan', $headingStyle);
        $section->addTextBreak(2);

        $preparedBy = $meeting->prepared_by ?? $meeting->createdBy?->name ?? '________________';
        $chairName = $meeting->createdBy?->name ?? '________________';

        $table = $section->addTable(['borderSize' => 0, 'borderColor' => 'ffffff']);
        $table->addRow();
        $cell1 = $table->addCell(4500);
        $cell1->addText('_________________________________', $bodyStyle);
        $cell1->addText($preparedBy, ['bold' => true, 'size' => 11]);
        $cell1->addText('Disediakan oleh / Setiausaha', $infoStyle);
        $cell1->addText('Tarikh: ____________________', $infoStyle);

        $cell2 = $table->addCell(4500);
        $cell2->addText('_________________________________', $bodyStyle);
        $cell2->addText($chairName, ['bold' => true, 'size' => 11]);
        $cell2->addText('Disahkan oleh / Pengerusi', $infoStyle);
        $cell2->addText('Tarikh: ____________________', $infoStyle);

        $section->addTextBreak(2);
        $section->addText(
            'Dijana oleh antaraNote pada '.now()->format('d F Y g:i A'),
            ['size' => 9, 'color' => '9ca3af'],
            ['alignment' => Jc::CENTER]
        );

        $filename = "meeting-{$meeting->id}.docx";

        return response()->streamDownload(function () use ($phpWord) {
            $writer = IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }
}
