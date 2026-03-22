<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Jobs;

use App\Domain\Transcription\Models\VoiceNote;
use App\Infrastructure\AI\Contracts\TranscriberInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class TranscribeVoiceNoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(
        public VoiceNote $voiceNote,
    ) {}

    public function handle(TranscriberInterface $transcriber): void
    {
        $this->voiceNote->update(['status' => 'transcribing']);

        try {
            $filePath = Storage::disk('local')->path($this->voiceNote->file_path);

            $result = $transcriber->transcribe($filePath, [
                'language' => 'en',
            ]);

            $this->voiceNote->update([
                'transcript' => $result->fullText,
                'status' => 'completed',
            ]);
        } catch (\Throwable $e) {
            $this->voiceNote->update([
                'status' => 'failed',
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->voiceNote->update(['status' => 'failed']);
    }
}
