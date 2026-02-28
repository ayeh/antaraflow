<h1>Create Manual Note</h1>
<form method="POST" action="{{ route('meetings.manual-notes.store', $meeting) }}">
    @csrf
    <input type="text" name="title" placeholder="Title">
    <textarea name="content"></textarea>
    <button type="submit">Save</button>
</form>
