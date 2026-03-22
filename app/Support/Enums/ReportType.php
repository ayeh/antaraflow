<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum ReportType: string
{
    case MonthlySummary = 'monthly_summary';
    case ActionItemStatus = 'action_item_status';
    case AttendanceReport = 'attendance_report';
    case GovernanceCompliance = 'governance_compliance';
    case MeetingTrend = 'meeting_trend';

    public function label(): string
    {
        return match ($this) {
            self::MonthlySummary => 'Monthly Summary',
            self::ActionItemStatus => 'Action Item Status',
            self::AttendanceReport => 'Attendance Report',
            self::GovernanceCompliance => 'Governance Compliance',
            self::MeetingTrend => 'Meeting Trend & Pattern',
        };
    }
}
