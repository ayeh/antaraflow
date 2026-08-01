<?php

declare(strict_types=1);

namespace App\Domain\Export\Blocks;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use Carbon\Carbon;

/**
 * Resolves {{token}} and {{token|format}} placeholders against a MOM.
 */
final class TokenResolver
{
    public function __construct(private readonly MinutesOfMeeting $mom) {}

    /**
     * Replace all {{...}} tokens in the given string.
     */
    public function resolve(string $text): string
    {
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function (array $m) {
            [$key, $format] = array_pad(explode('|', $m[1], 2), 2, null);
            $key = trim($key);

            return (string) $this->value($key, $format ? trim($format) : null);
        }, $text) ?? $text;
    }

    private function value(string $key, ?string $format): mixed
    {
        $mom = $this->mom;

        return match ($key) {
            'title' => $mom->title,
            'mom_number' => $mom->mom_number ?? '',
            'year' => $mom->meeting_date?->format('Y') ?? '',
            'meeting_date' => $mom->meeting_date
                                    ? ($format ? $mom->meeting_date->translatedFormat($format) : $mom->meeting_date->format('d F Y'))
                                    : '',
            'start_time' => $mom->start_time ? Carbon::parse($mom->start_time)->format('g:i A') : '',
            'end_time' => $mom->end_time ? Carbon::parse($mom->end_time)->format('g:i A') : '',
            'location' => $mom->location ?? '',
            'language' => $mom->language ?? 'ms',
            'chair.name' => $mom->createdBy?->name ?? '',
            'prepared_by' => $mom->prepared_by ?? $mom->createdBy?->name ?? '',
            'organization.name' => $mom->organization?->name ?? '',
            'series.name' => $mom->series?->name ?? '',
            'status' => ucfirst(str_replace('_', ' ', $mom->status->value)),
            default => '',
        };
    }
}
