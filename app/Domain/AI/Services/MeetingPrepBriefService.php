<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Domain\Account\Models\AiProviderConfig;
use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\AI\Models\MeetingPrepBrief;
use App\Domain\AI\Models\MomExtraction;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Infrastructure\AI\AIProviderFactory;
use App\Infrastructure\AI\Contracts\AIProviderInterface;
use App\Support\Enums\ActionItemStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MeetingPrepBriefService
{
    /**
     * Generate personalized prep briefs for all attendees of a meeting.
     *
     * @return Collection<int, MeetingPrepBrief>
     */
    public function generateForMeeting(MinutesOfMeeting $meeting): Collection
    {
        $meeting->load('attendees.user');

        return $meeting->attendees->map(
            fn (MomAttendee $attendee) => $this->generateForAttendee($meeting, $attendee)
        );
    }

    /**
     * Generate a personalized prep brief for a single attendee.
     */
    public function generateForAttendee(MinutesOfMeeting $meeting, MomAttendee $attendee): MeetingPrepBrief
    {
        MeetingPrepBrief::query()
            ->where('minutes_of_meeting_id', $meeting->id)
            ->where('attendee_id', $attendee->id)
            ->delete();

        $content = $this->buildBriefContent($meeting, $attendee);
        $highlights = $this->buildHighlights($content);
        $prepMinutes = $this->estimatePrepTime($content);

        return MeetingPrepBrief::query()->create([
            'minutes_of_meeting_id' => $meeting->id,
            'attendee_id' => $attendee->id,
            'user_id' => $attendee->user_id,
            'content' => $content,
            'summary_highlights' => $highlights,
            'estimated_prep_minutes' => $prepMinutes,
            'generated_at' => now(),
        ]);
    }

    /**
     * Orchestrate all data gathering and return structured brief content.
     *
     * @return array{executive_summary: string, action_items: array<string, list<array<string, mixed>>>, unresolved_items: list<array<string, mixed>>, agenda_deep_dive: list<array<string, mixed>>, metrics: array<string, mixed>, reading_list: list<array<string, mixed>>, conflicts: list<mixed>}
     */
    private function buildBriefContent(MinutesOfMeeting $meeting, MomAttendee $attendee): array
    {
        $actionItems = $this->getAttendeeActionItems($meeting, $attendee);
        $previousMeetings = $this->getPreviousMeetingContext($meeting);
        $unresolvedItems = $this->getUnresolvedItems($meeting);
        $readingList = $this->getReadingList($meeting);
        $metrics = $this->getGovernanceMetrics($meeting, $attendee);
        $aiInsights = $this->generateAiInsights($meeting, $attendee, $previousMeetings);

        return [
            'executive_summary' => $aiInsights['executive_summary'] ?? '',
            'action_items' => $actionItems,
            'unresolved_items' => $unresolvedItems,
            'agenda_deep_dive' => $previousMeetings,
            'metrics' => $metrics,
            'reading_list' => $readingList,
            'conflicts' => [],
        ];
    }

    /**
     * Get action items assigned to the attendee, grouped by status.
     *
     * @return array{overdue: list<array<string, mixed>>, pending: list<array<string, mixed>>, completed: list<array<string, mixed>>}
     */
    private function getAttendeeActionItems(MinutesOfMeeting $meeting, MomAttendee $attendee): array
    {
        if (! $attendee->user_id) {
            return ['overdue' => [], 'pending' => [], 'completed' => []];
        }

        $overdue = ActionItem::query()
            ->where('organization_id', $meeting->organization_id)
            ->where('assigned_to', $attendee->user_id)
            ->whereIn('status', [ActionItemStatus::Open, ActionItemStatus::InProgress])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->with('meeting')
            ->get()
            ->map(fn (ActionItem $item) => [
                'title' => $item->title,
                'description' => $item->description,
                'priority' => $item->priority?->value,
                'status' => $item->status->value,
                'due_date' => $item->due_date?->toDateString(),
                'days_overdue' => (int) abs(now()->diffInDays($item->due_date)),
                'meeting_title' => $item->meeting?->title,
            ])
            ->values()
            ->all();

        $pending = ActionItem::query()
            ->where('organization_id', $meeting->organization_id)
            ->where('assigned_to', $attendee->user_id)
            ->whereIn('status', [ActionItemStatus::Open, ActionItemStatus::InProgress])
            ->where(fn ($q) => $q->where('due_date', '>=', now())->orWhereNull('due_date'))
            ->with('meeting')
            ->get()
            ->map(fn (ActionItem $item) => [
                'title' => $item->title,
                'description' => $item->description,
                'priority' => $item->priority?->value,
                'status' => $item->status->value,
                'due_date' => $item->due_date?->toDateString(),
                'meeting_title' => $item->meeting?->title,
            ])
            ->values()
            ->all();

        $completed = ActionItem::query()
            ->where('organization_id', $meeting->organization_id)
            ->where('assigned_to', $attendee->user_id)
            ->where('status', ActionItemStatus::Completed)
            ->where('completed_at', '>=', now()->subMonth())
            ->with('meeting')
            ->latest('completed_at')
            ->limit(5)
            ->get()
            ->map(fn (ActionItem $item) => [
                'title' => $item->title,
                'status' => $item->status->value,
                'completed_at' => $item->completed_at?->toDateString(),
                'meeting_title' => $item->meeting?->title,
            ])
            ->values()
            ->all();

        return [
            'overdue' => $overdue,
            'pending' => $pending,
            'completed' => $completed,
        ];
    }

    /**
     * Get context from previous meetings in the same project or series.
     *
     * @return list<array{title: string, meeting_date: string|null, summary: string|null, decisions: string|null}>
     */
    private function getPreviousMeetingContext(MinutesOfMeeting $meeting): array
    {
        if (! $meeting->project_id && ! $meeting->meeting_series_id) {
            return [];
        }

        $query = MinutesOfMeeting::query()
            ->where('organization_id', $meeting->organization_id)
            ->where('id', '!=', $meeting->id)
            ->orderByDesc('meeting_date')
            ->limit(5);

        if ($meeting->project_id) {
            $query->where('project_id', $meeting->project_id);
        } elseif ($meeting->meeting_series_id) {
            $query->where('meeting_series_id', $meeting->meeting_series_id);
        }

        return $query->get()->map(function (MinutesOfMeeting $prevMeeting) {
            $summary = MomExtraction::query()
                ->where('minutes_of_meeting_id', $prevMeeting->id)
                ->where('type', 'summary')
                ->latest()
                ->first();

            $decisions = MomExtraction::query()
                ->where('minutes_of_meeting_id', $prevMeeting->id)
                ->where('type', 'decisions')
                ->latest()
                ->first();

            return [
                'title' => $prevMeeting->title,
                'meeting_date' => $prevMeeting->meeting_date?->toDateString(),
                'summary' => $summary?->content,
                'decisions' => $decisions?->content,
            ];
        })->values()->all();
    }

    /**
     * Get carried-forward (unresolved) action items in the same organization.
     *
     * @return list<array{title: string, status: string, due_date: string|null, meeting_title: string|null}>
     */
    private function getUnresolvedItems(MinutesOfMeeting $meeting): array
    {
        return ActionItem::query()
            ->where('organization_id', $meeting->organization_id)
            ->where('status', ActionItemStatus::CarriedForward)
            ->with('meeting')
            ->limit(10)
            ->get()
            ->map(fn (ActionItem $item) => [
                'title' => $item->title,
                'status' => $item->status->value,
                'due_date' => $item->due_date?->toDateString(),
                'meeting_title' => $item->meeting?->title,
            ])
            ->values()
            ->all();
    }

    /**
     * Get documents attached to the meeting with estimated reading time.
     *
     * @return list<array{filename: string, mime_type: string, file_size: int, estimated_pages: int, reading_time_minutes: int}>
     */
    private function getReadingList(MinutesOfMeeting $meeting): array
    {
        return $meeting->documents->map(function ($doc) {
            $fileSizeBytes = $doc->file_size ?? 0;
            $fileSizeMb = max($fileSizeBytes / (1024 * 1024), 0.01);
            $estimatedPages = (int) ceil($fileSizeMb * 4);
            $readingTimeMinutes = (int) ceil($estimatedPages * 1.5);

            return [
                'filename' => $doc->original_filename,
                'mime_type' => $doc->mime_type,
                'file_size' => $fileSizeBytes,
                'estimated_pages' => $estimatedPages,
                'reading_time_minutes' => $readingTimeMinutes,
            ];
        })->values()->all();
    }

    /**
     * Calculate governance metrics for the attendee.
     *
     * @return array{attendance_rate: float, total_meetings_6m: int, action_completion_rate: float}
     */
    private function getGovernanceMetrics(MinutesOfMeeting $meeting, MomAttendee $attendee): array
    {
        if (! $attendee->user_id) {
            return [
                'attendance_rate' => 0.0,
                'total_meetings_6m' => 0,
                'action_completion_rate' => 0.0,
            ];
        }

        $sixMonthsAgo = now()->subMonths(6);

        $totalMeetings = MomAttendee::query()
            ->where('user_id', $attendee->user_id)
            ->whereHas('meeting', fn ($q) => $q
                ->where('organization_id', $meeting->organization_id)
                ->where('meeting_date', '>=', $sixMonthsAgo)
            )
            ->count();

        $attendedMeetings = MomAttendee::query()
            ->where('user_id', $attendee->user_id)
            ->where('is_present', true)
            ->whereHas('meeting', fn ($q) => $q
                ->where('organization_id', $meeting->organization_id)
                ->where('meeting_date', '>=', $sixMonthsAgo)
            )
            ->count();

        $attendanceRate = $totalMeetings > 0
            ? round($attendedMeetings / $totalMeetings, 2)
            : 0.0;

        $totalActions = ActionItem::query()
            ->where('organization_id', $meeting->organization_id)
            ->where('assigned_to', $attendee->user_id)
            ->where('created_at', '>=', $sixMonthsAgo)
            ->count();

        $completedActions = ActionItem::query()
            ->where('organization_id', $meeting->organization_id)
            ->where('assigned_to', $attendee->user_id)
            ->where('status', ActionItemStatus::Completed)
            ->where('created_at', '>=', $sixMonthsAgo)
            ->count();

        $completionRate = $totalActions > 0
            ? round($completedActions / $totalActions, 2)
            : 0.0;

        return [
            'attendance_rate' => $attendanceRate,
            'total_meetings_6m' => $totalMeetings,
            'action_completion_rate' => $completionRate,
        ];
    }

    /**
     * Call AI provider to generate executive summary, suggested questions, and reading priorities.
     *
     * @param  list<array<string, mixed>>  $previousMeetings
     * @return array{executive_summary: string, suggested_questions: list<string>, reading_priorities: list<string>}
     */
    private function generateAiInsights(MinutesOfMeeting $meeting, MomAttendee $attendee, array $previousMeetings): array
    {
        $fallback = [
            'executive_summary' => "Preparation brief for {$meeting->title}.",
            'suggested_questions' => [],
            'reading_priorities' => [],
        ];

        try {
            $provider = $this->resolveProvider($meeting->organization);

            $contextParts = [
                "Meeting Title: {$meeting->title}",
                'Meeting Date: '.($meeting->meeting_date?->format('F j, Y') ?? 'N/A'),
                'Attendee Name: '.($attendee->name ?? $attendee->user?->name ?? 'Unknown'),
                'Role: '.($attendee->role?->value ?? 'participant'),
            ];

            if (! empty($previousMeetings)) {
                $contextParts[] = "\nPrevious Meeting Context:";
                foreach (array_slice($previousMeetings, 0, 3) as $prev) {
                    $contextParts[] = "- {$prev['title']} ({$prev['meeting_date']})";
                    if ($prev['summary']) {
                        $contextParts[] = "  Summary: {$prev['summary']}";
                    }
                    if ($prev['decisions']) {
                        $contextParts[] = "  Decisions: {$prev['decisions']}";
                    }
                }
            }

            $context = implode("\n", $contextParts);

            $prompt = "Based on the following meeting context, generate a personalized meeting preparation brief.\n\n"
                ."Return a JSON object with these keys:\n"
                ."- \"executive_summary\": A concise summary to help the attendee prepare (2-3 sentences)\n"
                ."- \"suggested_questions\": An array of 3-5 relevant questions the attendee might want to raise\n"
                ."- \"reading_priorities\": An array of 2-3 suggestions on what to review first\n\n"
                ."Respond with ONLY valid JSON, no other text.\n\n"
                ."Context:\n{$context}";

            $response = $provider->chat($prompt, [
                'system' => 'You are a meeting preparation expert. Generate personalized meeting briefs. Respond with ONLY valid JSON.',
            ]);

            $data = json_decode(trim($response), true);

            if (! is_array($data)) {
                return $fallback;
            }

            return [
                'executive_summary' => is_string($data['executive_summary'] ?? null) ? $data['executive_summary'] : $fallback['executive_summary'],
                'suggested_questions' => is_array($data['suggested_questions'] ?? null) ? $data['suggested_questions'] : [],
                'reading_priorities' => is_array($data['reading_priorities'] ?? null) ? $data['reading_priorities'] : [],
            ];
        } catch (\Throwable $e) {
            Log::warning('MeetingPrepBriefService: AI insights generation failed', [
                'meeting_id' => $meeting->id,
                'attendee_id' => $attendee->id,
                'error' => $e->getMessage(),
            ]);

            return $fallback;
        }
    }

    /**
     * Build top 3 highlights for email digest from brief content.
     *
     * @param  array<string, mixed>  $content
     * @return list<string>
     */
    private function buildHighlights(array $content): array
    {
        $highlights = [];

        $overdue = $content['action_items']['overdue'] ?? [];
        if (count($overdue) > 0) {
            $highlights[] = count($overdue).' overdue action item(s) require attention.';
        }

        $unresolved = $content['unresolved_items'] ?? [];
        if (count($unresolved) > 0) {
            $highlights[] = count($unresolved).' carried-forward item(s) remain unresolved.';
        }

        $summary = $content['executive_summary'] ?? '';
        if ($summary !== '') {
            $highlights[] = $summary;
        }

        return array_slice($highlights, 0, 3);
    }

    /**
     * Estimate total preparation time in minutes.
     *
     * @param  array<string, mixed>  $content
     */
    private function estimatePrepTime(array $content): int
    {
        $readingTime = 0;
        foreach ($content['reading_list'] ?? [] as $doc) {
            $readingTime += $doc['reading_time_minutes'] ?? 0;
        }

        $actionItemCount = count($content['action_items']['overdue'] ?? [])
            + count($content['action_items']['pending'] ?? []);
        $actionReviewTime = (int) ceil($actionItemCount * 2);

        $baseReviewTime = 5;

        return max($baseReviewTime + $readingTime + $actionReviewTime, 5);
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
