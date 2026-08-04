<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Controllers;

use App\Domain\Collaboration\Models\Comment;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Services\CirculationService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class AmendmentDecisionController extends Controller
{
    public function __construct(
        private readonly CirculationService $circulationService,
    ) {}

    public function __invoke(Request $request, MinutesOfMeeting $meeting, Comment $comment): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:minor,material,reject'],
        ]);

        DB::transaction(function () use ($validated, $meeting, $comment, $request): void {
            $now = Carbon::now();

            match ($validated['decision']) {
                'minor' => $comment->update([
                    'resolved_at' => $now,
                    'resolution' => 'minor',
                ]),
                'reject' => $comment->update([
                    'resolved_at' => $now,
                    'resolution' => 'rejected',
                ]),
                'material' => $this->handleMaterial($meeting, $comment, $request->user(), $now),
            };
        });

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Keputusan terhadap pindaan telah direkodkan.');
    }

    private function handleMaterial(
        MinutesOfMeeting $meeting,
        Comment $comment,
        User $user,
        Carbon $now,
    ): void {
        $comment->update([
            'resolved_at' => $now,
            'resolution' => 'material',
        ]);

        $openCirculation = $meeting->circulations()->where('status', 'open')->latest()->first();

        if (! $openCirculation) {
            return;
        }

        $openCirculation->update([
            'status' => 'closed_amended',
            'closed_at' => $now,
        ]);

        // Invalidate all previous confirmations so recipients must re-confirm.
        $openCirculation->recipients()->whereNotNull('responded_at')->update([
            'deemed_confirmed_at' => null,
            'responded_at' => null,
            'response' => null,
        ]);

        // Collect recipients for Round 2.
        $recipients = $openCirculation->recipients->map(fn ($r) => [
            'name' => $r->name,
            'email' => $r->email,
            'mom_attendee_id' => $r->mom_attendee_id ?? null,
        ])->toArray();

        $this->circulationService->circulate(
            meeting: $meeting,
            sentBy: $user,
            recipients: $recipients,
            subject: $openCirculation->subject,
            deadline: now()->addDays(3),
            bodyNote: 'Minit telah dipinda dan memerlukan pengesahan semula.',
            round: $openCirculation->round + 1,
        );
    }
}
