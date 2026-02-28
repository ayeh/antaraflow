<h1>{{ $transcription->original_filename }}</h1>
<p>Status: {{ $transcription->status->value }}</p>
@if($transcription->full_text)
    <div>{{ $transcription->full_text }}</div>
@endif
@foreach($transcription->segments as $segment)
    <div>{{ $segment->text }}</div>
@endforeach
