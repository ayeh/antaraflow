<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Support;

/**
 * Audio containers accepted for a live chunk.
 *
 * The browser recorder produces WebM/Opus; iOS and Android record AAC in an
 * MP4 container and report it under several different type strings depending on
 * the device, so all of them have to be listed or native uploads are rejected
 * as invalid files.
 */
final class AudioChunkFormats
{
    /** @var array<int, string> */
    public const MIMETYPES = [
        'audio/webm',
        'video/webm',
        'audio/mp4',
        'audio/m4a',
        'audio/x-m4a',
        'audio/aac',
        'audio/aacp',
        'audio/3gpp',
        'audio/ogg',
        'audio/wav',
        'audio/x-wav',
        'audio/mpeg',
        'audio/mp3',
    ];

    /** Maximum size of a single chunk, in kilobytes. */
    public const MAX_KILOBYTES = 51200;
}
