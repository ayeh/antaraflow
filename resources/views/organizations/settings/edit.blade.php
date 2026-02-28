<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings - {{ $organization->name }}</title>
</head>
<body>
    <h1>Settings for {{ $organization->name }}</h1>

    <form method="POST" action="{{ route('organizations.settings.update', $organization) }}">
        @csrf
        @method('PUT')
        <div>
            <label for="settings">Settings (JSON)</label>
            <textarea id="settings" name="settings[theme]">{{ $organization->settings['theme'] ?? '' }}</textarea>
        </div>
        <button type="submit">Save Settings</button>
    </form>
</body>
</html>
