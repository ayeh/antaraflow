<h1>Edit Action Item - {{ $actionItem->title }}</h1>

<form method="POST" action="{{ route('meetings.action-items.update', [$meeting, $actionItem]) }}">
    @csrf
    @method('PUT')
    <div>
        <label for="title">Title</label>
        <input type="text" name="title" id="title" value="{{ $actionItem->title }}" required>
    </div>
    <div>
        <label for="description">Description</label>
        <textarea name="description" id="description">{{ $actionItem->description }}</textarea>
    </div>
    <button type="submit">Update</button>
</form>
