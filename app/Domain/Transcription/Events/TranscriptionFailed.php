<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Events;

use App\Domain\Transcription\Models\AudioTranscription;
use Illuminate\Foundation\Events\Dispatchable;

class TranscriptionFailed
{
    use Dispatchable;

    public function __construct(
        public readonly AudioTranscription $transcription,
    ) {}
}
