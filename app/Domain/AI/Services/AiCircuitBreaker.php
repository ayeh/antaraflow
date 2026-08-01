<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Short-lived cutout for provider-side quota and rate-limit failures.
 *
 * Live meetings dispatch one transcription job per audio chunk, so a provider
 * outage would otherwise be re-discovered by every chunk independently — each
 * burning its full retry allowance against an API that is certain to refuse.
 * The first failure trips the breaker and the rest fail fast until it expires.
 */
class AiCircuitBreaker
{
    private const COOLDOWN_SECONDS = 120;

    public function trip(string $feature, ?int $cooldownSeconds = null): void
    {
        Cache::put(
            $this->key($feature),
            now()->addSeconds($cooldownSeconds ?? self::COOLDOWN_SECONDS)->toIso8601String(),
            $cooldownSeconds ?? self::COOLDOWN_SECONDS,
        );
    }

    public function isOpen(string $feature): bool
    {
        return Cache::has($this->key($feature));
    }

    /** ISO-8601 timestamp the breaker stays open until, or null when closed. */
    public function openUntil(string $feature): ?string
    {
        return Cache::get($this->key($feature));
    }

    public function reset(string $feature): void
    {
        Cache::forget($this->key($feature));
    }

    private function key(string $feature): string
    {
        return "ai:circuit:{$feature}";
    }
}
