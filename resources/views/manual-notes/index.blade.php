<h1>Manual Notes</h1>
@foreach($notes as $note)
    <div>{{ $note->title }} - {{ $note->content }}</div>
@endforeach
