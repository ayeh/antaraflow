<?php

declare(strict_types=1);

namespace App\Domain\Account\Services;

use App\Domain\Account\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(string $action, Model $auditable, ?array $oldValues = null, ?array $newValues = null): AuditLog
    {
        $log = new AuditLog([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // organization_id is not mass-assignable, so it must be set on the instance —
        // passing it to create() silently dropped it and left the BelongsToOrganization
        // hook to guess from the current user. That attributed the entry to the actor's
        // organisation rather than the audited record's, and produced a NOT NULL failure
        // whenever there was no authenticated user (queued jobs, console commands).
        $log->organization_id = $auditable->organization_id
            ?? auth()->user()?->current_organization_id;

        $log->save();

        return $log;
    }
}
