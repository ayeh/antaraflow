<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Enums;

/**
 * What a device was doing in the room.
 *
 * The primary is the recording. It is the device that opened the session, it
 * is the floor below which quality cannot fall, and a sitting has exactly one.
 *
 * A satellite is a second device helping — usually a phone placed nearer the
 * far end of the table than the primary is. It is additive and never required:
 * if every satellite fails, drops out, or is killed by the operating system,
 * the result must be exactly what the sitting would have produced without one.
 */
enum ChunkRole: string
{
    case Primary = 'primary';
    case Satellite = 'satellite';
}
