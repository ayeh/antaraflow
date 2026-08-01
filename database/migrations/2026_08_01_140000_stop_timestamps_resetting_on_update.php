<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MySQL runs with explicit_defaults_for_timestamp OFF, which silently gives a
 * `TIMESTAMP NOT NULL` column both DEFAULT CURRENT_TIMESTAMP *and* ON UPDATE
 * CURRENT_TIMESTAMP. Every one of these columns records when something happened
 * — a session started, an invitation expires, a vote was cast — so having the
 * database rewrite them on each unrelated UPDATE corrupted them, and to the
 * database server's own clock, which is eight hours ahead of the application's.
 *
 * Naming a DEFAULT explicitly suppresses the implicit ON UPDATE.
 */
return new class extends Migration
{
    /** @var array<int, array{0: string, 1: string}> */
    private const COLUMNS = [
        ['analytics_events', 'occurred_at'],
        ['generated_reports', 'generated_at'],
        ['live_meeting_sessions', 'started_at'],
        ['meeting_prep_briefs', 'generated_at'],
        ['organization_invitations', 'expires_at'],
        ['proactive_insights', 'generated_at'],
        ['resolution_votes', 'voted_at'],
    ];

    public function up(): void
    {
        // The behaviour being corrected is MySQL's alone; other drivers never
        // attached ON UPDATE to these columns and have nothing to repair.
        if (! $this->onMySql()) {
            return;
        }

        foreach (self::COLUMNS as [$table, $column]) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
        }

        $this->repairLiveSessionStarts();
        $this->repairInvitationExpiries();
        $this->repairInsightGeneratedAt();
    }

    public function down(): void
    {
        if (! $this->onMySql()) {
            return;
        }

        foreach (self::COLUMNS as [$table, $column]) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }
    }

    private function onMySql(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    /**
     * total_duration_seconds was calculated from the correct start before the
     * row was written, so it survived — the original start is recoverable
     * exactly by subtracting it from the end.
     */
    private function repairLiveSessionStarts(): void
    {
        DB::table('live_meeting_sessions')
            ->whereColumn('started_at', '>', 'ended_at')
            ->whereNotNull('ended_at')
            ->whereNotNull('total_duration_seconds')
            ->update([
                'started_at' => DB::raw('DATE_SUB(ended_at, INTERVAL total_duration_seconds SECOND)'),
            ]);
    }

    /** Invitations are always issued with a seven-day window. */
    private function repairInvitationExpiries(): void
    {
        DB::table('organization_invitations')
            ->whereNotNull('created_at')
            ->whereRaw('expires_at > DATE_ADD(created_at, INTERVAL 8 DAY)')
            ->update([
                'expires_at' => DB::raw('DATE_ADD(created_at, INTERVAL 7 DAY)'),
            ]);
    }

    /** Insights are stamped at creation, so created_at is the original value. */
    private function repairInsightGeneratedAt(): void
    {
        DB::table('proactive_insights')
            ->whereNotNull('created_at')
            ->whereRaw('generated_at > DATE_ADD(created_at, INTERVAL 1 HOUR)')
            ->update(['generated_at' => DB::raw('created_at')]);
    }
};
