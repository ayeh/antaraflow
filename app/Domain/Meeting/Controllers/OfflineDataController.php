<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Controllers;

use App\Domain\Collaboration\Models\Comment;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomManualNote;
use App\Support\Enums\InputType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OfflineDataController extends Controller
{
    use AuthorizesRequests;

    /**
     * Return full meeting JSON for offline caching.
     */
    public function show(MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $meeting->load([
            'attendees.user',
            'actionItems.assignedTo',
            'extractions',
            'manualNotes.createdBy',
        ]);

        return response()->json([
            'id' => $meeting->id,
            'title' => $meeting->title,
            'mom_number' => $meeting->mom_number,
            'status' => $meeting->status->value,
            'meeting_date' => $meeting->meeting_date?->toISOString(),
            'location' => $meeting->location,
            'summary' => $meeting->summary,
            'content' => $meeting->content,
            'attendees' => $meeting->attendees->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->user?->name ?? $a->name,
                'email' => $a->user?->email ?? $a->email,
                'is_present' => $a->is_present,
                'rsvp_status' => $a->rsvp_status,
            ]),
            'action_items' => $meeting->actionItems->map(fn ($ai) => [
                'id' => $ai->id,
                'title' => $ai->title,
                'description' => $ai->description,
                'status' => $ai->status->value,
                'due_date' => $ai->due_date?->toISOString(),
                'assigned_to' => $ai->assignedTo?->name,
            ]),
            'extractions' => $meeting->extractions->map(fn ($e) => [
                'id' => $e->id,
                'type' => $e->type,
                'content' => $e->content,
                'created_at' => $e->created_at?->toISOString(),
            ]),
            'notes' => $meeting->manualNotes->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->title,
                'content' => $n->content,
                'created_by' => $n->createdBy?->name,
                'created_at' => $n->created_at?->toISOString(),
            ]),
            'cached_at' => now()->toISOString(),
        ]);
    }

    /**
     * Process queued offline actions (notes, comments).
     */
    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'actions' => ['required', 'array', 'min:1'],
            'actions.*.type' => ['required', 'string', 'in:note,comment'],
            'actions.*.meeting_id' => ['required', 'integer', 'exists:minutes_of_meetings,id'],
            'actions.*.payload' => ['required', 'array'],
            'actions.*.offline_id' => ['required', 'string'],
        ]);

        $results = [];

        foreach ($validated['actions'] as $action) {
            $meeting = MinutesOfMeeting::findOrFail($action['meeting_id']);
            $this->authorize('update', $meeting);

            $result = match ($action['type']) {
                'note' => $this->processNote($meeting, $action['payload'], $request->user()),
                'comment' => $this->processComment($meeting, $action['payload'], $request->user()),
            };

            $results[] = [
                'offline_id' => $action['offline_id'],
                'status' => 'synced',
                'server_id' => $result->id,
            ];
        }

        return response()->json([
            'synced' => $results,
            'synced_at' => now()->toISOString(),
        ]);
    }

    /**
     * Create a manual note from an offline action.
     */
    private function processNote(MinutesOfMeeting $meeting, array $payload, $user): MomManualNote
    {
        $note = $meeting->manualNotes()->create([
            'created_by' => $user->id,
            'title' => $payload['title'] ?? 'Offline Note',
            'content' => $payload['content'] ?? '',
        ]);

        $meeting->inputs()->create([
            'type' => InputType::ManualNote,
            'source_type' => MomManualNote::class,
            'source_id' => $note->id,
        ]);

        return $note;
    }

    /**
     * Create a comment from an offline action.
     */
    private function processComment(MinutesOfMeeting $meeting, array $payload, $user): Comment
    {
        return Comment::create([
            'user_id' => $user->id,
            'organization_id' => $meeting->organization_id,
            'commentable_type' => MinutesOfMeeting::class,
            'commentable_id' => $meeting->id,
            'body' => $payload['body'] ?? '',
        ]);
    }
}
