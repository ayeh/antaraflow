@php $brandColor = $branding->get('primary_color', '#7c3aed'); @endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { border-bottom: 2px solid {{ $brandColor }}; padding-bottom: 16px; margin-bottom: 24px; }
        .header h2 { color: {{ $brandColor }}; margin: 0; font-size: 20px; }
        .body-content { margin-bottom: 24px; }
        .body-content p { margin: 0 0 12px; }
        .meta { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px 16px; margin-bottom: 24px; font-size: 14px; color: #374151; }
        .meta strong { color: #111827; }
        .alert { background: #fef3c7; border: 1px solid #fcd34d; border-radius: 6px; padding: 10px 16px; margin-bottom: 20px; font-size: 14px; color: #92400e; }
        .footer { border-top: 1px solid #e5e7eb; padding-top: 16px; margin-top: 24px; font-size: 13px; color: #6b7280; }
        .btn { display: inline-block; background-color: {{ $brandColor }}; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 500; }
    </style>
</head>
<body>
    @if($branding->get('email_header_html'))
        {!! $branding->get('email_header_html') !!}
    @endif

    <div class="header">
        <h2>⏰ Peringatan: Pengesahan Minit Mesyuarat</h2>
    </div>

    <div class="body-content">
        <p>Kepada <strong>{{ $recipient->name }}</strong>,</p>

        @if($hasOpened)
            <p>Anda telah membuka minit mesyuarat tetapi belum memberi maklum balas. Masa semakin terhad!</p>
        @else
            <p>Anda belum membuka minit mesyuarat yang telah diedarkan kepada anda. Sila semak sebelum tempoh tamat.</p>
        @endif
    </div>

    <div class="alert">
        ⚠️ Kurang dari 24 jam lagi — sila sahkan sebelum tempoh tamat.
    </div>

    <div class="meta">
        <strong>Mesyuarat:</strong> {{ $recipient->circulation->meeting->title }}<br>
        <strong>Tempoh pengesahan:</strong> {{ $recipient->circulation->deadline_at->format('d M Y, g:i A') }}
    </div>

    <p><a href="{{ route('mom.confirm', $recipient->token) }}" class="btn">Semak &amp; Sahkan Minit</a></p>

    <div class="footer">
        @if($branding->get('email_footer_html'))
            {!! $branding->get('email_footer_html') !!}
        @else
            <p>Tiada maklum balas akan dianggap sebagai pengesahan.</p>
            <p>E-mel ini dihantar oleh {{ $branding->appName() }}.</p>
        @endif
    </div>
</body>
</html>
