<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupStaleChunksCommand extends Command
{
    protected $signature = 'transcription:cleanup-chunks {--hours=24 : Hours after which chunks are considered stale}';

    protected $description = 'Delete audio recording chunks older than the specified threshold';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $disk = Storage::disk('local');
        $threshold = Carbon::now()->subHours($hours);
        $deleted = 0;

        if (! $disk->exists('organizations')) {
            $this->info("Cleaned up {$deleted} stale chunk directories.");

            return self::SUCCESS;
        }

        foreach ($disk->directories('organizations') as $orgDir) {
            $chunkBase = "{$orgDir}/audio/chunks";

            if (! $disk->exists($chunkBase)) {
                continue;
            }

            foreach ($disk->directories($chunkBase) as $sessionDir) {
                $files = $disk->files($sessionDir);

                if (empty($files)) {
                    $disk->deleteDirectory($sessionDir);
                    $deleted++;

                    continue;
                }

                $lastModified = collect($files)
                    ->map(fn (string $file) => $disk->lastModified($file))
                    ->max();

                if (Carbon::createFromTimestamp($lastModified)->lt($threshold)) {
                    $disk->deleteDirectory($sessionDir);
                    $deleted++;
                }
            }
        }

        $this->info("Cleaned up {$deleted} stale chunk directories.");

        return self::SUCCESS;
    }
}
