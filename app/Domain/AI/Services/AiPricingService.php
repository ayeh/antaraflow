<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

class AiPricingService
{
    /**
     * Estimate the USD cost of a chat/completion call from token counts.
     */
    public function chatCost(string $model, int $promptTokens, int $completionTokens): float
    {
        // Model names contain dots (e.g. gpt-5.4-mini), so index the map by
        // literal key rather than config() dot-notation which would split them.
        $rates = config('ai.pricing.chat', [])[$model] ?? null;

        if (! is_array($rates)) {
            return 0.0;
        }

        $inputCost = ($promptTokens / 1_000_000) * (float) ($rates['input'] ?? 0);
        $outputCost = ($completionTokens / 1_000_000) * (float) ($rates['output'] ?? 0);

        return round($inputCost + $outputCost, 6);
    }

    /**
     * Estimate the USD cost of a transcription call from audio duration.
     */
    public function transcriptionCost(string $model, float $audioSeconds): float
    {
        $rates = config('ai.pricing.transcription', [])[$model] ?? null;

        if (! is_array($rates)) {
            return 0.0;
        }

        $perMinute = (float) ($rates['per_minute'] ?? 0);

        return round(($audioSeconds / 60) * $perMinute, 6);
    }
}
