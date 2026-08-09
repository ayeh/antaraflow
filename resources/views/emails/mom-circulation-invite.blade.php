<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
        h2 { color: #1e1b4b; }
        p { margin: 0 0 1em; }
        .btn { display: inline-block; padding: 12px 24px; background: #1e1b4b; color: #fff; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <p>Kepada {{ $recipient->name }},</p>

    <p>Minit mesyuarat telah diedarkan kepada anda untuk pengesahan.</p>

    @if($recipient->circulation->body_note)
        <p>{{ $recipient->circulation->body_note }}</p>
    @endif

    <p>
        <a class="btn" href="{{ route('mom.confirm', $recipient->token) }}">
            Klik di sini untuk semak dan sahkan
        </a>
    </p>

    <p>Tempoh pengesahan: {{ $recipient->circulation->deadline_at->format('d M Y, g:i A') }}</p>

    <p>Tiada maklum balas akan dianggap sebagai pengesahan.</p>
</body>
</html>
