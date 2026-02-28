<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Meetings</title>
</head>
<body>
    <h1>Meetings</h1>

    @foreach ($meetings as $meeting)
        <div>
            <a href="{{ route('meetings.show', $meeting) }}">{{ $meeting->title }}</a>
            <span>{{ $meeting->status->value }}</span>
        </div>
    @endforeach

    <a href="{{ route('meetings.create') }}">Create Meeting</a>

    {{ $meetings->links() }}
</body>
</html>
