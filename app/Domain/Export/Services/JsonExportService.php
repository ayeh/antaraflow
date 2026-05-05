<?php

declare(strict_types=1);

namespace App\Domain\Export\Services;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Http\JsonResponse;

class JsonExportService
{
    public function export(MinutesOfMeeting $meeting): JsonResponse
    {
        $meeting->load(['createdBy', 'attendees.user', 'actionItems.assignedTo', 'extractions', 'manualNotes', 'transcriptions']);

        $filename = $this->buildFilename($meeting);

        $extractionsByType = $meeting->extractions->groupBy('type');
        $summary = $extractionsByType->get('summary')?->sortByDesc('created_at')->first();
        $decisions = $extractionsByType->get('decisions')?->sortByDesc('created_at')->first();
        $topics = $extractionsByType->get('topics')?->sortByDesc('created_at')->first();
        $risks = $extractionsByType->get('risks')?->sortByDesc('created_at')->first();

        $data = [
            'id' => $meeting->id,
            'mom_number' => $meeting->mom_number,
            'title' => $meeting->title,
            'meeting_date' => $meeting->meeting_date?->toIso8601String(),
            'location' => $meeting->location,
            'status' => $meeting->status->value,
            'organized_by' => $meeting->createdBy?->name,
            'attendees' => $meeting->attendees->map(fn ($attendee) => [
                'name' => $attendee->user?->name ?? $attendee->name ?? 'Unknown',
                'email' => $attendee->user?->email ?? $attendee->email,
                'role' => $attendee->role?->value,
                'rsvp_status' => $attendee->rsvp_status?->value,
                'is_present' => $attendee->is_present,
            ])->values()->all(),
            'summary' => $summary?->content,
            'key_points' => $summary?->structured_data['key_points'] ?? [],
            'decisions' => $decisions && ! isset($decisions->structured_data['custom_template'])
                ? $decisions->structured_data
                : ($decisions?->content ? [['description' => $decisions->content]] : []),
            'topics' => $topics && ! isset($topics->structured_data['custom_template'])
                ? $topics->structured_data
                : ($topics?->content ? [['title' => $topics->content]] : []),
            'risks' => $risks && ! isset($risks->structured_data['custom_template'])
                ? $risks->structured_data
                : ($risks?->content ? [['description' => $risks->content]] : []),
            'action_items' => $meeting->actionItems->map(fn ($item) => [
                'title' => $item->title,
                'description' => $item->description,
                'assignee_name' => $item->assignedTo?->name,
                'due_date' => $item->due_date?->toDateString(),
                'status' => $item->status->value,
                'priority' => $item->priority?->value,
            ])->values()->all(),
            'manual_notes' => $meeting->manualNotes->map(fn ($note) => [
                'content' => strip_tags($note->content ?? ''),
                'created_by' => $note->createdBy?->name,
                'created_at' => $note->created_at?->toIso8601String(),
            ])->values()->all(),
            'transcriptions' => $meeting->transcriptions->where('status', 'completed')->map(fn ($t) => [
                'filename' => $t->original_filename,
                'duration_seconds' => $t->duration_seconds,
                'text' => $t->full_text,
            ])->values()->all(),
            'exported_at' => now()->toIso8601String(),
        ];

        return response()->json($data, 200, [
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private function buildFilename(MinutesOfMeeting $meeting): string
    {
        $identifier = $meeting->mom_number ?? $meeting->id;
        $slug = str($meeting->title)->slug()->limit(50)->toString();

        return "meeting-{$identifier}-{$slug}.json";
    }
}
