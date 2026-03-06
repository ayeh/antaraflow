<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Controllers;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomManualNote;
use App\Domain\Meeting\Requests\CreateManualNoteRequest;
use App\Support\Enums\InputType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ManualNoteController extends Controller
{
    use AuthorizesRequests;

    public function index(MinutesOfMeeting $meeting): View
    {
        $this->authorize('view', $meeting);

        $notes = $meeting->manualNotes()->orderBy('sort_order')->get();

        return view('manual-notes.index', compact('meeting', 'notes'));
    }

    public function store(CreateManualNoteRequest $request, MinutesOfMeeting $meeting): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $meeting);

        $note = $meeting->manualNotes()->create([
            'created_by' => $request->user()->id,
            'title' => $request->validated('title'),
            'content' => $request->validated('content'),
        ]);

        $meeting->inputs()->create([
            'type' => InputType::ManualNote,
            'source_type' => MomManualNote::class,
            'source_id' => $note->id,
        ]);

        if ($request->wantsJson()) {
            $note->load('createdBy');

            return response()->json(['note' => $note], 201);
        }

        return redirect()->route('meetings.manual-notes.index', $meeting)
            ->with('success', 'Note added successfully.');
    }

    public function show(MinutesOfMeeting $meeting, MomManualNote $manualNote): View
    {
        $this->authorize('view', $meeting);

        return view('manual-notes.show', compact('meeting', 'manualNote'));
    }

    public function update(CreateManualNoteRequest $request, MinutesOfMeeting $meeting, MomManualNote $manualNote): RedirectResponse
    {
        $this->authorize('update', $meeting);

        $manualNote->update($request->validated());

        return redirect()->route('meetings.manual-notes.show', [$meeting, $manualNote])
            ->with('success', 'Note updated successfully.');
    }

    public function destroy(MinutesOfMeeting $meeting, MomManualNote $manualNote): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $meeting);

        $meeting->inputs()
            ->where('source_type', MomManualNote::class)
            ->where('source_id', $manualNote->id)
            ->delete();

        $manualNote->delete();

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Note deleted successfully.']);
        }

        return redirect()->route('meetings.manual-notes.index', $meeting)
            ->with('success', 'Note deleted successfully.');
    }
}
