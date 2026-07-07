<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Meeting Trend & Pattern Report') }}</title>
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
    <h1>{{ __('Meeting Trend & Pattern Report') }}</h1>
    <p class="meta">{{ $template->name }}</p>
    <p class="meta">{{ __('Period: :start — :end', ['start' => $startDate->format('F j, Y'), 'end' => $endDate->format('F j, Y')]) }}</p>

    <h2>{{ __('Overview') }}</h2>
    <div class="stat-grid">
        <div class="stat-cell">
            <div class="stat-value">{{ $totalMeetings }}</div>
            <div class="stat-label">{{ __('Total Meetings') }}</div>
        </div>
        <div class="stat-cell">
            <div class="stat-value">{{ $totalActionItems }}</div>
            <div class="stat-label">{{ __('Action Items') }}</div>
        </div>
        <div class="stat-cell">
            <div class="stat-value">{{ $avgAttendance }}</div>
            <div class="stat-label">{{ __('Avg. Attendance') }}</div>
        </div>
        <div class="stat-cell">
            <div class="stat-value">{{ $totalDecisions }}</div>
            <div class="stat-label">{{ __('Decisions Made') }}</div>
        </div>
    </div>

    @if($meetingsByMonth->isNotEmpty())
        <h2>{{ __('Meeting Frequency by Month') }}</h2>
        <table>
            <thead>
                <tr>
                    <th>{{ __('Month') }}</th>
                    <th class="text-right">{{ __('Meetings') }}</th>
                    <th class="text-right">{{ __('Total Attendees') }}</th>
                    <th class="text-right">{{ __('Avg. Duration (min)') }}</th>
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
        <h2>{{ __('Top Recurring Topics') }}</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th>{{ __('Topic') }}</th>
                    <th class="text-right" style="width: 20%;">{{ __('Frequency') }}</th>
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
        <h2>{{ __('Action Item Completion Rate by Month') }}</h2>
        <table>
            <thead>
                <tr>
                    <th>{{ __('Month') }}</th>
                    <th class="text-right">{{ __('Completion Rate') }}</th>
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
        <h2>{{ __('Top Assignee Performance') }}</h2>
        <table>
            <thead>
                <tr>
                    <th>{{ __('Assignee') }}</th>
                    <th class="text-right">{{ __('Total') }}</th>
                    <th class="text-right">{{ __('Completed') }}</th>
                    <th class="text-right">{{ __('Overdue') }}</th>
                    <th class="text-right">{{ __('Completion Rate') }}</th>
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
        {{ __('Generated by antaraNote on :date', ['date' => now()->format('F j, Y g:i A')]) }}
    </div>
</body>
</html>
