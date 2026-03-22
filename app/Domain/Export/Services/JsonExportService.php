<?php

declare(strict_types=1);

namespace App\Domain\Export\Services;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Http\Response;

class JsonExportService
{
    public function export(MinutesOfMeeting $meeting): Response
    {
        $meeting->load(['attendees', 'topics', 'actionItems.assignedTo', 'resolutions']);

        $filename = $this->buildFilename($meeting);

        $data = [
            'id' => $meeting->id,
            'mom_number' => $meeting->mom_number,
            'title' => $meeting->title,
            'meeting_date' => $meeting->meeting_date?->toIso8601String(),
            'location' => $meeting->location,
            'status' => $meeting->status->value,
            'attendees' => $meeting->attendees->map(fn ($attendee) => [
                'name' => $attendee->name,
                'role' => $attendee->role->value,
                'email' => $attendee->email,
                'is_present' => $attendee->is_present,
            ])->values()->all(),
            'topics' => $meeting->topics->map(fn ($topic) => [
                'title' => $topic->title,
                'content' => $topic->description,
                'order' => $topic->sort_order,
            ])->values()->all(),
            'action_items' => $meeting->actionItems->map(fn ($item) => [
                'description' => $item->description ?? $item->title,
                'assignee_name' => $item->assignedTo?->name,
                'due_date' => $item->due_date?->toDateString(),
                'status' => $item->status->value,
                'priority' => $item->priority->value,
            ])->values()->all(),
            'decisions' => $meeting->resolutions->map(fn ($resolution) => [
                'description' => $resolution->description,
            ])->values()->all(),
            'summary' => $meeting->summary,
            'exported_at' => now()->toIso8601String(),
        ];

        return response(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function buildFilename(MinutesOfMeeting $meeting): string
    {
        $identifier = $meeting->mom_number ?? $meeting->id;
        $slug = str($meeting->title)->slug()->limit(50)->toString();

        return "meeting-{$identifier}-{$slug}.json";
    }
}
