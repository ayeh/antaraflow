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

    /**
     * Statuses whose content may still be rewritten in place.
     *
     * Anything further along is locked: finalized minutes are a fixed record,
     * and circulated or approved ones are what recipients are acting on.
     *
     * @return array<int, self>
     */
    public static function editable(): array
    {
        return [self::Draft, self::InProgress];
    }

    public function isEditable(): bool
    {
        return in_array($this, self::editable(), true);
    }
}
