<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Domain\AI\Models\AiModelPrice;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves per-model pricing from a DB registry (exact match, then regex by
 * priority) and falls back to the built-in config map. Pricing drift and new
 * models are handled as data (edit the registry), not code changes.
 */
class AiPricingService
{
    private const CACHE_KEY = 'ai_model_prices_registry';

    /**
     * Estimate the USD cost of a chat/completion call. Cached prompt tokens are
     * billed at the model's cached rate when one is defined, else at input rate.
     */
    public function chatCost(string $model, int $promptTokens, int $completionTokens, int $cachedTokens = 0): float
    {
        $rates = $this->resolveChatRates($model);

        if ($rates === null) {
            return 0.0;
        }

        $billableCached = max(0, min($cachedTokens, $promptTokens));
        $uncachedPrompt = $promptTokens - $billableCached;

        $cost = ($uncachedPrompt / 1_000_000) * $rates['input']
            + ($completionTokens / 1_000_000) * $rates['output'];

        $cachedRate = $rates['cached_input'] ?? $rates['input'];
        $cost += ($billableCached / 1_000_000) * $cachedRate;

        return round($cost, 6);
    }

    /**
     * Estimate the USD cost of a transcription call from audio duration.
     */
    public function transcriptionCost(string $model, float $audioSeconds): float
    {
        $perMinute = $this->resolveTranscriptionRate($model);

        if ($perMinute === null) {
            return 0.0;
        }

        return round(($audioSeconds / 60) * $perMinute, 6);
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{input: float, output: float, cached_input: ?float}|null
     */
    private function resolveChatRates(string $model): ?array
    {
        foreach ($this->registry() as $price) {
            if ($price->input_per_mtok === null) {
                continue;
            }

            if ($this->matches($price, $model)) {
                return [
                    'input' => (float) $price->input_per_mtok,
                    'output' => (float) $price->output_per_mtok,
                    'cached_input' => $price->cached_input_per_mtok !== null ? (float) $price->cached_input_per_mtok : null,
                ];
            }
        }

        // Built-in config fallback. Indexed by literal key — model names contain
        // dots (gpt-5.4-mini), so config() dot-notation would split them.
        $rates = config('ai.pricing.chat', [])[$model] ?? null;

        if (is_array($rates)) {
            return [
                'input' => (float) ($rates['input'] ?? 0),
                'output' => (float) ($rates['output'] ?? 0),
                'cached_input' => null,
            ];
        }

        return null;
    }

    private function resolveTranscriptionRate(string $model): ?float
    {
        foreach ($this->registry() as $price) {
            if ($price->per_minute === null) {
                continue;
            }

            if ($this->matches($price, $model)) {
                return (float) $price->per_minute;
            }
        }

        $rates = config('ai.pricing.transcription', [])[$model] ?? null;

        return is_array($rates) && isset($rates['per_minute']) ? (float) $rates['per_minute'] : null;
    }

    private function matches(AiModelPrice $price, string $model): bool
    {
        if (! $price->is_regex) {
            return $price->pattern === $model;
        }

        return @preg_match('#'.$price->pattern.'#', $model) === 1;
    }

    /**
     * Registry rows ordered so the best match wins: exact matches before regex,
     * then by descending priority. Cached for five minutes.
     *
     * @return \Illuminate\Support\Collection<int, AiModelPrice>
     */
    private function registry(): \Illuminate\Support\Collection
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(5), function () {
            return AiModelPrice::query()
                ->orderBy('is_regex')
                ->orderByDesc('priority')
                ->get();
        });
    }
}
