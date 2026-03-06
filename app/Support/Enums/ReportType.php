<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum ReportType: string
{
    case MonthlySummary = 'monthly_summary';
    case ActionItemStatus = 'action_item_status';
    case AttendanceReport = 'attendance_report';
    case GovernanceCompliance = 'governance_compliance';

    public function label(): string
    {
        return match ($this) {
            self::MonthlySummary => 'Monthly Summary',
            self::ActionItemStatus => 'Action Item Status',
            self::AttendanceReport => 'Attendance Report',
            self::GovernanceCompliance => 'Governance Compliance',
        };
    }
}
