<?php

declare(strict_types=1);

namespace App\Support\Enums;

/**
 * What the transcript is needed for, which decides how much the call has to
 * return — and therefore which model answers it.
 */
enum TranscriptionMode: string
{
    /** A complete recording: needs speaker labels and segment timings. */
    case Upload = 'upload';

    /** A short live chunk: text only, timing comes from the session. */
    case Live = 'live';
}
