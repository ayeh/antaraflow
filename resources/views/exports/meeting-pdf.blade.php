<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $meeting->title }}</title>
    <style>
        body { margin: 20px; font-family: Arial, sans-serif; font-size: 12px; color: #333; }
        h1 { font-size: 20px; color: #1e1b4b; margin-bottom: 4px; }
        h2 { font-size: 14px; color: #4c1d95; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; margin-top: 20px; margin-bottom: 8px; }
        h3 { font-size: 12px; color: #374151; margin: 8px 0 4px; }
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
        .box { background: #f9fafb; padding: 8px 10px; margin-top: 4px; font-size: 11px; line-height: 1.6; border-left: 3px solid #7c3aed; }
        .note-box { background: #f9fafb; padding: 8px 10px; margin-top: 6px; font-size: 11px; line-height: 1.6; border-left: 3px solid #d1d5db; }
        .priority-low { color: #6b7280; }
        .priority-medium { color: #d97706; }
        .priority-high { color: #dc2626; }
        .key-point { margin: 2px 0; padding-left: 12px; }
        .key-point::before { content: "• "; color: #7c3aed; }
        .decision-item { margin: 4px 0; padding: 6px 8px; background: #faf5ff; border-left: 2px solid #7c3aed; font-size: 11px; }
        .risk-item { margin: 4px 0; padding: 6px 8px; background: #fff7ed; border-left: 2px solid #f97316; font-size: 11px; }
        .topic-item { margin: 4px 0; padding: 6px 8px; background: #f0fdf4; border-left: 2px solid #22c55e; font-size: 11px; }
        .agenda-item { margin: 4px 0; padding: 6px 8px; background: #eff6ff; border-left: 2px solid #3b82f6; font-size: 11px; }
        .signature-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        .signature-table td { border: none; padding: 4px 16px 4px 0; vertical-align: bottom; width: 50%; font-size: 11px; }
        .signature-line { border-bottom: 1px solid #374151; margin-top: 40px; margin-bottom: 4px; }
        .next-meeting-box { background: #f9fafb; border: 1px dashed #d1d5db; padding: 10px 12px; margin-top: 6px; font-size: 11px; min-height: 40px; }
    </style>
</head>
<body>

    {{-- Header --}}
    <h1>{{ $meeting->title }}</h1>
    @if($meeting->mom_number)
        <p class="meta"><strong>Ref No:</strong> {{ $meeting->mom_number }}</p>
    @endif
    @if($meeting->meeting_date)
        <p class="meta"><strong>Tarikh:</strong> {{ $meeting->meeting_date->format('d F Y') }}
            @if($meeting->start_time) &nbsp;|&nbsp; {{ \Carbon\Carbon::parse($meeting->start_time)->format('g:i A') }}
                @if($meeting->end_time) – {{ \Carbon\Carbon::parse($meeting->end_time)->format('g:i A') }} @endif
            @endif
        </p>
    @endif
    @if($meeting->location)
        <p class="meta"><strong>Tempat:</strong> {{ $meeting->location }}</p>
    @endif
    @if($meeting->duration_minutes)
        <p class="meta"><strong>Tempoh:</strong> {{ $meeting->duration_minutes }} minit</p>
    @endif
    @if($meeting->createdBy)
        <p class="meta"><strong>Pengerusi:</strong> {{ $meeting->createdBy->name }}</p>
    @endif
    @if($meeting->prepared_by)
        <p class="meta"><strong>Disediakan oleh:</strong> {{ $meeting->prepared_by }}</p>
    @endif
    <p class="meta">
        <strong>Status:</strong> <span class="status status-{{ str_replace(' ', '_', $meeting->status->value) }}">{{ ucfirst(str_replace('_', ' ', $meeting->status->value)) }}</span>
    </p>

    <hr>

    {{-- Attendees --}}
    @if($meeting->attendees->isNotEmpty())
        <h2>Senarai Peserta</h2>
        <table>
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>E-mel</th>
                    <th>RSVP</th>
                    <th>Hadir</th>
                </tr>
            </thead>
            <tbody>
                @foreach($meeting->attendees as $attendee)
                    <tr>
                        <td>{{ $attendee->user?->name ?? $attendee->name ?? 'Unknown' }}</td>
                        <td>{{ $attendee->user?->email ?? $attendee->email ?? '—' }}</td>
                        <td>{{ $attendee->rsvp_status ? ucfirst($attendee->rsvp_status->value) : '—' }}</td>
                        <td>{{ $attendee->is_present ? 'Ya' : 'Tidak' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Agenda --}}
    @if($meeting->topics->isNotEmpty())
        <h2>Agenda</h2>
        @foreach($meeting->topics->sortBy('sort_order') as $i => $item)
            <div class="agenda-item">
                <strong>{{ $i + 1 }}. {{ $item->title }}</strong>
                @if($item->description)
                    <br><span style="color:#6b7280">{{ $item->description }}</span>
                @endif
                @if($item->duration_minutes)
                    <span style="color:#9ca3af; float:right">{{ $item->duration_minutes }} min</span>
                @endif
            </div>
        @endforeach
    @endif

    @php
        $extractionsByType = $meeting->extractions->groupBy('type');
        $summary    = $extractionsByType->get('summary')?->sortByDesc('created_at')->first();
        $decisions  = $extractionsByType->get('decisions')?->sortByDesc('created_at')->first();
        $topics     = $extractionsByType->get('topics')?->sortByDesc('created_at')->first();
        $risks      = $extractionsByType->get('risks')?->sortByDesc('created_at')->first();
    @endphp

    {{-- Summary / Rumusan --}}
    @if($summary && $summary->content)
        <h2>Rumusan</h2>
        <div class="box">{{ $summary->content }}</div>
        @if(!empty($summary->structured_data['key_points']))
            <h3>Perkara Utama</h3>
            @php
                $keyPoints = is_array($summary->structured_data['key_points'])
                    ? $summary->structured_data['key_points']
                    : array_filter(explode("\n", $summary->structured_data['key_points']));
            @endphp
            @foreach($keyPoints as $point)
                <div class="key-point">{{ trim($point) }}</div>
            @endforeach
        @endif
    @endif

    {{-- Meeting Notes / Content --}}
    @if($meeting->content)
        <h2>Nota Mesyuarat</h2>
        <div class="box">{{ strip_tags($meeting->content) }}</div>
    @endif

    {{-- Manual Notes --}}
    @if($meeting->manualNotes->isNotEmpty())
        <h2>Nota Manual</h2>
        @foreach($meeting->manualNotes as $note)
            <div class="note-box">
                <strong>{{ $note->createdBy?->name ?? 'Unknown' }}</strong>
                @if($note->created_at) &mdash; {{ $note->created_at->format('d M Y g:i A') }} @endif
                <br>{{ strip_tags($note->content ?? '') }}
            </div>
        @endforeach
    @endif

    {{-- Topics Discussed --}}
    @if($topics && !empty($topics->structured_data) && !isset($topics->structured_data['custom_template']))
        <h2>Perkara yang Dibincangkan</h2>
        @foreach($topics->structured_data as $topic)
            <div class="topic-item">
                @if(is_array($topic))
                    <strong>{{ $topic['title'] ?? $topic['topic'] ?? '' }}</strong>
                    @if(!empty($topic['description'] ?? $topic['summary'] ?? ''))
                        <br><span style="color:#6b7280">{{ $topic['description'] ?? $topic['summary'] ?? '' }}</span>
                    @endif
                @else
                    {{ $topic }}
                @endif
            </div>
        @endforeach
    @elseif($topics && $topics->content)
        <h2>Perkara yang Dibincangkan</h2>
        <div class="box">{{ $topics->content }}</div>
    @endif

    {{-- Decisions --}}
    @if($decisions && !empty($decisions->structured_data) && !isset($decisions->structured_data['custom_template']))
        <h2>Keputusan</h2>
        @foreach($decisions->structured_data as $decision)
            <div class="decision-item">
                @if(is_array($decision))
                    <strong>{{ $decision['title'] ?? $decision['decision'] ?? '' }}</strong>
                    @if(!empty($decision['description'] ?? $decision['rationale'] ?? $decision['context'] ?? ''))
                        <br><span style="color:#6b7280">{{ $decision['description'] ?? $decision['rationale'] ?? $decision['context'] ?? '' }}</span>
                    @endif
                @else
                    {{ $decision }}
                @endif
            </div>
        @endforeach
    @elseif($decisions && $decisions->content)
        <h2>Keputusan</h2>
        <div class="box">{{ $decisions->content }}</div>
    @endif

    {{-- Risks --}}
    @if($risks && !empty($risks->structured_data) && !isset($risks->structured_data['custom_template']))
        <h2>Risiko &amp; Isu</h2>
        @foreach($risks->structured_data as $risk)
            <div class="risk-item">
                @if(is_array($risk))
                    <strong>{{ $risk['title'] ?? $risk['risk'] ?? '' }}</strong>
                    @if(!empty($risk['description'] ?? $risk['mitigation'] ?? ''))
                        <br><span style="color:#6b7280">{{ $risk['description'] ?? $risk['mitigation'] ?? '' }}</span>
                    @endif
                @else
                    {{ $risk }}
                @endif
            </div>
        @endforeach
    @elseif($risks && $risks->content)
        <h2>Risiko &amp; Isu</h2>
        <div class="box">{{ $risks->content }}</div>
    @endif

    {{-- Action Items --}}
    @if($meeting->actionItems->isNotEmpty())
        <h2>Tindakan Susulan</h2>
        <table>
            <thead>
                <tr>
                    <th>Tindakan</th>
                    <th>Tanggungjawab</th>
                    <th>Keutamaan</th>
                    <th>Status</th>
                    <th>Tarikh Siap</th>
                </tr>
            </thead>
            <tbody>
                @foreach($meeting->actionItems as $item)
                    <tr>
                        <td>{{ $item->title }}</td>
                        <td>{{ $item->assignedTo?->name ?? 'Belum Ditetapkan' }}</td>
                        <td class="priority-{{ $item->priority?->value ?? 'low' }}">{{ ucfirst($item->priority?->value ?? '—') }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $item->status->value)) }}</td>
                        <td>{{ $item->due_date?->format('d M Y') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Mesyuarat Seterusnya --}}
    <h2>Mesyuarat Seterusnya</h2>
    <div class="next-meeting-box">
        <table style="width:100%; border:none;">
            <tr>
                <td style="border:none; width:20%; color:#6b7280;">Tarikh &amp; Masa:</td>
                <td style="border:none; border-bottom:1px solid #d1d5db;">&nbsp;</td>
            </tr>
            <tr><td colspan="2" style="border:none; height:8px;"></td></tr>
            <tr>
                <td style="border:none; color:#6b7280;">Tempat:</td>
                <td style="border:none; border-bottom:1px solid #d1d5db;">&nbsp;</td>
            </tr>
        </table>
    </div>

    {{-- Signature --}}
    <h2>Pengesahan</h2>
    <table class="signature-table">
        <tr>
            <td>
                <div class="signature-line"></div>
                <strong>{{ $meeting->prepared_by ?? $meeting->createdBy?->name ?? '________________' }}</strong><br>
                <span style="color:#6b7280">Disediakan oleh / Setiausaha</span>
            </td>
            <td>
                <div class="signature-line"></div>
                <strong>{{ $meeting->createdBy?->name ?? '________________' }}</strong><br>
                <span style="color:#6b7280">Disahkan oleh / Pengerusi</span>
            </td>
        </tr>
        <tr>
            <td style="padding-top:12px;">
                <span style="color:#6b7280">Tarikh: </span>
                <span style="border-bottom:1px solid #374151; display:inline-block; width:120px;">&nbsp;</span>
            </td>
            <td style="padding-top:12px;">
                <span style="color:#6b7280">Tarikh: </span>
                <span style="border-bottom:1px solid #374151; display:inline-block; width:120px;">&nbsp;</span>
            </td>
        </tr>
    </table>

    <div class="footer">
        Dijana oleh antaraNote pada {{ now()->format('d F Y g:i A') }}
    </div>
</body>
</html>
