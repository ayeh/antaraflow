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
        Schema::table('live_transcript_chunks', function (Blueprint $table) {
            $table->string('device_id', 64)->default('')->after('live_meeting_session_id');
            $table->string('role', 16)->default('primary')->after('device_id');
        });

        // Separate statement: the columns have to exist, and be populated with
        // their defaults, before anything unique can be built over them.
        Schema::table('live_transcript_chunks', function (Blueprint $table) {
            $table->unique(
                ['live_meeting_session_id', 'device_id', 'chunk_number'],
                'ltc_session_device_chunk_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('live_transcript_chunks', function (Blueprint $table) {
            $table->dropUnique('ltc_session_device_chunk_unique');
            $table->dropColumn(['device_id', 'role']);
        });
    }
};
