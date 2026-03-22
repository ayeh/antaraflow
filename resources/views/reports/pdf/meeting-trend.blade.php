<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Meeting Trend &amp; Pattern Report</title>
    <style>
        body { margin: 20px; font-family: Arial, sans-serif; font-size: 12px; color: #333; }
        h1 { font-size: 20px; color: #1e1b4b; margin-bottom: 4px; }
        h2 { font-size: 14px; color: #4c1d95; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; margin-top: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #f3f4f6; text-align: left; padding: 6px; font-size: 11px; border: 1px solid #e5e7eb; }
        td { padding: 6px; border: 1px solid #e5e7eb; font-size: 11px; vertical-align: top; }
        .meta { color: #6b7280; font-size: 11px; margin-bottom: 4px; }
        .stat-grid { display: table; width: 100%; margin-top: 8px; }
        .stat-cell { display: table-cell; width: 25%; text-align: center; padding: 12px; }
        .stat-value { font-size: 24px; font-weight: bold; color: #4c1d95; }
        .stat-label { font-size: 10px; color: #6b7280; margin-top: 4px; }
        .footer { margin-top: 24px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #9ca3af; text-align: center; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <h1>Meeting Trend &amp; Pattern Report</h1>
    <p class="meta">{{ $template->name }}</p>
    <p class="meta">Period: {{ $startDate->format('F j, Y') }} &mdash; {{ $endDate->format('F j, Y') }}</p>

    <h2>Overview</h2>
    <div class="stat-grid">
        <div class="stat-cell">
            <div class="stat-value">{{ $totalMeetings }}</div>
            <div class="stat-label">Total Meetings</div>
        </div>
        <div class="stat-cell">
            <div class="stat-value">{{ $totalActionItems }}</div>
            <div class="stat-label">Action Items</div>
        </div>
        <div class="stat-cell">
            <div class="stat-value">{{ $avgAttendance }}</div>
            <div class="stat-label">Avg. Attendance</div>
        </div>
        <div class="stat-cell">
            <div class="stat-value">{{ $totalDecisions }}</div>
            <div class="stat-label">Decisions Made</div>
        </div>
    </div>

    @if($meetingsByMonth->isNotEmpty())
        <h2>Meeting Frequency by Month</h2>
        <table>
            <thead>
                <tr>
                    <th>Month</th>
                    <th class="text-right">Meetings</th>
                    <th class="text-right">Total Attendees</th>
                    <th class="text-right">Avg. Duration (min)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($meetingsByMonth as $month => $monthMeetings)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($month . '-01')->format('F Y') }}</td>
                        <td class="text-right">{{ $monthMeetings->count() }}</td>
                        <td class="text-right">{{ $monthMeetings->sum(fn ($m) => $m->attendees->count()) }}</td>
                        <td class="text-right">{{ $monthMeetings->avg('duration_minutes') ? round($monthMeetings->avg('duration_minutes')) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(count($topTopics) > 0)
        <h2>Top Recurring Topics</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th>Topic</th>
                    <th class="text-right" style="width: 20%;">Frequency</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topTopics as $topic => $count)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ ucfirst($topic) }}</td>
                        <td class="text-right">{{ $count }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(count($completionRateByMonth) > 0)
        <h2>Action Item Completion Rate by Month</h2>
        <table>
            <thead>
                <tr>
                    <th>Month</th>
                    <th class="text-right">Completion Rate</th>
                </tr>
            </thead>
            <tbody>
                @foreach($completionRateByMonth as $month => $rate)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($month . '-01')->format('F Y') }}</td>
                        <td class="text-right">{{ $rate }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($assigneeStats->isNotEmpty())
        <h2>Top Assignee Performance</h2>
        <table>
            <thead>
                <tr>
                    <th>Assignee</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">Completed</th>
                    <th class="text-right">Overdue</th>
                    <th class="text-right">Completion Rate</th>
                </tr>
            </thead>
            <tbody>
                @foreach($assigneeStats as $stat)
                    <tr>
                        <td>{{ $stat['assigned_to'] ?: '—' }}</td>
                        <td class="text-right">{{ $stat['total'] }}</td>
                        <td class="text-right">{{ $stat['completed'] }}</td>
                        <td class="text-right">{{ $stat['overdue'] }}</td>
                        <td class="text-right">{{ $stat['completion_rate'] }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Generated by antaraFLOW on {{ now()->format('F j, Y g:i A') }}
    </div>
</body>
</html>
