<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Commands;

use App\Domain\LiveMeeting\Enums\ChunkStatus;
use App\Domain\LiveMeeting\Enums\LiveSessionStatus;
use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Live meetings keep one audio file per thirty seconds and nothing ever
 * removed them, so the directory grows for as long as the product is used.
 * Once a chunk is transcribed and its session has ended, the transcript is the
 * artefact worth keeping; the audio is only useful for re-running a chunk that
 * failed, which stops being realistic after a while.
 */
class PruneLiveAudioCommand extends Command
{
    protected $signature = 'live:prune-audio
        {--days=30 : Age in days beyond which transcribed audio is removed}
        {--include-failed : Also remove audio for chunks that never transcribed}
        {--dry-run : Report what would be removed without deleting anything}';

    protected $description = 'Delete stored audio for transcribed chunks of ended live sessions';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk('local');

        $chunks = LiveTranscriptChunk::query()
            ->withoutGlobalScopes()
            ->whereNotNull('audio_file_path')
            ->where('created_at', '<', now()->subDays($days))
            ->when(! $this->option('include-failed'), fn ($query) => $query->where('status', ChunkStatus::Completed))
            // Never touch a session that is still running.
            ->whereHas('session', fn ($query) => $query->where('status', LiveSessionStatus::Ended))
            ->get();

        $removed = 0;
        $bytes = 0;
        $missing = 0;

        foreach ($chunks as $chunk) {
            if (! $disk->exists($chunk->audio_file_path)) {
                $missing++;

                continue;
            }

            $bytes += $disk->size($chunk->audio_file_path);

            if ($dryRun) {
                $removed++;

                continue;
            }

            $disk->delete($chunk->audio_file_path);

            // Clear the pointer so nothing later reads a path that is gone.
            $chunk->forceFill(['audio_file_path' => null])->saveQuietly();
            $removed++;
        }

        $this->info(sprintf(
            '%s %d chunk file(s), %s MB%s.',
            $dryRun ? 'Would remove' : 'Removed',
            $removed,
            number_format($bytes / 1048576, 1),
            $missing > 0 ? sprintf(' (%d already gone)', $missing) : '',
        ));

        return self::SUCCESS;
    }
}
