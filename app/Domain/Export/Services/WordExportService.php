<?php

declare(strict_types=1);

namespace App\Domain\Export\Services;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use PhpOffice\PhpWord\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WordExportService
{
    public function __construct(
        private readonly TemplateRenderService $renderer,
        private readonly MomTranslationService $translator,
    ) {}

    public function export(MinutesOfMeeting $meeting): StreamedResponse
    {
        $template = $this->renderer->resolveTemplate($meeting);
        $language = $meeting->output_language ?? $meeting->language ?? 'ms';

        if ($this->translator->needsTranslation($meeting, $language)) {
            $this->translator->applyTranslations($meeting, $language);
        }

        $word = $this->renderer->renderWord($meeting, $template, $language);

        $filename = "meeting-{$meeting->id}.docx";

        return response()->streamDownload(function () use ($word) {
            $writer = IOFactory::createWriter($word, 'Word2007');
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }
}
