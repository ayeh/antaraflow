<h1>Extractions for {{ $meeting->title }}</h1>

@forelse($extractions as $extraction)
    <div>
        <h2>{{ ucfirst(str_replace('_', ' ', $extraction->type)) }}</h2>
        <p>{{ $extraction->content }}</p>
        <small>Provider: {{ $extraction->provider }} | Model: {{ $extraction->model }}</small>
    </div>
@empty
    <p>No extractions yet.</p>
@endforelse

@if($topics->isNotEmpty())
    <h2>Topics</h2>
    @foreach($topics as $topic)
        <div>
            <h3>{{ $topic->title }}</h3>
            @if($topic->description)
                <p>{{ $topic->description }}</p>
            @endif
        </div>
    @endforeach
@endif
