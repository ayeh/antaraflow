<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Models\AnalyticsEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AnalyticsEventService
{
    public static function track(
        string $eventType,
        Model $subject,
        ?User $user = null,
        array $properties = [],
    ): void {
        $orgId = $subject->organization_id ?? $user?->current_organization_id;

        if (! $orgId) {
            return;
        }

        AnalyticsEvent::create([
            'organization_id' => $orgId,
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'properties' => $properties ?: null,
            'occurred_at' => now(),
        ]);
    }
}
