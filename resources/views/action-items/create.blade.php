<h1>Create Action Item - {{ $meeting->title }}</h1>

<form method="POST" action="{{ route('meetings.action-items.store', $meeting) }}">
    @csrf
    <div>
        <label for="title">Title</label>
        <input type="text" name="title" id="title" required>
    </div>
    <div>
        <label for="description">Description</label>
        <textarea name="description" id="description"></textarea>
    </div>
    <button type="submit">Create</button>
</form>
