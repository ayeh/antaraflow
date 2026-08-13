<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Services;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class VersionService
{
    public function createVersion(MinutesOfMeeting $mom, User $user, ?string $changeSummary = null): MomVersion
    {
        $latestVersion = $mom->versions()->max('version_number') ?? 0;

        return $mom->versions()->create([
            'created_by' => $user->id,
            'version_number' => $latestVersion + 1,
            'content' => $mom->content ?? '',
            'change_summary' => $changeSummary,
            'snapshot' => [
                'title' => $mom->title,
                'summary' => $mom->summary,
                'content' => $mom->content,
                'status' => $mom->status->value,
                'metadata' => $mom->metadata,
            ],
        ]);
    }

    public function getVersionHistory(MinutesOfMeeting $mom): Collection
    {
        return $mom->versions()->with('createdBy')->orderByDesc('version_number')->get();
    }

    /**
     * Roll the minutes back to an earlier snapshot, keeping the current text as
     * a new version first so a restore is itself undoable.
     *
     * The snapshot's own status is deliberately not restored — reviving a stale
     * 'finalized' or 'pending_confirmation' would desync the circulation state.
     */
    public function restoreVersion(MinutesOfMeeting $mom, MomVersion $version, User $user): MinutesOfMeeting
    {
        return DB::transaction(function () use ($mom, $version, $user): MinutesOfMeeting {
            $this->createVersion($mom, $user, "Restored from version {$version->version_number}");

            $snapshot = $version->snapshot;
            $mom->update([
                'title' => $snapshot['title'] ?? $mom->title,
                'summary' => $snapshot['summary'] ?? $mom->summary,
                'content' => $snapshot['content'] ?? $mom->content,
            ]);

            return $mom->fresh();
        });
    }
}
