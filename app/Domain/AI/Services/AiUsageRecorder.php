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
        ?int $organizationId = null,
    ): void {
        try {
            AiUsageLog::query()->create([
                'organization_id' => $organizationId,
                'provider' => $provider,
                'model' => $model,
                'operation' => $operation,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $promptTokens + $completionTokens,
                'cost' => $this->pricing->chatCost($model, $promptTokens, $completionTokens),
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to record AI chat usage', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Record a transcription call. Best-effort: never throws into the caller.
     */
    public function recordTranscription(
        string $provider,
        string $model,
        float $audioSeconds,
        ?int $organizationId = null,
    ): void {
        try {
            AiUsageLog::query()->create([
                'organization_id' => $organizationId,
                'provider' => $provider,
                'model' => $model,
                'operation' => 'transcription',
                'audio_seconds' => $audioSeconds,
                'cost' => $this->pricing->transcriptionCost($model, $audioSeconds),
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to record AI transcription usage', ['error' => $e->getMessage()]);
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
