<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Services;

use App\Domain\Meeting\Models\MinutesOfMeeting;

class MomNumberService
{
    public function generate(int $organizationId): string
    {
        $year = date('Y');
        $prefix = "MOM-{$year}-";

        $lastNumber = MinutesOfMeeting::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('mom_number', 'like', "{$prefix}%")
            ->orderByDesc('mom_number')
            ->value('mom_number');

        if ($lastNumber) {
            $sequence = (int) str_replace($prefix, '', $lastNumber) + 1;
        } else {
            $sequence = 1;
        }

        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
