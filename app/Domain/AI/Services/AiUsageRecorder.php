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
            'cost' => $this->pricing->chatCost($model, $promptTokens, $completionTokens),
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
}
