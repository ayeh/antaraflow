<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Providers;

use App\Domain\AI\Services\AiUsageRecorder;
use App\Infrastructure\AI\Contracts\AIProviderInterface;
use App\Infrastructure\AI\DTOs\ExtractedActionItem;
use App\Infrastructure\AI\DTOs\ExtractedDecision;
use App\Infrastructure\AI\DTOs\ExtractedRisk;
use App\Infrastructure\AI\DTOs\MeetingSummary;
use Illuminate\Support\Facades\Http;

class OpenAIProvider implements AIProviderInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gpt-5.4-mini',
    ) {}

    /** {@inheritDoc} */
    public function chat(string $prompt, array $context = []): string
    {
        $messages = [];

        if (! empty($context['system'])) {
            $messages[] = ['role' => 'system', 'content' => $context['system']];
        }

        if (! empty($context['history'])) {
            foreach ($context['history'] as $msg) {
                $messages[] = $msg;
            }
        }

        $messages[] = ['role' => 'user', 'content' => $prompt];

        $operation = is_string($context['operation'] ?? null) ? $context['operation'] : 'chat';
        $start = microtime(true);

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(120)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $this->model,
                    'messages' => $messages,
                    'temperature' => 0.3,
                ]);

            $response->throw();
        } catch (\Throwable $e) {
            app(AiUsageRecorder::class)->recordChatError('openai', $this->model, $operation, (int) round((microtime(true) - $start) * 1000));
            throw $e;
        }

        app(AiUsageRecorder::class)->recordChat(
            provider: 'openai',
            model: $this->model,
            promptTokens: (int) $response->json('usage.prompt_tokens', 0),
            completionTokens: (int) $response->json('usage.completion_tokens', 0),
            operation: $operation,
            cachedTokens: (int) $response->json('usage.prompt_tokens_details.cached_tokens', 0),
            durationMs: (int) round((microtime(true) - $start) * 1000),
        );

        return $response->json('choices.0.message.content', '');
    }

    /** {@inheritDoc} */
    public function summarize(string $text, ?string $language = null): MeetingSummary
    {
        $prompt = "Analyze the following meeting transcript and provide a JSON response with these fields:\n"
            ."- \"summary\": A concise summary of the meeting (2-3 paragraphs)\n"
            ."- \"key_points\": Bullet points of the most important topics discussed\n"
            ."- \"confidence_score\": Your confidence in the accuracy (0.0 to 1.0)\n\n"
            ."Respond with ONLY valid JSON, no other text.\n\n"
            ."Transcript:\n{$text}";

        $system = 'You are a meeting summarization expert. Always respond with valid JSON.'.$this->languageInstruction($language);
        $response = $this->chat($prompt, ['system' => $system]);
        $data = json_decode($this->cleanJsonResponse($response), true) ?? [];

        $keyPoints = $data['key_points'] ?? '';
        if (is_array($keyPoints)) {
            $keyPoints = implode("\n", $keyPoints);
        }

        return new MeetingSummary(
            summary: $data['summary'] ?? $response,
            keyPoints: $keyPoints,
            confidenceScore: (float) ($data['confidence_score'] ?? 0.8),
        );
    }

    /** {@inheritDoc} */
    public function extractActionItems(string $text, ?string $language = null): array
    {
        $prompt = "Extract action items from the following meeting transcript. Return a JSON array where each item has:\n"
            ."- \"title\": Brief action item title\n"
            ."- \"description\": Detailed description (optional)\n"
            ."- \"assignee\": Person responsible (optional)\n"
            ."- \"due_date\": Due date if mentioned (optional)\n"
            ."- \"priority\": high, medium, or low\n\n"
            ."Respond with ONLY a valid JSON array, no other text.\n\n"
            ."Transcript:\n{$text}";

        $system = 'You are an expert at extracting action items from meetings. Always respond with valid JSON.'.$this->languageInstruction($language);
        $response = $this->chat($prompt, ['system' => $system]);
        $items = json_decode($this->cleanJsonResponse($response), true) ?? [];

        return array_map(
            fn (array $item) => new ExtractedActionItem(
                title: $item['title'] ?? '',
                description: $item['description'] ?? null,
                assignee: $item['assignee'] ?? null,
                dueDate: $item['due_date'] ?? null,
                priority: $item['priority'] ?? 'medium',
            ),
            $items,
        );
    }

    /** {@inheritDoc} */
    public function extractDecisions(string $text, ?string $language = null): array
    {
        $prompt = "Extract decisions made during the following meeting transcript. Return a JSON array where each item has:\n"
            ."- \"decision\": The decision that was made\n"
            ."- \"context\": Background context for the decision (optional)\n"
            ."- \"made_by\": Who made or proposed the decision (optional)\n\n"
            ."Respond with ONLY a valid JSON array, no other text.\n\n"
            ."Transcript:\n{$text}";

        $system = 'You are an expert at identifying decisions from meetings. Always respond with valid JSON.'.$this->languageInstruction($language);
        $response = $this->chat($prompt, ['system' => $system]);
        $decisions = json_decode($this->cleanJsonResponse($response), true) ?? [];

        return array_map(
            fn (array $item) => new ExtractedDecision(
                decision: $item['decision'] ?? '',
                context: $item['context'] ?? null,
                madeBy: $item['made_by'] ?? null,
            ),
            $decisions,
        );
    }

    /** {@inheritDoc} */
    public function extractRisks(string $text, ?string $language = null): array
    {
        $prompt = "Extract risks and concerns raised during the following meeting transcript. Return a JSON array where each item has:\n"
            ."- \"risk\": Description of the risk or concern\n"
            ."- \"severity\": high, medium, or low\n"
            ."- \"mitigation\": Suggested mitigation if mentioned (optional)\n"
            ."- \"raised_by\": Who raised the concern (optional)\n\n"
            ."Respond with ONLY a valid JSON array, no other text.\n\n"
            ."Transcript:\n{$text}";

        $system = 'You are an expert at identifying risks and concerns from meetings. Always respond with valid JSON.'.$this->languageInstruction($language);
        $response = $this->chat($prompt, ['system' => $system]);
        $risks = json_decode($this->cleanJsonResponse($response), true) ?? [];

        return array_map(
            fn (array $item) => new ExtractedRisk(
                risk: $item['risk'] ?? '',
                severity: $item['severity'] ?? 'medium',
                mitigation: $item['mitigation'] ?? null,
                raisedBy: $item['raised_by'] ?? null,
            ),
            $risks,
        );
    }

    /**
     * Strip markdown code block fences from AI responses before JSON parsing.
     */
    private function cleanJsonResponse(string $response): string
    {
        $cleaned = trim($response);

        if (preg_match('/^```(?:json)?\s*\n?(.*?)\n?\s*```$/s', $cleaned, $matches)) {
            return trim($matches[1]);
        }

        return $cleaned;
    }

    private function languageInstruction(?string $language): string
    {
        if (! $language || $language === 'en') {
            return '';
        }

        $names = ['ms' => 'Bahasa Melayu (Malay)'];

        return ' Respond in '.($names[$language] ?? $language).'.';
    }
}
