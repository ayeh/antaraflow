<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Domain\Account\Models\AiProviderConfig;
use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\AI\Models\ExtractionTemplate;
use App\Domain\AI\Models\MomExtraction;
use App\Domain\AI\Models\MomTopic;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Infrastructure\AI\AIProviderFactory;
use App\Infrastructure\AI\Contracts\AIProviderInterface;
use App\Models\User;
use App\Support\Enums\ActionItemPriority;

class ExtractionService
{
    public function extractAll(MinutesOfMeeting $mom): void
    {
        $provider = $this->resolveProvider($mom->organization);
        $providerConfig = $this->getProviderConfig($mom->organization);
        $rawText = $this->getFullText($mom);

        if (empty($rawText)) {
            return;
        }

        $text = ChatService::sanitizeForPrompt($rawText);
        $providerName = $providerConfig?->provider ?? config('ai.default');
        $modelName = $providerConfig?->model ?? config('ai.providers.'.$providerName.'.model');

        $this->extractSummary($mom, $provider, $providerName, $modelName, $text);
        $this->extractActionItems($mom, $provider, $providerName, $modelName, $text);
        $this->extractDecisions($mom, $provider, $providerName, $modelName, $text);
        $this->extractTopics($mom, $provider, $providerName, $modelName, $text);
        $this->extractRisks($mom, $provider, $providerName, $modelName, $text);
    }

    /**
     * Create ActionItem records from extracted action items data.
     */
    public function createActionItemRecords(MinutesOfMeeting $mom, User $user): void
    {
        $extraction = $mom->extractions()
            ->where('type', 'action_items')
            ->latest()
            ->first();

        if (! $extraction || empty($extraction->structured_data)) {
            return;
        }

        // Remove previously AI-generated action items (those with ai_generated metadata flag)
        ActionItem::query()
            ->where('minutes_of_meeting_id', $mom->id)
            ->whereJsonContains('metadata->ai_generated', true)
            ->delete();

        $attendees = $mom->attendees()->with('user')->get();

        foreach ($extraction->structured_data as $item) {
            $assignedTo = $this->matchAssignee($item['assignee'] ?? null, $attendees);

            $priorityValue = strtolower($item['priority'] ?? 'medium');
            $priority = ActionItemPriority::tryFrom($priorityValue) ?? ActionItemPriority::Medium;

            ActionItem::query()->create([
                'organization_id' => $mom->organization_id,
                'minutes_of_meeting_id' => $mom->id,
                'created_by' => $user->id,
                'assigned_to' => $assignedTo,
                'title' => strip_tags($item['title'] ?? $item['description'] ?? 'Untitled'),
                'description' => strip_tags($item['description'] ?? ''),
                'priority' => $priority,
                'status' => 'open',
                'due_date' => $this->parseDueDate($item['due_date'] ?? null),
                'metadata' => ['ai_generated' => true],
            ]);
        }
    }

    /**
     * Try to match an assignee name to an attendee's user.
     */
    private function matchAssignee(?string $assigneeName, $attendees): ?int
    {
        if (! $assigneeName) {
            return null;
        }

        $normalised = mb_strtolower(trim($assigneeName));

        foreach ($attendees as $attendee) {
            if ($attendee->user && str_contains(mb_strtolower($attendee->user->name), $normalised)) {
                return $attendee->user->id;
            }

            if ($attendee->external_name && str_contains(mb_strtolower($attendee->external_name), $normalised)) {
                return $attendee->user_id;
            }
        }

        return null;
    }

