<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads actual spend from OpenAI's organization Costs API using an Admin key.
 * OpenAI exposes no reliable prepaid-balance endpoint, so balance is estimated
 * as (recorded top-up − spend since the top-up date).
 */
class OpenAiBillingService
{
    private const COSTS_ENDPOINT = 'https://api.openai.com/v1/organization/costs';

    public function isConfigured(): bool
    {
        return (bool) $this->adminKey();
    }

    public function monthCost(): ?float
    {
        return $this->costSince(now()->startOfMonth());
    }

    /**
     * Total USD cost reported by OpenAI from $start until now. Null when the
     * Admin key is missing or the API call fails. Cached for 15 minutes.
     */
    public function costSince(CarbonInterface $start): ?float
    {
        $key = $this->adminKey();

        if (! $key) {
            return null;
        }

        $cacheKey = 'openai_cost_since_'.$start->toDateString();
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return (float) $cached;
        }

        $total = 0.0;
        $page = null;
        $guard = 0;

        do {
            $response = Http::withToken($key)
                ->timeout(20)
                ->get(self::COSTS_ENDPOINT, array_filter([
                    'start_time' => $start->getTimestamp(),
                    'bucket_width' => '1d',
                    'limit' => 180,
                    'page' => $page,
                ], fn ($value) => $value !== null));

            if ($response->failed()) {
                Log::warning('OpenAI Costs API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            foreach ($response->json('data', []) as $bucket) {
                foreach ($bucket['results'] ?? [] as $result) {
                    $total += (float) ($result['amount']['value'] ?? 0);
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
        $key = config('ai.openai_admin_key');

        return is_string($key) && $key !== '' ? $key : null;
    }
}
