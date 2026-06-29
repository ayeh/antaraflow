<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Domain\Account\Models\AiProviderConfig;
use App\Domain\Account\Models\Organization;
use App\Domain\AI\Models\ExtractionTemplate;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Infrastructure\AI\AIProviderFactory;
use App\Infrastructure\AI\Contracts\AIProviderInterface;
use App\Support\Enums\ExtractionType;

class AgendaEmailService
{
    /**
     * Generate a pre-meeting agenda email from the meeting's topics.
     *
     * @return array{subject: string, body: string, recipients: list<string>}
     */
    public function generate(MinutesOfMeeting $mom): array
    {
        $provider = $this->resolveProvider($mom->organization);
        $context = $this->buildContext($mom);
        $prompt = $this->buildPrompt($mom, $context);

        $template = ExtractionTemplate::query()
            ->where('organization_id', $mom->organization_id)
            ->where('extraction_type', ExtractionType::AgendaEmail)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('meeting_type', $mom->meeting_type?->value)->orWhereNull('meeting_type'))
            ->orderByRaw('meeting_type IS NULL ASC')
            ->orderBy('sort_order')
            ->first();

        if ($template) {
            $prompt = $template->renderPrompt($context);
            $systemMessage = $template->system_message ?? 'You are a professional email writer.';
        } else {
            $systemMessage = 'You are a professional email writer. Generate clear, concise pre-meeting emails that share the agenda and ask attendees to come prepared. Respond with ONLY valid JSON.';
        }

        $response = $provider->chat($prompt, ['system' => $systemMessage]);

        $parsed = $this->parseResponse($response, $mom);

        $parsed['recipients'] = $this->collectRecipients($mom);

        return $parsed;
    }

    private function buildContext(MinutesOfMeeting $mom): string
    {
        $parts = [];

        $parts[] = "Meeting Title: {$mom->title}";
        $parts[] = 'Meeting Date: '.($mom->meeting_date?->format('F j, Y') ?? 'N/A');

        if ($mom->start_time) {
            $parts[] = 'Start Time: '.$mom->start_time->format('g:i A');
        }

        if ($mom->location) {
            $parts[] = "Location: {$mom->location}";
        }

        $attendees = $mom->attendees()->with('user')->get();
        $attendeeNames = $attendees->map(fn ($a) => $a->user?->name ?? $a->name)->filter()->implode(', ');
        if ($attendeeNames) {
            $parts[] = "Attendees: {$attendeeNames}";
        }

        $topics = $mom->topics()->orderBy('sort_order')->get();
        if ($topics->isNotEmpty()) {
            $agendaLines = $topics->map(function ($topic, $index) {
                $line = ($index + 1).'. '.$topic->title;
                if ($topic->description) {
                    $line .= ' — '.$topic->description;
                }

                return $line;
            })->implode("\n");
            $parts[] = "Agenda:\n{$agendaLines}";
        }

        return implode("\n\n", $parts);
    }

    private function buildPrompt(MinutesOfMeeting $mom, string $context): string
    {
        return "Based on the following upcoming meeting details and agenda, generate a professional pre-meeting email.\n\n"
            ."Return a JSON object with two keys:\n"
            ."- \"subject\": A concise email subject line\n"
            ."- \"body\": The email body in markdown with proper formatting\n\n"
            ."The email should:\n"
            ."- Greet the attendees\n"
            ."- State the meeting date, time, and location when available\n"
            ."- Present the agenda as a numbered list\n"
            ."- Ask attendees to review the agenda and come prepared with relevant updates\n"
            ."- Be professional and concise\n\n"
            ."Respond with ONLY valid JSON, no other text.\n\n"
            ."Meeting Details:\n{$context}";
    }

    /**
     * @return array{subject: string, body: string}
     */
    private function parseResponse(string $response, MinutesOfMeeting $mom): array
    {
        $cleaned = trim($response);
        if (preg_match('/^```(?:json)?\s*\n?(.*?)\n?\s*```$/s', $cleaned, $matches)) {
            $cleaned = trim($matches[1]);
        }

        $data = json_decode($cleaned, true);

        if (! $data || ! isset($data['subject'], $data['body'])) {
            return [
                'subject' => "Agenda: {$mom->title}",
                'body' => $response,
            ];
        }

        return [
            'subject' => $data['subject'],
            'body' => $data['body'],
        ];
    }

    /**
     * @return list<string>
     */
    private function collectRecipients(MinutesOfMeeting $mom): array
    {
        $emails = [];

        $attendees = $mom->attendees()->with('user')->get();
        foreach ($attendees as $attendee) {
            if ($attendee->user?->email) {
                $emails[] = $attendee->user->email;
            } elseif ($attendee->email) {
                $emails[] = $attendee->email;
            }
        }

        return array_values(array_unique($emails));
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
