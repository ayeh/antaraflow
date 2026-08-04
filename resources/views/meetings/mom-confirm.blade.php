<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $meeting->title ?? 'Pengesahan Minit' }}</title>
</head>
<body>
    <h1>{{ $meeting->title ?? 'Minit Mesyuarat' }}</h1>
    <p>Disahkan oleh: {{ $recipient->name }}</p>
</body>
</html>
