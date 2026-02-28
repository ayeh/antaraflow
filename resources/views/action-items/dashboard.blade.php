<h1>Action Items Dashboard</h1>

@forelse($actionItems as $item)
    <div>
        <h3>{{ $item->title }}</h3>
        <p>Meeting: {{ $item->meeting->title }}</p>
        <p>Status: {{ $item->status->value }} | Priority: {{ $item->priority->value }}</p>
        @if($item->assignedTo)
            <p>Assigned to: {{ $item->assignedTo->name }}</p>
        @endif
        @if($item->due_date)
            <p>Due: {{ $item->due_date->format('Y-m-d') }}</p>
        @endif
    </div>
@empty
    <p>No action items found.</p>
@endforelse
