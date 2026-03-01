<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $meeting->title }}</title>
    <style>
        body { margin: 20px; font-family: Arial, sans-serif; font-size: 12px; color: #333; }
        h1 { font-size: 20px; color: #1e1b4b; margin-bottom: 4px; }
        h2 { font-size: 14px; color: #4c1d95; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; margin-top: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #f3f4f6; text-align: left; padding: 6px; font-size: 11px; border: 1px solid #e5e7eb; }
        td { padding: 6px; border: 1px solid #e5e7eb; font-size: 11px; vertical-align: top; }
        .meta { color: #6b7280; font-size: 11px; margin-bottom: 4px; }
        .status { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .status-draft { color: #374151; background: #f3f4f6; }
        .status-in_progress { color: #1d4ed8; background: #dbeafe; }
        .status-finalized { color: #92400e; background: #fef3c7; }
        .status-approved { color: #065f46; background: #d1fae5; }
        hr { border: none; border-top: 1px solid #e5e7eb; margin: 16px 0; }
        .footer { margin-top: 24px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #9ca3af; text-align: center; }
        .note-content { background: #f9fafb; padding: 8px; margin-top: 4px; font-size: 11px; }
        .extraction-summary { background: #f9fafb; padding: 8px; margin-top: 4px; font-size: 11px; }
        .priority-low { color: #6b7280; }
        .priority-medium { color: #d97706; }
        .priority-high { color: #dc2626; }
    </style>
</head>
<body>
    <h1>{{ $meeting->title }}</h1>

    @if($meeting->meeting_date)
        <p class="meta">Date: {{ $meeting->meeting_date->format('F j, Y g:i A') }}</p>
    @endif

    @if($meeting->location)
        <p class="meta">Location: {{ $meeting->location }}</p>
    @endif

    @if($meeting->duration_minutes)
        <p class="meta">Duration: {{ $meeting->duration_minutes }} minutes</p>
    @endif

    @if($meeting->createdBy)
        <p class="meta">Organized by: {{ $meeting->createdBy->name }}</p>
    @endif

    <p class="meta">
        Status: <span class="status status-{{ str_replace(' ', '_', $meeting->status->value) }}">{{ ucfirst(str_replace('_', ' ', $meeting->status->value)) }}</span>
    </p>

    <hr>

    {{-- Attendees --}}
    @if($meeting->attendees->isNotEmpty())
        <h2>Attendees</h2>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>RSVP Status</th>
                    <th>Present</th>
                </tr>
            </thead>
            <tbody>
                @foreach($meeting->attendees as $attendee)
                    <tr>
                        <td>{{ $attendee->user?->name ?? $attendee->name ?? 'Unknown' }}</td>
                        <td>{{ $attendee->user?->email ?? $attendee->email ?? '—' }}</td>
                        <td>{{ $attendee->rsvp_status ? ucfirst($attendee->rsvp_status->value) : '—' }}</td>
                        <td>{{ $attendee->is_present ? 'Yes' : 'No' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Content / Summary --}}
    @if($meeting->extractions->isNotEmpty() || $meeting->manualNotes->isNotEmpty())
        <h2>Content &amp; Summary</h2>

        @foreach($meeting->extractions as $extraction)
            @if(!empty($extraction->structured_data))
                <div class="extraction-summary">
                    <strong>AI Extracted Summary</strong><br>
                    @if(isset($extraction->structured_data['summary']))
                        {{ $extraction->structured_data['summary'] }}
                    @elseif(isset($extraction->structured_data['content']))
                        {{ $extraction->structured_data['content'] }}
                    @else
                        {{ $extraction->raw_text ?? '' }}
                    @endif
                </div>
            @endif
        @endforeach

        @foreach($meeting->manualNotes as $note)
            <div class="note-content">
                <strong>{{ $note->createdBy?->name ?? 'Unknown' }}</strong>
                @if($note->created_at)
                    &mdash; {{ $note->created_at->format('M j, Y g:i A') }}
                @endif
                <br>
                {{ $note->content ?? '' }}
            </div>
        @endforeach
    @endif

    {{-- Action Items --}}
    @if($meeting->actionItems->isNotEmpty())
        <h2>Action Items</h2>
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Assigned To</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Due Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($meeting->actionItems as $item)
                    <tr>
                        <td>{{ $item->title }}</td>
                        <td>{{ $item->assignedTo?->name ?? 'Unassigned' }}</td>
                        <td class="priority-{{ $item->priority?->value ?? 'low' }}">{{ ucfirst($item->priority?->value ?? '—') }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $item->status->value)) }}</td>
                        <td>{{ $item->due_date?->format('M j, Y') ?? '—' }}</td>
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
