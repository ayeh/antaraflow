<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('Add your microphone') }} · antaraNote</title>
    <style>
        :root { --navy: #0B1F3A; --navy-soft: #16305a; --lime: #A3E635; --ink: #E8EEF7; --ink-soft: #9DB0CC; }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; }
        body {
            background: var(--navy); color: var(--ink);
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex; align-items: center; justify-content: center; padding: 24px;
            -webkit-font-smoothing: antialiased;
        }
        .card { width: 100%; max-width: 420px; text-align: center; }
        .mark {
            width: 64px; height: 64px; border-radius: 16px; background: var(--lime);
            color: var(--navy); font-weight: 800; font-size: 34px; line-height: 64px;
            display: inline-block; margin-bottom: 24px; font-style: italic;
        }
        h1 { font-size: 26px; font-weight: 800; margin: 0 0 12px; line-height: 1.2; }
        p { color: var(--ink-soft); line-height: 1.5; margin: 0 0 28px; }
        .btn {
            display: block; width: 100%; padding: 16px; border-radius: 12px;
            font-size: 17px; font-weight: 800; text-decoration: none; margin-bottom: 12px;
        }
        .btn-primary { background: var(--lime); color: var(--navy); }
        .btn-ghost { background: transparent; color: var(--ink); border: 1px solid var(--navy-soft); }
        .stores { margin-top: 24px; font-size: 14px; color: var(--ink-soft); }
        .stores a { color: var(--ink); }
    </style>
</head>
<body>
    <div class="card">
        <div class="mark">n</div>
        <h1>{{ __('Add your microphone') }}</h1>
        <p>{{ __('A meeting is being recorded and this link is asking your phone to help — open it in antaraNote to add your microphone.') }}</p>

        <a class="btn btn-primary" href="{{ $deepLink }}">{{ __('Open in antaraNote') }}</a>

        <div class="stores">
            {{ __("Don't have the app?") }}
            <a href="{{ $iosStoreUrl }}">{{ __('App Store') }}</a>
            ·
            <a href="{{ $androidStoreUrl }}">{{ __('Google Play') }}</a>
        </div>
    </div>

    <script>
        // Best effort: if the app is installed but the platform association did
        // not open it directly, nudge the scheme once. A real gesture (the
        // button) is the reliable path, so this only tries, and never blocks it.
        (function () {
            try { window.location.href = @json($deepLink); } catch (e) {}
        })();
    </script>
</body>
</html>
