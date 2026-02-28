<h1>AI Chat - {{ $meeting->title }}</h1>

<div id="chat-history">
    @forelse($history as $message)
        <div>
            <strong>{{ ucfirst($message->role) }}:</strong>
            <p>{{ $message->message }}</p>
            <small>{{ $message->created_at->diffForHumans() }}</small>
        </div>
    @empty
        <p>No messages yet. Start a conversation about this meeting.</p>
    @endforelse
</div>
