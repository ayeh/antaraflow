<h1>Edit Manual Note</h1>
<form method="POST" action="{{ route('meetings.manual-notes.update', [$meeting, $manualNote]) }}">
    @csrf
    @method('PUT')
    <input type="text" name="title" value="{{ $manualNote->title }}">
    <textarea name="content">{{ $manualNote->content }}</textarea>
    <button type="submit">Update</button>
</form>
