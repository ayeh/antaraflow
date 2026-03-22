<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Controllers;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Domain\Transcription\Services\SpeakerDiarizationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

class SpeakerDiarizationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private SpeakerDiarizationService $diarizationService,
    ) {}

    public function analyze(MinutesOfMeeting $meeting, AudioTranscription $transcription): RedirectResponse
    {
        $this->authorize('update', $meeting);

        $updated = $this->diarizationService->diarize($transcription);

        return back()->with('success', "AI speaker analysis complete. {$updated} segments updated.");
    }
}
