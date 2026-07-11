<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Domain\AI\Models\AiUsageLog;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiUsageRecorder
{
    public function __construct(
        private readonly AiPricingService $pricing,
        private readonly AiUsageContext $context,
    ) {}

    /**
     * Record a chat/completion call. Best-effort: never throws into the caller.
     */
    public function recordChat(
        string $provider,
        string $model,
        int $promptTokens,
        int $completionTokens,
        string $operation = 'chat',
        int $cachedTokens = 0,
        ?int $durationMs = null,
        string $status = 'success',
    ): void {
        $this->write([
            'provider' => $provider,
            'model' => $model,
            'operation' => $operation,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'cached_tokens' => $cachedTokens,
            'total_tokens' => $promptTokens + $completionTokens,
            'duration_ms' => $durationMs,
            'status' => $status,
            'cost' => $this->pricing->chatCost($model, $promptTokens, $completionTokens, $cachedTokens),
        ]);
    }

    /**
     * Record a failed chat/completion call so the error rate reflects reality.
     */
    public function recordChatError(string $provider, string $model, string $operation = 'chat', ?int $durationMs = null): void
    {
        $this->write([
            'provider' => $provider,
            'model' => $model,
            'operation' => $operation,
            'duration_ms' => $durationMs,
            'status' => 'error',
            'cost' => 0,
        ]);
    }

    /**
     * Record a transcription call. Best-effort: never throws into the caller.
     */
    public function recordTranscription(
        string $provider,
        string $model,
        float $audioSeconds,
        ?int $durationMs = null,
        string $status = 'success',
    ): void {
        $this->write([
            'provider' => $provider,
            'model' => $model,
            'operation' => 'transcription',
            'audio_seconds' => $audioSeconds,
            'duration_ms' => $durationMs,
            'status' => $status,
            'cost' => $this->pricing->transcriptionCost($model, $audioSeconds),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function write(array $attributes): void
    {
        try {
            AiUsageLog::query()->create([
                'organization_id' => $this->context->organizationId(),
                'user_id' => $this->context->userId(),
                'feature' => $this->context->feature(),
                'session_id' => $this->context->sessionId(),
                ...$attributes,
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to record AI usage', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Total USD spend since the start of today.
     */
    public function todaySpend(): float
    {
        return (float) AiUsageLog::query()
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('cost');
    }

    /**
     * Total USD spend since the start of the current month.
     */
    public function monthSpend(): float
    {
        return (float) AiUsageLog::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('cost');
    }

    /**
     * Average daily USD spend over the previous $days complete days (excludes
     * today), used as a rolling baseline for anomaly detection.
     */
    public function dailyBaseline(int $days = 7): float
    {
        $start = now()->subDays($days)->startOfDay();
        $end = now()->startOfDay();

        $total = (float) AiUsageLog::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->sum('cost');

        return $days > 0 ? round($total / $days, 6) : 0.0;
    }

    /**
     * USD spend per calendar day for the last $days days (oldest first),
     * keyed by Y-m-d, with zero-filled gaps — for dashboard charting.
     *
     * @return array<string, float>
     */
    public function dailySeries(int $days = 30): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $rows = AiUsageLog::query()
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, SUM(cost) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $day = now()->subDays($days - 1 - $i)->toDateString();
            $series[$day] = round((float) ($rows[$day] ?? 0), 6);
        }

        return $series;
    }
}
