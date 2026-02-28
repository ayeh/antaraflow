<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Meeting</title>
</head>
<body>
    <h1>Edit Meeting</h1>

    <form method="POST" action="{{ route('meetings.update', $meeting) }}">
        @csrf
        @method('PUT')
        <div>
            <label for="title">Title</label>
            <input type="text" name="title" id="title" value="{{ old('title', $meeting->title) }}">
        </div>
        <button type="submit">Update</button>
    </form>
</body>
</html>
