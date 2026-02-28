<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Meeting</title>
</head>
<body>
    <h1>Create Meeting</h1>

    <form method="POST" action="{{ route('meetings.store') }}">
        @csrf
        <div>
            <label for="title">Title</label>
            <input type="text" name="title" id="title" value="{{ old('title') }}">
        </div>
        <button type="submit">Create</button>
    </form>
</body>
</html>
