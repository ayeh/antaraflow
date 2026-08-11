<?php

declare(strict_types=1);

namespace App\Infrastructure\Sync;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\ActionItem\Services\ActionItemService;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Services\CommentService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomManualNote;
use App\Models\User;
use App\Support\Enums\ActionItemStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Two-way sync for the offline store on the phone.
 *
 * Pull is a delta keyed on a cursor; push is a batch of queued mutations, each
 * applied in its own transaction so one rejected item cannot take the rest of
 * the batch down with it.
 */
class MobileSyncService
{
    /**
     * How far back a cursor may be and still be usable. Past this a client has
     * to start clean, because deletions older than this are no longer recorded
     * and it would otherwise keep rows that are gone.
     */
    private const TOMBSTONE_RETENTION_DAYS = 30;

    /** Entities a client can pull, in dependency order. */
    private const PULLABLE = [
        'meetings' => MinutesOfMeeting::class,
        'action_items' => ActionItem::class,
        'attendees' => MomAttendee::class,
        'comments' => Comment::class,
        'manual_notes' => MomManualNote::class,
    ];

    public function __construct(
        private readonly ActionItemService $actionItemService,
        private readonly CommentService $commentService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function pull(User $user, ?SyncCursor $cursor, int $limit = 500): array
    {
        if ($cursor !== null && $cursor->since->lt(now()->subDays(self::TOMBSTONE_RETENTION_DAYS))) {
            return [
                'changes' => [],
                'cursor' => (new SyncCursor(now()))->encode(),
                'has_more' => false,
                'full_resync_required' => true,
            ];
        }

        $since = $cursor?->since;
        $changes = [];
        $highWater = $since ?? now()->subYears(10);
        $hasMore = false;

        foreach (self::PULLABLE as $key => $model) {
            /** @var Builder<Model> $query */
            $query = $model::query();

            if ($this->usesSoftDeletes($model)) {
                $query->withTrashed();
            }

            if ($since !== null) {
                $query->where('updated_at', '>', $since);
            }

            $rows = $query->orderBy('updated_at')->orderBy('id')->limit($limit + 1)->get();

            if ($rows->count() > $limit) {
                $hasMore = true;
                $rows = $rows->take($limit);
            }

            [$upserted, $deleted] = $this->partition($rows);

            $changes[$key] = ['upserted' => $upserted, 'deleted' => $deleted];

            $last = $rows->last();

            if ($last?->updated_at !== null && $last->updated_at->gt($highWater)) {
                $highWater = $last->updated_at;
            }
        }

        $changes['tombstones'] = $this->tombstones($user, $since);

        return [
            'changes' => $changes,
            'cursor' => (new SyncCursor($highWater))->encode(),
            'has_more' => $hasMore,
            'full_resync_required' => false,
        ];
    }

    /**
     * Apply one queued mutation.
     *
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    public function apply(User $user, array $operation): array
    {
        $clientId = (string) ($operation['client_id'] ?? '');

        try {
            $result = DB::transaction(fn () => $this->dispatch($user, $operation));

            return ['client_id' => $clientId, 'status' => 'applied', ...$result];
        } catch (SyncConflict $conflict) {
            return [
                'client_id' => $clientId,
                'status' => 'conflict',
                'reason' => $conflict->reason,
                'server_state' => $conflict->serverState,
            ];
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            return ['client_id' => $clientId, 'status' => 'rejected', 'reason' => 'FORBIDDEN'];
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ['client_id' => $clientId, 'status' => 'rejected', 'reason' => 'NOT_FOUND'];
        } catch (\Throwable $e) {
            return [
                'client_id' => $clientId,
                'status' => 'rejected',
                'reason' => 'ERROR',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function dispatch(User $user, array $operation): array
    {
        return match ($operation['entity']) {
            'action_item' => $this->applyActionItem($user, $operation),
            'comment' => $this->applyComment($user, $operation),
            'manual_note' => $this->applyManualNote($user, $operation),
            default => throw new \InvalidArgumentException('Unsupported entity.'),
        };
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function applyActionItem(User $user, array $operation): array
    {
        $payload = $operation['payload'] ?? [];

        if (($operation['op'] ?? 'update') === 'create') {
            $meeting = MinutesOfMeeting::query()->findOrFail($operation['meeting_id']);
            $item = $this->actionItemService->create($payload, $meeting, $user);

            return ['id' => $item->id, 'server_updated_at' => $item->updated_at?->toIso8601String()];
        }

        /** @var ActionItem $item */
        $item = ActionItem::query()->findOrFail($operation['id']);

        if (! $user->can('update', $item)) {
            throw new \Illuminate\Auth\Access\AuthorizationException;
        }

        $this->guardStaleWrite($item, $operation);

        if (isset($payload['status'])) {
            $this->actionItemService->changeStatus(
                $item,
                ActionItemStatus::from($payload['status']),
                $user,
                $payload['comment'] ?? null,
            );
            unset($payload['status'], $payload['comment']);
        }

        if ($payload !== []) {
            $this->actionItemService->update($item, $payload, $user);
        }

        $item->refresh();

        return ['id' => $item->id, 'server_updated_at' => $item->updated_at?->toIso8601String()];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function applyComment(User $user, array $operation): array
    {
        // Comments only ever append, so there is nothing here to conflict.
        $meeting = MinutesOfMeeting::query()->findOrFail($operation['meeting_id']);

        if (! $user->can('view', $meeting)) {
            throw new \Illuminate\Auth\Access\AuthorizationException;
        }

        $comment = $this->commentService->addComment(
            $meeting,
            $user,
            (string) ($operation['payload']['body'] ?? ''),
            isset($operation['payload']['parent_id']) ? (int) $operation['payload']['parent_id'] : null,
        );

        return ['id' => $comment->id, 'server_updated_at' => $comment->updated_at?->toIso8601String()];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function applyManualNote(User $user, array $operation): array
    {
        $meeting = MinutesOfMeeting::query()->findOrFail($operation['meeting_id']);

        if (! $user->can('update', $meeting)) {
            throw new \Illuminate\Auth\Access\AuthorizationException;
        }

        $payload = $operation['payload'] ?? [];

        if (($operation['op'] ?? 'create') === 'create') {
            $note = $meeting->manualNotes()->create([
                'created_by' => $user->id,
                'title' => $payload['title'] ?? null,
                'content' => $payload['content'] ?? '',
            ]);

            return ['id' => $note->id, 'server_updated_at' => $note->updated_at?->toIso8601String()];
        }

        /** @var MomManualNote $note */
        $note = MomManualNote::query()->findOrFail($operation['id']);
        $this->guardStaleWrite($note, $operation);
        $note->update(array_intersect_key($payload, array_flip(['title', 'content'])));

        return ['id' => $note->id, 'server_updated_at' => $note->fresh()->updated_at?->toIso8601String()];
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function guardStaleWrite(Model $model, array $operation): void
    {
        $base = $operation['base_updated_at'] ?? null;

        if ($base === null) {
            return;
        }

        $serverUpdatedAt = $model->updated_at;

        if ($serverUpdatedAt !== null && $serverUpdatedAt->gt(Carbon::parse($base))) {
            throw new SyncConflict('STALE_WRITE', $model->toArray());
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Model>  $rows
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, int>}
     */
    private function partition(\Illuminate\Support\Collection $rows): array
    {
        $upserted = [];
        $deleted = [];

        foreach ($rows as $row) {
            if (method_exists($row, 'trashed') && $row->trashed()) {
                $deleted[] = $row->getKey();

                continue;
            }

            $upserted[] = $row->toArray();
        }

        return [$upserted, $deleted];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tombstones(User $user, ?Carbon $since): array
    {
        return SyncTombstone::query()
            ->where('organization_id', $user->current_organization_id)
            ->when($since !== null, fn (Builder $query) => $query->where('deleted_at', '>', $since))
            ->orderBy('deleted_at')
            ->limit(1000)
            ->get(['entity', 'entity_id', 'deleted_at'])
            ->map(fn (SyncTombstone $tombstone) => [
                'entity' => $tombstone->entity,
                'entity_id' => $tombstone->entity_id,
                'deleted_at' => $tombstone->deleted_at?->toIso8601String(),
            ])
            ->all();
    }

    /** @param class-string<Model> $model */
    private function usesSoftDeletes(string $model): bool
    {
        return in_array(
            \Illuminate\Database\Eloquent\SoftDeletes::class,
            class_uses_recursive($model),
            true,
        );
    }
}
