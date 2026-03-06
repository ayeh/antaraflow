<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { border-bottom: 2px solid #7c3aed; padding-bottom: 16px; margin-bottom: 24px; }
        .header h2 { color: #7c3aed; margin: 0; font-size: 20px; }
        .body-content { margin-bottom: 24px; }
        .footer { border-top: 1px solid #e5e7eb; padding-top: 16px; margin-top: 24px; font-size: 13px; color: #6b7280; }
        .btn { display: inline-block; background-color: #7c3aed; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 500; }
        .info { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 20px; }
        .info p { margin: 4px 0; font-size: 14px; }
        .info strong { color: #374151; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Report Ready</h2>
    </div>

    <div class="body-content">
        <p>Your report has been generated and is ready for download.</p>

        <div class="info">
            <p><strong>Report:</strong> {{ $reportName }}</p>
            <p><strong>Type:</strong> {{ $reportType }}</p>
            <p><strong>Generated:</strong> {{ $generatedAt->format('F j, Y g:i A') }}</p>
        </div>

        <p><a href="{{ $downloadUrl }}" class="btn">Download Report</a></p>
    </div>

    <div class="footer">
        <p>This email was generated from {{ config('app.name') }}.</p>
    </div>
</body>
</html>
