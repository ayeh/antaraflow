<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Organizations</title>
</head>
<body>
    <h1>Organizations</h1>

    @foreach ($organizations as $organization)
        <div>
            <a href="{{ route('organizations.show', $organization) }}">{{ $organization->name }}</a>
        </div>
    @endforeach

    <a href="{{ route('organizations.create') }}">Create Organization</a>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Logout</button>
    </form>
</body>
</html>
