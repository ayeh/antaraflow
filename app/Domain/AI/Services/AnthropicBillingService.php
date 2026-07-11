<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads actual spend from Anthropic's organization Cost Report API using an
 * Admin key (sk-ant-admin-...). Mirrors OpenAiBillingService.
 */
class AnthropicBillingService
{
    private const COST_ENDPOINT = 'https://api.anthropic.com/v1/organizations/cost_report';

    public function isConfigured(): bool
    {
        return (bool) $this->adminKey();
    }

    public function monthCost(): ?float
    {
        return $this->costSince(now()->startOfMonth());
    }

    /**
     * Total USD cost reported by Anthropic from $start until now. Null when the
     * Admin key is missing or the API call fails. Cached for 15 minutes.
     */
    public function costSince(CarbonInterface $start): ?float
    {
        $key = $this->adminKey();

        if (! $key) {
            return null;
        }

        $cacheKey = 'anthropic_cost_since_'.$start->toDateString();
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return (float) $cached;
        }

        $total = 0.0;
        $page = null;
        $guard = 0;

        do {
            $response = Http::withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
            ])
                ->timeout(20)
                ->get(self::COST_ENDPOINT, array_filter([
                    'starting_at' => $start->toIso8601ZuluString(),
                    'ending_at' => now()->toIso8601ZuluString(),
                    'limit' => 31,
                    'page' => $page,
                ], fn ($value) => $value !== null));

            if ($response->failed()) {
                Log::warning('Anthropic Cost Report API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            foreach ($response->json('data', []) as $bucket) {
                foreach ($bucket['results'] ?? [] as $result) {
                    $total += (float) ($result['amount'] ?? 0);
                }
            }

            $page = $response->json('next_page');
        } while ($page && ++$guard < 10);

        $total = round($total, 4);
        Cache::put($cacheKey, $total, now()->addMinutes(15));

        return $total;
    }

    private function adminKey(): ?string
    {
        $key = config('ai.anthropic_admin_key');

        return is_string($key) && $key !== '' ? $key : null;
    }
}
