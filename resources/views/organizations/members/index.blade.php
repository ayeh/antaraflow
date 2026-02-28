<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Members - {{ $organization->name }}</title>
</head>
<body>
    <h1>Members of {{ $organization->name }}</h1>

    @foreach ($members as $member)
        <div>{{ $member->name }} ({{ $member->pivot->role }})</div>
    @endforeach
</body>
</html>
