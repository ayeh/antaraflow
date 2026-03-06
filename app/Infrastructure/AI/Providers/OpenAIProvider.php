<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Providers;

use App\Infrastructure\AI\Contracts\AIProviderInterface;
use App\Infrastructure\AI\DTOs\ExtractedActionItem;
use App\Infrastructure\AI\DTOs\ExtractedDecision;
use App\Infrastructure\AI\DTOs\MeetingSummary;
use Illuminate\Support\Facades\Http;

class OpenAIProvider implements AIProviderInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gpt-4o',
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

        $response = Http::withToken($this->apiKey)
            ->timeout(120)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.3,
            ]);

        $response->throw();

        return $response->json('choices.0.message.content', '');
    }

    /** {@inheritDoc} */
    public function summarize(string $text): MeetingSummary
    {
        $prompt = "Analyze the following meeting transcript and provide a JSON response with these fields:\n"
            ."- \"summary\": A concise summary of the meeting (2-3 paragraphs)\n"
            ."- \"key_points\": Bullet points of the most important topics discussed\n"
            ."- \"confidence_score\": Your confidence in the accuracy (0.0 to 1.0)\n\n"
            ."Respond with ONLY valid JSON, no other text.\n\n"
            ."Transcript:\n{$text}";

        $response = $this->chat($prompt, ['system' => 'You are a meeting summarization expert. Always respond with valid JSON.']);
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
    public function extractActionItems(string $text): array
    {
        $prompt = "Extract action items from the following meeting transcript. Return a JSON array where each item has:\n"
            ."- \"title\": Brief action item title\n"
            ."- \"description\": Detailed description (optional)\n"
            ."- \"assignee\": Person responsible (optional)\n"
            ."- \"due_date\": Due date if mentioned (optional)\n"
            ."- \"priority\": high, medium, or low\n\n"
            ."Respond with ONLY a valid JSON array, no other text.\n\n"
            ."Transcript:\n{$text}";

        $response = $this->chat($prompt, ['system' => 'You are an expert at extracting action items from meetings. Always respond with valid JSON.']);
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
    public function extractDecisions(string $text): array
    {
        $prompt = "Extract decisions made during the following meeting transcript. Return a JSON array where each item has:\n"
            ."- \"decision\": The decision that was made\n"
            ."- \"context\": Background context for the decision (optional)\n"
            ."- \"made_by\": Who made or proposed the decision (optional)\n\n"
            ."Respond with ONLY a valid JSON array, no other text.\n\n"
            ."Transcript:\n{$text}";

        $response = $this->chat($prompt, ['system' => 'You are an expert at identifying decisions from meetings. Always respond with valid JSON.']);
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
}
