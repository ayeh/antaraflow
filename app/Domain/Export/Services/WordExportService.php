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
        $word = $this->buildWord($meeting);

        return response()->streamDownload(function () use ($word) {
            IOFactory::createWriter($word, 'Word2007')->save('php://output');
        }, "meeting-{$meeting->id}.docx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function generate(MinutesOfMeeting $meeting): string
    {
        ob_start();
        IOFactory::createWriter($this->buildWord($meeting), 'Word2007')->save('php://output');

        return (string) ob_get_clean();
    }

    private function buildWord(MinutesOfMeeting $meeting): \PhpOffice\PhpWord\PhpWord
    {
        $template = $this->renderer->resolveTemplate($meeting);
        $language = $meeting->output_language ?? $meeting->language ?? 'ms';

        if ($this->translator->needsTranslation($meeting, $language)) {
            $this->translator->applyTranslations($meeting, $language);
        }

        return $this->renderer->renderWord($meeting, $template, $language);
    }
}
