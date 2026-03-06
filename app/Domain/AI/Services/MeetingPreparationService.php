<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Domain\Account\Models\AiProviderConfig;
use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\AI\Models\ExtractionTemplate;
use App\Domain\AI\Models\MomExtraction;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Infrastructure\AI\AIProviderFactory;
use App\Infrastructure\AI\Contracts\AIProviderInterface;
use App\Support\Enums\ActionItemStatus;
use App\Support\Enums\ExtractionType;

class MeetingPreparationService
{
    /**
     * Generate AI-powered meeting preparation data.
     *
     * @return array{suggested_agenda: list<string>, carryover_items: list<array{title: string, status: string}>, discussion_topics: list<string>, estimated_duration_minutes: int}
     */
    public function generate(MinutesOfMeeting $mom): array
    {
        $provider = $this->resolveProvider($mom->organization);
        $context = $this->buildContext($mom);
        $prompt = $this->buildPrompt($mom, $context);

        $template = ExtractionTemplate::query()
            ->where('organization_id', $mom->organization_id)
            ->where('extraction_type', ExtractionType::MeetingPreparation)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('meeting_type', $mom->meeting_type?->value)->orWhereNull('meeting_type'))
            ->orderByRaw('meeting_type IS NULL ASC')
            ->orderBy('sort_order')
            ->first();

        if ($template) {
            $prompt = $template->renderPrompt($context);
            $systemMessage = $template->system_message ?? 'You are a meeting preparation expert. Respond with ONLY valid JSON.';
        } else {
            $systemMessage = 'You are a meeting preparation expert. Analyze past meeting data and generate a well-structured agenda for the upcoming meeting. Respond with ONLY valid JSON.';
        }

        $response = $provider->chat($prompt, ['system' => $systemMessage]);

        return $this->parseResponse($response);
    }

    private function buildContext(MinutesOfMeeting $mom): string
    {
        $parts = [];

        $parts[] = "Current Meeting Title: {$mom->title}";
        $parts[] = 'Meeting Date: '.($mom->meeting_date?->format('F j, Y') ?? 'N/A');

        $previousMeetings = $this->getPreviousMeetings($mom);
        if ($previousMeetings->isNotEmpty()) {
            $parts[] = 'Previous Meetings in Project:';
            foreach ($previousMeetings as $prevMeeting) {
                $parts[] = "- {$prevMeeting->title} ({$prevMeeting->meeting_date?->format('M j, Y')})";

                $summary = MomExtraction::query()
                    ->where('minutes_of_meeting_id', $prevMeeting->id)
                    ->where('type', 'summary')
                    ->latest()
                    ->first();

                if ($summary) {
                    $parts[] = "  Summary: {$summary->content}";
                }

                $decisions = MomExtraction::query()
                    ->where('minutes_of_meeting_id', $prevMeeting->id)
                    ->where('type', 'decisions')
                    ->latest()
                    ->first();

                if ($decisions) {
                    $parts[] = "  Decisions: {$decisions->content}";
                }
            }
        }

        $openActionItems = $this->getOpenActionItems($mom);
        if ($openActionItems->isNotEmpty()) {
            $parts[] = "\nOpen/In-Progress Action Items:";
            foreach ($openActionItems as $item) {
                $assignee = $item->assignedTo?->name ?? 'Unassigned';
                $parts[] = "- [{$item->status->value}] {$item->title} (Assigned: {$assignee})";
            }
        }

        $carriedForwardItems = $this->getCarriedForwardItems($mom);
        if ($carriedForwardItems->isNotEmpty()) {
            $parts[] = "\nCarried-Forward Items:";
            foreach ($carriedForwardItems as $item) {
                $parts[] = "- {$item->title}";
            }
        }

        return implode("\n", $parts);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, MinutesOfMeeting>
     */
    private function getPreviousMeetings(MinutesOfMeeting $mom): \Illuminate\Database\Eloquent\Collection
    {
        if (! $mom->project_id) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        return MinutesOfMeeting::query()
            ->where('project_id', $mom->project_id)
            ->where('organization_id', $mom->organization_id)
            ->where('id', '!=', $mom->id)
            ->orderByDesc('meeting_date')
            ->limit(3)
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, ActionItem>
     */
    private function getOpenActionItems(MinutesOfMeeting $mom): \Illuminate\Database\Eloquent\Collection
    {
        $attendeeUserIds = $mom->attendees()
            ->whereNotNull('user_id')
            ->pluck('user_id');

        if ($attendeeUserIds->isEmpty()) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        return ActionItem::query()
            ->where('organization_id', $mom->organization_id)
            ->whereIn('assigned_to', $attendeeUserIds)
            ->whereIn('status', [ActionItemStatus::Open, ActionItemStatus::InProgress])
            ->with('assignedTo')
            ->limit(20)
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, ActionItem>
     */
    private function getCarriedForwardItems(MinutesOfMeeting $mom): \Illuminate\Database\Eloquent\Collection
    {
        if (! $mom->project_id) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        $previousMeetingIds = MinutesOfMeeting::query()
            ->where('project_id', $mom->project_id)
            ->where('organization_id', $mom->organization_id)
            ->where('id', '!=', $mom->id)
            ->pluck('id');

        if ($previousMeetingIds->isEmpty()) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        return ActionItem::query()
            ->whereIn('minutes_of_meeting_id', $previousMeetingIds)
            ->where('status', ActionItemStatus::CarriedForward)
            ->get();
    }

    private function buildPrompt(MinutesOfMeeting $mom, string $context): string
    {
        return "Based on the following meeting context and history, generate a meeting preparation package.\n\n"
            ."Return a JSON object with these keys:\n"
            ."- \"suggested_agenda\": An array of strings, each being a suggested agenda item\n"
            ."- \"carryover_items\": An array of objects with \"title\" and \"status\" keys for items carried from previous meetings\n"
            ."- \"discussion_topics\": An array of strings for recommended discussion topics\n"
            ."- \"estimated_duration_minutes\": An integer estimating meeting duration\n\n"
            ."Consider:\n"
            ."- Previous meeting summaries and decisions\n"
            ."- Open and in-progress action items\n"
            ."- Carried-forward items that need follow-up\n"
            ."- Logical flow and prioritization of topics\n\n"
            ."Respond with ONLY valid JSON, no other text.\n\n"
            ."Meeting Context:\n{$context}";
    }

    /**
     * @return array{suggested_agenda: list<string>, carryover_items: list<array{title: string, status: string}>, discussion_topics: list<string>, estimated_duration_minutes: int}
     */
    private function parseResponse(string $response): array
    {
        $cleaned = trim($response);
        if (preg_match('/^```(?:json)?\s*\n?(.*?)\n?\s*```$/s', $cleaned, $matches)) {
            $cleaned = trim($matches[1]);
        }

        $data = json_decode($cleaned, true);

        $defaults = [
            'suggested_agenda' => [],
            'carryover_items' => [],
            'discussion_topics' => [],
            'estimated_duration_minutes' => 60,
        ];

        if (! is_array($data)) {
            return $defaults;
        }

        return [
            'suggested_agenda' => is_array($data['suggested_agenda'] ?? null) ? $data['suggested_agenda'] : [],
            'carryover_items' => is_array($data['carryover_items'] ?? null) ? $data['carryover_items'] : [],
            'discussion_topics' => is_array($data['discussion_topics'] ?? null) ? $data['discussion_topics'] : [],
            'estimated_duration_minutes' => is_int($data['estimated_duration_minutes'] ?? null) ? $data['estimated_duration_minutes'] : 60,
        ];
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
