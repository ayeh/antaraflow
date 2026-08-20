<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Commands;

use App\Domain\LiveMeeting\Enums\LiveSessionStatus;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\LiveMeeting\Notifications\LiveRecordingStalledNotification;
use App\Domain\LiveMeeting\Services\LiveMeetingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * A live recording can stop without ending its session: the browser tab is
 * backgrounded or closed and simply stops sending audio, with no error to
 * catch. The session then sits Active, and whoever started it may not know
 * they are recording nothing — one prod meeting ran 95 minutes past its last
 * chunk this way.
 *
 * This watches Active sessions that have recorded at least one chunk and gone
 * quiet since. It escalates: a heads-up first so the user can come back and
 * resume, then — if the silence continues — finalising the session so the
 * transcript is closed off and the minutes generated from what was captured
 * rather than waiting on a recording that is never coming back.
 */
class DetectStalledLiveSessionsCommand extends Command
{
    protected $signature = 'live:detect-stalled
        {--alert-after=3 : Minutes of silence before the user is told recording seems to have stopped}
        {--finalize-after=10 : Minutes of silence before the session is auto-finalised}';

    protected $description = 'Alert on, then finalise, live sessions whose recording has silently stopped';

    public function handle(LiveMeetingService $service): int
    {
        $alertAfter = max(1, (int) $this->option('alert-after')) * 60;
        $finalizeAfter = max(1, (int) $this->option('finalize-after')) * 60;
        $now = now();

        $sessions = LiveMeetingSession::query()
            ->where('status', LiveSessionStatus::Active)
            ->has('chunks')
            ->withMax('chunks', 'created_at')
            ->get();

        $alerted = 0;
        $finalised = 0;

        foreach ($sessions as $session) {
            // withMax returns a raw string, not a cast Carbon, so parse it.
            $lastActivity = $session->chunks_max_created_at
                ? Carbon::parse($session->chunks_max_created_at)
                : $session->started_at;
            $idleSeconds = $now->getTimestamp() - $lastActivity->getTimestamp();

            if ($idleSeconds >= $finalizeAfter) {
                $service->endSession($session);
                $finalised++;

                Log::warning('Auto-finalised a live session whose recording had stopped.', [
                    'session_id' => $session->id,
                    'meeting_id' => $session->minutes_of_meeting_id,
                    'idle_seconds' => $idleSeconds,
                ]);

                continue;
            }

            if ($idleSeconds >= $alertAfter) {
                // Once per stall: the watcher runs every minute and must not nag.
                if ($session->stall_notified_at === null) {
                    $session->startedBy?->notify(new LiveRecordingStalledNotification($session));
                    $session->update(['stall_notified_at' => $now]);
                    $alerted++;
                }

                continue;
            }

            // Chunks are flowing again — the recording recovered. Clear the mark
            // so a later stall in the same session is alerted on afresh.
            if ($session->stall_notified_at !== null) {
                $session->update(['stall_notified_at' => null]);
            }
        }

        $this->info("Live session watch: {$alerted} alerted, {$finalised} auto-finalised.");

        return self::SUCCESS;
    }
}
