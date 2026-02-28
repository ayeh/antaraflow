<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit {{ $organization->name }}</title>
</head>
<body>
    <h1>Edit {{ $organization->name }}</h1>

    <form method="POST" action="{{ route('organizations.update', $organization) }}">
        @csrf
        @method('PUT')
        <div>
            <label for="name">Name</label>
            <input type="text" id="name" name="name" value="{{ old('name', $organization->name) }}" required>
        </div>
        <div>
            <label for="slug">Slug</label>
            <input type="text" id="slug" name="slug" value="{{ old('slug', $organization->slug) }}" required>
        </div>
        <div>
            <label for="description">Description</label>
            <textarea id="description" name="description">{{ old('description', $organization->description) }}</textarea>
        </div>
        <button type="submit">Update</button>
    </form>
</body>
</html>
