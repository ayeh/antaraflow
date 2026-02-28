<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $organization->name }}</title>
</head>
<body>
    <h1>{{ $organization->name }}</h1>
    <p>{{ $organization->description }}</p>
    <a href="{{ route('organizations.index') }}">Back</a>
</body>
</html>
