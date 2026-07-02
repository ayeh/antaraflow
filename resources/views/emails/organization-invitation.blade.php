<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { border-bottom: 2px solid #7c3aed; padding-bottom: 16px; margin-bottom: 24px; }
        .header h2 { color: #7c3aed; margin: 0; font-size: 20px; }
        .body-content { margin-bottom: 24px; }
        .body-content p { margin: 0 0 12px; }
        .footer { border-top: 1px solid #e5e7eb; padding-top: 16px; margin-top: 24px; font-size: 13px; color: #6b7280; }
        .btn { display: inline-block; background-color: #7c3aed; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 500; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Join {{ $organizationName }}</h2>
    </div>

    <div class="body-content">
        <p>You've been invited to join <strong>{{ $organizationName }}</strong> on {{ config('app.name') }} as a <strong>{{ ucfirst($role) }}</strong>.</p>
        <p>Click the button below to accept the invitation. If you don't have an account yet, you'll be able to create one.</p>
    </div>

    <p><a href="{{ $acceptUrl }}" class="btn">Accept invitation</a></p>

    <div class="footer">
        <p>This invitation expires on {{ $expiresAt->format('M j, Y \a\t g:i A') }}.</p>
        <p>If you weren't expecting this invitation, you can safely ignore this email.</p>
    </div>
</body>
</html>
