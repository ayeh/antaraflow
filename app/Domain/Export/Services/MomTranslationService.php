<?php

declare(strict_types=1);

namespace App\Domain\Export\Services;

use App\Domain\Account\Models\AiProviderConfig;
use App\Domain\Account\Models\Organization;
use App\Domain\Admin\Services\AiControlService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Infrastructure\AI\AIProviderFactory;
use App\Infrastructure\AI\Contracts\AIProviderInterface;

class MomTranslationService
{
    public function needsTranslation(MinutesOfMeeting $mom, string $outputLanguage): bool
    {
        return ($mom->language ?? 'ms') !== $outputLanguage;
    }

    /**
     * Apply AI translations to the MOM's in-memory attributes and loaded relations.
     * Does NOT persist any changes to the database.
     */
    public function applyTranslations(MinutesOfMeeting $mom, string $targetLanguage): void
    {
        if (! app(AiControlService::class)->isEnabled()) {
            return;
        }

        $payload = $this->collectTranslatables($mom);
        if (empty($payload)) {
            return;
        }

        try {
            $provider = $this->resolveProvider($mom->organization);
            $sourceLang = $mom->language ?? 'ms';
            $prompt = $this->buildPrompt($payload, $sourceLang, $targetLanguage);

            $response = $provider->chat($prompt, [
                'system' => 'You are a professional translator for business and government meeting minutes. Return ONLY valid JSON, no explanation.',
            ]);

            $translated = json_decode($this->extractJson($response), true);
            if (is_array($translated)) {
                $this->applyToMom($mom, $translated);
            }
        } catch (\Throwable) {
            // Translation is best-effort; export still proceeds with original language
        }
    }

    /**
     * @return array<string, string>
     */
    private function collectTranslatables(MinutesOfMeeting $mom): array
    {
        $payload = [];

        if (! empty($mom->title)) {
            $payload['title'] = $mom->title;
        }

        foreach ($mom->extractions as $i => $extraction) {
            if (! empty($extraction->content)) {
                $payload["extraction_{$i}"] = $extraction->content;
            }
        }

        foreach ($mom->topics as $i => $topic) {
            if (! empty($topic->title)) {
                $payload["topic_{$i}_title"] = $topic->title;
            }
            if (! empty($topic->description)) {
                $payload["topic_{$i}_desc"] = $topic->description;
            }
        }

        foreach ($mom->actionItems as $i => $item) {
            if (! empty($item->title)) {
                $payload["action_{$i}_title"] = $item->title;
            }
            if (! empty($item->description)) {
                $payload["action_{$i}_desc"] = $item->description;
            }
        }

        foreach ($mom->manualNotes as $i => $note) {
            if (! empty($note->content)) {
                $payload["note_{$i}"] = $note->content;
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function buildPrompt(array $payload, string $from, string $to): string
    {
        $names = ['ms' => 'Malay', 'en' => 'English'];
        $fromName = $names[$from] ?? $from;
        $toName = $names[$to] ?? $to;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
        Translate the following JSON from {$fromName} to {$toName}.
        Rules:
        - Translate values only, not keys
        - Preserve all formatting, line breaks, numbers, and proper nouns
        - Return ONLY a JSON object — no markdown fences, no explanation

        {$json}
        PROMPT;
    }

    private function extractJson(string $response): string
    {
        // Strip markdown code fences if present
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $response, $m)) {
            return $m[1];
        }

        if (preg_match('/(\{.*\})/s', $response, $m)) {
            return $m[1];
        }

        return $response;
    }

    /**
     * @param  array<string, string>  $translated
     */
    private function applyToMom(MinutesOfMeeting $mom, array $translated): void
    {
        if (isset($translated['title'])) {
            $mom->title = $translated['title'];
        }

        foreach ($mom->extractions as $i => $extraction) {
            if (isset($translated["extraction_{$i}"])) {
                $extraction->content = $translated["extraction_{$i}"];
            }
        }

        foreach ($mom->topics as $i => $topic) {
            if (isset($translated["topic_{$i}_title"])) {
                $topic->title = $translated["topic_{$i}_title"];
            }
            if (isset($translated["topic_{$i}_desc"])) {
                $topic->description = $translated["topic_{$i}_desc"];
            }
        }

        foreach ($mom->actionItems as $i => $item) {
            if (isset($translated["action_{$i}_title"])) {
                $item->title = $translated["action_{$i}_title"];
            }
            if (isset($translated["action_{$i}_desc"])) {
                $item->description = $translated["action_{$i}_desc"];
            }
        }

        foreach ($mom->manualNotes as $i => $note) {
            if (isset($translated["note_{$i}"])) {
                $note->content = $translated["note_{$i}"];
            }
        }
    }

    private function resolveProvider(Organization $org): AIProviderInterface
    {
        $config = AiProviderConfig::query()
            ->where('organization_id', $org->id)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

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
}
