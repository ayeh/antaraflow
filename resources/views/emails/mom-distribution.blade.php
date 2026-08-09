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
        .footer { border-top: 1px solid #e5e7eb; padding-top: 16px; margin-top: 24px; font-size: 13px; color: #6b7280; }
    </style>
</head>
<body>
    @if($branding->get('email_header_html'))
        {!! $branding->get('email_header_html') !!}
    @endif
    @if($branding->get('logo_url'))
        <div style="margin-bottom: 16px;">
            <img src="{{ $branding->get('logo_url') }}" alt="{{ $branding->appName() }}" style="max-height: 50px; max-width: 180px;">
        </div>
    @endif
    <div class="header">
        <h2>{{ $distribution->meeting->title }}</h2>
    </div>

    <div class="body-content">
        @if($distribution->body_note)
            <p>{{ $distribution->body_note }}</p>
        @endif
        <p>{{ __('Please find the Minutes of Meeting attached.') }}</p>
    </div>

    <div class="footer">
        @if($branding->get('email_footer_html'))
            {!! $branding->get('email_footer_html') !!}
        @else
            <p>{{ __('This email was generated from :app.', ['app' => $branding->appName()]) }}</p>
        @endif
    </div>
</body>
</html>
