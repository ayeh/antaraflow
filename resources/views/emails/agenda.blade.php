@php $brandColor = $branding->get('primary_color', '#7c3aed'); @endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { border-bottom: 2px solid {{ $brandColor }}; padding-bottom: 16px; margin-bottom: 24px; }
        .header h2 { color: {{ $brandColor }}; margin: 0; font-size: 20px; }
        .header .date { color: #6b7280; font-size: 13px; margin-top: 4px; }
        .body-content { margin-bottom: 24px; }
        .body-content p { margin: 0 0 12px; }
        .body-content ul, .body-content ol { margin: 0 0 12px; padding-left: 20px; }
        .body-content li { margin-bottom: 4px; }
        .body-content h1, .body-content h2, .body-content h3, .body-content h4 { margin: 16px 0 8px; }
        .footer { border-top: 1px solid #e5e7eb; padding-top: 16px; margin-top: 24px; font-size: 13px; color: #6b7280; }
        .btn { display: inline-block; background-color: {{ $brandColor }}; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 500; }
    </style>
</head>
<body>
    @if($branding->get('email_header_html'))
        {!! $branding->get('email_header_html') !!}
    @endif
    @if($branding->logoUrl())
        <div style="margin-bottom: 16px;">
            <img src="{{ $branding->logoUrl() }}" alt="{{ $branding->appName() }}" style="max-height: 50px; max-width: 180px;">
        </div>
    @endif
    <div class="header">
        <h2>{{ $meetingTitle }}</h2>
        @if($meetingDate)
            <div class="date">{{ $meetingDate }}</div>
        @endif
    </div>

    <div class="body-content">{!! Str::markdown($emailBody, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</div>

    @if($meetingUrl)
        <p><a href="{{ $meetingUrl }}" class="btn">{{ __('View Meeting Details') }}</a></p>
    @endif

    <div class="footer">
        @if($branding->get('email_footer_html'))
            {!! $branding->get('email_footer_html') !!}
        @else
            <p>{{ __('This email was generated from :app.', ['app' => $branding->appName()]) }}</p>
        @endif
    </div>
</body>
</html>
