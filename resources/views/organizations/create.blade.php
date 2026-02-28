<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Organization</title>
</head>
<body>
    <h1>Create Organization</h1>

    <form method="POST" action="{{ route('organizations.store') }}">
        @csrf
        <div>
            <label for="name">Name</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required>
        </div>
        <div>
            <label for="slug">Slug</label>
            <input type="text" id="slug" name="slug" value="{{ old('slug') }}" required>
        </div>
        <div>
            <label for="description">Description</label>
            <textarea id="description" name="description">{{ old('description') }}</textarea>
        </div>
        <button type="submit">Create</button>
    </form>
</body>
</html>
