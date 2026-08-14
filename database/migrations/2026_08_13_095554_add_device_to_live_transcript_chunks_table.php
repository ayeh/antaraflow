<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which device recorded this chunk, so a second one can record the same room.
 *
 * `device_id` is **not nullable**, and that is the whole reason this migration
 * is careful. A unique index treats two NULLs as distinct on both MySQL and
 * SQLite, so a nullable column would let the constraint below pass over
 * exactly the rows it exists to protect — every chunk from the browser
 * recorder, which sends no device, and every chunk recorded before this ran.
 * The empty string means "the session's one primary, device unknown", and
 * compares equal to itself.
 *
 * The unique index itself is new behaviour, not a formality. Deduplication has
 * been a check-then-insert in `findExistingChunk()` since the table was
 * created, with nothing behind it — two retries racing could always store the
 * same audio twice and pay to transcribe it twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Each step checks for itself, because this migration is already
        // half-applied somewhere. The columns went on, the unique index was
        // then rejected because one four-month-old test session held eight
        // rows for the same chunk number, and the migration never recorded
        // itself — so a plain re-run starts at the top and dies on a column it
        // added itself. Guarding each step lets the same file finish the job
        // wherever it stopped, instead of needing the migrations table edited
        // by hand on the one box that matters.
        Schema::table('live_transcript_chunks', function (Blueprint $table) {
            if (! Schema::hasColumn('live_transcript_chunks', 'device_id')) {
                $table->string('device_id', 64)->default('')->after('live_meeting_session_id');
            }

            if (! Schema::hasColumn('live_transcript_chunks', 'role')) {
                $table->string('role', 16)->default('primary')->after('device_id');
            }
        });

        // Separate statement: the columns have to exist, and be populated with
        // their defaults, before anything unique can be built over them.
        if ($this->missingUniqueIndex()) {
            Schema::table('live_transcript_chunks', function (Blueprint $table) {
                $table->unique(
                    ['live_meeting_session_id', 'device_id', 'chunk_number'],
                    'ltc_session_device_chunk_unique',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::table('live_transcript_chunks', function (Blueprint $table) {
            if (! $this->missingUniqueIndex()) {
                $table->dropUnique('ltc_session_device_chunk_unique');
            }

            $table->dropColumn(['device_id', 'role']);
        });
    }

    /**
     * Whether the unique index still has to be built.
     *
     * Asked of the schema rather than assumed, and written against the
     * abstraction rather than SHOW INDEX so the test suite's SQLite answers it
     * as readily as MySQL does.
     */
    private function missingUniqueIndex(): bool
    {
        return ! collect(Schema::getIndexes('live_transcript_chunks'))
            ->contains(fn (array $index): bool => $index['name'] === 'ltc_session_device_chunk_unique');
    }
};
