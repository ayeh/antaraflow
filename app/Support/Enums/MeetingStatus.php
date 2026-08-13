<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum MeetingStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case Finalized = 'finalized';
    case PendingConfirmation = 'pending_confirmation';
    case Approved = 'approved';

    /**
     * Statuses a MOM can be reverted to draft from.
     *
     * Single source of truth for the policy, the service and the views —
     * they drifted apart before, which rendered buttons that always 403'd.
     *
     * @return array<int, self>
     */
    public static function revertable(): array
    {
        return [self::Finalized, self::PendingConfirmation, self::Approved];
    }

    public function isRevertable(): bool
    {
        return in_array($this, self::revertable(), true);
    }

}
