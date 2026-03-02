<?php

declare(strict_types=1);

namespace App\Domain\ActionItem\Services;

use App\Domain\Account\Services\AuditService;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\ActionItemStatus;
use Illuminate\Database\Eloquent\Collection;

class ActionItemService
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function create(array $data, MinutesOfMeeting $mom, User $user): ActionItem
    {
        $data['minutes_of_meeting_id'] = $mom->id;
        $data['organization_id'] = $mom->organization_id;
        $data['created_by'] = $user->id;
        $data['status'] = ActionItemStatus::Open;

        $item = ActionItem::query()->create($data);
        $this->auditService->log('created', $item);

        return $item->fresh();
    }

    /** @param  array<string, mixed>  $data */
    public function update(ActionItem $item, array $data, User $user): ActionItem
    {
        foreach ($data as $field => $newValue) {
            $oldValue = $item->getAttribute($field);
            if ($oldValue != $newValue) {
                $item->histories()->create([
                    'changed_by' => $user->id,
                    'field_changed' => $field,
                    'old_value' => $oldValue instanceof \BackedEnum ? $oldValue->value : (string) $oldValue,
                    'new_value' => $newValue instanceof \BackedEnum ? $newValue->value : (string) $newValue,
                ]);
            }
        }

        $item->update($data);

        return $item->fresh();
    }

    public function changeStatus(ActionItem $item, ActionItemStatus $status, User $user, ?string $comment = null): ActionItem
    {
        $item->histories()->create([
            'changed_by' => $user->id,
            'field_changed' => 'status',
            'old_value' => $item->status->value,
            'new_value' => $status->value,
            'comment' => $comment,
        ]);

        $updateData = ['status' => $status];
        if ($status === ActionItemStatus::Completed) {
            $updateData['completed_at'] = now();
        }

        $item->update($updateData);

        return $item->fresh();
    }

    public function carryForward(ActionItem $item, MinutesOfMeeting $newMom, User $user): ActionItem
    {
        $newItem = ActionItem::query()->create([
            'organization_id' => $newMom->organization_id,
            'minutes_of_meeting_id' => $newMom->id,
            'assigned_to' => $item->assigned_to,
            'created_by' => $user->id,
            'carried_from_id' => $item->id,
            'title' => $item->title,
            'description' => $item->description,
            'priority' => $item->priority,
            'status' => ActionItemStatus::Open,
            'due_date' => $item->due_date,
        ]);

        $this->changeStatus($item, ActionItemStatus::CarriedForward, $user, "Carried forward to meeting #{$newMom->id}");

        return $newItem;
    }

    public function getOverdueItems(int $organizationId): Collection
    {
        return ActionItem::query()
            ->where('organization_id', $organizationId)
            ->whereNotIn('status', [ActionItemStatus::Completed, ActionItemStatus::Cancelled, ActionItemStatus::CarriedForward])
            ->where('due_date', '<', now())
            ->with(['assignedTo', 'meeting'])
            ->get();
    }

    /**
     * Mark all open action items in a meeting as having tasks created.
     *
     * @return int Number of action items marked
     */
    public function createAllTasks(MinutesOfMeeting $mom, User $user): int
    {
        $items = $mom->actionItems()
            ->whereNotIn('status', [ActionItemStatus::Completed, ActionItemStatus::Cancelled, ActionItemStatus::CarriedForward])
            ->whereNull('metadata->tasks_created_at')
            ->get();

        foreach ($items as $item) {
            $metadata = $item->metadata ?? [];
            $metadata['tasks_created_at'] = now()->toIso8601String();
            $metadata['tasks_created_by'] = $user->id;
            $item->update(['metadata' => $metadata]);
            $this->auditService->log('tasks_created', $item);
        }

        return $items->count();
    }

    public function getDashboard(int $organizationId, ?int $userId = null): Collection
    {
        $query = ActionItem::query()
            ->where('organization_id', $organizationId)
            ->whereNotIn('status', [ActionItemStatus::Cancelled, ActionItemStatus::CarriedForward])
            ->with(['assignedTo', 'meeting', 'createdBy']);

        if ($userId) {
            $query->where('assigned_to', $userId);
        }

        return $query->orderByRaw('CASE WHEN due_date IS NOT NULL AND due_date < ? THEN 0 ELSE 1 END', [now()])
            ->orderBy('due_date')
            ->orderBy('priority')
            ->get();
    }
}
