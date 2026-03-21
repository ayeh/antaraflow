<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
        h2 { color: #1e1b4b; }
        p { margin: 0 0 1em; }
    </style>
</head>
<body>
    <h2>{{ $distribution->meeting->title }}</h2>
    @if($distribution->body_note)
        <p>{{ $distribution->body_note }}</p>
    @endif
    <p>Please find the Minutes of Meeting attached.</p>
</body>
</html>
