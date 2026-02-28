<h1>{{ $actionItem->title }}</h1>

<p>Status: {{ $actionItem->status->value }}</p>
<p>Priority: {{ $actionItem->priority->value }}</p>
@if($actionItem->description)
    <p>{{ $actionItem->description }}</p>
@endif
@if($actionItem->assignedTo)
    <p>Assigned to: {{ $actionItem->assignedTo->name }}</p>
@endif
@if($actionItem->due_date)
    <p>Due: {{ $actionItem->due_date->format('Y-m-d') }}</p>
@endif
