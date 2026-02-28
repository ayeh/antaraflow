<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Domain\Account\Models\AiProviderConfig;
use App\Domain\Account\Models\Organization;
use App\Domain\AI\Models\MomExtraction;
use App\Domain\AI\Models\MomTopic;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Infrastructure\AI\AIProviderFactory;
use App\Infrastructure\AI\Contracts\AIProviderInterface;

class ExtractionService
{
    public function extractAll(MinutesOfMeeting $mom): void
    {
        $provider = $this->resolveProvider($mom->organization);
        $providerConfig = $this->getProviderConfig($mom->organization);
        $text = $this->getFullText($mom);

        if (empty($text)) {
            return;
        }

        $providerName = $providerConfig?->provider ?? config('ai.default');
        $modelName = $providerConfig?->model ?? config('ai.providers.'.$providerName.'.model');

        $this->extractSummary($mom, $provider, $providerName, $modelName, $text);
        $this->extractActionItems($mom, $provider, $providerName, $modelName, $text);
        $this->extractDecisions($mom, $provider, $providerName, $modelName, $text);
        $this->extractTopics($mom, $provider, $providerName, $modelName, $text);
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

    private function getProviderConfig(Organization $org): ?AiProviderConfig
    {
        return AiProviderConfig::query()
            ->where('organization_id', $org->id)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();
    }

    private function getFullText(MinutesOfMeeting $mom): string
    {
        $parts = [];

        if ($mom->content) {
            $parts[] = $mom->content;
        }

        foreach ($mom->transcriptions()->where('status', 'completed')->get() as $transcription) {
            if ($transcription->full_text) {
                $parts[] = $transcription->full_text;
            }
        }

        foreach ($mom->manualNotes as $note) {
            $parts[] = $note->content;
        }

        return implode("\n\n", $parts);
    }

    private function extractSummary(
        MinutesOfMeeting $mom,
        AIProviderInterface $provider,
        string $providerName,
        string $modelName,
        string $text,
    ): void {
        $result = $provider->summarize($text);

        MomExtraction::query()->updateOrCreate(
            ['minutes_of_meeting_id' => $mom->id, 'type' => 'summary'],
            [
                'content' => $result->summary,
                'structured_data' => ['key_points' => $result->keyPoints],
                'provider' => $providerName,
                'model' => $modelName,
                'confidence_score' => $result->confidenceScore,
            ],
        );
    }

    private function extractActionItems(
        MinutesOfMeeting $mom,
        AIProviderInterface $provider,
        string $providerName,
        string $modelName,
        string $text,
    ): void {
        $items = $provider->extractActionItems($text);

        $structuredData = array_map(
            fn ($item) => [
                'title' => $item->title,
                'description' => $item->description,
                'assignee' => $item->assignee,
                'due_date' => $item->dueDate,
                'priority' => $item->priority,
            ],
            $items,
        );

        $content = implode("\n", array_map(
            fn ($item) => "- {$item->title}".($item->assignee ? " (Assigned: {$item->assignee})" : ''),
            $items,
        ));

        MomExtraction::query()->updateOrCreate(
            ['minutes_of_meeting_id' => $mom->id, 'type' => 'action_items'],
            [
                'content' => $content,
                'structured_data' => $structuredData,
                'provider' => $providerName,
                'model' => $modelName,
            ],
        );
    }

    private function extractDecisions(
        MinutesOfMeeting $mom,
        AIProviderInterface $provider,
        string $providerName,
        string $modelName,
        string $text,
    ): void {
        $decisions = $provider->extractDecisions($text);

        $structuredData = array_map(
            fn ($decision) => [
                'decision' => $decision->decision,
                'context' => $decision->context,
                'made_by' => $decision->madeBy,
            ],
            $decisions,
        );

        $content = implode("\n", array_map(
            fn ($decision) => "- {$decision->decision}",
            $decisions,
        ));

        MomExtraction::query()->updateOrCreate(
            ['minutes_of_meeting_id' => $mom->id, 'type' => 'decisions'],
            [
                'content' => $content,
                'structured_data' => $structuredData,
                'provider' => $providerName,
                'model' => $modelName,
            ],
        );
    }

    private function extractTopics(
        MinutesOfMeeting $mom,
        AIProviderInterface $provider,
        string $providerName,
        string $modelName,
        string $text,
    ): void {
        $prompt = "Identify the main topics discussed in the following meeting transcript. Return a JSON array where each item has:\n"
            ."- \"title\": Topic title\n"
            ."- \"description\": Brief description of what was discussed\n"
            ."- \"duration_minutes\": Estimated duration in minutes (optional)\n\n"
            ."Respond with ONLY a valid JSON array, no other text.\n\n"
            ."Transcript:\n{$text}";

        $response = $provider->chat($prompt, ['system' => 'You are an expert at identifying discussion topics from meetings. Always respond with valid JSON.']);
        $topics = json_decode($response, true) ?? [];

        MomTopic::query()->where('minutes_of_meeting_id', $mom->id)->delete();

        foreach ($topics as $index => $topic) {
            MomTopic::query()->create([
                'minutes_of_meeting_id' => $mom->id,
                'title' => $topic['title'] ?? '',
                'description' => $topic['description'] ?? null,
                'duration_minutes' => $topic['duration_minutes'] ?? null,
                'sort_order' => $index,
            ]);
        }

        $content = implode("\n", array_map(
            fn ($topic) => '- '.($topic['title'] ?? ''),
            $topics,
        ));

        MomExtraction::query()->updateOrCreate(
            ['minutes_of_meeting_id' => $mom->id, 'type' => 'topics'],
            [
                'content' => $content,
                'structured_data' => $topics,
                'provider' => $providerName,
                'model' => $modelName,
            ],
        );
    }
}
