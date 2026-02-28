<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Domain\Account\Models\AiProviderConfig;
use App\Domain\Account\Models\Organization;
use App\Domain\AI\Models\MomAiConversation;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Infrastructure\AI\AIProviderFactory;
use App\Infrastructure\AI\Contracts\AIProviderInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ChatService
{
    public function sendMessage(MinutesOfMeeting $mom, User $user, string $message): MomAiConversation
    {
        $mom->aiConversations()->create([
            'user_id' => $user->id,
            'role' => 'user',
            'message' => $message,
        ]);

        $context = $this->buildContext($mom, $user);

        $provider = $this->resolveProvider($mom->organization);
        $response = $provider->chat($message, $context);

        return $mom->aiConversations()->create([
            'user_id' => $user->id,
            'role' => 'assistant',
            'message' => $response,
            'provider' => $this->getProviderName($mom->organization),
        ]);
    }

    public function getHistory(MinutesOfMeeting $mom, User $user): Collection
    {
        return $mom->aiConversations()
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return array{system: string, history: array<int, array{role: string, content: string}>}
     */
    private function buildContext(MinutesOfMeeting $mom, User $user): array
    {
        $meetingContext = "Meeting: {$mom->title}";
        if ($mom->summary) {
            $meetingContext .= "\nSummary: {$mom->summary}";
        }
        if ($mom->content) {
            $meetingContext .= "\nContent: ".mb_substr($mom->content, 0, 2000);
        }

        $history = $mom->aiConversations()
            ->where('user_id', $user->id)
            ->latest()
            ->take(10)
            ->get()
            ->sortBy('created_at')
            ->map(fn (MomAiConversation $msg): array => [
                'role' => $msg->role,
                'content' => $msg->message,
            ])
            ->values()
            ->toArray();

        return [
            'system' => "You are an AI assistant helping with meeting minutes. Here is the meeting context:\n\n{$meetingContext}",
            'history' => $history,
        ];
    }

    private function resolveProvider(Organization $org): AIProviderInterface
    {
        $config = $this->getProviderConfig($org);

        if ($config) {
            return AIProviderFactory::make($config->provider, [
                'api_key' => $config->api_key_encrypted,
                'model' => $config->model,
                'base_url' => $config->base_url,
            ]);
        }

        return AIProviderFactory::make(
            config('ai.default'),
            config('ai.providers.'.config('ai.default')),
        );
    }

    private function getProviderName(Organization $org): string
    {
        $config = $this->getProviderConfig($org);

        return $config?->provider ?? config('ai.default');
    }

    private function getProviderConfig(Organization $org): ?AiProviderConfig
    {
        return AiProviderConfig::query()
            ->where('organization_id', $org->id)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();
    }
}
