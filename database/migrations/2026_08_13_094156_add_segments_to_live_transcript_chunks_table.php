<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The transcript segments for this chunk, held until the session ends.
 *
 * A buffer, not a record. Segments belong to an `AudioTranscription`, and that
 * row does not exist until the sitting is over and the chunks are merged — so
 * they have to wait somewhere, and the chunk that produced them is the only
 * place that knows which fifteen seconds they describe.
 *
 * Overwritten wholesale on every transcription of the chunk rather than
 * appended to, because a chunk can be retried and a retry must not leave the
 * previous attempt's segments behind next to its own.
 *
 * Times inside are on the chunk's own clock, starting near zero. Shifting them
 * onto the meeting's timeline needs the chunk's `start_time`, and baking that
 * in here would make a later correction to `start_time` unable to fix them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_transcript_chunks', function (Blueprint $table) {
            $table->json('segments')->nullable()->after('text');
        });
    }

    public function down(): void
    {
        Schema::table('live_transcript_chunks', function (Blueprint $table) {
            $table->dropColumn('segments');
        });
    }
};
