<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Services;

use App\Domain\Account\Services\AuditService;
use App\Domain\Meeting\Models\MeetingSeries;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use Illuminate\Database\Eloquent\Collection;

class MeetingSeriesService
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $user): MeetingSeries
    {
        $data['organization_id'] = $user->current_organization_id;

        $series = MeetingSeries::query()->create($data);
        $this->auditService->log('created', $series);

        return $series->fresh();
    }

    /** @param array<string, mixed> $data */
    public function update(MeetingSeries $series, array $data): MeetingSeries
    {
        $series->update($data);
        $this->auditService->log('updated', $series);

        return $series->fresh();
    }

    public function delete(MeetingSeries $series): void
    {
        $this->auditService->log('deleted', $series);
        $series->delete();
    }

    /** @return Collection<int, MinutesOfMeeting> */
    public function generateUpcoming(MeetingSeries $series, int $count, User $user): Collection
    {
        $lastMeeting = $series->meetings()->latest('meeting_date')->first();
        $nextDate = $lastMeeting?->meeting_date ?? now();

        $created = new Collection;

        for ($i = 0; $i < $count; $i++) {
            $nextDate = match ($series->recurrence_pattern) {
                'weekly' => $nextDate->copy()->addWeek(),
                'biweekly' => $nextDate->copy()->addWeeks(2),
                'monthly' => $nextDate->copy()->addMonth(),
                default => $nextDate->copy()->addWeek(),
            };

            $meeting = MinutesOfMeeting::query()->create([
                'organization_id' => $series->organization_id,
                'meeting_series_id' => $series->id,
                'created_by' => $user->id,
                'title' => $series->name,
                'meeting_date' => $nextDate,
                'status' => MeetingStatus::Draft,
            ]);

            $created->push($meeting);
        }

        return $created;
    }
}