    /**
     * Safely parse a due date string.
     */
    private function parseDueDate(?string $dateString): ?\DateTimeInterface
    {
        if (! $dateString) {
            return null;
        }

        try {
            return new \DateTimeImmutable($dateString);
        } catch (\Exception) {
            return null;
        }
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
        $template = $this->resolveTemplate($mom, 'summary');

        if ($template) {
            $response = $provider->chat(
                $template->renderPrompt($text),
                $template->system_message ? ['system' => $template->system_message] : [],
            );

            MomExtraction::query()->updateOrCreate(
                ['minutes_of_meeting_id' => $mom->id, 'type' => 'summary'],
                [
                    'content' => strip_tags($response),
                    'structured_data' => ['custom_template' => $template->id],
                    'provider' => $providerName,
                    'model' => $modelName,
                ],
            );

            return;
        }

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
        $template = $this->resolveTemplate($mom, 'action_items');

        if ($template) {
            $response = $provider->chat(
                $template->renderPrompt($text),
                $template->system_message ? ['system' => $template->system_message] : [],
            );

            MomExtraction::query()->updateOrCreate(
                ['minutes_of_meeting_id' => $mom->id, 'type' => 'action_items'],
                [
                    'content' => strip_tags($response),
                    'structured_data' => ['custom_template' => $template->id],
                    'provider' => $providerName,
                    'model' => $modelName,
                ],
            );

            return;
        }

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
        $template = $this->resolveTemplate($mom, 'decisions');

        if ($template) {
            $response = $provider->chat(
                $template->renderPrompt($text),
                $template->system_message ? ['system' => $template->system_message] : [],
            );

            MomExtraction::query()->updateOrCreate(
                ['minutes_of_meeting_id' => $mom->id, 'type' => 'decisions'],
                [
                    'content' => strip_tags($response),
                    'structured_data' => ['custom_template' => $template->id],
                    'provider' => $providerName,
                    'model' => $modelName,
                ],
            );

            return;
        }

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
        $template = $this->resolveTemplate($mom, 'topics');

        if ($template) {
            $response = $provider->chat(
                $template->renderPrompt($text),
                $template->system_message ? ['system' => $template->system_message] : [],
            );

            MomExtraction::query()->updateOrCreate(
                ['minutes_of_meeting_id' => $mom->id, 'type' => 'topics'],
                [
                    'content' => strip_tags($response),
                    'structured_data' => ['custom_template' => $template->id],
                    'provider' => $providerName,
                    'model' => $modelName,
                ],
            );

            return;
        }

        $prompt = "Identify the main topics discussed in the following meeting transcript. Return a JSON array where each item has:\n"
            ."- \"title\": Topic title\n"
            ."- \"description\": Brief description of what was discussed\n"
            ."- \"duration_minutes\": Estimated duration in minutes (optional)\n\n"
            ."Respond with ONLY a valid JSON array, no other text.\n\n"
            ."Transcript:\n{$text}";

        $response = $provider->chat($prompt, ['system' => 'You are an expert at identifying discussion topics from meetings. Always respond with valid JSON.']);
        $cleaned = trim($response);
        if (preg_match('/^```(?:json)?\s*\n?(.*?)\n?\s*```$/s', $cleaned, $matches)) {
            $cleaned = trim($matches[1]);
        }
        $topics = json_decode($cleaned, true) ?? [];

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

    private function extractRisks(
        MinutesOfMeeting $mom,
        AIProviderInterface $provider,
        string $providerName,
        string $modelName,
        string $text,
    ): void {
        $template = $this->resolveTemplate($mom, 'risks');

        if ($template) {
            $response = $provider->chat(
                $template->renderPrompt($text),
                $template->system_message ? ['system' => $template->system_message] : [],
            );

            MomExtraction::query()->updateOrCreate(
                ['minutes_of_meeting_id' => $mom->id, 'type' => 'risks'],
                [
                    'content' => strip_tags($response),
                    'structured_data' => ['custom_template' => $template->id],
                    'provider' => $providerName,
                    'model' => $modelName,
                ],
            );

            return;
        }

        $risks = $provider->extractRisks($text);

        $structuredData = array_map(
            fn ($risk) => [
                'risk' => $risk->risk,
                'severity' => $risk->severity,
                'mitigation' => $risk->mitigation,
                'raised_by' => $risk->raisedBy,
            ],
            $risks,
        );

        $content = implode("\n", array_map(
            fn ($risk) => "- [{$risk->severity}] {$risk->risk}",
            $risks,
        ));

        MomExtraction::query()->updateOrCreate(
            ['minutes_of_meeting_id' => $mom->id, 'type' => 'risks'],
            [
                'content' => $content,
                'structured_data' => $structuredData,
                'provider' => $providerName,
                'model' => $modelName,
            ],
        );
    }

    /**
     * Resolve a custom extraction template for the given meeting and extraction type.
     * Specific meeting_type match takes priority over wildcard (null) match.
     */
    private function resolveTemplate(MinutesOfMeeting $mom, string $extractionType): ?ExtractionTemplate
    {
        return ExtractionTemplate::query()
            ->where('organization_id', $mom->organization_id)
            ->where('extraction_type', $extractionType)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('meeting_type', $mom->meeting_type?->value)->orWhereNull('meeting_type'))
            ->orderByRaw('meeting_type IS NULL ASC')
            ->orderBy('sort_order')
            ->first();
    }
}
