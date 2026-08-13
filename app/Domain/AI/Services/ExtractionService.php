<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Domain\Account\Models\AiProviderConfig;
use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Admin\Services\AiControlService;
use App\Domain\AI\Models\ExtractionTemplate;
use App\Domain\AI\Models\MomExtraction;
use App\Domain\AI\Models\MomTopic;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Infrastructure\AI\AIProviderFactory;
use App\Infrastructure\AI\Contracts\AIProviderInterface;
use App\Infrastructure\AI\Exceptions\AiDisabledException;
use App\Infrastructure\AI\Prompts\ExtractionPrompts;
use App\Models\User;
use App\Support\Enums\ActionItemPriority;
use Illuminate\Support\Str;

class ExtractionService
{
    /**
     * @param  string|null  $overrideText  Transcript to extract from instead of
     *                                     the meeting's own content. A live
     *                                     session has nothing stored yet, and
     *                                     writing its partial transcript onto
     *                                     the meeting to be read back would put
     *                                     the wrong content in front of anyone
     *                                     viewing it — permanently, if the
     *                                     worker died before restoring it.
     */
    public function extractAll(MinutesOfMeeting $mom, ?string $overrideText = null): void
    {
        if (! app(AiControlService::class)->isEnabled()) {
            throw AiDisabledException::make();
        }

        app(OrgBudgetService::class)->guard($mom->organization_id);

        app(AiUsageContext::class)->set(
            organizationId: $mom->organization_id,
            userId: $mom->created_by,
            feature: 'mom_generation',
            sessionId: (string) Str::uuid(),
        );

        $provider = $this->resolveProvider($mom->organization);
        $providerConfig = $this->getProviderConfig($mom->organization);
        $rawText = $overrideText ?? $this->getFullText($mom);

        if (empty($rawText)) {
            return;
        }

        $text = ChatService::sanitizeForPrompt($rawText);
        $providerName = $providerConfig?->provider ?? config('ai.default');
        $modelName = $providerConfig?->model ?? config('ai.providers.'.$providerName.'.model');

        $language = $mom->language ?? 'en';

        $this->extractSummary($mom, $provider, $providerName, $modelName, $text, $language);
        $this->extractActionItems($mom, $provider, $providerName, $modelName, $text, $language);
        $this->extractDecisions($mom, $provider, $providerName, $modelName, $text, $language);
        $this->extractTopics($mom, $provider, $providerName, $modelName, $text, $language);
        $this->extractRisks($mom, $provider, $providerName, $modelName, $text, $language);
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
            $assignee = $item['assignee'] ?? null;
            $assignedTo = $this->matchAssignee($assignee, $attendees);

            $priorityValue = strtolower($item['priority'] ?? 'medium');
            $priority = ActionItemPriority::tryFrom($priorityValue) ?? ActionItemPriority::Medium;

            ActionItem::createForOrganization($mom->organization_id, [
                'minutes_of_meeting_id' => $mom->id,
                'created_by' => $user->id,
                'assigned_to' => $assignedTo,
                // Kept even when it matched a user, and especially when it did
                // not. The minutes are clearer about who owns the work than
                // anything else in them, and discarding the name because it is
                // not an account threw that away.
                'assignee_name' => $this->assigneeName($assignee),
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
    /**
     * The extracted assignee, or null when the model was telling us it did not
     * find one.
     *
     * It says so in words rather than by leaving the field out — "Tidak
     * dinyatakan", "Not specified", a dash. Stored as-is those read like the
     * name of the person responsible, which is worse than an empty column:
     * a reader can tell that nobody was named, but not that "Tidak dinyatakan"
     * means nobody was named.
     */
    private function assigneeName(mixed $assignee): ?string
    {
        if (! is_string($assignee)) {
            return null;
        }

        $name = trim(strip_tags($assignee));

        if ($name === '') {
            return null;
        }

        $placeholders = [
            'tidak dinyatakan', 'tiada', 'tiada penerima', 'belum ditentukan',
            'tidak ditetapkan', 'tidak berkenaan',
            'not specified', 'not stated', 'unspecified', 'unassigned',
            'none', 'n/a', 'na', 'tbd', 'tba', 'unknown', '-', '—',
        ];

        return in_array(mb_strtolower(rtrim($name, '.')), $placeholders, true)
            ? null
            : mb_substr($name, 0, 255);
    }

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

    /**
     * How much audio this record was built from, or null when it was not built
     * from audio at all.
     */
    private function recordedSeconds(MinutesOfMeeting $mom): ?int
    {
        $seconds = (int) $mom->transcriptions()
            ->where('status', 'completed')
            ->sum('duration_seconds');

        return $seconds > 0 ? $seconds : null;
    }

    /**
     * Whether the durations the model returned can be true of this recording.
     *
     * Asked to estimate from text alone the model returns what an agenda
     * usually looks like — five topics at three or four minutes each, whether
     * the recording ran two minutes or two hours. Rather than trim the numbers
     * into range, which would keep a fabrication and only make it plausible,
     * the whole set is dropped. No duration is honest; a wrong one is not.
     *
     * A minute of slack absorbs rounding, since a topic under a minute is
     * asked for as 0 and several of those still sum to less than the whole.
     *
     * @param  list<array<string, mixed>>  $topics
     */
    private static function durationsFit(array $topics, ?int $recordedSeconds): bool
    {
        if ($recordedSeconds === null) {
            return false;
        }

        $claimed = 0;
        foreach ($topics as $topic) {
            $claimed += (int) ($topic['duration_minutes'] ?? 0);
        }

        return $claimed * 60 <= $recordedSeconds + 60;
    }

    private function extractSummary(
        MinutesOfMeeting $mom,
        AIProviderInterface $provider,
        string $providerName,
        string $modelName,
        string $text,
        string $language = 'en',
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

        $result = $provider->summarize($text, $language);

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
        string $language = 'en',
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

        $items = $provider->extractActionItems($text, $language);

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
        string $language = 'en',
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

        $decisions = $provider->extractDecisions($text, $language);

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
        string $language = 'en',
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

        $recordedSeconds = $this->recordedSeconds($mom);
        $prompt = ExtractionPrompts::topics($text, $recordedSeconds);

        $languageNote = ($language && $language !== 'en') ? ' Respond in '.(['ms' => 'Bahasa Melayu (Malay)'][$language] ?? $language).'.' : '';
        $response = $provider->chat($prompt, ['system' => 'You are an expert at identifying discussion topics from meetings. Always respond with valid JSON.'.$languageNote]);
        $cleaned = trim($response);
        if (preg_match('/^```(?:json)?\s*\n?(.*?)\n?\s*```$/s', $cleaned, $matches)) {
            $cleaned = trim($matches[1]);
        }
        $topics = json_decode($cleaned, true) ?? [];

        MomTopic::query()->where('minutes_of_meeting_id', $mom->id)->delete();

        $keepDurations = self::durationsFit($topics, $recordedSeconds);

        foreach ($topics as $index => $topic) {
            MomTopic::query()->create([
                'minutes_of_meeting_id' => $mom->id,
                'title' => $topic['title'] ?? '',
                'description' => $topic['description'] ?? null,
                'duration_minutes' => $keepDurations ? ($topic['duration_minutes'] ?? null) : null,
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
        string $language = 'en',
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

        $risks = $provider->extractRisks($text, $language);

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
