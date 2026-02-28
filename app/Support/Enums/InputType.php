<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum InputType: string
{
    case Audio = 'audio';
    case Document = 'document';
    case ManualNote = 'manual_note';
    case BrowserRecording = 'browser_recording';
}
