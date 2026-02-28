<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $meeting->title }}</title>
</head>
<body>
    <h1>{{ $meeting->title }}</h1>
    <p>Status: {{ $meeting->status->value }}</p>
    <p>Created by: {{ $meeting->createdBy->name }}</p>

    @if ($meeting->summary)
        <p>{{ $meeting->summary }}</p>
    @endif

    <a href="{{ route('meetings.index') }}">Back to Meetings</a>
</body>
</html>
